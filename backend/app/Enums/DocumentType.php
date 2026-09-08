<?php

namespace App\Enums;

/**
 * Types de documents de vérification déposés par les investisseurs et les
 * promoteurs (§7.1, §7.2, entité « Document de vérification » §12).
 *
 * Trois niveaux se superposent, et un même type n'appartient qu'à un seul :
 *   1. compte      — aucune pièce, seulement des champs de formulaire ;
 *   2. opérateur   — pièces déposées **une seule fois** (dossier personnel du
 *                    particulier, dossier de structure du promoteur confirmé) ;
 *   3. opération   — pièces refaites **à chaque projet**.
 *
 * La répartition entre les niveaux 2 et 3 est portée par `PromoterType`, pas
 * par cette énumération : elle dépend du sous-type de promoteur, pas du type
 * de pièce.
 *
 * Les libellés emploient les termes sénégalais (RCCM, NINEA, quitus fiscal) ;
 * l'équivalent français d'origine figure en indication quand il diffère.
 */
enum DocumentType: string
{
    // --- Niveau 2, communs -------------------------------------------------
    case IdCard = 'id_card';                       // Pièce d'identité
    case ProofOfAddress = 'proof_of_address';      // Justificatif de domicile

    // --- Niveau 2, particulier --------------------------------------------
    case FamilyStatus = 'family_status';           // Livret de famille / contrat de mariage
    case EmploymentContract = 'employment_contract';
    case Payslips = 'payslips';                    // Bulletins de salaire
    case BankStatements = 'bank_statements';       // Relevés de comptes
    case TaxNotice = 'tax_notice';                 // Avis d'imposition / quitus fiscal
    case SavingsProof = 'savings_proof';           // Épargne / capacité d'apport

    // --- Niveau 2, promoteur confirmé -------------------------------------
    case BusinessRegistration = 'business_registration'; // RCCM + NINEA (ex-Kbis)
    case CompanyBylaws = 'company_bylaws';         // Statuts de la société portante
    case FinancialStatements = 'financial_statements';   // 3 derniers bilans audités
    case OrgChart = 'org_chart';                   // Organigramme juridique du groupe
    case DirectorCv = 'director_cv';               // CV des dirigeants
    case CashPosition = 'cash_position';           // Trésorerie et encours bancaires
    case DecennialInsurance = 'decennial_insurance';     // RC décennale de la structure

    // --- Niveau 3, communs aux deux sous-types ----------------------------
    case ProjectDeed = 'project_deed';             // Titre foncier / acte de propriété
    case BuildingPermit = 'building_permit';       // Permis de construire
    case ArchitectPlans = 'architect_plans';       // Plans (masse, coupes, façades)
    case WorksQuote = 'works_quote';               // Devis descriptif et quantitatif
    case EquityProof = 'equity_proof';             // Apport injecté dans l'opération

    // --- Niveau 3, particulier --------------------------------------------
    case SaleAgreement = 'sale_agreement';         // Compromis de vente / contrat d'acquisition

    // --- Niveau 3, promoteur confirmé -------------------------------------
    case SpvBylaws = 'spv_bylaws';                 // Statuts de la société dédiée (SCI/SCCV)
    case SoilStudy = 'soil_study';                 // Étude de sol G2 et diagnostics
    case WorksContract = 'works_contract';         // Maîtrise d'œuvre / contrat de promotion
    case WorksSchedule = 'works_schedule';         // Planning prévisionnel de chantier
    case FinancialForecast = 'financial_forecast'; // Bilan financier prévisionnel
    case CashFlowPlan = 'cash_flow_plan';          // Plan de trésorerie mensualisé
    case CompletionGuarantee = 'completion_guarantee';   // Garantie d'achèvement (GFA)
    case ConstructionInsurance = 'construction_insurance'; // Dommages-Ouvrage
    case PriceSchedule = 'price_schedule';         // Grille de prix de vente par lot
    case ReservationContracts = 'reservation_contracts';   // Réservations + dépôts de garantie
    case PresaleAttestation = 'presale_attestation';       // Taux de précommercialisation
    case MarketStudy = 'market_study';             // Étude de marché locale

    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::IdCard => "Pièce d'identité",
            self::ProofOfAddress => 'Justificatif de domicile',
            self::FamilyStatus => 'Situation de famille',
            self::EmploymentContract => "Contrat de travail ou attestation d'emploi",
            self::Payslips => 'Bulletins de salaire',
            self::BankStatements => 'Relevés bancaires',
            self::TaxNotice => "Avis d'imposition",
            self::SavingsProof => "Épargne ou capacité d'apport",

            self::BusinessRegistration => 'RCCM et NINEA',
            self::CompanyBylaws => 'Statuts de la société',
            self::FinancialStatements => 'Bilans comptables',
            self::OrgChart => 'Organigramme du groupe',
            self::DirectorCv => 'CV des dirigeants',
            self::CashPosition => 'Trésorerie et encours bancaires',
            self::DecennialInsurance => 'Assurance décennale',

            self::ProjectDeed => 'Titre foncier ou acte de propriété',
            self::BuildingPermit => 'Permis de construire',
            self::ArchitectPlans => "Plans d'architecte",
            self::WorksQuote => 'Devis des travaux',
            self::EquityProof => "Apport propre de l'opération",

            self::SaleAgreement => "Compromis de vente ou contrat d'acquisition",

