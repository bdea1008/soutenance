<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rapport d'avancement de chantier (§7.5). Comme pour les pièces KYC, les
 * photos ne sont pas servies depuis un dossier public : chacune est exposée
 * par une URL indexée, derrière l'authentification.
 *
 * @mixin \App\Models\SiteReport
 */
class SiteReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,

            'title' => $this->title,
            'description' => $this->description,
            'progress_percentage' => $this->progress_percentage,

            'photos' => collect($this->photos ?? [])->values()->map(fn ($photo, $index) => [
                'index' => $index,
                'original_name' => $photo['original_name'] ?? null,
                'url' => "/api/reports/{$this->id}/photos/{$index}",
            ]),

            'reported_at' => $this->reported_at,

            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
                'role' => $this->author->role->value,
            ]),

            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'title' => $this->project->title,
                'status' => $this->project->status->value,
                'status_label' => $this->project->status->label(),
            ]),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
