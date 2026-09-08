<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ContributionStatus;
use App\Enums\KycStatus;
use App\Enums\NotificationType;
use App\Enums\PromoterType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Http\Resources\ContributionResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Contribution;
use App\Models\Project;
use App\Models\User;
use App\Services\Notifier;
use App\Support\PromoterIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Gestion des comptes par l'administration (§5 : validation, suivi et
 * supervision de la plateforme).
 *
 * Invariant tenu ici : la plateforme ne peut jamais se retrouver sans
 * administrateur actif. Il suffit pour cela d'interdire à un administrateur de
 * se désactiver ou de se déclasser lui-même — puisque seul un administrateur
 * actif peut atteindre ces routes (middlewares `role:admin` + `account.active`),
 * l'auteur d'une décision reste toujours administrateur après celle-ci.
 */
class UserController extends Controller
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    /**
     * Annuaire des comptes, filtrable par rôle, état KYC et activité.
     * Les plus récents d'abord : c'est l'ordre dans lequel on modère.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->withCount(['projects', 'contributions', 'verificationDocuments', 'pendingDocuments'])
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->filled('kyc_status'), fn ($q) => $q->where('kyc_status', $request->string('kyc_status')))
            // `active=0` doit filtrer les comptes suspendus : on teste la
            // présence du paramètre, pas sa véracité (filled('active') serait
            // faux pour la chaîne « 0 » selon les cas).
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search');
                $q->where(fn ($sub) => $sub
                    ->where('name', 'like', "%$term%")
                    ->orWhere('email', 'like', "%$term%")
                    ->orWhere('phone', 'like', "%$term%"));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        $this->attachVolumes($users->getCollection());

        return AdminUserResource::collection($users);
    }

    /** Compteurs d'en-tête de l'annuaire. */
    public function stats(): JsonResponse
    {
        $byRole = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return response()->json([
            'total' => (int) $byRole->sum(),
            'investors' => (int) ($byRole[UserRole::Investor->value] ?? 0),
            'promoters' => (int) ($byRole[UserRole::Promoter->value] ?? 0),
            'admins' => (int) ($byRole[UserRole::Admin->value] ?? 0),
            'legal' => (int) ($byRole[UserRole::Legal->value] ?? 0),
            'suspended' => User::where('is_active', false)->count(),
            'kyc_pending' => User::where('kyc_status', KycStatus::Pending->value)->count(),
            'kyc_verified' => User::where('kyc_status', KycStatus::Verified->value)->count(),
        ]);
    }

    /**
     * Création d'un compte par l'administration.
     *
     * Seule porte d'entrée du rôle interne « juridique & conformité » : il ne
     * figure pas dans les choix de l'inscription publique. Le compte est créé
     * actif et sans dossier KYC, exactement comme une inscription ordinaire —
     * seul le mot de passe est fixé ici plutôt que choisi par l'intéressé, qui
     * devra en changer un attribué par un tiers.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => $data['role'],
            // Sous-type de promoteur et structure porteuse (voir PromoterIdentity).
            ...PromoterIdentity::columns($data),
            'kyc_status' => KycStatus::None->value,
            'country' => $data['country'] ?? null,
            'city' => $data['city'] ?? null,
            // Explicite plutôt que de compter sur le défaut de colonne : sans
            // cette ligne l'objet en mémoire ne porte pas la valeur tant qu'il
            // n'est pas rechargé, et la réponse annonce un compte inactif à tort.
            'is_active' => true,
        ]);

        return response()->json([
            'message' => "Compte créé : {$user->name} ({$user->role->label()}).",
            'user' => new AdminUserResource($user),
        ], 201);
    }

    /**
     * Fiche complète d'un compte : ce qu'il a déposé, publié ou investi.
     * C'est la vue sur laquelle un gestionnaire décide d'une suspension.
     */
    public function show(User $user): JsonResponse
    {
        $user->loadCount(['projects', 'contributions', 'verificationDocuments', 'pendingDocuments']);
        $this->attachVolumes(collect([$user]));

        // L'état pièce par pièce est plus parlant que le seul statut agrégé :
        // il dit précisément ce qui manque au dossier. La péremption y figure
        // aussi — une pièce validée en janvier peut être hors délai en décembre.
        $kycChecklist = collect($user->kycChecklist())
            ->map(fn (array $item) => [
                'type' => $item['type'],
                'label' => $item['label'],
                'required' => $item['required'],
                'status' => $item['status'],
                'status_label' => $item['status'] === null ? 'Non déposée' : $item['status_label'],
                'expires_at' => $item['expires_at'],
            ])
            ->values();

        return response()->json([
            'user' => new AdminUserResource($user),
            'kyc_checklist' => $kycChecklist,
            'documents' => DocumentResource::collection(
                $user->verificationDocuments()->with(['user', 'reviewer'])->latest()->get()
            ),
            'projects' => ProjectResource::collection(
                $user->projects()->with('latestScore')->withCount('contributions')->latest()->get()
            ),
            'contributions' => ContributionResource::collection(
                $user->contributions()->with(['project', 'payment'])->latest()->get()
            ),
            'subscriptions' => SubscriptionResource::collection(
                $user->subscriptions()->with('payments')->latest()->get()
            ),
        ]);
    }

    /**
     * Activation / désactivation d'un compte. Une désactivation doit être
     * motivée : la notification adressée au titulaire reprend ce motif, et
     * c'est la seule explication qu'il recevra.
     */
    public function setStatus(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'active' => ['required', 'boolean'],
            'reason' => [
                Rule::requiredIf(fn () => $request->boolean('active') === false),
                'nullable', 'string', 'max:500',
            ],
        ], [
            'reason.required' => 'Un motif est requis pour désactiver un compte.',
        ]);

        $active = (bool) $validated['active'];

        if (! $active && $user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ], 422);
        }

        if ($user->is_active === $active) {
            return response()->json([
                'message' => $active ? 'Ce compte est déjà actif.' : 'Ce compte est déjà désactivé.',
            ], 409);
        }

        $user->update(['is_active' => $active]);

        $this->notifier->notify(
            $user,
            $active ? NotificationType::AccountReactivated : NotificationType::AccountSuspended,
            ['reason' => $validated['reason'] ?? null, 'url' => '/tableau-de-bord'],
        );

        return response()->json([
            'message' => $active ? 'Compte réactivé.' : 'Compte désactivé.',
            'user' => new AdminUserResource($user->fresh()),
        ]);
    }

    /**
     * Changement de rôle. Le KYC est recalculé dans la foulée : les pièces
     * exigées ne sont pas les mêmes selon le rôle (§7.1), un promoteur devenu
     * investisseur n'a par exemple pas encore fourni son justificatif de
     * domicile et ne doit pas rester « vérifié » à tort.
     */
    public function setRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
            // Le sous-type n'a de sens que pour un promoteur ; à défaut, le
            // repli « promoteur confirmé » de User::promoterProfile() s'applique.
            'promoter_type' => ['nullable', Rule::enum(PromoterType::class)],
        ]);

        $role = UserRole::from($validated['role']);

        if ($user->id === $request->user()->id && $role !== UserRole::Admin) {
            return response()->json([
                'message' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.',
            ], 422);
        }

        if ($user->role === $role) {
            return response()->json(['message' => 'Ce compte a déjà ce rôle.'], 409);
        }

        $user->update([
            'role' => $role->value,
            // Les pièces exigées dépendent du sous-type : un compte qui cesse
            // d'être promoteur ne doit pas en garder un.
            'promoter_type' => $role === UserRole::Promoter
                ? ($validated['promoter_type'] ?? $user->promoter_type?->value ?? PromoterType::Company->value)
                : null,
        ]);

        // Les pièces exigées diffèrent d'un rôle à l'autre : le statut KYC doit
        // être recalculé, sinon un compte reste « vérifié » sur des pièces qui
        // ne sont plus celles de son rôle.
        $user->refresh()->recomputeKycStatus();

        return response()->json([
            'message' => "Rôle mis à jour : {$role->label()}.",
            'user' => new AdminUserResource($user->fresh()),
        ]);
    }

    /**
     * Suppression d'un compte — douce, jamais réelle en base (§5).
     *
     * Les clés étrangères vers `users` (projets, contributions, paiements,
     * abonnements…) sont en cascade : une suppression réelle effacerait
     * l'historique financier et le catalogue du compte visé, potentiellement
     * partagé avec d'autres utilisateurs (co-investisseurs d'un projet). Le
     * compte disparaît de l'annuaire et ne peut plus se connecter — la trace
     * de ce qu'il a fait reste intacte, comme pour un projet retiré (jamais
     * supprimé, seulement écarté du catalogue).
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => "Compte supprimé : {$user->name}.",
        ]);
    }

    // --- Agrégats ---------------------------------------------------------

    /**
     * Montants investis / collectés, calculés en une requête par famille
     * plutôt qu'une par ligne — l'annuaire pagine jusqu'à 100 comptes.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $users
     */
    private function attachVolumes($users): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $ids = $users->pluck('id');

        $invested = Contribution::whereIn('investor_id', $ids)
            ->where('status', ContributionStatus::Confirmed->value)
            ->groupBy('investor_id')
            ->selectRaw('investor_id, sum(amount) as total')
            ->pluck('total', 'investor_id');

        $raised = Project::whereIn('promoter_id', $ids)
            ->groupBy('promoter_id')
            ->selectRaw('promoter_id, sum(amount_raised) as total')
            ->pluck('total', 'promoter_id');

        foreach ($users as $user) {
            if ($user->role === UserRole::Investor) {
                $user->invested_total = (int) ($invested[$user->id] ?? 0);
            }

            if ($user->role === UserRole::Promoter) {
                $user->raised_total = (int) ($raised[$user->id] ?? 0);
            }
        }
    }
}
