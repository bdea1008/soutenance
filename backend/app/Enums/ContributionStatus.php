<?php

namespace App\Enums;

/**
 * État d'une contribution d'investisseur. En MVP, les contributions sont
 * simulées (pas de gestion réelle de fonds, cf. §16.5).
 */
enum ContributionStatus: string
{
    case Pending = 'pending';       // Initiée
    case Confirmed = 'confirmed';   // Confirmée (simulée en MVP)
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Confirmed => 'Confirmée',
            self::Cancelled => 'Annulée',
        };
    }
}
