<?php

namespace App\Http\Resources;

use App\Support\DossierChecklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue « détail complet » d'un projet — réservée aux utilisateurs authentifiés
 * (niveau 2) et au promoteur propriétaire / admin.
 *
 * @mixin \App\Models\Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'category' => $this->category,

            'location' => [
                'region' => $this->region,
                'city' => $this->city,
                'address' => $this->address,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],

            'financials' => [
                'currency' => 'XOF',
                'funding_goal' => $this->funding_goal,
                'amount_raised' => $this->amount_raised,
                'funding_progress' => $this->fundingProgress(),
                'min_investment' => $this->min_investment,
                'expected_return_rate' => $this->expected_return_rate,
                'duration_months' => $this->duration_months,
            ],

            'cover_image' => $this->cover_image,
            'images' => $this->images ?? [],

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'published_at' => $this->published_at,

            // Avancement du dossier de l'opération (niveau 3), présent
            // uniquement là où les pièces ont été chargées — c'est-à-dire dans
            // l'espace du promoteur, jamais dans la fiche publique.
            'dossier' => $this->when(
                $this->relationLoaded('verificationDocuments'),
                fn () => DossierChecklist::progress($this->dossierChecklist()),
            ),

            'promoter' => $this->whenLoaded('promoter', fn () => [
                'id' => $this->promoter->id,
                'name' => $this->promoter->name,
                'kyc_verified' => $this->promoter->isKycVerified(),
            ]),

            'ai_score' => $this->whenLoaded('latestScore', function () {
                return $this->latestScore ? [
                    'confidence_score' => $this->latestScore->confidence_score,
                    'roi_estimate' => $this->latestScore->roi_estimate,
                    'payback_months' => $this->latestScore->payback_months,
                    'risk_level' => $this->latestScore->risk_level->value,
                    'risk_label' => $this->latestScore->risk_level->label(),
                    'factors' => $this->latestScore->factors,
                    'model_version' => $this->latestScore->model_version,
                    'computed_at' => $this->latestScore->computed_at,
                ] : null;
            }),

            'contributors_count' => $this->whenCounted('contributions'),

            // Avis d'investisseurs (§2, extension d'« Investir ») : moyenne et
            // nombre, calculés en une requête plutôt que de charger les avis
            // eux-mêmes — la liste complète vit sur sa propre route paginée.
            'reviews_summary' => $this->when($this->reviews_count !== null, fn () => [
                'average' => $this->reviews_avg_rating !== null ? round((float) $this->reviews_avg_rating, 1) : null,
                'count' => (int) $this->reviews_count,
            ]),

            // Suivi de chantier (§7.5) : dernier point d'avancement publié.
            'reports_count' => $this->whenCounted('siteReports'),
            'construction' => $this->whenLoaded('latestReport', function () {
                return $this->latestReport ? [
                    'progress_percentage' => $this->latestReport->progress_percentage,
                    'last_report_title' => $this->latestReport->title,
                    'last_reported_at' => $this->latestReport->reported_at,
                ] : null;
            }),

            'created_at' => $this->created_at,
        ];
    }
}
