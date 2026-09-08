<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\AiScoringClient;
use Illuminate\Console\Command;

/**
 * (Re)calcule les scores IA des projets (§8).
 *
 *   php artisan projects:score            # projets publics sans score à jour
 *   php artisan projects:score --all      # tous les projets, score refait
 *   php artisan projects:score --id=3
 */
class ScoreProjects extends Command
{
    protected $signature = 'projects:score
                            {--all : recalculer même les projets déjà scorés}
                            {--id=* : limiter à ces identifiants de projet}';

    protected $description = 'Calcule le score de confiance des projets via le service IA';

    public function handle(AiScoringClient $ai): int
    {
        if (! $ai->isEnabled()) {
            $this->warn('Scoring IA désactivé (AI_SCORING_ENABLED=false).');

            return self::SUCCESS;
        }

        $health = $ai->health();

        if ($health === null) {
            $this->error('Service de scoring injoignable sur '.config('services.ai_scoring.url').'.');
            $this->line('Démarrez-le : cd ai-service && ./run.sh');

            return self::FAILURE;
        }

        $this->info("Service IA : {$health['status']} · modèle {$health['model_version']}");

        $projects = Project::query()
            ->with('promoter')
            ->when($this->option('id'), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->when(
                ! $this->option('all') && ! $this->option('id'),
                // Par défaut, on ne score que ce qui est visible du public.
                fn ($q) => $q->whereIn('status', array_map(
                    fn (ProjectStatus $s) => $s->value,
                    ProjectStatus::publicStatuses(),
                )),
            )
            ->get();

        if ($projects->isEmpty()) {
            $this->warn('Aucun projet à scorer.');

            return self::SUCCESS;
        }

        $rows = [];
        $failures = 0;

        foreach ($projects as $project) {
            $score = $ai->scoreProject($project);

            if ($score === null) {
                $failures++;
                $rows[] = [$project->id, $project->title, '—', '—', 'échec'];

                continue;
            }

            $rows[] = [
                $project->id,
                mb_strimwidth($project->title, 0, 34, '…'),
                $score->confidence_score,
                $score->roi_estimate.' %',
                $score->risk_level->label(),
            ];
        }

        $this->table(['#', 'Projet', 'Confiance', 'ROI estimé', 'Risque'], $rows);

        $scored = $projects->count() - $failures;
        $this->info("{$scored} projet(s) scorés.".($failures > 0 ? " {$failures} échec(s)." : ''));

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
