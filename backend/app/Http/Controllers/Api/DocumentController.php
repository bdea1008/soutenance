<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\VerificationContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Project;
use App\Models\VerificationDocument;
use App\Support\DossierChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dépôt et consultation des pièces de vérification (KYC, §7.1/§7.2).
 * Les fichiers sont stockés sur le disque privé : ils ne sont jamais servis
 * directement, seulement via `download()` après contrôle d'accès.
 */
class DocumentController extends Controller
{
    /** Disque de stockage des pièces (storage/app/private). */
    private const DISK = 'local';

    /**
     * Dossier de l'opérateur (niveau 2) : les pièces déposées une seule fois,
     * l'état de chacune et le statut de vérification global.
     *
     * Les pièces rattachées à un projet en sont exclues : elles relèvent du
     * dossier de l'opération, servi par ProjectDossierController.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $documents = $user->verificationDocuments()
            ->whereNull('project_id')
            ->latest()
            ->get();

        $checklist = $user->kycChecklist();

        return response()->json([
            'kyc_status' => $user->kyc_status->value,
            'kyc_status_label' => $user->kyc_status->label(),
            'kyc_verified' => $user->isKycVerified(),
            // Le sous-type explique au promoteur pourquoi c'est cette liste-là
            // qu'on lui demande, et pas celle de l'autre profil.
            'profile' => $user->promoterProfile()?->value,
            'profile_label' => $user->promoterProfile()?->label(),
            'checklist' => $checklist,
            'progress' => DossierChecklist::progress($checklist),
            'documents' => DocumentResource::collection($documents),
            'accepted' => ['formats' => ['jpg', 'jpeg', 'png', 'pdf'], 'max_mb' => 5],
        ]);
    }

    /**
     * Déposer une pièce. Le dépôt remet la vérification en attente d'examen :
     * un utilisateur rejeté peut ainsi corriger sa pièce.
     */
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $user = $request->user();

        // Toute pièce appartient à un contexte de vérification. L'administrateur
        // n'étant soumis à aucun KYC, il n'en a pas — sauf pièce rattachée à un projet.
        $context = $request->filled('project_id')
            ? VerificationContext::ProjectVerification
            : $user->role->kycContext();

        if ($context === null) {
            return response()->json([
                'message' => "Votre rôle n'est pas soumis à la vérification KYC.",
            ], 422);
        }

        // Une pièce de projet ne peut être déposée que par le promoteur du projet.
        if ($request->filled('project_id')) {
            $project = Project::find($request->integer('project_id'));

            if ($project->promoter_id !== $user->id && ! $user->isAdmin()) {
                return response()->json([
                    'message' => "Vous ne pouvez déposer de pièce que pour vos propres projets.",
                ], 403);
            }
        }

        $file = $request->file('file');

        // Nom de stockage neutre : le nom d'origine est conservé à part.
        $name = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("kyc/{$user->id}", $name, self::DISK);

        $type = DocumentType::from($request->string('type')->value());
        $issuedAt = $request->input('issued_at');

        $document = VerificationDocument::create([
            'user_id' => $user->id,
            'project_id' => $request->input('project_id'),
            'context' => $context->value,
            'type' => $type->value,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'issued_at' => $issuedAt,
            // Jamais posée à la main : la date d'expiration se déduit du type
            // et de la date d'émission (VerificationDocument::expiryFor).
            'expires_at' => VerificationDocument::expiryFor($type, $issuedAt),
            'status' => DocumentStatus::Pending->value,
        ]);

        $status = $user->recomputeKycStatus();

        return response()->json([
            'message' => 'Pièce déposée. Elle sera examinée par un administrateur.',
            'document' => new DocumentResource($document),
            'kyc_status' => $status->value,
        ], 201);
    }

    /**
     * Retirer une pièce. Une pièce déjà validée ne peut plus être retirée :
     * elle constitue la preuve de la vérification.
     */
    public function destroy(Request $request, VerificationDocument $document): JsonResponse
    {
        $user = $request->user();

        if ($document->user_id !== $user->id && ! $user->isAdmin()) {
            return response()->json(['message' => 'Document introuvable.'], 404);
        }

        if ($document->status === DocumentStatus::Approved) {
            return response()->json([
                'message' => 'Une pièce validée ne peut pas être retirée.',
            ], 409);
        }

        Storage::disk(self::DISK)->delete($document->file_path);
        $owner = $document->user;
        $document->delete();

        $owner->recomputeKycStatus();

        return response()->json(['message' => 'Pièce retirée.']);
    }

    /**
     * Téléchargement d'une pièce : réservé à son propriétaire et aux
     * administrateurs (les pièces d'identité ne sont jamais publiques).
     */
    public function download(Request $request, VerificationDocument $document): StreamedResponse|JsonResponse
    {
        $user = $request->user();

        if ($document->user_id !== $user->id && ! $user->isAdmin()) {
            return response()->json(['message' => 'Document introuvable.'], 404);
        }

        if (! Storage::disk(self::DISK)->exists($document->file_path)) {
            return response()->json(['message' => 'Fichier introuvable.'], 404);
        }

        return Storage::disk(self::DISK)->download(
            $document->file_path,
            $document->original_name ?? basename($document->file_path),
        );
    }
}
