<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Http\Requests\Concerns\NormalizesPhoneNumber;
use App\Http\Requests\Concerns\ValidatesPromoterIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Création d'un compte par l'administration.
 *
 * Contrairement à l'inscription publique, les quatre rôles sont accessibles —
 * c'est la seule porte d'entrée du rôle interne « juridique & conformité »
 * (`UserRole::Legal`), qui ne s'auto-attribue jamais.
 */
class StoreUserRequest extends FormRequest
{
    use NormalizesPhoneNumber, ValidatesPromoterIdentity;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhoneInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // Même règle qu'à l'inscription : c'est le canal de contact du
            // terrain, aucun compte n'en est dispensé.
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+[1-9]\d{7,14}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],

            // Mêmes exigences qu'à l'inscription publique : un compte promoteur
            // créé depuis la console porte lui aussi son sous-type.
            ...$this->promoterIdentityRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->promoterIdentityAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.regex' => 'Numéro invalide : indiquez-le au format international, par exemple +221 77 123 45 67.',
            'phone.unique' => 'Ce numéro de téléphone est déjà associé à un compte.',
            ...$this->promoterIdentityMessages(),
        ];
    }
}
