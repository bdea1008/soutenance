<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PaymentProvider;
use App\Rules\CardExpiry;
use App\Rules\CardNumber;
use Illuminate\Validation\Rule;

/**
 * Règles du moyen de paiement, communes à tous les encaissements (§7.4).
 *
 * Investir et souscrire un abonnement se paient de la même façon : le jour où
 * un troisième opérateur arrive, ou où la carte exige un champ de plus, il n'y
 * a qu'un endroit à modifier. Sans ça, les deux parcours divergeraient — l'un
 * exigeant un numéro que l'autre laisserait passer.
 *
 * Les champs sont exigés **selon le moyen choisi** : demander un numéro de
 * carte à qui paie par Wave n'aurait aucun sens, mais laisser les deux
 * facultatifs reviendrait à accepter un paiement sans coordonnées.
 */
trait ValidatesPaymentInstrument
{
    use NormalizesPhoneNumber;

    /**
     * À appeler depuis `prepareForValidation()`.
     *
     * Le numéro est mis au format international par le trait partagé avec
     * l'inscription : « 77 123 45 67 » et « +221 77 123 45 67 » doivent être
     * reçus pareil ici comme là-bas.
     */
    protected function prepareInstrumentInput(): void
    {
        $this->normalizePhoneInput('phone');

        // Le numéro de carte est saisi par groupes de quatre : les espaces
        // viennent de l'aide à la saisie, pas de l'utilisateur.
        if (is_string($this->input('card_number'))) {
            $this->merge(['card_number' => preg_replace('/\s/', '', $this->input('card_number'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function instrumentRules(): array
    {
        return [
            'provider' => ['required', Rule::enum(PaymentProvider::class)],

            // --- Mobile Money -------------------------------------------
            // Facultatif au sens strict : à défaut, c'est le numéro du compte
            // qui est débité. Mais s'il est fourni, il doit être valable.
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+[1-9]\d{7,14}$/'],

            // --- Carte bancaire -----------------------------------------
            'card_number' => [Rule::requiredIf(fn () => $this->paysByCard()), 'nullable', 'string', new CardNumber],
            'card_expiry' => [Rule::requiredIf(fn () => $this->paysByCard()), 'nullable', 'string', new CardExpiry],
            'card_cvc' => [Rule::requiredIf(fn () => $this->paysByCard()), 'nullable', 'string', 'digits_between:3,4'],
            'card_holder' => [Rule::requiredIf(fn () => $this->paysByCard()), 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function instrumentMessages(): array
    {
        return [
            'provider.required' => 'Veuillez choisir un moyen de paiement.',
            'phone.regex' => 'Numéro invalide : indiquez-le au format international, par exemple +221 77 123 45 67.',
            'card_number.required' => 'Le numéro de carte est obligatoire.',
            'card_expiry.required' => 'La date d’expiration est obligatoire.',
            'card_cvc.required' => 'Le CVC est obligatoire.',
            'card_cvc.digits_between' => 'Le CVC comporte 3 chiffres (4 sur American Express).',
            'card_holder.required' => 'Indiquez le nom inscrit sur la carte.',
        ];
    }

    /** Le moyen choisi est-il une carte ? Sert aux règles conditionnelles. */
    protected function paysByCard(): bool
    {
        return PaymentProvider::tryFrom((string) $this->input('provider'))?->isCard() === true;
    }
}
