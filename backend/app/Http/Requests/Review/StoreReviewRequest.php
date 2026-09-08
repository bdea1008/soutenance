<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Note et commentaire sur un projet. Réservé aux investisseurs vérifiés KYC
 * ayant réellement investi dans le projet — vérifié en contrôleur, pas ici,
 * puisque ça dépend du projet visé par la route.
 */
class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'Une note de 1 à 5 est requise.',
            'rating.min' => 'La note doit être comprise entre 1 et 5.',
            'rating.max' => 'La note doit être comprise entre 1 et 5.',
        ];
    }
}
