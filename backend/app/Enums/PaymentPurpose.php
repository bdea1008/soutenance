<?php

namespace App\Enums;

/**
 * Motif d'un paiement : abonnement promoteur ou contribution à un projet.
 *
 * « Sans frais pour l'investisseur » (§2, §16.2.b) ne veut pas dire qu'investir
 * ne génère aucun paiement — le montant investi *est* le paiement, transféré
 * sans commission ni majoration. `Contribution` trace ce transfert (§2,
 * « Investir dans un projet » inclut « Effectuer un paiement ») ; `Subscription`
 * reste le seul motif qui rapporte quelque chose à la plateforme.
 */
enum PaymentPurpose: string
{
    case Subscription = 'subscription'; // Abonnement promoteur (préalable à la publication)
    case Contribution = 'contribution'; // Montant investi dans un projet

    public function label(): string
    {
        return match ($this) {
            self::Subscription => 'Abonnement promoteur',
            self::Contribution => 'Contribution',
        };
    }
}
