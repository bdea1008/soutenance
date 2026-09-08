<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            // Sous-type de promoteur : le frontend s'en sert pour n'afficher
            // que les pièces et les paliers d'abonnement qui le concernent.
            'promoter_type' => $this->when(
                $this->isPromoter(),
                fn () => $this->promoterProfile()->value,
            ),
            'promoter_type_label' => $this->when(
                $this->isPromoter(),
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
            'kyc_verified' => $this->isKycVerified(),
            'country' => $this->country,
            'city' => $this->city,
            'is_active' => $this->is_active,
            'has_active_subscription' => $this->when(
                $this->isPromoter(),
                fn () => $this->hasActiveSubscription()
            ),
            'created_at' => $this->created_at,
        ];
    }
}
