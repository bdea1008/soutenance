<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\DocumentStatus;
use App\Enums\ProjectStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PromoterSubscription;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Http\JsonResponse;

/**
 * Console d'accueil de l'administration.
 *
 * Répond à une seule question : « qu'est-ce qui attend une décision ? ».
 * Les statistiques d'analyse restent dans AnalyticsController (§7.6) ; ici on
 * n'expose que des files de travail et l'activité récente, pour que l'écran
 * d'accueil soit actionnable plutôt que contemplatif.
 */
class OverviewController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'generated_at' => now(),
            'queues' => $this->queues(),
            'recent_users' => $this->recentUsers(),
            'recent_projects' => $this->recentProjects(),
            'recent_payments' => $this->recentPayments(),
        ]);
    }

    /**
     * Files de travail. Chaque entrée porte le lien vers l'écran qui permet de
     * la traiter : la console doit mener à l'action en un clic.
     *
     * @return array<int, array<string, mixed>>
     */
    private function queues(): array
    {
        $pendingDocuments = VerificationDocument::where('status', DocumentStatus::Pending->value)->count();

        $awaitingProjects = Project::whereIn('status', [
            ProjectStatus::PendingReview->value,
            ProjectStatus::Draft->value,
        ])->count();

        $suspended = User::where('is_active', false)->count();

        $expiring = PromoterSubscription::where('status', SubscriptionStatus::Active->value)
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now(), now()->addDays(30)])
            ->count();

        return [
            [
                'key' => 'documents',
                'label' => 'Pièces KYC à examiner',
                'value' => $pendingDocuments,
                'url' => '/admin/documents',
                'tone' => $pendingDocuments > 0 ? 'alert' : 'neutral',
            ],
            [
                'key' => 'projects',
                'label' => 'Projets en attente de mise en ligne',
                'value' => $awaitingProjects,
                'url' => '/admin/projets?statut=pending_review',
                'tone' => $awaitingProjects > 0 ? 'accent' : 'neutral',
            ],
            [
                'key' => 'suspended',
                'label' => 'Comptes désactivés',
                'value' => $suspended,
                'url' => '/admin/utilisateurs?actif=0',
                'tone' => 'neutral',
            ],
            [
                'key' => 'expiring',
                'label' => 'Abonnements à échéance sous 30 jours',
                'value' => $expiring,
                // Le parc de contrats est tenu par l'écran Abonnements ; les
                // finances ne parlent que d'argent encaissé.
                'url' => '/admin/abonnements?etat=active',
                'tone' => $expiring > 0 ? 'accent' : 'neutral',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentUsers(): array
    {
        return User::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_label' => $user->role->label(),
                'kyc_status' => $user->kyc_status->value,
                'kyc_status_label' => $user->kyc_status->label(),
                'is_active' => (bool) $user->is_active,
                'created_at' => $user->created_at,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentProjects(): array
    {
        return Project::query()
            ->with('promoter')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'title' => $project->title,
                'promoter' => $project->promoter?->name,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'funding_progress' => $project->fundingProgress(),
                'created_at' => $project->created_at,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentPayments(): array
    {
        return Payment::query()
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'user' => $payment->user?->name,
                'amount' => (int) $payment->amount,
                'provider_label' => $payment->provider->label(),
                'purpose_label' => $payment->purpose->label(),
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'created_at' => $payment->paid_at ?? $payment->created_at,
            ])
            ->all();
    }
}
