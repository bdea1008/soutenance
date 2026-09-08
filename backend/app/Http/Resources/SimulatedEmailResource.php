<?php

namespace App\Http\Resources;

use App\Models\SimulatedEmail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SimulatedEmail
 */
class SimulatedEmailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'to' => [
                'email' => $this->to_email,
                'name' => $this->to_name,
            ],
            'from' => [
                'email' => $this->from_email,
                'name' => $this->from_name,
            ],
            'subject' => $this->subject,
            'context' => $this->context,
            'context_label' => SimulatedEmail::contextLabel($this->context),
            'action_url' => $this->action_url,
            'preview_url' => $this->previewUrl(),
            'sent_at' => $this->created_at?->toIso8601String(),

            // Le corps n'est joint qu'au détail : une liste de vingt messages
            // transporterait autrement plusieurs dizaines de kilo-octets de
            // HTML que personne ne regarde (§14, connexions limitées).
            'body_html' => $this->when($request->routeIs('*.emails.show'), fn () => $this->body_html),
            'body_text' => $this->when($request->routeIs('*.emails.show'), fn () => $this->body_text),

            'recipient' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'role' => $this->user->role?->value,
                'role_label' => $this->user->role?->label(),
            ]),
        ];
    }
}
