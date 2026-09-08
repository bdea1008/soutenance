<?php

namespace App\Enums;

/**
 * Rôles applicatifs. Le « visiteur » du descriptif (niveau 1) n'est pas
 * authentifié : il n'a donc pas de rôle en base.
 */
enum UserRole: string
{
    case Investor = 'investor';   // Investisseur
    case Promoter = 'promoter';   // Promoteur immobilier
    case Admin = 'admin';         // Administrateur / gestionnaire
    // Consultation juridique des projets avant validation finale par
    // l'administration (diagramme de cas d'utilisation, acteur « Responsable
    // juridique et conformité »). Rôle interne, jamais auto-attribuable : on
    // ne le trouve ni dans les choix d'inscription, ni dans le seeder de
    // démonstration sauf s'il est explicitement créé par un administrateur.
    case Legal = 'legal';

    public function label(): string
    {
        return match ($this) {
            self::Investor => 'Investisseur',
            self::Promoter => 'Promoteur',
            self::Admin => 'Administrateur',
            self::Legal => 'Juridique & conformité',
        };
    }

    /**
     * Pièces exigées pour que la vérification KYC aboutisse (§7.1, §7.2).
     * Ni l'administrateur ni le rôle juridique ne sont soumis au KYC : ce
     * sont des rôles internes, pas des parties prenantes de la plateforme.
     *
     * Pour un promoteur, la liste dépend du **sous-type** de compte et non du
     * rôle : passer par `User::requiredKycDocuments()`, qui délègue à
     * `PromoterType`. La branche ci-dessous n'est que le repli d'un compte
     * dont le sous-type n'aurait pas été renseigné.
     *
     * @return array<int, DocumentType>
     */
    public function requiredKycDocuments(): array
    {
        return match ($this) {
            self::Investor => [DocumentType::IdCard, DocumentType::ProofOfAddress],
            self::Promoter => PromoterType::Company->requiredKycDocuments(),
            self::Admin, self::Legal => [],
        };
    }

    /** Contexte de vérification associé au rôle. */
    public function kycContext(): ?VerificationContext
    {
        return match ($this) {
            self::Investor => VerificationContext::InvestorKyc,
            self::Promoter => VerificationContext::PromoterKyc,
            self::Admin, self::Legal => null,
        };
    }
}
