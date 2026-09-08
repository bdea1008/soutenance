<?php

namespace App\Http\Requests\Subscription;

use App\Http\Requests\Concerns\ValidatesPaymentInstrument;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Renouvellement de l'abonnement en cours.
 *
 * Le palier n'est pas redemandé — on prolonge celui qu'on a — mais le
 * paiement, lui, est un vrai paiement : il exige les mêmes coordonnées qu'une
 * première souscription. Le renouvellement lisait auparavant `provider`
 * directement sur la requête, sans validation ; le moyen de paiement y était
 * donc moins contrôlé que partout ailleurs.
 */
class RenewSubscriptionRequest extends FormRequest
{
    use ValidatesPaymentInstrument;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareInstrumentInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->instrumentRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->instrumentMessages();
    }
}
