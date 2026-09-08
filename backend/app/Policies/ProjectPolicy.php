<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Politique d'accès aux projets. Auto-découverte par Laravel (Project → ProjectPolicy).
 */
class ProjectPolicy
{
    // Pas de passe-droit administrateur ici.
    //
    // Ces trois capacités (modifier / publier / supprimer) sont celles du
    // promoteur sur son propre projet. L'administration agit sur les projets
    // par Admin\ProjectModerationController, qui trace chaque décision, la
    // motive et notifie le promoteur. Un `before()` renvoyant true pour l'admin
    // rouvrirait silencieusement le chemin non tracé — dont la suppression pure
    // et simple d'un projet ayant déjà reçu des contributions.

    /** Voir le détail : tout utilisateur authentifié (niveau 2). */
    public function view(User $user, Project $project): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $user->id === $project->promoter_id;
    }

    public function publish(User $user, Project $project): bool
    {
        return $user->id === $project->promoter_id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->id === $project->promoter_id;
    }
}
