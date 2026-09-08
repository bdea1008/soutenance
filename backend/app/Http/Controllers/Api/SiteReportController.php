<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationType;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SiteReport\StoreSiteReportRequest;
use App\Http\Requests\SiteReport\UpdateSiteReportRequest;
use App\Http\Resources\SiteReportResource;
use App\Models\Project;
use App\Models\SiteReport;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Suivi de chantier (§7.5) : le promoteur rend compte de l'avancement de son
 * projet, les investisseurs suivent la progression et les preuves photo.
 *
 * La lecture est ouverte à tout utilisateur authentifié (niveau 2), au même
 * titre que le détail d'un projet : la transparence du chantier est ce qui
 * donne confiance avant d'investir.
 */
class SiteReportController extends Controller
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    /** Disque de stockage des photos (storage/app/private). */
    private const DISK = 'local';

    /**
     * Statuts d'un projet pour lesquels un rapport a du sens : le chantier
     * n'existe qu'une fois le financement bouclé.
     *
     * @return array<int, ProjectStatus>
     */
    private static function reportableStatuses(): array
    {
        return [ProjectStatus::Funded, ProjectStatus::InProgress, ProjectStatus::Completed];
    }

    /** Journal d'avancement d'un projet, du plus récent au plus ancien. */
    public function index(Request $request, Project $project): AnonymousResourceCollection|JsonResponse
    {
        // Même règle de visibilité que le détail du projet : un projet retiré
        // ne doit pas rester consultable par la porte de derrière.
        if (! $this->manages($project, $request->user())
            && ! in_array($project->status, ProjectStatus::publicStatuses(), true)) {
            return response()->json(['message' => 'Projet introuvable.'], 404);
        }

        $reports = $project->siteReports()
            ->with('author')
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10));

        return SiteReportResource::collection($reports);
    }

    /**
     * Publier un rapport. Réservé au promoteur du projet : c'est lui qui est
     * sur le chantier, et sa responsabilité qui est engagée.
     */
    public function store(StoreSiteReportRequest $request, Project $project): JsonResponse
    {
        $user = $request->user();

        if (! $this->manages($project, $user)) {
            return response()->json([
                'message' => 'Seul le promoteur du projet peut publier un rapport.',
            ], 403);
        }

        if (! in_array($project->status, self::reportableStatuses(), true)) {
            return response()->json([
                'message' => "Le suivi de chantier s'ouvre une fois le projet financé "
                    ."(statut actuel : {$project->status->label()}).",
            ], 409);
        }

        $progress = $request->integer('progress_percentage');

        // Un chantier ne recule pas : le rapport précédent fait plancher.
        $previous = $project->siteReports()->max('progress_percentage');
        if ($previous !== null && $progress < $previous) {
            return response()->json([
                'message' => "L'avancement ne peut pas être inférieur au dernier rapport ({$previous} %).",
                'previous_progress' => (int) $previous,
            ], 422);
        }

        $report = SiteReport::create([
            'project_id' => $project->id,
            'author_id' => $user->id,
            'title' => $request->string('title')->value(),
            'description' => $request->input('description'),
            'progress_percentage' => $progress,
            'photos' => $this->storePhotos($project, $request->file('photos', [])),
            'reported_at' => $request->date('reported_at') ?? now(),
        ]);

        // Le premier rapport acte le démarrage effectif du chantier.
        if ($project->status === ProjectStatus::Funded) {
            $project->update(['status' => ProjectStatus::InProgress->value]);
        }

        // Notification automatique aux investisseurs du projet (§7.5) : c'est
        // la contrepartie de leur engagement, ils n'ont pas à venir vérifier.
        $this->notifier->notifyMany(
            $project->contributors(),
            NotificationType::ReportPublished,
            [
                'project_id' => $project->id,
                'project_title' => $project->title,
                'report_title' => $report->title,
                'progress' => $report->progress_percentage,
                'url' => "/projets/{$project->id}",
            ],
            exceptUserId: $user->id,
        );

        return response()->json([
            'message' => 'Rapport publié.',
            'report' => new SiteReportResource($report->load('author')),
            'project_status' => $project->fresh()->status->value,
        ], 201);
    }

    /** Corriger un rapport. Les photos envoyées ici s'ajoutent aux existantes. */
    public function update(UpdateSiteReportRequest $request, SiteReport $report): JsonResponse
    {
        $user = $request->user();

        if (! $this->manages($report->project, $user)) {
            return response()->json(['message' => 'Rapport introuvable.'], 404);
        }

        $photos = $report->photos ?? [];
        $incoming = $request->file('photos', []);

        if ($incoming !== [] && count($photos) + count($incoming) > StoreSiteReportRequest::MAX_PHOTOS) {
            return response()->json([
                'message' => 'Six photos au maximum par rapport ('.count($photos).' déjà déposées).',
            ], 422);
        }

        $report->update([
            ...$request->safe()->only(['title', 'description', 'progress_percentage', 'reported_at']),
            'photos' => [...$photos, ...$this->storePhotos($report->project, $incoming)],
        ]);

        return response()->json([
            'message' => 'Rapport mis à jour.',
            'report' => new SiteReportResource($report->fresh()->load('author')),
        ]);
    }

    /** Retirer un rapport, ainsi que ses photos sur le disque. */
    public function destroy(Request $request, SiteReport $report): JsonResponse
    {
        if (! $this->manages($report->project, $request->user())) {
            return response()->json(['message' => 'Rapport introuvable.'], 404);
        }

        foreach ($report->photos ?? [] as $photo) {
            Storage::disk(self::DISK)->delete($photo['path']);
        }

        $report->delete();

        return response()->json(['message' => 'Rapport retiré.']);
    }

    /**
     * Servir une photo du rapport. Comme les pièces KYC, les fichiers vivent
     * hors du dossier public et ne transitent que par cette route authentifiée.
     */
    public function photo(Request $request, SiteReport $report, int $index): StreamedResponse|JsonResponse
    {
        if (! $this->manages($report->project, $request->user())
            && ! in_array($report->project->status, ProjectStatus::publicStatuses(), true)) {
            return response()->json(['message' => 'Photo introuvable.'], 404);
        }

        $photo = ($report->photos ?? [])[$index] ?? null;

        if ($photo === null || ! Storage::disk(self::DISK)->exists($photo['path'])) {
            return response()->json(['message' => 'Photo introuvable.'], 404);
        }

        return Storage::disk(self::DISK)->response(
            $photo['path'],
            $photo['original_name'] ?? basename($photo['path']),
            ['Cache-Control' => 'private, max-age=3600'],
        );
    }

    /**
     * Le promoteur propriétaire du projet, un administrateur, ou le rôle
     * juridique.
     *
     * Sert ici deux usages : la **visibilité** d'un journal dont le projet
     * n'est plus public (index, photo) — où l'accès administratif ou
     * juridique relève de la supervision — et l'**écriture** (store, update,
     * destroy), qui reste l'affaire du seul promoteur. L'écriture est fermée
     * à l'administrateur et au rôle juridique en amont, par le middleware
     * `role:promoter` de son groupe de routes.
     */
    private function manages(Project $project, User $user): bool
    {
        return $project->promoter_id === $user->id || $user->isAdmin() || $user->isLegal();
    }

    /**
     * Enregistre les photos sous un nom neutre et renvoie les métadonnées
     * destinées à la colonne JSON.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, array{path: string, original_name: string}>
     */
    private function storePhotos(Project $project, array $files): array
    {
        return array_map(function (UploadedFile $file) use ($project) {
            $name = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();

            return [
                'path' => $file->storeAs("reports/{$project->id}", $name, self::DISK),
                'original_name' => $file->getClientOriginalName(),
            ];
        }, array_values($files));
    }
}
