<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue « supervision » d'un utilisateur, réservée à l'administration (§5).
 *
 * Distincte de UserResource, qui est la vue que l'utilisateur a de lui-même :
 * on y ajoute ce dont un gestionnaire a besoin pour arbitrer (volumétrie,
 * état de l'abonnement, pièces en attente) sans jamais exposer ces agrégats
 * dans les réponses destinées aux autres rôles.
 *
 * @mixin \App\Models\User
 */
class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,

            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'promoter_type' => $this->when(
                $this->role === UserRole::Promoter,
                fn () => $this->promoterProfile()->value,
            ),
            'promoter_type_label' => $this->when(
                $this->role === UserRole::Promoter,
                fn () => $this->promoterProfile()->label(),
            ),
            'company' => $this->when(
                $this->isCompanyPromoter(),
                fn () => [
                    'name' => $this->company_name,
                    'legal_form' => $this->legal_form,
                    'registration_number' => $this->registration_number,
                    'tax_number' => $this->tax_number,
                    'signatory_role' => $this->signatory_role,
                ],
            ),
            'kyc_status' => $this->kyc_status->value,
            'kyc_status_label' => $this->kyc_status->label(),
            'is_active' => (bool) $this->is_active,

            'country' => $this->country,
            'city' => $this->city,

            // Comptages chargés par le contrôleur (withCount) : absents des
            // réponses où ils n'ont pas été demandés plutôt que faux à zéro.
            'projects_count' => $this->whenCounted('projects'),
            'contributions_count' => $this->whenCounted('contributions'),
            'documents_count' => $this->whenCounted('verificationDocuments'),
            'pending_documents_count' => $this->whenCounted('pendingDocuments'),

            // Volumétrie financière : investi pour un investisseur, collecté
            // pour un promoteur. Le contrôleur ne renseigne que celle du rôle.
            'invested_total' => $this->whenNotNull($this->invested_total ?? null),
            'raised_total' => $this->whenNotNull($this->raised_total ?? null),

            'subscription' => $this->when(
                $this->role === UserRole::Promoter,
                fn () => ($sub = $this->activeSubscription()) ? [
                    'tier' => $sub->tier->value,
                    'tier_label' => $sub->tier->label(),
                    'ends_at' => $sub->ends_at,
                ] : null,
            ),

            'created_at' => $this->created_at,
        ];
    }
}
