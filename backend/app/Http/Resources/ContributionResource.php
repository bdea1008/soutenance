<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Contribution
 */
class ContributionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'title' => $this->project->title,
                'slug' => $this->project->slug,
                'status' => $this->project->status->value,
            ]),
            'amount' => $this->amount,
            'currency' => 'XOF',
            'share_percentage' => $this->share_percentage,
            'estimated_return' => $this->estimated_return,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_simulated' => $this->is_simulated,
            'confirmed_at' => $this->confirmed_at,
            'created_at' => $this->created_at,

            // Paiement du montant investi (§2, « Effectuer un paiement »).
            'payment' => $this->whenLoaded('payment', fn () => $this->payment ? [
                'reference' => $this->payment->reference,
                'provider' => $this->payment->provider->value,
                'provider_label' => $this->payment->provider->label(),
                'status_label' => $this->payment->status->label(),
                'paid_at' => $this->payment->paid_at,
            ] : null),
        ];
    }
}
