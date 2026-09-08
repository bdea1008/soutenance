<?php

namespace App\Http\Requests\Subscription;

use App\Enums\SubscriptionTier;
use App\Http\Requests\Concerns\ValidatesPaymentInstrument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Souscription à un palier d'abonnement promoteur. Le rôle est garanti par le
 * middleware `role:promoter` sur la route.
 */
class StoreSubscriptionRequest extends FormRequest
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
            // Les paliers ouverts dépendent du sous-type de promoteur : le
            // palier « Particulier » n'est pas un palier d'entrée bon marché
            // pour une société, et les paliers professionnels n'ont pas de sens
            // pour quelqu'un qui finance sa propre maison.
            'tier' => ['required', Rule::in(array_map(
                fn (SubscriptionTier $tier) => $tier->value,
                $this->user()->promoterProfile()?->subscriptionTiers() ?? SubscriptionTier::cases(),
            ))],
            ...$this->instrumentRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tier.required' => 'Veuillez choisir un palier d’abonnement.',
            'tier.in' => "Ce palier n'est pas proposé pour votre type de compte.",
            ...$this->instrumentMessages(),
        ];
    }
}
