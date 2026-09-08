<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mise à jour d'un projet. L'autorisation (propriétaire / admin) est gérée
 * par la ProjectPolicy dans le contrôleur.
 */
class UpdateProjectRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:280'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:60'],

            'region' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],

            'funding_goal' => ['sometimes', 'integer', 'min:100000'],
            'min_investment' => ['nullable', 'integer', 'min:0'],
            'expected_return_rate' => ['nullable', 'numeric', 'between:0,100'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:120'],

            'cover_image' => ['nullable', 'string', 'max:255'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'max:255'],
        ];
    }
}
