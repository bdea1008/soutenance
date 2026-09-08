<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SimulatedEmailResource;
use App\Models\SimulatedEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Boîte d'envoi simulée, réservée à l'administration.
 *
 * Même rôle que le journal des paiements pour les paiements simulés : montrer
 * ce que la plateforme aurait envoyé. Tant qu'aucun serveur de messagerie
 * n'est branché, c'est le seul endroit où l'on peut constater qu'un message
 * est bien parti — et, en démonstration, l'ouvrir.
 *
 * L'écran n'a de sens que pendant la phase simulée : une fois MAIL_MAILER
 * basculé sur un vrai transport, la boîte cesse de se remplir et les
 * réponses le signalent (`meta.simulation_active`).
 */
class MailboxController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $emails = SimulatedEmail::query()
            ->with('user')
            ->when($request->filled('context'), fn ($q) => $q->where('context', $request->string('context')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $needle = '%'.$request->string('q').'%';

                $q->where(fn ($sub) => $sub
                    ->where('to_email', 'like', $needle)
                    ->orWhere('to_name', 'like', $needle)
                    ->orWhere('subject', 'like', $needle));
            })
            // Deux messages peuvent naître dans la même seconde (demande puis
            // confirmation) : sans départage sur l'identifiant, leur ordre
            // d'affichage serait arbitraire.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return SimulatedEmailResource::collection($emails)
            ->additional(['meta_counts' => $this->counts()]);
    }

    public function show(SimulatedEmail $email): JsonResource
    {
        return new SimulatedEmailResource($email->load('user'));
    }

    /** Vider la boîte — outil de démonstration, pas un journal à conserver. */
    public function destroyAll(): JsonResponse
    {
        $deleted = SimulatedEmail::query()->delete();

        return response()->json([
            'message' => $deleted === 0
                ? 'La boîte était déjà vide.'
                : "{$deleted} message(s) supprimé(s).",
            'deleted' => $deleted,
        ]);
    }

    /**
     * Compteurs servant de filtres à l'écran.
     *
     * @return array<string, mixed>
     */
    private function counts(): array
    {
        $byContext = SimulatedEmail::query()
            ->selectRaw('context, count(*) as total')
            ->groupBy('context')
            ->pluck('total', 'context');

        return [
            'total' => (int) $byContext->sum(),
            'simulation_active' => config('mail.default') === 'simulated',
            'by_context' => $byContext
                ->map(fn (int $total, ?string $context) => [
                    'context' => $context,
                    'label' => SimulatedEmail::contextLabel($context),
                    'value' => $total,
                ])
                ->values(),
        ];
    }
}
