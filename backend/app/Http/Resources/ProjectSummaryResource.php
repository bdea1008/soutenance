<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue « aperçu » d'un projet — utilisée pour la liste publique de la page
 * d'accueil (niveau 1, visiteur) et les listings. Ne divulgue pas les détails
 * réservés aux utilisateurs authentifiés.
 *
 * @mixin \App\Models\Project
 */
class ProjectSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'category' => $this->category,
            'region' => $this->region,
            'city' => $this->city,
            'cover_image' => $this->cover_image,
            'funding_goal' => $this->funding_goal,
            'amount_raised' => $this->amount_raised,
            'funding_progress' => $this->fundingProgress(),
            'expected_return_rate' => $this->expected_return_rate,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            // Date de mise en ligne : elle situe le projet dans le temps pour
            // qui parcourt le catalogue. Posée par le serveur à la publication,
            // jamais saisie par le promoteur.
            'published_at' => $this->published_at,
            'currency' => 'XOF',
            // Score de confiance IA (badge public de mise en confiance).
            'confidence_score' => $this->whenLoaded('latestScore', fn () => $this->latestScore?->confidence_score),
            'risk_level' => $this->whenLoaded('latestScore', fn () => $this->latestScore?->risk_level?->value),
        ];
    }
}
