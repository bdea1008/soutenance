<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContributionStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contribution\StoreContributionRequest;
use App\Http\Resources\ContributionResource;
use App\Models\Contribution;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Notifier;
use App\Support\PaymentInstrument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContributionController extends Controller
{
    public function __construct(private readonly Notifier $notifier) {}

    /** Historique des contributions de l'investisseur authentifié (§7.3). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $contributions = $request->user()
            ->contributions()
            ->with(['project', 'payment'])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ContributionResource::collection($contributions);
    }

    /**
     * Investir dans un projet (contribution simulée en MVP, §16.5) : inclut
     * le paiement du montant investi (§2, « Effectuer un paiement ») — sans
     * frais pour l'investisseur, le paiement transfère exactement le montant
     * investi, rien de plus. Route protégée par `auth:api` + `role:investor`
     * + `kyc.verified`.
     */
    public function store(StoreContributionRequest $request, Project $project): JsonResponse
    {
        // On n'investit que dans un projet ouvert au financement.
        if (! in_array($project->status, [ProjectStatus::Published], true)) {
            return response()->json([
                'message' => "Ce projet n'est pas ouvert au co-investissement.",
            ], 409);
        }

        $amount = $request->integer('amount');

        if ($project->min_investment > 0 && $amount < $project->min_investment) {
            return response()->json([
                'message' => "Le montant est inférieur au ticket minimum ({$project->min_investment} FCFA).",
            ], 422);
        }

        $remaining = max(0, $project->funding_goal - $project->amount_raised);
        if ($amount > $remaining) {
            return response()->json([
                'message' => "Le montant dépasse le reste à financer ($remaining FCFA).",
                'remaining' => $remaining,
            ], 422);
        }

        // Le tri de ce qui sera conservé est fait une fois pour toutes ici :
        // le contrôleur ne voit jamais le numéro de carte ni le cryptogramme.
        $instrument = PaymentInstrument::fromValidated($request->validated(), $request->user());

        $contribution = DB::transaction(function () use ($request, $project, $amount, $instrument) {
            $user = $request->user();

            // Verrou pessimiste pour un cumul cohérent des montants.
            $locked = Project::whereKey($project->id)->lockForUpdate()->first();

            $share = $locked->funding_goal > 0
                ? round($amount / $locked->funding_goal * 100, 3)
                : 0;

            $estimatedReturn = $locked->expected_return_rate
                ? round($amount * ($locked->expected_return_rate / 100), 2)
                : null;

            $contribution = Contribution::create([
                'investor_id' => $user->id,
                'project_id' => $locked->id,
                'amount' => $amount,
                'share_percentage' => $share,
                'estimated_return' => $estimatedReturn,
                'status' => ContributionStatus::Confirmed->value, // simulé => confirmé
                'is_simulated' => true,
                'confirmed_at' => now(),
            ]);

            // Paiement du montant investi (§2, inclus dans « Investir dans un
            // projet ») : même mécanique que l'abonnement promoteur — simulé,
            // sans mouvement de fonds réel (§16.5) — mais un vrai paiement,
            // pas une simple ligne comptable. Le montant est exactement celui
            // investi, l'investisseur ne paie rien de plus.
            $payment = Payment::create([
                'user_id' => $user->id,
                'contribution_id' => $contribution->id,
                'project_id' => $locked->id,
                'purpose' => PaymentPurpose::Contribution->value,
                ...$instrument->columns(),
                'amount' => $amount,
                'currency' => 'XOF',
                'status' => PaymentStatus::Simulated->value,
                'reference' => 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'provider_payload' => ['simulated' => true, 'project_id' => $locked->id],
                'paid_at' => now(),
            ]);

            Transaction::create([
                'payment_id' => $payment->id,
                'user_id' => $user->id,
                'direction' => 'debit',
                'amount' => $amount,
                'currency' => 'XOF',
                'label' => "Investissement — {$locked->title}",
                'meta' => ['contribution_id' => $contribution->id, 'project_id' => $locked->id],
            ]);

            $locked->amount_raised += $amount;
            if ($locked->amount_raised >= $locked->funding_goal) {
                $locked->status = ProjectStatus::Funded->value;
            }
            $locked->save();

            return $contribution;
        });

        $this->notifyInvestment($project->fresh(), $contribution, $request->user());

        return response()->json([
            'message' => 'Investissement enregistré (simulé).',
            'contribution' => new ContributionResource($contribution->load(['project', 'payment'])),
        ], 201);
    }

    /**
     * Notifications déclenchées par un investissement (§7.7). Hors transaction :
     * un incident d'écriture de notification ne doit pas annuler l'opération
     * financière qui, elle, a bien eu lieu.
     */
    private function notifyInvestment(Project $project, Contribution $contribution, User $investor): void
    {
        $context = [
            'project_id' => $project->id,
            'project_title' => $project->title,
            'amount' => $contribution->amount,
            'url' => "/projets/{$project->id}",
        ];

        // Confirmation à l'investisseur.
        $this->notifier->notify($investor, NotificationType::InvestmentConfirmed, $context);

        // Le promoteur suit la collecte de son projet en temps réel.
        if ($project->promoter) {
            $this->notifier->notify($project->promoter, NotificationType::InvestmentReceived, $context);
        }

        // Objectif atteint : tout le monde est concerné, promoteur comme
        // co-investisseurs déjà engagés.
        if ($project->status === ProjectStatus::Funded) {
            $this->notifier->notifyMany(
                $project->contributors()->push($project->promoter),
                NotificationType::ProjectFunded,
                $context,
            );
        }
    }
}
