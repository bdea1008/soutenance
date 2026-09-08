<?php

namespace App\Enums;

/**
 * Sous-type d'un compte promoteur. Deux profils très différents demandent un
 * financement sur la plateforme :
 *
 *   - le **particulier**, qui finance son propre bien (construction, rénovation
 *     ou acquisition) et se qualifie par ses revenus ;
 *   - le **promoteur confirmé**, une structure qui monte des opérations et se
 *     qualifie par ses comptes et ses références.
 *
 * C'est un sous-type de `UserRole::Promoter`, pas un rôle à part : les routes,
 * gardes, quotas et policies restent identiques, seules les pièces exigées
 * changent. Un rôle supplémentaire aurait obligé à repasser sur tous les
 * `match ($user->role)` de l'application pour un besoin purement documentaire.
 */
enum PromoterType: string
{
    case Individual = 'individual'; // Particulier
    case Company = 'company';       // Promoteur immobilier confirmé

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Particulier',
            self::Company => 'Promoteur immobilier',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Individual => 'Vous financez votre propre bien : construction, rénovation ou acquisition.',
            self::Company => 'Vous êtes une société qui monte des opérations immobilières.',
        };
    }

    /** La structure porteuse doit-elle être renseignée à l'inscription ? */
    public function requiresCompanyIdentity(): bool
    {
        return $this === self::Company;
    }

    // --- Niveau 2 : dossier de l'opérateur, déposé une seule fois ----------

    /**
     * Pièces exigées pour que le dossier de l'opérateur aboutisse. Elles ne
     * dépendent pas du projet : elles décrivent la personne ou la structure.
     *
     * @return array<int, DocumentType>
     */
    public function requiredKycDocuments(): array
    {
        return match ($this) {
            self::Individual => [
                DocumentType::IdCard,
                DocumentType::ProofOfAddress,
                DocumentType::FamilyStatus,
                DocumentType::EmploymentContract,
                DocumentType::Payslips,
                DocumentType::BankStatements,
                DocumentType::TaxNotice,
            ],
            self::Company => [
                // Identité du signataire : une société ne signe jamais seule.
                DocumentType::IdCard,
                DocumentType::BusinessRegistration,
                DocumentType::CompanyBylaws,
                DocumentType::FinancialStatements,
                DocumentType::OrgChart,
                DocumentType::DirectorCv,
                DocumentType::CashPosition,
            ],
        };
    }

    /**
     * Pièces acceptées mais non bloquantes au niveau 2.
     *
     * @return array<int, DocumentType>
     */
    public function optionalKycDocuments(): array
    {
        return match ($this) {
            // L'épargne générale renforce le dossier ; l'apport réellement
            // injecté dans une opération est demandé au niveau du projet.
            self::Individual => [DocumentType::SavingsProof],
            // La décennale couvre les entreprises intervenantes, pas l'ouvrage :
            // elle se renouvelle annuellement au niveau de la structure.
            self::Company => [DocumentType::DecennialInsurance],
        };
    }

    // --- Niveau 3 : dossier de l'opération, refait à chaque projet ---------

    /**
     * Pièces exigées pour qu'un projet donné puisse être mis en financement.
     *
     * @return array<int, DocumentType>
     */
    public function requiredProjectDocuments(): array
    {
        return match ($this) {
            self::Individual => [
                DocumentType::ProjectDeed,
                DocumentType::ArchitectPlans,
                DocumentType::WorksQuote,
                DocumentType::EquityProof,
            ],
            self::Company => [
                // Société ad hoc propre à l'opération : c'est bien une pièce
                // de projet, malgré sa proximité avec les statuts de la
                // société portante (niveau 2).
                DocumentType::SpvBylaws,
                DocumentType::ProjectDeed,
                DocumentType::BuildingPermit,
                DocumentType::ArchitectPlans,
                DocumentType::SoilStudy,
                DocumentType::WorksQuote,
                DocumentType::WorksContract,
                DocumentType::WorksSchedule,
                DocumentType::FinancialForecast,
                DocumentType::CashFlowPlan,
                DocumentType::EquityProof,
                DocumentType::CompletionGuarantee,
                DocumentType::ConstructionInsurance,
                DocumentType::PriceSchedule,
                DocumentType::PresaleAttestation,
                DocumentType::MarketStudy,
            ],
        };
    }

    /**
     * Pièces acceptées mais non bloquantes au niveau 3.
     *
     * @return array<int, DocumentType>
     */
    public function optionalProjectDocuments(): array
    {
        return match ($this) {
            self::Individual => [
                // Le compromis n'existe que si le bien est acheté, le permis
                // que s'il y a construction : exiger les deux rendrait le
                // dossier impossible à compléter dans les deux cas.
                DocumentType::SaleAgreement,
                DocumentType::BuildingPermit,
                DocumentType::Other,
            ],
            self::Company => [
                // Un projet en début de commercialisation n'a encore aucune
                // réservation signée ; le taux de précommercialisation, lui,
                // reste exigé (il peut valoir 0 %).
                DocumentType::ReservationContracts,
                DocumentType::SaleAgreement,
                DocumentType::Other,
            ],
        };
    }

    /**
     * Types recevables au dépôt pour un projet — obligatoires et facultatifs.
     * Toute autre pièce est refusée : elle encombrerait la file de modération
     * sans faire avancer le dossier.
     *
     * @return array<int, DocumentType>
     */
    public function acceptedProjectDocuments(): array
    {
        return [...$this->requiredProjectDocuments(), ...$this->optionalProjectDocuments()];
    }

    /**
     * Types recevables pour le dossier de l'opérateur.
     *
     * @return array<int, DocumentType>
     */
    public function acceptedKycDocuments(): array
    {
        return [...$this->requiredKycDocuments(), ...$this->optionalKycDocuments()];
    }

    // --- Abonnement --------------------------------------------------------

    /**
     * Paliers souscriptibles par ce sous-type. Un particulier ne finance que
     * son propre bien : les paliers professionnels n'auraient pas de sens pour
     * lui, et inversement.
     *
     * @return array<int, SubscriptionTier>
     */
    public function subscriptionTiers(): array
    {
        return match ($this) {
            self::Individual => [SubscriptionTier::Individual],
            self::Company => [
                SubscriptionTier::Basic,
                SubscriptionTier::Premium,
                SubscriptionTier::Enterprise,
            ],
        };
    }
}
