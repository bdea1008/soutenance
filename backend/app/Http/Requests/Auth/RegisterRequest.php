<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Http\Requests\Concerns\NormalizesPhoneNumber;
use App\Http\Requests\Concerns\ValidatesPromoterIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            // Obligatoire : c'est le canal de contact du terrain (Wave, Orange
            // Money, relance d'un promoteur) et le seul identifiant que
            // beaucoup d'utilisateurs consultent au quotidien.
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+[1-9]\d{7,14}$/', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::defaults()],
            // À l'inscription, l'utilisateur choisit d'être investisseur ou promoteur.
            // Le rôle admin ne peut pas être auto-attribué.
            'role' => ['required', Rule::in([UserRole::Investor->value, UserRole::Promoter->value])],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],

            // Un promoteur précise en plus son sous-type, et sa structure s'il
            // en est une. Rien de plus à l'inscription : le dossier de pièces
            // n'est demandé qu'au moment de mettre un projet en financement.
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
            'role.in' => 'Choisissez « investisseur » ou « promoteur ».',
            ...$this->promoterIdentityMessages(),
        ];
    }
}
