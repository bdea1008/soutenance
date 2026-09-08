<?php

namespace App\Enums;

/**
 * Moyens de paiement supportés (§7.4).
 *
 * Deux familles, qui ne se renseignent pas de la même façon : le Mobile Money
 * s'identifie par un numéro de téléphone, la carte bancaire par ses propres
 * coordonnées. C'est `kind()` qui porte cette distinction, et le reste du code
 * s'y réfère plutôt que d'énumérer les cas à chaque fois — ajouter un
 * troisième opérateur mobile ne demandera alors rien d'autre qu'un cas ici.
 */
enum PaymentProvider: string
{
    case Wave = 'wave';
    case OrangeMoney = 'orange_money';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Wave => 'Wave',
            self::OrangeMoney => 'Orange Money',
            self::Card => 'Carte bancaire',
        };
    }

    /** Famille d'instrument : `mobile_money` ou `card`. */
    public function kind(): string
    {
        return match ($this) {
            self::Wave, self::OrangeMoney => 'mobile_money',
            self::Card => 'card',
        };
    }

    /** Le paiement s'identifie-t-il par un numéro de téléphone ? */
    public function isMobileMoney(): bool
    {
        return $this->kind() === 'mobile_money';
    }

    public function isCard(): bool
    {
        return $this->kind() === 'card';
    }
}
