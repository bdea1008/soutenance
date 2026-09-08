<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PromoterSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Suivi financier de la plateforme, réservé à l'administration.
 *
 * Le chiffre d'affaires d'AndTabbax, ce sont les abonnements promoteur
 * (§16.2.a) : investir est sans frais pour l'investisseur. Les montants
 * collectés sur les projets transitent donc par la plateforme sans lui
 * appartenir — les deux sont présentés séparément pour que la confusion ne
 * soit pas possible.
 *
 * Rappel MVP : les paiements sont simulés (§16.5), aucun mouvement de fonds
 * réel n'a lieu. Les réponses le signalent explicitement.
 */
class FinanceController extends Controller
{
    /** Profondeur de l'historique mensuel, en mois. */
    private const MONTHS = 12;

    /** Synthèse : revenus, abonnements en cours, collecte des projets. */
    public function summary(): JsonResponse
    {
        $paid = [PaymentStatus::Simulated->value, PaymentStatus::Completed->value];

        $payments = Payment::whereIn('status', $paid)->get();
        $subscriptionPayments = $payments->where('purpose', PaymentPurpose::Subscription);

        $subscriptions = PromoterSubscription::all();
        $active = $subscriptions->filter(fn (PromoterSubscription $s) => $s->isActive());

        // Revenu par palier : quel palier fait vivre la plateforme ?
        $byTier = collect(SubscriptionTier::cases())
            ->map(function (SubscriptionTier $tier) use ($subscriptions, $active) {
                $ofTier = $subscriptions->where('tier', $tier);

                return [
                    'tier' => $tier->value,
                    'label' => $tier->label(),
                    'subscriptions' => $ofTier->count(),
                    'active' => $active->where('tier', $tier)->count(),
                    'value' => (int) $ofTier->sum('price'),
                ];
            })
            ->values();

        return response()->json([
            'currency' => 'XOF',
            // Aucun encaissement réel en MVP : à afficher tel quel côté écran.
            'simulated' => true,
            'generated_at' => now(),

            'kpis' => [
                'revenue' => (int) $subscriptionPayments->sum('amount'),
                'revenue_this_month' => (int) $subscriptionPayments
                    ->filter(fn (Payment $p) => ($p->paid_at ?? $p->created_at)?->isCurrentMonth())
                    ->sum('amount'),
                // Comptés parmi les abonnements uniquement : la carte porte
                // le revenu, pas le volume total du journal (qui inclut
                // désormais les paiements de contribution — hors revenu).
                'payments_count' => $subscriptionPayments->count(),
                'contribution_payments_count' => $payments->where('purpose', PaymentPurpose::Contribution)->count(),
                'active_subscriptions' => $active->count(),
                'subscriptions_total' => $subscriptions->count(),
                // Revenu récurrent : les abonnements sont facturés au mois
                // (SubscriptionController::PERIOD_MONTHS), la somme des prix
                // en cours est donc directement le récurrent mensuel.
                'monthly_recurring' => (int) $active->sum('price'),
                'expiring_30d' => $active
                    ->filter(fn (PromoterSubscription $s) => $s->ends_at?->isBefore(now()->addDays(30)))
                    ->count(),
                // Collecte des projets : transite par la plateforme, ne lui
                // appartient pas. Séparé du revenu, jamais additionné.
                //
                // Source : `projects.amount_raised`, et non la somme des
                // contributions — c'est le montant qui fait foi partout
                // ailleurs (fiche projet, catalogue public, analyses). Les
                // deux divergent dès qu'une collecte est reprise d'un
                // historique hors plateforme, et deux chiffres différents
                // pour la même notion dans le même back-office sont pires
                // que pas de chiffre du tout.
                'contributions_volume' => (int) Project::sum('amount_raised'),
            ],

            'revenue_by_tier' => $byTier,
            'revenue_timeline' => $this->monthly($subscriptionPayments),
            'status_breakdown' => collect(SubscriptionStatus::cases())
                ->map(fn (SubscriptionStatus $status) => [
                    'status' => $status->value,
                    'label' => $status->label(),
                    'value' => $subscriptions->where('status', $status)->count(),
                ])
                ->filter(fn (array $row) => $row['value'] > 0)
                ->values(),
        ]);
    }

    /**
     * Parc d'abonnements promoteur, du plus récent au plus ancien.
     *
     * Les compteurs par état et par palier accompagnent la page courante
     * (`meta_counts`) : ce sont les filtres de l'écran, ils doivent porter sur
     * l'ensemble du parc et non sur les vingt lignes affichées.
     */
    public function subscriptions(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->string('search'));

