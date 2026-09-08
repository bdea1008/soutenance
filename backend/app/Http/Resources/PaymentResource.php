<?php

namespace App\Http\Resources;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Paiement (abonnement promoteur en MVP). Les paiements sont simulés :
 * aucun mouvement de fonds réel n'a lieu (§16.5).
 *
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'purpose' => $this->purpose->value,
            'purpose_label' => $this->purpose->label(),
            'provider' => $this->provider->value,
            'provider_label' => $this->provider->label(),
            // Coordonnées du moyen employé, telles qu'elles sont conservées :
            // un numéro Mobile Money, ou le réseau et les 4 derniers chiffres
            // d'une carte. Jamais de numéro de carte complet — il n'existe
            // nulle part en base (App\Support\PaymentInstrument).
            'instrument' => [
                'kind' => $this->provider->kind(),
                'label' => $this->instrumentLabel(),
                'phone' => $this->payer_phone,
                'card_brand' => $this->card_brand,
                'card_last4' => $this->card_last4,
            ],
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_simulated' => $this->status === PaymentStatus::Simulated,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
