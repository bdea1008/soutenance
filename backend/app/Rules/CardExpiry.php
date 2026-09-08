<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Date d'expiration d'une carte, au format `MM/AA` (ou `MM/AAAA`).
 *
 * Une carte reste valable jusqu'au **dernier jour** de son mois d'expiration :
 * comparer à `now()` sans passer par la fin de mois refuserait, le 15 mars,
 * une carte expirant en 03/26 qui a encore deux semaines à vivre.
 */
class CardExpiry implements ValidationRule
{
    /** Au-delà, c'est une faute de frappe sur l'année, pas une vraie carte. */
    private const MAX_YEARS_AHEAD = 20;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('#^(0[1-9]|1[0-2])\s*/\s*(\d{2}|\d{4})$#', trim((string) $value), $m)) {
            $fail('La date d’expiration doit être au format MM/AA (par exemple 09/28).');

            return;
        }

        $month = (int) $m[1];
        $year = (int) $m[2];

        if ($year < 100) {
            $year += 2000;
        }

        $expiresAt = Carbon::create($year, $month)->endOfMonth();

        if ($expiresAt->isPast()) {
            $fail('Cette carte est expirée.');

            return;
        }

        if ($expiresAt->greaterThan(now()->addYears(self::MAX_YEARS_AHEAD))) {
            $fail('Cette date d’expiration n’est pas plausible. Vérifiez votre saisie.');
        }
    }
}