            self::SpvBylaws => 'Statuts de la société dédiée',
            self::SoilStudy => 'Étude de sol et diagnostics',
            self::WorksContract => "Contrat de maîtrise d'œuvre",
            self::WorksSchedule => 'Planning de chantier',
            self::FinancialForecast => 'Bilan financier prévisionnel',
            self::CashFlowPlan => 'Plan de trésorerie',
            self::CompletionGuarantee => "Garantie financière d'achèvement",
            self::ConstructionInsurance => 'Assurance dommages-ouvrage',
            self::PriceSchedule => 'Grille de prix de vente',
            self::ReservationContracts => 'Contrats de réservation',
            self::PresaleAttestation => 'Taux de précommercialisation',
            self::MarketStudy => 'Étude de marché',

            self::Other => 'Autre',
        };
    }

    /**
     * Précision affichée sous le libellé : ce que l'on attend exactement, dans
     * les termes du dossier bancaire. Sans elle, « Bilans comptables » laisse
     * le promoteur deviner combien d'exercices déposer.
     */
    public function hint(): ?string
    {
        return match ($this) {
            self::IdCard => "Carte nationale d'identité ou passeport en cours de validité.",
            self::ProofOfAddress => 'Facture ou attestation de moins de 3 mois.',
            self::FamilyStatus => 'Livret de famille ou contrat de mariage.',
            self::EmploymentContract => "Contrat signé, ou attestation d'emploi de l'employeur.",
            self::Payslips => 'Les 3 à 6 derniers bulletins.',
            self::BankStatements => 'Tous vos comptes, sur les 3 derniers mois.',
            self::TaxNotice => 'Dernier avis reçu (ou quitus fiscal DGID).',
            self::SavingsProof => "Relevé d'épargne, donation ou tout justificatif de fonds disponibles.",

            self::BusinessRegistration => 'Extrait RCCM de moins de 3 mois et attestation NINEA. Équivalent local du Kbis.',
            self::CompanyBylaws => 'Statuts à jour de la société portante, avec les dernières modifications.',
            self::FinancialStatements => 'Les 3 derniers exercices, audités et certifiés, annexe détaillée comprise.',
            self::OrgChart => 'Organigramme juridique : détentions, filiales, sociétés liées.',
            self::DirectorCv => 'Parcours et opérations déjà livrées par chaque dirigeant.',
            self::CashPosition => 'Situation de trésorerie et encours bancaires globaux du groupe.',
            self::DecennialInsurance => 'Attestation en cours de validité au nom de la structure.',

            self::ProjectDeed => 'Titre de propriété du terrain ou du bien, de moins de 3 mois.',
            self::BuildingPermit => 'Permis obtenu, complet et purgé de tout recours des tiers.',
            self::ArchitectPlans => 'Plan de masse, coupes et façades validés.',
            self::WorksQuote => 'Devis descriptif, estimatif et quantitatif détaillé.',
            self::EquityProof => 'Fonds propres réellement injectés dans cette opération (distincts de votre épargne générale).',

            self::SaleAgreement => "Compromis signé, ou contrat d'acquisition du bien.",

            self::SpvBylaws => 'Statuts de la SCI/SCCV créée pour cette opération. Une pièce par projet : la société est propre au projet.',
            self::SoilStudy => 'Rapport G2 et diagnostics obligatoires.',
            self::WorksContract => "Contrat de maîtrise d'œuvre ou contrat de promotion immobilière.",
            self::WorksSchedule => 'Date de démarrage, phases et date de livraison.',
            self::FinancialForecast => "Bilan prévisionnel détaillé de l'opération.",
            self::CashFlowPlan => 'Décaissements des travaux et encaissements des ventes, mois par mois.',
            self::CompletionGuarantee => "Accord de principe du garant (GFA ou garantie bancaire d'achèvement).",
            self::ConstructionInsurance => 'Devis ou attestation dommages-ouvrage pour cette opération.',
            self::PriceSchedule => 'Prix de vente détaillé, lot par lot.',
            self::ReservationContracts => 'Contrats signés et justificatifs des dépôts de garantie.',
            self::PresaleAttestation => 'Taux atteint à ce jour. Les banques exigent généralement 40 à 50 %.',
            self::MarketStudy => 'Étude locale justifiant les prix de vente retenus.',

            self::Other => null,
        };
    }

    /**
     * Durée de validité en mois, `null` si la pièce ne périme pas.
     *
     * Une bonne moitié des pièces « à déposer une seule fois » n'en sont pas :
     * un RCCM de moins de 3 mois ou des bulletins de salaire vieillissent. La
     * validité se compte depuis la date d'émission déclarée au dépôt, et elle
     * est contrôlée **au moment où le promoteur publie**, pas au dépôt — sinon
     * un dossier validé en janvier autoriserait une publication en décembre.
     */
    public function validityMonths(): ?int
    {
        return match ($this) {
            // Pièces datées, à rafraîchir.
            self::ProofOfAddress,
            self::BusinessRegistration,
            self::Payslips,
            self::BankStatements,
            self::PresaleAttestation,
            self::ProjectDeed => 3,

            self::CashPosition,
            self::SavingsProof => 6,

            self::TaxNotice,
            self::FinancialStatements,
            self::DecennialInsurance,
            self::MarketStudy => 12,

            // Pièces stables : statuts, plans, permis, contrats, prévisionnels.
            default => null,
        };
    }

    /** La date d'émission est-elle exigée au dépôt ? */
    public function requiresIssueDate(): bool
    {
        return $this->validityMonths() !== null;
    }
}
