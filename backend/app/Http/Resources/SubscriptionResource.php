<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Abonnement promoteur (§16.2.a). Un abonnement actif conditionne la
 * publication de projets (§7.2).
 *
 * @mixin \App\Models\PromoterSubscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tier' => $this->tier->value,
            'tier_label' => $this->tier->label(),
            'price' => $this->price,
            'currency' => $this->currency,

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_active' => $this->isActive(),

            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            // Jours restants avant échéance (0 si échu ou résilié).
            'days_remaining' => $this->ends_at && $this->isActive()
                ? max(0, (int) now()->diffInDays($this->ends_at, false))
                : 0,

            'max_active_projects' => $this->tier->maxActiveProjects(),

            'payments' => PaymentResource::collection($this->whenLoaded('payments')),

            'created_at' => $this->created_at,
        ];
    }
}
