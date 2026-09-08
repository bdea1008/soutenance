<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Numéro de carte bancaire : longueur plausible + clé de Luhn.
 *
 * Le contrôle de Luhn ne prouve évidemment pas que la carte existe — seul le
 * réseau bancaire peut le dire. Il attrape en revanche les fautes de frappe,
 * qui sont l'écrasante majorité des saisies invalides, et le fait tout de
 * suite plutôt qu'après un aller-retour vers l'opérateur.
 *
 * Le numéro n'apparaît dans aucun message d'erreur : ce n'est pas parce que la
 * saisie est refusée qu'il faut la recopier dans un journal.
 */
class CardNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        // 13 à 19 chiffres couvre tous les réseaux en circulation (Visa 13/16,
        // Mastercard 16, Amex 15, cartes GIM-UEMOA 16 à 19).
        if (strlen($digits) < 13 || strlen($digits) > 19) {
            $fail('Le numéro de carte doit comporter entre 13 et 19 chiffres.');

            return;
        }

        if (! $this->passesLuhn($digits)) {
            $fail('Ce numéro de carte est invalide. Vérifiez votre saisie.');
        }
    }

    /**
     * Somme de contrôle de Luhn : en partant de la droite, on double un
     * chiffre sur deux (en retranchant 9 au-delà de 9) et le total doit être
     * un multiple de 10.
     */
    private function passesLuhn(string $digits): bool
    {
        $sum = 0;
        $double = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = ! $double;
        }

        return $sum % 10 === 0;
    }
}
