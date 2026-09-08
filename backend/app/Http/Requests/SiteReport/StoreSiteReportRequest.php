<?php

namespace App\Http\Requests\SiteReport;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Publication d'un rapport d'avancement de chantier (§7.5).
 */
class StoreSiteReportRequest extends FormRequest
{
    /** Taille maximale d'une photo, en kilo-octets. */
    public const MAX_PHOTO_KB = 5120; // 5 Mo

    /** Nombre de photos par rapport. */
    public const MAX_PHOTOS = 6;

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
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'progress_percentage' => ['required', 'integer', 'between:0,100'],
            // Date de l'observation : le rapport peut être publié après coup,
            // mais jamais daté du futur.
            'reported_at' => ['nullable', 'date', 'before_or_equal:today'],

            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_PHOTO_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Donnez un titre au rapport.',
            'progress_percentage.required' => "Indiquez l'avancement du chantier.",
            'progress_percentage.between' => "L'avancement doit être compris entre 0 et 100 %.",
            'reported_at.before_or_equal' => 'Un rapport ne peut pas être daté du futur.',
            'photos.max' => 'Six photos au maximum par rapport.',
            'photos.*.mimes' => 'Formats de photo acceptés : JPG, PNG ou WEBP.',
            'photos.*.max' => 'Chaque photo doit faire moins de 5 Mo.',
        ];
    }
}
