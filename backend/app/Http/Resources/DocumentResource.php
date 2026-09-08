<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Document de vérification (KYC). Le chemin de stockage n'est jamais exposé :
 * le fichier n'est accessible que par la route de téléchargement authentifiée.
 *
 * @mixin \App\Models\VerificationDocument
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'context' => $this->context->value,
            'context_label' => $this->context->label(),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),

            'original_name' => $this->original_name,
            'download_url' => "/api/documents/{$this->id}/download",

            // Péremption : une pièce validée mais hors délai ne vaut plus rien
            // pour le dossier, l'interface doit pouvoir le dire.
            'issued_at' => $this->issued_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            'expired' => $this->isExpired(),

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'review_note' => $this->review_note,
            'reviewed_at' => $this->reviewed_at,

            'project_id' => $this->project_id,

            // Réservé à la file de modération : qui a déposé la pièce.
            'owner' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => $this->user->role->value,
                'role_label' => $this->user->role->label(),
                'kyc_status' => $this->user->kyc_status->value,
            ]),

            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->name),

            'created_at' => $this->created_at,
        ];
    }
}
