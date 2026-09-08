<?php

namespace App\Enums;

/**
 * Paliers d'abonnement promoteur (§16.2.a). Ordre de grandeur indicatif :
 * 25 000 à 100 000 FCFA / mois selon le palier.
 */
enum SubscriptionTier: string
{
    // Palier réservé au particulier : il ne finance que son propre bien, les
    // paliers professionnels n'auraient pas de sens pour lui. Voir
    // PromoterType::subscriptionTiers(), qui fait foi sur qui peut souscrire quoi.
    case Individual = 'individual';
    case Basic = 'basic';
    case Premium = 'premium';
    case Enterprise = 'enterprise';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Particulier',
            self::Basic => 'Essentiel',
            self::Premium => 'Premium',
            self::Enterprise => 'Entreprise',
        };
    }

    /** Prix mensuel indicatif en FCFA (XOF). */
    public function monthlyPrice(): int
    {
        return match ($this) {
            self::Individual => 5000,
            self::Basic => 25000,
            self::Premium => 50000,
            self::Enterprise => 100000,
        };
    }

    /** Le palier est-il réservé aux particuliers ? */
    public function isIndividual(): bool
    {
        return $this === self::Individual;
    }

    /**
     * Nombre de projets pouvant être en ligne simultanément (`null` = illimité).
     * Quota vérifié à la publication (ProjectController::publish).
     */
    public function maxActiveProjects(): ?int
    {
        return match ($this) {
            // Un particulier porte un projet à la fois : le sien.
            self::Individual => 1,
            self::Basic => 3,
            self::Premium => 10,
            self::Enterprise => null,
        };
    }

    /** Arguments commerciaux affichés sur la page Tarifs. */
    public function features(): array
    {
        $quota = $this->maxActiveProjects();
        $projects = $quota === null
            ? 'Projets en ligne illimités'
            : "Jusqu'à $quota projets en ligne simultanément";

        return match ($this) {
            self::Individual => [
                $projects,
                'Dossier de financement accompagné',
                'Analyse IA de votre projet',
                'Suivi des contributions reçues',
                'Support par email',
            ],
            self::Basic => [
                $projects,
                'Fiche projet complète et analyse IA',
                'Suivi des contributions reçues',
                'Support par email',
            ],
            self::Premium => [
                $projects,
                'Mise en avant dans le catalogue',
                'Fiche projet complète et analyse IA',
                'Rapports de chantier illimités',
                'Support prioritaire',
            ],
            self::Enterprise => [
                $projects,
                'Page promoteur dédiée',
                'Accompagnement personnalisé',
                'Accès anticipé aux nouveaux modules',
                'Support téléphonique dédié',
            ],
        };
    }
}
