<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\ProjectStatus;
use App\Models\AiScore;
use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Client du service de scoring IA (§8), hébergé à part (dossier ai-service/).
 *
 * Principe de dégradation : le scoring enrichit la décision d'investissement,
 * il ne la conditionne pas. Si le service est arrêté ou lent, le backend
 * continue de fonctionner et le dernier score connu reste affiché.
 */
class AiScoringClient
{
    public function isEnabled(): bool
    {
        return (bool) config('services.ai_scoring.enabled');
    }

    /** Le service répond-il ? Utilisé par la commande de scoring et le diagnostic. */
    public function health(): ?array
    {
        try {
            $response = Http::timeout($this->timeout())
                ->get($this->url('/health'));

            return $response->successful() ? $response->json() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Calcule et enregistre le score d'un projet.
     * Renvoie `null` si le service est indisponible — l'appelant doit
     * simplement poursuivre.
     */
    public function scoreProject(Project $project): ?AiScore
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $payload = $this->featuresFor($project);

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->post($this->url('/score'), $payload);

            if (! $response->successful()) {
                Log::warning('Scoring IA : réponse inattendue', [
                    'project_id' => $project->id,
                    'status' => $response->status(),
                ]);

                return null;
            }
        } catch (Throwable $e) {
            Log::warning('Scoring IA injoignable', [
                'project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        return $this->store($project, $response->json());
    }

    /**
     * Caractéristiques envoyées au modèle (§8.1). L'historique du promoteur et
     * la vérification des pièces sont des signaux de confiance à part entière :
     * ils viennent des modules projets et KYC déjà en place.
     *
     * @return array<string, mixed>
     */
    public function featuresFor(Project $project): array
    {
        $promoter = $project->promoter;

        $completed = $promoter
            ? $promoter->projects()->where('status', ProjectStatus::Completed->value)->count()
            : 0;

        $active = $promoter
            ? $promoter->projects()
                ->whereIn('status', array_map(
                    fn (ProjectStatus $s) => $s->value,
                    ProjectStatus::quotaStatuses(),
                ))
                ->count()
            : 0;

        // Pièces propres au projet (titre foncier, permis) validées par un admin.
        $documentsVerified = $project->verificationDocuments()
            ->where('status', DocumentStatus::Approved->value)
            ->exists();

        return [
            'project_id' => $project->id,
            'region' => $project->region,
            'category' => $project->category,
            'funding_goal' => (float) $project->funding_goal,
            'expected_return_rate' => (float) $project->expected_return_rate,
            'duration_months' => (int) ($project->duration_months ?: 12),
            'min_investment' => (float) $project->min_investment,

            'promoter_completed_projects' => $completed,
            'promoter_active_projects' => $active,
            'promoter_months_active' => $promoter
                ? (int) $promoter->created_at->diffInMonths(now())
                : 0,
            'promoter_kyc_verified' => (bool) $promoter?->isKycVerified(),
            'project_documents_verified' => $documentsVerified,
        ];
    }

    /**
     * Enregistre un score. L'historique est conservé : `latestScore` prend le
     * plus récent, ce qui permet de montrer l'évolution d'un projet.
     *
     * @param  array<string, mixed>  $result
     */
    private function store(Project $project, array $result): AiScore
    {
        return $project->scores()->create([
            'confidence_score' => $result['confidence_score'],
            'roi_estimate' => $result['roi_estimate'] ?? null,
            'payback_months' => $result['payback_months'] ?? null,
            'risk_level' => $result['risk_level'] ?? 'medium',
            'factors' => $result['factors'] ?? null,
            'model_version' => $result['model_version'] ?? null,
            'computed_at' => now(),
        ]);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.ai_scoring.url'), '/').$path;
    }

    private function timeout(): int
    {
        return (int) config('services.ai_scoring.timeout', 5);
    }
}
