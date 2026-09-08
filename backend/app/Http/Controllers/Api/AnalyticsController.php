<?php

namespace App\Http\Controllers\Api;

use App\Enums\KycStatus;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PromoterSubscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tableau de bord analytique (§7.6) : statistiques financières, rendement
 * estimé, analyse des projets.
 *
 * Un seul point d'entrée, trois lectures selon le rôle — l'investisseur suit
 * son portefeuille, le promoteur sa collecte, l'administrateur la plateforme.
 *
 * Les agrégations par mois sont faites en PHP plutôt qu'en SQL : `strftime`
 * (SQLite) et `DATE_FORMAT` (MySQL) ne sont pas interchangeables, et le projet
 * doit tourner sur les deux. Les volumes du MVP le permettent sans peine.
 */
class AnalyticsController extends Controller
{
    /** Profondeur de l'historique affiché, en mois. */
    private const MONTHS = 12;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'role' => $user->role->value,
            'currency' => 'XOF',
            'generated_at' => now(),
            ...match ($user->role) {
                UserRole::Investor => $this->forInvestor($user),
                UserRole::Promoter => $this->forPromoter($user),
                // Le rôle juridique n'a pas plus d'espace personnel que
                // l'administrateur : même lecture, plateforme entière. En
                // pratique l'interface ne l'y mène jamais (il n'a pas de
                // tableau de bord), mais l'API doit rester correcte si
                // jamais la route est appelée directement.
                UserRole::Admin, UserRole::Legal => $this->forAdmin(),
            },
        ]);
    }

    // --- Investisseur -----------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function forInvestor(User $user): array
    {
        // Le rapport et le score les plus récents sont chargés d'emblée :
        // les positions les affichent toutes, un chargement paresseux ferait
        // deux requêtes de plus par ligne.
        $contributions = $user->contributions()
            ->with(['project.latestReport', 'project.latestScore'])
            ->get();

        $invested = (int) $contributions->sum('amount');
        $expectedReturn = (int) $contributions->sum('estimated_return');

        // Répartition par catégorie de projet : où l'argent est-il placé ?
        $allocation = $contributions
            ->groupBy(fn (Contribution $c) => $c->project?->category ?? 'autre')
            ->map(fn (Collection $group, string $category) => [
                'label' => ucfirst($category),
                'value' => (int) $group->sum('amount'),
            ])
            ->sortByDesc('value')
            ->values();

        // Positions détaillées, avec l'avancement du chantier quand il existe.
        $positions = $contributions
            ->sortByDesc('confirmed_at')
            ->map(function (Contribution $c) {
                $project = $c->project;

                return [
                    'project_id' => $c->project_id,
                    'title' => $project?->title ?? "Projet #{$c->project_id}",
                    'status' => $project?->status->value,
                    'status_label' => $project?->status->label(),
                    'amount' => (int) $c->amount,
                    'share_percentage' => (float) $c->share_percentage,
                    'estimated_return' => (int) $c->estimated_return,
                    'funding_progress' => $project?->fundingProgress(),
                    'construction_progress' => $project?->latestReport?->progress_percentage,
                    'risk_level' => $project?->latestScore?->risk_level->value,
                    'confirmed_at' => $c->confirmed_at,
                ];
            })
            ->values();

        return [
            'kpis' => [
                'invested' => $invested,
                'expected_return' => $expectedReturn,
                'expected_total' => $invested + $expectedReturn,
                'projects_backed' => $contributions->pluck('project_id')->unique()->count(),
                // Rendement pondéré du portefeuille, pas la moyenne des taux :
                // un gros placement ne pèse pas comme un petit.
                'weighted_return_rate' => $invested > 0
                    ? round($expectedReturn / $invested * 100, 2)
                    : 0,
            ],
            'allocation' => $allocation,
            'timeline' => $this->monthlyCumulative($contributions, 'confirmed_at', 'amount'),
            'positions' => $positions,
        ];
    }

    // --- Promoteur --------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function forPromoter(User $user): array
    {
        $projects = $user->projects()->with(['latestReport', 'latestScore'])->get();

        $raised = (int) $projects->sum('amount_raised');
        $goal = (int) $projects->sum('funding_goal');

        $contributions = Contribution::whereIn('project_id', $projects->pluck('id'))->get();

        $byProject = $projects
            ->sortByDesc('amount_raised')
            ->map(fn (Project $p) => [
                'project_id' => $p->id,
                'title' => $p->title,
                'raised' => (int) $p->amount_raised,
                'goal' => (int) $p->funding_goal,
                'progress' => $p->fundingProgress(),
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'construction_progress' => $p->latestReport?->progress_percentage,
                'confidence_score' => $p->latestScore?->confidence_score,
            ])
            ->values();

        return [
            'kpis' => [
                'raised' => $raised,
                'goal' => $goal,
                'funding_rate' => $goal > 0 ? round($raised / $goal * 100, 1) : 0,
                'investors' => $contributions->pluck('investor_id')->unique()->count(),
                'online_projects' => $projects
                    ->filter(fn (Project $p) => in_array($p->status, ProjectStatus::quotaStatuses(), true))
                    ->count(),
                'projects_total' => $projects->count(),
            ],
            'by_project' => $byProject,
            'timeline' => $this->monthlyCumulative($contributions, 'confirmed_at', 'amount'),
            'status_breakdown' => $this->countByStatus($projects),
        ];
    }

    // --- Administrateur ---------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function forAdmin(): array
    {
        $projects = Project::all();
        $contributions = Contribution::all();

        // Chiffre d'affaires de la plateforme : les abonnements promoteur
        // (les paiements sont simulés en MVP, §16.5).
        $revenue = (int) Payment::where('purpose', PaymentPurpose::Subscription->value)
            ->whereIn('status', [PaymentStatus::Simulated->value, PaymentStatus::Completed->value])
            ->sum('amount');

        // Répartition de la collecte par région : quelles zones attirent ?
        $byRegion = $projects
            ->groupBy(fn (Project $p) => $p->region ?: 'Non précisée')
            ->map(fn (Collection $group, string $region) => [
                'label' => $region,
                'value' => (int) $group->sum('amount_raised'),
                'projects' => $group->count(),
            ])
            ->sortByDesc('value')
            ->values();

        // Rôles internes (admin, juridique) exclus : ils ne sont jamais soumis
        // au KYC, les compter fausserait le lot « non vérifié » d'un funnel
        // qui parle des investisseurs et promoteurs.
        $kycFunnel = collect(KycStatus::cases())
            ->map(fn (KycStatus $status) => [
                'status' => $status->value,
                'label' => $status->label(),
                'value' => User::whereNotIn('role', [UserRole::Admin->value, UserRole::Legal->value])
                    ->where('kyc_status', $status->value)
                    ->count(),
            ])
            ->values();

        return [
            'kpis' => [
                'users' => User::count(),
                'investors' => User::where('role', UserRole::Investor->value)->count(),
                'promoters' => User::where('role', UserRole::Promoter->value)->count(),
                'projects' => $projects->count(),
                'raised' => (int) $projects->sum('amount_raised'),
                'revenue' => $revenue,
                'active_subscriptions' => PromoterSubscription::where('status', SubscriptionStatus::Active->value)
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                    ->count(),
                'pending_documents' => \App\Models\VerificationDocument::where('status', 'pending')->count(),
            ],
            'kyc_funnel' => $kycFunnel,
            'status_breakdown' => $this->countByStatus($projects),
            'by_region' => $byRegion,
            'timeline' => $this->monthlyCumulative($contributions, 'confirmed_at', 'amount'),
        ];
    }

    // --- Agrégations partagées --------------------------------------------

    /**
     * Série mensuelle sur les douze derniers mois : montant du mois et cumul.
     * Les mois sans mouvement sont présents à zéro — une courbe temporelle
     * trouée se lit de travers.
     *
     * @param  Collection<int, mixed>  $records
     * @return array<int, array<string, mixed>>
     */
    private function monthlyCumulative(Collection $records, string $dateField, string $valueField): array
    {
        $start = now()->startOfMonth()->subMonths(self::MONTHS - 1);

        $buckets = [];
        for ($i = 0; $i < self::MONTHS; $i++) {
            $month = $start->copy()->addMonths($i);
            $buckets[$month->format('Y-m')] = 0;
        }

        // Le cumul doit intégrer ce qui précède la fenêtre affichée, sinon la
        // courbe repartirait artificiellement de zéro.
        $carry = 0;

        foreach ($records as $record) {
            $date = $record->{$dateField} ?? $record->created_at;

            if ($date === null) {
                continue;
            }

            $key = Carbon::parse($date)->format('Y-m');

            if (array_key_exists($key, $buckets)) {
                $buckets[$key] += (int) $record->{$valueField};
            } elseif (Carbon::parse($date)->lt($start)) {
                $carry += (int) $record->{$valueField};
            }
        }

        $series = [];
        $cumulative = $carry;

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

    /**
     * Nombre de projets par statut, dans l'ordre du cycle de vie.
     *
     * @param  Collection<int, Project>  $projects
     * @return array<int, array<string, mixed>>
     */
    private function countByStatus(Collection $projects): array
    {
        return collect(ProjectStatus::cases())
            ->map(fn (ProjectStatus $status) => [
                'status' => $status->value,
                'label' => $status->label(),
                'value' => $projects->where('status', $status)->count(),
            ])
            ->filter(fn (array $row) => $row['value'] > 0)
            ->values()
            ->all();
    }
}
