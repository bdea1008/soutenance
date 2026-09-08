<?php

namespace App\Enums;

/**
 * Cycle de vie d'un projet immobilier, du brouillon promoteur jusqu'à la
 * livraison du chantier.
 */
enum ProjectStatus: string
{
    case Draft = 'draft';                   // Brouillon (promoteur)
    case PendingReview = 'pending_review';  // Soumis, en attente de validation admin/KYC
    case Published = 'published';           // Publié, ouvert au co-investissement
    case Funded = 'funded';                 // Objectif financier atteint
    case InProgress = 'in_progress';        // Chantier en cours
    case Completed = 'completed';           // Projet livré
    case Cancelled = 'cancelled';           // Annulé / rejeté

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::PendingReview => 'En validation',
            self::Published => 'Publié',
            self::Funded => 'Financé',
            self::InProgress => 'En chantier',
            self::Completed => 'Livré',
            self::Cancelled => 'Annulé',
        };
    }

    /** Statuts visibles publiquement (aperçu page d'accueil). */
    public static function publicStatuses(): array
    {
        return [self::Published, self::Funded, self::InProgress, self::Completed];
    }

    /**
     * Statuts occupant le quota de projets du palier d'abonnement (§16.2.a).
     * Un projet livré n'immobilise plus de place, contrairement à `publicStatuses()`.
     */
    public static function quotaStatuses(): array
    {
        return [self::Published, self::Funded, self::InProgress];
    }
}
