<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Project;
use App\Support\DossierChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dossier d'une opération (niveau 3) : les pièces refaites à chaque projet,
 * par opposition au dossier de l'opérateur déposé une seule fois (§7.2).
 *
 * C'est la checklist que le promoteur doit solder pour mettre son projet en
 * financement — le même calcul sert de verrou dans `ProjectController::publish`,
 * pour que l'écran et la garde ne puissent pas diverger.
 *
 * Le dépôt lui-même passe par `DocumentController::store` avec un `project_id` :
 * une pièce de projet est une pièce de vérification comme une autre, elle suit
 * la même file de modération.
 */
class ProjectDossierController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();

        // Un dossier de financement est confidentiel : seuls son porteur,
        // l'administration et le rôle juridique y accèdent. 404 plutôt que 403,
        // pour ne pas révéler l'existence du projet à un tiers.
        if ($project->promoter_id !== $user->id && ! $user->isAdmin() && ! $user->isLegal()) {
            return response()->json(['message' => 'Projet introuvable.'], 404);
        }

        $project->load('verificationDocuments.reviewer');

        $checklist = $project->dossierChecklist();
        $profile = $project->promoterProfile();

        return response()->json([
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
            ],
            'profile' => $profile->value,
            'profile_label' => $profile->label(),
            'checklist' => $checklist,
            'progress' => DossierChecklist::progress($checklist),
            'complete' => DossierChecklist::isComplete($checklist),
            'missing' => array_map(
                fn (array $item) => ['type' => $item['type'], 'label' => $item['label'], 'status' => $item['status']],
                DossierChecklist::missing($checklist),
            ),
            'documents' => DocumentResource::collection($project->verificationDocuments),
            'accepted' => ['formats' => ['jpg', 'jpeg', 'png', 'pdf'], 'max_mb' => 5],
        ]);
    }
}
