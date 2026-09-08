<?php

namespace App\Http\Requests\SiteReport;

/**
 * Correction d'un rapport déjà publié. Les photos déposées ici s'ajoutent aux
 * précédentes (le total reste borné, contrôle fait en contrôleur).
 */
class UpdateSiteReportRequest extends StoreSiteReportRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'progress_percentage' => ['sometimes', 'required', 'integer', 'between:0,100'],
        ];
    }
}
