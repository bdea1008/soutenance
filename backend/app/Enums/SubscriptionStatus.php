<?php

namespace App\Enums;

/**
 * État d'un abonnement promoteur. Un abonnement « actif » est requis pour
 * publier effectivement un projet (§7.2, niveau 3 du parcours).
 */
enum SubscriptionStatus: string
{
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'En attente de paiement',
            self::Active => 'Actif',
            self::Expired => 'Expiré',
            self::Cancelled => 'Résilié',
        };
    }
}
