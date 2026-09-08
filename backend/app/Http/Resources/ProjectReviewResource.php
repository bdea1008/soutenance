<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProjectReview
 */
class ProjectReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'investor' => $this->whenLoaded('investor', fn () => [
                'id' => $this->investor->id,
                'name' => $this->investor->name,
            ]),
            // Une note peut être modifiée sans changer de date de dépôt —
            // c'est la dernière mise à jour qui compte pour le lecteur.
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
