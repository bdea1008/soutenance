<?php

namespace App\Http\Requests\Concerns;

/**
 * Met un numéro de téléphone au format international avant validation.
 *
 * La colonne `phone` est unique : « 77 123 45 67 » et « +221 77 123 45 67 »
 * désignent la même personne, et sans mise au format elles ouvriraient deux
 * comptes. Partagé par l'inscription (`RegisterRequest`) et la création d'un
 * compte par l'administration (`Admin\StoreUserRequest`) — même règle, un
 * seul endroit.
 */
trait NormalizesPhoneNumber
{
    /**
     * Les séparateurs sont retirés, un préfixe international `00` devient `+`,
     * et un numéro sénégalais donné en local (9 chiffres commençant par 7) est
     * complété en `+221`. Un numéro étranger doit porter son indicatif.
     */
    protected function normalizePhoneInput(string $field = 'phone'): void
    {
        $phone = $this->input($field);

        if (! is_string($phone) || trim($phone) === '') {
            return;
        }

        $phone = preg_replace('/[\s.\-()]/', '', trim($phone));
        $phone = preg_replace('/^00/', '+', $phone);

        if (preg_match('/^7\d{8}$/', $phone)) {
            $phone = '+221'.$phone;
        }

        $this->merge([$field => $phone]);
    }
}
