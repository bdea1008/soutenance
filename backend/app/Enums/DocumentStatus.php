<?php

namespace App\Enums;

/**
 * État de validation d'un document de vérification (KYC).
 */
enum DocumentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Approved => 'Validé',
            self::Rejected => 'Rejeté',
        };
    }
}
