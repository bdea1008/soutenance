<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\DocumentStatus;
use App\Enums\KycStatus;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\VerificationDocument;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * File de modération des pièces KYC (§7.5, espace administrateur).
 * Chaque décision recalcule le statut de vérification de l'utilisateur.
 */
class DocumentReviewController extends Controller
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    /** Pièces à examiner, les plus anciennes d'abord. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $documents = VerificationDocument::query()
            ->with(['user', 'reviewer'])
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')),
                fn ($q) => $q->where('status', DocumentStatus::Pending->value),
            )
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->orderBy('created_at')
            ->paginate($request->integer('per_page', 20));

        return DocumentResource::collection($documents);
    }

    /** Compteurs pour le tableau de bord administrateur. */
    public function stats(): JsonResponse
    {
        $counts = VerificationDocument::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'pending' => (int) ($counts[DocumentStatus::Pending->value] ?? 0),
            'approved' => (int) ($counts[DocumentStatus::Approved->value] ?? 0),
            'rejected' => (int) ($counts[DocumentStatus::Rejected->value] ?? 0),
        ]);
    }

    /**
     * Valider ou rejeter une pièce. Un rejet doit être motivé : le demandeur
     * a besoin de savoir quoi corriger.
     */
    public function review(Request $request, VerificationDocument $document): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => [
                Rule::requiredIf($request->input('decision') === 'reject'),
                'nullable', 'string', 'max:500',
            ],
        ], [
            'note.required' => 'Un motif est requis pour rejeter une pièce.',
        ]);

        $approved = $validated['decision'] === 'approve';

        $document->update([
            'status' => $approved ? DocumentStatus::Approved->value : DocumentStatus::Rejected->value,
            'review_note' => $validated['note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Le statut KYC de l'utilisateur découle de l'état de ses pièces.
        $owner = $document->user;
        $wasVerified = $owner->isKycVerified();
        $kycStatus = $owner->recomputeKycStatus();

        // L'utilisateur doit savoir qu'une décision a été prise sur sa pièce —
        // surtout en cas de rejet, où il a une action à mener (§7.7).
        $this->notifier->notify(
            $owner,
            $approved ? NotificationType::DocumentApproved : NotificationType::DocumentRejected,
            [
                'document_type' => $document->type->label(),
                'review_note' => $validated['note'] ?? null,
                'url' => '/verification',
            ],
        );

        // Franchissement du seuil : le dossier vient d'être complété.
        if (! $wasVerified && $kycStatus === KycStatus::Verified) {
            $this->notifier->notify($owner, NotificationType::KycVerified, [
                'role' => $owner->role->value,
                'url' => '/verification',
            ]);
        }

        return response()->json([
            'message' => $approved ? 'Pièce validée.' : 'Pièce rejetée.',
            'document' => new DocumentResource($document->fresh()->load(['user', 'reviewer'])),
            'user_kyc_status' => $kycStatus->value,
            'user_kyc_status_label' => $kycStatus->label(),
        ]);
    }
}
