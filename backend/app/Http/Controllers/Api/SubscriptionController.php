<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationType;
use App\Enums\PaymentProvider;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\PromoterType;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\RenewSubscriptionRequest;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Payment;
use App\Models\PromoterSubscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Notifier;
use App\Support\PaymentInstrument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Abonnements promoteur (§16.2.a). Un abonnement actif est le prérequis de la
 * publication de projets (§7.2). En MVP les paiements sont simulés (§16.5) :
 * la souscription est donc active immédiatement, sans mouvement de fonds réel.
 */
class SubscriptionController extends Controller
{
    public function __construct(private readonly Notifier $notifier) {}

    /** Durée d'une période d'abonnement, en mois. */
    private const PERIOD_MONTHS = 1;

    /**
     * Catalogue public des paliers et des moyens de paiement (niveau 1) —
     * alimente la page Tarifs, consultable sans compte.
     */
    public function plans(): JsonResponse
    {
        return response()->json([
            'currency' => 'XOF',
            'period' => 'mois',
            'plans' => array_map(fn (SubscriptionTier $tier) => [
                'tier' => $tier->value,
                'label' => $tier->label(),
                'monthly_price' => $tier->monthlyPrice(),
                'max_active_projects' => $tier->maxActiveProjects(),
                'features' => $tier->features(),
                // Sous-types de promoteur autorisés à souscrire ce palier. La
                // route est publique : la page Tarifs les affiche tous, et
                // n'en propose qu'un jeu une fois l'utilisateur connu.
                'promoter_types' => array_values(array_map(
                    fn (PromoterType $type) => $type->value,
                    array_filter(
                        PromoterType::cases(),
                        fn (PromoterType $type) => in_array($tier, $type->subscriptionTiers(), true),
                    ),
                )),
            ], SubscriptionTier::cases()),
            'providers' => array_map(fn (PaymentProvider $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ], PaymentProvider::cases()),
        ]);
    }

    /**
     * Abonnement courant du promoteur authentifié, son historique et le quota
     * de publication consommé.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $active = $user->activeSubscription();

        return response()->json([
            'subscription' => $active
                ? new SubscriptionResource($active->load('payments'))
                : null,
            'history' => SubscriptionResource::collection(
                $user->subscriptions()->latest()->get()
            ),
            'usage' => [
                'active_projects' => $user->projects()->countingTowardQuota()->count(),
                'max_active_projects' => $active?->tier->maxActiveProjects(),
            ],
        ]);
    }

    /**
     * Souscrire à un palier. Refusé si un abonnement est déjà actif : le
     * changement de palier passe par une résiliation explicite.
     */
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->activeSubscription()) {
            return response()->json([
                'message' => 'Vous avez déjà un abonnement actif. Résiliez-le pour changer de palier, ou renouvelez-le.',
                'code' => 'subscription_already_active',
            ], 409);
        }

        $tier = SubscriptionTier::from($request->string('tier')->value());
        $instrument = PaymentInstrument::fromValidated($request->validated(), $user);

        $subscription = DB::transaction(function () use ($user, $tier, $instrument) {
            $subscription = PromoterSubscription::create([
                'user_id' => $user->id,
                'tier' => $tier->value,
                'price' => $tier->monthlyPrice(),
                'currency' => 'XOF',
                // Paiement simulé : l'abonnement est actif sans attente.
                'status' => SubscriptionStatus::Active->value,
                'starts_at' => now(),
                'ends_at' => now()->addMonths(self::PERIOD_MONTHS),
            ]);

            $this->simulatePayment($user, $subscription, $instrument, "Abonnement {$tier->label()}");

            return $subscription;
        });

        $this->notifier->notify($user, NotificationType::SubscriptionActivated, [
            'tier' => $tier->label(),
            'url' => '/promoteur/abonnement',
        ]);

        return response()->json([
            'message' => "Abonnement {$tier->label()} activé (paiement simulé).",
            'subscription' => new SubscriptionResource($subscription->load('payments')),
        ], 201);
    }

    /**
     * Renouveler l'abonnement actif : prolonge l'échéance d'une période
     * supplémentaire et enregistre un nouveau paiement.
     */
    public function renew(RenewSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription();

        if (! $subscription) {
            return response()->json([
                'message' => 'Aucun abonnement actif à renouveler.',
                'code' => 'no_active_subscription',
            ], 409);
        }

        // Un renouvellement est un paiement à part entière : mêmes
        // coordonnées exigées qu'à la souscription. Le moyen n'est plus déduit
        // silencieusement (l'ancienne version retombait sur Wave si le champ
        // manquait, et enregistrait donc un paiement qui n'avait pas eu lieu).
        $instrument = PaymentInstrument::fromValidated($request->validated(), $user);

        DB::transaction(function () use ($user, $subscription, $instrument) {
            // On prolonge depuis l'échéance en cours pour ne pas perdre de jours.
            $from = $subscription->ends_at && $subscription->ends_at->isFuture()
                ? $subscription->ends_at
                : now();

            $subscription->update(['ends_at' => $from->copy()->addMonths(self::PERIOD_MONTHS)]);

            $this->simulatePayment(
                $user,
                $subscription,
                $instrument,
                "Renouvellement abonnement {$subscription->tier->label()}",
            );
        });

        return response()->json([
            'message' => 'Abonnement renouvelé (paiement simulé).',
            'subscription' => new SubscriptionResource($subscription->fresh()->load('payments')),
        ]);
    }

    /**
     * Résilier l'abonnement actif. La résiliation est immédiate en MVP : la
     * publication de nouveaux projets est bloquée dès la confirmation, les
     * projets déjà en ligne restent visibles.
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription();

        if (! $subscription) {
            return response()->json([
                'message' => 'Aucun abonnement actif à résilier.',
                'code' => 'no_active_subscription',
            ], 409);
        }

        $subscription->update([
            'status' => SubscriptionStatus::Cancelled->value,
            'ends_at' => now(),
        ]);

        $this->notifier->notify($user, NotificationType::SubscriptionCancelled, [
            'url' => '/tarifs',
        ]);

        return response()->json([
            'message' => 'Abonnement résilié.',
            'subscription' => new SubscriptionResource($subscription->fresh()),
        ]);
    }

    /** Historique des paiements du promoteur (§7.4). */
    public function payments(Request $request): JsonResponse
    {
        $payments = $request->user()
            ->payments()
            ->where('purpose', PaymentPurpose::Subscription->value)
            ->latest()
            ->get();

        return response()->json(['payments' => PaymentResource::collection($payments)]);
    }

    // --- Interne ----------------------------------------------------------

    /**
     * Enregistre un paiement simulé et son écriture comptable.
     * Aucun appel opérateur n'est effectué en MVP (§16.5).
     */
    private function simulatePayment(
        User $user,
        PromoterSubscription $subscription,
        PaymentInstrument $instrument,
        string $label,
    ): Payment {
        $payment = Payment::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'purpose' => PaymentPurpose::Subscription->value,
            ...$instrument->columns(),
            'amount' => $subscription->price,
            'currency' => $subscription->currency,
            'status' => PaymentStatus::Simulated->value,
            'reference' => 'SUB-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'provider_payload' => ['simulated' => true, 'tier' => $subscription->tier->value],
            'paid_at' => now(),
        ]);

        Transaction::create([
            'payment_id' => $payment->id,
            'user_id' => $user->id,
            'direction' => 'debit',
            'amount' => $subscription->price,
            'currency' => $subscription->currency,
            'label' => $label,
            'meta' => ['subscription_id' => $subscription->id],
        ]);

        return $payment;
    }
}
