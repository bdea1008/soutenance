<?php

namespace App\Enums;

/**
 * Niveau de risque estimé par le service de scoring IA (§8).
 */
enum RiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Faible',
            self::Medium => 'Modéré',
            self::High => 'Élevé',
        };
    }
}
