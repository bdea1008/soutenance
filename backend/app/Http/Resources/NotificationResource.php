<?php

namespace App\Http\Resources;

use App\Enums\NotificationType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Notification applicative (§7.7).
 *
 * @mixin \App\Models\AppNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = NotificationType::tryFrom($this->type);

        return [
            'id' => $this->id,
            'type' => $this->type,
            // Nom d'icône, résolu par l'interface (voir NotificationType::icon).
            'icon' => $type?->icon() ?? 'bell',

            'title' => $this->title,
            'body' => $this->body,

            // Destination au clic, déposée par l'émetteur (ex. /projets/3).
            'url' => $this->data['url'] ?? null,

            'channel' => $this->channel->value,
            'read' => $this->read_at !== null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
