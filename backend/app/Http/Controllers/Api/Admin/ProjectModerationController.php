<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\NotificationType;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\AiScoringClient;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Supervision des projets par l'administration (§5).
 *
 * Le promoteur pilote son propre catalogue (ProjectController) ; l'administration
 * arbitre au-dessus : elle voit tous les projets quel que soit leur statut et
 * peut retirer de la plateforme un projet problématique.
 *
 * Le retrait passe par le statut « annulé » plutôt que par une suppression :
 * un projet ayant reçu des contributions ne doit jamais disparaître de
 * l'historique des investisseurs.
 */
class ProjectModerationController extends Controller
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly AiScoringClient $ai,
    ) {
    }

    /** Catalogue complet, brouillons et projets annulés inclus. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->with(['promoter', 'latestScore', 'latestReport'])
            ->withCount(['contributions', 'siteReports'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('region'), fn ($q) => $q->where('region', $request->string('region')))
            ->when($request->filled('promoter_id'), fn ($q) => $q->where('promoter_id', $request->integer('promoter_id')))
            ->when($request->filled('risk'), fn ($q) => $q->whereHas(
                'latestScore',
                fn ($s) => $s->where('risk_level', $request->string('risk'))
            ))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(fn ($sub) => $sub
                    ->where('title', 'like', "%$term%")
                    ->orWhere('city', 'like', "%$term%")
                    ->orWhere('region', 'like', "%$term%"));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ProjectResource::collection($projects);
    }

    /** Compteurs par statut, dans l'ordre du cycle de vie. */
    public function stats(): JsonResponse
    {
        $counts = Project::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'total' => (int) $counts->sum(),
            'by_status' => collect(ProjectStatus::cases())
                ->map(fn (ProjectStatus $status) => [
                    'status' => $status->value,
                    'label' => $status->label(),
                    'value' => (int) ($counts[$status->value] ?? 0),
                ])
                ->values(),
            // Ce qui attend une décision : brouillons soumis et projets en
            // validation. C'est la file de travail de l'administrateur.
            'awaiting_review' => (int) ($counts[ProjectStatus::PendingReview->value] ?? 0),
        ]);
    }

    /**
     * Décision de modération sur un projet.
     *
     * - `approve`  : met en ligne un projet soumis (le promoteur reste soumis
     *                à son abonnement pour publier lui-même ; l'administration
     *                tranche au-dessus de cette règle, par exemple pour un
     *                dossier régularisé hors ligne).
     * - `cancel`   : retire le projet de la plateforme, motif obligatoire.
     * - `restore`  : rend la main au promoteur en repassant le projet en
     *                brouillon, pour qu'il corrige et resoumette.
     */
    public function moderate(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'cancel', 'restore'])],
            'reason' => [
                Rule::requiredIf($request->input('decision') === 'cancel'),
                'nullable', 'string', 'max:500',
            ],
        ], [
            'reason.required' => 'Un motif est requis pour retirer un projet.',
        ]);

        $decision = $validated['decision'];
        $reason = $validated['reason'] ?? null;

        $response = match ($decision) {
            'approve' => $this->approve($project),
            'cancel' => $this->cancel($project, $reason),
            'restore' => $this->restore($project),
        };

        if ($response !== null) {
            return $response;
        }

        // Le promoteur doit savoir qu'une décision a été prise sur son projet
        // et, en cas de retrait, pourquoi (§7.7).
        $this->notifier->notify(
            $project->promoter,
            match ($decision) {
                'approve' => NotificationType::ProjectApproved,
                'cancel' => NotificationType::ProjectCancelled,
                'restore' => NotificationType::ProjectRestored,
            },
            [
                'project_title' => $project->title,
                'reason' => $reason,
                'status_label' => $project->status->label(),
                'url' => '/promoteur/projets',
            ],
        );

        return response()->json([
            'message' => match ($decision) {
                'approve' => 'Projet mis en ligne.',
                'cancel' => 'Projet retiré de la plateforme.',
                // Le statut de retour dépend des fonds déjà collectés.
                'restore' => "Projet rétabli ({$project->status->label()}).",
            },
            'project' => new ProjectResource(
                $project->fresh()->load(['promoter', 'latestScore'])->loadCount('contributions')
            ),
        ]);
    }

    // --- Transitions ------------------------------------------------------
    // Chaque méthode renvoie null si la transition a été appliquée, ou la
    // réponse d'erreur qui l'a refusée.

    private function approve(Project $project): ?JsonResponse
    {
        if (! in_array($project->status, [ProjectStatus::Draft, ProjectStatus::PendingReview], true)) {
            return response()->json([
                'message' => 'Seul un projet en brouillon ou en validation peut être mis en ligne.',
            ], 409);
        }

        $project->update([
            'status' => ProjectStatus::Published->value,
            'published_at' => $project->published_at ?? now(),
        ]);

        // Même règle qu'à la publication par le promoteur : le score IA est
        // calculé au moment où le projet devient visible (§8.1). Un service
        // indisponible ne fait pas échouer la décision.
        $this->ai->scoreProject($project);

        return null;
    }

    private function cancel(Project $project, ?string $reason): ?JsonResponse
    {
        if ($project->status === ProjectStatus::Cancelled) {
            return response()->json(['message' => 'Ce projet est déjà retiré.'], 409);
        }

        if ($project->status === ProjectStatus::Completed) {
            return response()->json([
                'message' => 'Un projet livré ne peut plus être retiré.',
            ], 409);
        }

        $project->update(['status' => ProjectStatus::Cancelled->value]);

        return null;
    }

    private function restore(Project $project): ?JsonResponse
    {
        if ($project->status !== ProjectStatus::Cancelled) {
            return response()->json([
                'message' => 'Seul un projet retiré peut être rétabli.',
            ], 409);
        }

        // Un projet qui a déjà collecté des fonds ne peut pas redevenir un
        // brouillon librement modifiable : il repart au statut qui correspond
        // à l'argent engagé, et l'administration reprend la main dessus.
        $project->update([
            'status' => $project->amount_raised > 0
                ? ProjectStatus::Published->value
                : ProjectStatus::Draft->value,
        ]);

        return null;
    }
}
