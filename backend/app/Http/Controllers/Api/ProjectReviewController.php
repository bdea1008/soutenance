<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContributionStatus;
use App\Enums\NotificationType;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Resources\ProjectReviewResource;
use App\Models\Project;
use App\Models\ProjectReview;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Notes et commentaires d'investisseurs (diagramme de cas d'utilisation :
 * « Noter / commenter un projet », extension d'« Investir dans un projet »).
 *
 * Réservé à qui a réellement investi dans le projet — un avis « vérifié
 * acheteur », jamais un forum ouvert à tout utilisateur authentifié. La
 * lecture, elle, suit la même règle de visibilité que le reste de la fiche
 * projet (§7.5, même raisonnement que le suivi de chantier).
 */
class ProjectReviewController extends Controller
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    /** Avis d'un projet, du plus récent au plus ancien. */
    public function index(Request $request, Project $project): AnonymousResourceCollection|JsonResponse
    {
        if (! $this->canSee($project, $request->user())
            && ! in_array($project->status, ProjectStatus::publicStatuses(), true)) {
            return response()->json(['message' => 'Projet introuvable.'], 404);
        }

        $reviews = $project->reviews()
            ->with('investor')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ProjectReviewResource::collection($reviews)->additional([
            'meta_summary' => [
                'average' => $project->reviews()->avg('rating'),
                'count' => $project->reviews()->count(),
            ],
        ]);
    }

    /**
     * Déposer ou modifier son avis — une seule note par investisseur et par
     * projet, republier revient à la corriger plutôt qu'à l'empiler.
     */
    public function store(StoreReviewRequest $request, Project $project): JsonResponse
    {
        $user = $request->user();

        $hasInvested = $project->contributions()
            ->where('investor_id', $user->id)
            ->where('status', ContributionStatus::Confirmed->value)
            ->exists();

        if (! $hasInvested) {
            return response()->json([
                'message' => 'Seuls les investisseurs ayant investi dans ce projet peuvent le noter.',
            ], 403);
        }

        $review = ProjectReview::updateOrCreate(
            ['project_id' => $project->id, 'investor_id' => $user->id],
            $request->validated(),
        );

        // Notification au promoteur, seulement au premier dépôt : republier
        // une correction ne doit pas le relancer à chaque virgule changée.
        if ($review->wasRecentlyCreated && $project->promoter) {
            $this->notifier->notify($project->promoter, NotificationType::ProjectReviewed, [
                'project_id' => $project->id,
                'project_title' => $project->title,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'url' => "/projets/{$project->id}",
            ]);
        }

        return response()->json([
            'message' => $review->wasRecentlyCreated ? 'Avis publié.' : 'Avis mis à jour.',
            'review' => new ProjectReviewResource($review->load('investor')),
        ], $review->wasRecentlyCreated ? 201 : 200);
    }

    /** Retirer son propre avis. */
    public function destroy(Request $request, Project $project): JsonResponse
    {
        $review = $project->reviews()->where('investor_id', $request->user()->id)->first();

        if ($review === null) {
            return response()->json(['message' => 'Vous n’avez pas encore noté ce projet.'], 404);
        }

        $review->delete();

        return response()->json(['message' => 'Avis retiré.']);
    }

    /**
     * Le promoteur propriétaire, un administrateur, ou le rôle juridique —
     * même règle de visibilité que le reste de la fiche projet.
     */
    private function canSee(Project $project, $user): bool
    {
        return $project->promoter_id === $user->id || $user->isAdmin() || $user->isLegal();
    }
}
