<?php

namespace App\Enums;

/**
 * État d'un paiement. En MVP les paiements réels sont simulés (cf. §14/§16.5).
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Simulated = 'simulated'; // Paiement simulé validé (MVP, sans mouvement de fonds réel)
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Simulated => 'Simulé',
            self::Completed => 'Confirmé',
            self::Failed => 'Échoué',
        };
    }
}
