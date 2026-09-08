<?php

namespace App\Console\Commands;

use App\Models\SiteReport;
use App\Models\VerificationDocument;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Supprime du disque privé les fichiers que plus aucune ligne ne référence.
 *
 *   php artisan storage:prune-orphans --dry-run   # inventaire seul
 *   php artisan storage:prune-orphans             # inventaire puis confirmation
 *   php artisan storage:prune-orphans --force     # sans confirmation
 *
 * Un `migrate:fresh --seed` vide les tables sans toucher au disque : pièces
 * KYC et photos de chantier s'y accumulent d'un seed à l'autre, plus servies
 * par personne — la route de téléchargement passe toujours par la ligne en
 * base. Cette commande fait le ménage.
 *
 * Elle signale aussi le symptôme inverse, plus grave : une ligne en base dont
 * le fichier a disparu. Elle n'y touche pas — c'est une décision métier, pas un
 * nettoyage.
 */
class PruneOrphanFiles extends Command
{
    protected $signature = 'storage:prune-orphans
                            {--dry-run : lister sans rien supprimer}
                            {--force : supprimer sans demander confirmation}';

    protected $description = 'Supprime les fichiers du disque privé qui ne sont plus référencés en base';

    /** Dossiers du disque privé gérés par l'application. */
    private const FOLDERS = ['kyc', 'reports'];

    public function handle(): int
    {
        $disk = Storage::disk('local');

        $referenced = $this->referencedPaths();
        $orphans = $this->orphans($disk, $referenced);

        // Une ligne sans fichier ne se répare pas ici, mais se signale : c'est
        // le seul cas où l'écart entre la base et le disque perd une donnée.
        $missing = array_values(array_filter(
            array_keys($referenced),
            fn (string $path) => ! $disk->exists($path),
        ));

        $this->line(sprintf(
            '%d fichier(s) référencé(s) en base, %d orphelin(s) sur le disque.',
            count($referenced),
            count($orphans),
        ));

        foreach ($missing as $path) {
            $this->warn("Référencé en base mais absent du disque : {$path}");
        }

        if ($orphans === []) {
            $this->info('Rien à supprimer.');

            return self::SUCCESS;
        }

        $this->table(
            ['Fichier', 'Taille'],
            array_map(fn (string $path) => [$path, $this->humanSize($disk->size($path))], $orphans),
        );

        $total = array_sum(array_map(fn (string $path) => $disk->size($path), $orphans));

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                'Inventaire seul : %s libérable(s). Relancez sans --dry-run pour supprimer.',
                $this->humanSize($total),
            ));

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! $this->confirm(sprintf('Supprimer ces %d fichier(s) ?', count($orphans)))) {
            $this->warn('Abandon, rien n’a été supprimé.');

            return self::SUCCESS;
        }

        foreach ($orphans as $path) {
            $disk->delete($path);
        }

        // Un dossier vidé de ses fichiers n'a plus de raison d'être : laissé en
        // place, il ferait croire à un dossier KYC encore constitué.
        foreach ($disk->allDirectories() as $directory) {
            if ($disk->allFiles($directory) === []) {
                $disk->deleteDirectory($directory);
            }
        }

        $this->info(sprintf('%d fichier(s) supprimé(s), %s libéré(s).', count($orphans), $this->humanSize($total)));

        return self::SUCCESS;
    }

    /**
     * Chemins référencés en base, indexés par chemin pour une comparaison en
     * temps constant.
     *
     * @return array<string, true>
     */
    private function referencedPaths(): array
    {
        $paths = VerificationDocument::pluck('file_path')->all();

        // Les photos de chantier sont des objets {path, original_name} : c'est
        // `path` qui porte le chemin sur le disque, pas l'entrée elle-même.
        foreach (SiteReport::pluck('photos') as $photos) {
            foreach ($photos ?? [] as $photo) {
                $paths[] = $photo['path'] ?? null;
            }
        }

        return array_fill_keys(array_filter($paths, fn ($path) => is_string($path) && $path !== ''), true);
    }

    /**
     * Fichiers présents sur le disque et absents de la base.
     *
     * @param  array<string, true>  $referenced
     * @return array<int, string>
     */
    private function orphans(Filesystem $disk, array $referenced): array
    {
        $orphans = [];

        foreach (self::FOLDERS as $folder) {
            foreach ($disk->allFiles($folder) as $path) {
                if (! isset($referenced[$path])) {
                    $orphans[] = $path;
                }
            }
        }

        sort($orphans);

        return $orphans;
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1).' Mo';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024).' Ko';
        }

        return $bytes.' o';
    }
}
