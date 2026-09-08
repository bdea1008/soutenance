<?php

namespace App\Enums;

/**
 * État de la vérification documentaire complémentaire (KYC) d'un utilisateur,
 * préalable à l'investissement effectif ou à la publication effective d'un projet.
 */
enum KycStatus: string
{
    case None = 'none';         // Aucun document déposé
    case Pending = 'pending';   // Documents déposés, en attente de validation
    case Verified = 'verified'; // Vérifié
    case Rejected = 'rejected'; // Rejeté

    public function label(): string
    {
        return match ($this) {
            self::None => 'Non vérifié',
            self::Pending => 'En attente',
            self::Verified => 'Vérifié',
            self::Rejected => 'Rejeté',
        };
    }
}
