<?php

namespace App\Http\Requests\Contribution;

use App\Http\Requests\Concerns\ValidatesPaymentInstrument;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Investissement dans un projet. Sans frais pour l'investisseur (§2) : le
 * moyen de paiement choisit seulement comment le montant investi transite,
 * il n'ajoute rien au-dessus. Réservé aux investisseurs vérifiés KYC
 * (middlewares sur la route).
 *
 * Les coordonnées du moyen de paiement sont validées par le trait partagé
 * avec l'abonnement : les deux encaissements de la plateforme exigent
 * exactement les mêmes.
 */
class StoreContributionRequest extends FormRequest
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
        return [
            'amount' => ['required', 'integer', 'min:1'],
            ...$this->instrumentRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->instrumentMessages();
    }
}
