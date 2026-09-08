<?php

namespace App\Enums;

/**
 * Contexte d'une pièce KYC : deux catégories d'actions déclenchent une
 * vérification documentaire complémentaire (§2).
 */
enum VerificationContext: string
{
    case InvestorKyc = 'investor_kyc';           // Avant d'investir effectivement
    case PromoterKyc = 'promoter_kyc';           // Identité du promoteur
    case ProjectVerification = 'project_verification'; // Légitimité d'un projet donné

    public function label(): string
    {
        return match ($this) {
            self::InvestorKyc => 'Vérification investisseur',
            self::PromoterKyc => 'Vérification promoteur',
            self::ProjectVerification => 'Vérification de projet',
        };
    }
}
