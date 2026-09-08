<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectSummaryResource;
use App\Models\Project;
use App\Services\AiScoringClient;
use App\Support\DossierChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function __construct(private readonly AiScoringClient $ai)
    {
    }

    /**
     * Aperçu public des projets (niveau 1, visiteur non authentifié).
     * Ne renvoie que les projets aux statuts visibles publiquement.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->public()
            ->with('latestScore')
            ->when($request->filled('region'), fn ($q) => $q->where('region', $request->string('region')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(fn ($sub) => $sub
                    ->where('title', 'like', "%$term%")
                    ->orWhere('summary', 'like', "%$term%")
                    ->orWhere('city', 'like', "%$term%"));
            })
            ->latest('published_at')
            ->paginate($request->integer('per_page', 12));

        return ProjectSummaryResource::collection($projects);
    }

    /**
     * Projets du promoteur connecté — tous statuts confondus, brouillons inclus
     * (l'aperçu public n'expose que les statuts publiables).
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->where('promoter_id', $request->user()->id)
            // `verificationDocuments` alimente l'avancement du dossier affiché
            // sur chaque carte : c'est ce qui manque au promoteur pour publier.
            ->with(['latestScore', 'promoter', 'verificationDocuments'])
            ->withCount('contributions')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 12));

        return ProjectResource::collection($projects);
    }

    /**
     * Détail complet d'un projet — réservé aux utilisateurs authentifiés
     * (niveau 2). Un promoteur/l'admin peut aussi voir ses projets non publiés.
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        // Le rôle juridique voit tout projet, même non public, pour la même
        // raison que l'admin : sa lecture porte précisément sur les dossiers
        // pas encore validés (§5, vérification avant décision finale).
        $canSeePrivate = $user->isAdmin() || $user->isLegal() || $user->id === $project->promoter_id;

        // Un projet non public n'est visible que de son promoteur, de l'admin
        // ou du rôle juridique.
        if (! $canSeePrivate && ! in_array($project->status, ProjectStatus::publicStatuses(), true)) {
            return response()->json(['message' => 'Projet introuvable.'], 404);
        }

        $project->load(['promoter', 'latestScore', 'latestReport'])
            ->loadCount(['contributions', 'siteReports', 'reviews'])
            ->loadAvg('reviews', 'rating');

        return response()->json(['project' => new ProjectResource($project)]);
    }

    /**
     * Création d'un projet (brouillon). Ouverte à tout promoteur, dossier
     * constitué ou non : il doit pouvoir préparer son opération pendant que
     * ses pièces sont examinées. C'est la publication qui exige les dossiers
     * (voir publish()).
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::create([
            ...$request->validated(),
            'promoter_id' => $request->user()->id,
            'slug' => $this->uniqueSlug($request->string('title')),
            'status' => ProjectStatus::Draft->value,
        ]);

        return response()->json([
            'message' => 'Projet créé (brouillon).',
            'project' => new ProjectResource($project),
        ], 201);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $project->update($request->validated());

        return response()->json([
            'message' => 'Projet mis à jour.',
            'project' => new ProjectResource($project->fresh()),
        ]);
    }

    /**
     * Publication effective d'un projet (niveau 3, promoteur).
     *
     * Quatre conditions, dans cet ordre : être le propriétaire, avoir un
     * abonnement actif, avoir un dossier d'opération complet, et rester dans le
     * quota du palier. Le dossier de l'opérateur (niveau 2) est garanti en
     * amont par le middleware `kyc.verified` (§7.2).
     */
    public function publish(Request $request, Project $project): JsonResponse
    {
        $this->authorize('publish', $project);

        $user = $request->user();

        // Route réservée aux promoteurs : plus de dérogation administrateur
        // ici. La mise en ligne par l'administration passe par
        // Admin\ProjectModerationController::moderate, qui est tracée.
        if (! $user->hasActiveSubscription()) {
            return response()->json([
                'message' => 'Un abonnement promoteur actif est requis pour publier un projet.',
                'code' => 'subscription_required',
            ], 402); // Payment Required
        }

        if (! in_array($project->status, [ProjectStatus::Draft, ProjectStatus::PendingReview], true)) {
            return response()->json([
                'message' => 'Ce projet ne peut pas être publié depuis son statut actuel.',
            ], 409);
        }

        // Dossier de l'opération (niveau 3) : les pièces propres à ce projet.
        // Le dossier de l'opérateur (niveau 2) est déjà garanti par le
        // middleware `kyc.verified` — celui-ci ne vaut qu'une fois, celui-là
        // est à refaire pour chaque projet mis en financement.
        $checklist = $project->dossierChecklist();

        if (! DossierChecklist::isComplete($checklist)) {
            $missing = DossierChecklist::missing($checklist);

            return response()->json([
                'message' => count($missing) === 1
                    ? "Le dossier de ce projet est incomplet : il manque « {$missing[0]['label']} »."
                    : 'Le dossier de ce projet est incomplet : '.count($missing).' pièces restent à fournir ou à faire valider.',
                'code' => 'dossier_incomplete',
                'missing' => array_map(fn (array $item) => [
                    'type' => $item['type'],
                    'label' => $item['label'],
                    'status' => $item['status'],
                    'status_label' => $item['status_label'],
                ], $missing),
                'progress' => DossierChecklist::progress($checklist),
            ], 422);
        }

        // Quota de projets en ligne, fixé par le palier d'abonnement (§16.2.a).
        $tier = $user->activeSubscriptionTier();
        $quota = $tier?->maxActiveProjects();

        if ($quota !== null) {
            $inUse = $user->projects()->countingTowardQuota()->count();

            if ($inUse >= $quota) {
                return response()->json([
                    'message' => "Votre palier {$tier->label()} autorise $quota projets en ligne simultanément "
                        ."(vous en avez $inUse). Passez à un palier supérieur pour publier davantage.",
                    'code' => 'quota_exceeded',
                    'quota' => ['limit' => $quota, 'in_use' => $inUse],
                ], 402);
            }
        }

        $project->update([
            'status' => ProjectStatus::Published->value,
            'published_at' => now(),
        ]);

        // Score IA calculé à la publication (§8) : c'est le moment où le projet
        // devient visible des investisseurs. Un service indisponible ne doit pas
        // faire échouer la publication — le score sera rattrapé par
        // `php artisan projects:score`.
        $this->ai->scoreProject($project);

        return response()->json([
            'message' => 'Projet publié.',
            'project' => new ProjectResource(
                $project->fresh()->load('latestScore'),
            ),
        ]);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(['message' => 'Projet supprimé.']);
    }

    /** Génère un slug unique à partir du titre. */
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 2;

        while (Project::where('slug', $slug)->exists()) {
            $slug = "$base-$i";
            $i++;
        }

        return $slug;
    }
}
