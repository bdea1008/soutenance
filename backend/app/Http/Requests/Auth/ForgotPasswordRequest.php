<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Les adresses sont stockées telles que saisies à l'inscription, mais
        // un utilisateur qui a créé son compte avec « Awa@… » tapera souvent
        // « awa@… » ici. Sans normalisation, la demande resterait sans effet
        // et — réponse générique oblige — sans explication.
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Indiquez l’adresse email de votre compte.',
            'email.email' => 'Cette adresse email n’est pas valide.',
        ];
    }
}