        $filtered = PromoterSubscription::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('tier'), fn ($q) => $q->where('tier', $request->string('tier')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            // Recherche sur le titulaire : à l'échelle de la plateforme, on
            // cherche « l'abonnement de untel », jamais un numéro de contrat.
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")));

        $subscriptions = $filtered
            ->with(['user', 'payments'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return SubscriptionResource::collection($subscriptions)->additional([
            'meta_holders' => $this->holders($subscriptions->getCollection()),
            'meta_counts' => $this->subscriptionCounts(),
        ]);
    }

    /** Journal des paiements (§7.4, « historique des transactions »). */
    public function payments(Request $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->with('user')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('purpose'), fn ($q) => $q->where('purpose', $request->string('purpose')))
            ->when($request->filled('provider'), fn ($q) => $q->where('provider', $request->string('provider')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return PaymentResource::collection($payments)
            ->additional(['meta_holders' => $this->holders($payments->getCollection())]);
    }

    // --- Utilitaires ------------------------------------------------------

    /**
     * Compteurs du parc d'abonnements : total, états, paliers.
     *
     * Servent de filtres à l'écran « Abonnements ». Les montants n'y figurent
     * pas : le revenu a son point d'affichage unique dans le suivi financier.
     *
     * @return array<string, mixed>
     */
    private function subscriptionCounts(): array
    {
        $subscriptions = PromoterSubscription::all();
        $active = $subscriptions->filter(fn (PromoterSubscription $s) => $s->isActive());

        return [
            'total' => $subscriptions->count(),
            'active' => $active->count(),
            // Contrats à relancer : c'est la file de travail de cet écran.
            'expiring_30d' => $active
                ->filter(fn (PromoterSubscription $s) => $s->ends_at?->isBefore(now()->addDays(30)))
                ->count(),
            'by_status' => collect(SubscriptionStatus::cases())
                ->map(fn (SubscriptionStatus $status) => [
                    'status' => $status->value,
                    'label' => $status->label(),
                    'value' => $subscriptions->where('status', $status)->count(),
                ])
                ->values(),
            'by_tier' => collect(SubscriptionTier::cases())
                ->map(fn (SubscriptionTier $tier) => [
                    'tier' => $tier->value,
                    'label' => $tier->label(),
                    'value' => $subscriptions->where('tier', $tier)->count(),
                ])
                ->values(),
        ];
    }

    /**
     * Titulaires des lignes affichées, indexés par identifiant de ligne.
     *
     * SubscriptionResource et PaymentResource sont partagées avec l'espace
     * promoteur, où l'utilisateur est implicite : plutôt que d'y ajouter un
     * champ qui n'aurait de sens que pour l'administration, on transporte les
     * titulaires à côté de la collection.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function holders($rows): array
    {
        return $rows
            ->mapWithKeys(fn ($row) => [$row->id => [
                'id' => $row->user?->id,
                'name' => $row->user?->name ?? 'Compte supprimé',
                'email' => $row->user?->email,
            ]])
            ->all();
    }

    /**
     * Revenu mensuel sur les douze derniers mois, mois vides compris.
     * Agrégation en PHP : `strftime` (SQLite) et `DATE_FORMAT` (MySQL) ne sont
     * pas interchangeables et le projet doit tourner sur les deux.
     *
     * @param  \Illuminate\Support\Collection<int, Payment>  $payments
     * @return array<int, array<string, mixed>>
     */
    private function monthly($payments): array
    {
        $start = now()->startOfMonth()->subMonths(self::MONTHS - 1);

        $buckets = [];
        for ($i = 0; $i < self::MONTHS; $i++) {
            $buckets[$start->copy()->addMonths($i)->format('Y-m')] = 0;
        }

        foreach ($payments as $payment) {
            $date = $payment->paid_at ?? $payment->created_at;

            if ($date === null) {
                continue;
            }

            $key = Carbon::parse($date)->format('Y-m');

            if (array_key_exists($key, $buckets)) {
                $buckets[$key] += (int) $payment->amount;
            }
        }

        $series = [];
        $cumulative = 0;

        foreach ($buckets as $month => $amount) {
            $cumulative += $amount;
            $series[] = [
                'month' => $month,
                'label' => Carbon::createFromFormat('Y-m', $month)->locale('fr')->isoFormat('MMM YY'),
                'amount' => $amount,
                'cumulative' => $cumulative,
            ];
        }

        return $series;
    }
}
