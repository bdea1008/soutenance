<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\KycStatus;
use App\Enums\PromoterType;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use App\Enums\UserRole;
use App\Mail\PasswordResetMail;
use App\Support\DossierChecklist;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'phone',
        'password',
        'role',
        // Sous-type promoteur (particulier / promoteur confirmé) et identité de
        // la structure porteuse — renseignés dès l'inscription (niveau 1).
        'promoter_type',
        'company_name',
        'legal_form',
        'registration_number',
        'tax_number',
        'signatory_role',
        'kyc_status',
        'country',
        'city',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'promoter_type' => PromoterType::class,
            'kyc_status' => KycStatus::class,
            'is_active' => 'boolean',
        ];
    }

    // --- JWTSubject -------------------------------------------------------

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Claims personnalisés embarqués dans le token.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role?->value,
            'kyc_status' => $this->kyc_status?->value,
        ];
    }

    // --- Réinitialisation du mot de passe ---------------------------------

    /**
     * Envoi du lien de réinitialisation, appelé par le courtier
     * `Password::sendResetLink()` une fois le jeton créé et stocké haché.
     *
     * Redéfini pour passer par notre propre Mailable plutôt que par la
     * notification générique de Laravel : le message doit porter la charte de
     * la plateforme et renvoyer vers l'interface React, pas vers une route
     * `password.reset` côté serveur — le backend ne sert qu'une API.
     */
    public function sendPasswordResetNotification($token): void
    {
        Mail::send(new PasswordResetMail($this, $token));
    }

    // --- Helpers de rôle --------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isPromoter(): bool
    {
        return $this->role === UserRole::Promoter;
    }

    public function isInvestor(): bool
    {
        return $this->role === UserRole::Investor;
    }

    public function isLegal(): bool
    {
        return $this->role === UserRole::Legal;
    }

    public function isKycVerified(): bool
    {
        return $this->kyc_status === KycStatus::Verified;
    }

    // --- Sous-type de promoteur -------------------------------------------

    /**
     * Sous-type du compte promoteur, `null` pour tout autre rôle.
     *
     * Repli sur « promoteur confirmé » si la colonne est vide : c'était le seul
     * profil accepté avant l'ouverture aux particuliers, et une valeur absente
     * ne doit jamais faire disparaître les pièces exigées d'un dossier.
     */
    public function promoterProfile(): ?PromoterType
    {
        if (! $this->isPromoter()) {
            return null;
        }

        return $this->promoter_type ?? PromoterType::Company;
    }

    public function isIndividualPromoter(): bool
    {
        return $this->promoterProfile() === PromoterType::Individual;
    }

    public function isCompanyPromoter(): bool
    {
        return $this->promoterProfile() === PromoterType::Company;
    }

    // --- Dossier de l'opérateur (niveau 2) --------------------------------

    /**
     * Pièces exigées pour que le dossier de l'opérateur aboutisse. Pour un
     * promoteur, la liste dépend du sous-type et non du rôle : un particulier
     * justifie ses revenus, une société ses comptes.
     *
     * @return array<int, DocumentType>
     */
    public function requiredKycDocuments(): array
    {
        return $this->promoterProfile()?->requiredKycDocuments()
            ?? $this->role->requiredKycDocuments();
    }

    /** @return array<int, DocumentType> */
    public function optionalKycDocuments(): array
    {
        return $this->promoterProfile()?->optionalKycDocuments() ?? [];
    }

    /**
     * Types recevables pour le dossier de l'opérateur (obligatoires + facultatifs).
     *
     * @return array<int, DocumentType>
     */
    public function acceptedKycDocuments(): array
    {
        return [...$this->requiredKycDocuments(), ...$this->optionalKycDocuments()];
    }

    /**
     * Pièces du dossier de l'opérateur : celles qui ne sont rattachées à aucun
     * projet. Les pièces de projet relèvent du niveau 3 et sont comptées par
     * `Project::dossierChecklist()`.
     *
     * @return Collection<int, VerificationDocument>
     */
    public function operatorDocuments(): Collection
    {
        $documents = $this->relationLoaded('verificationDocuments')
            ? $this->verificationDocuments
            : $this->verificationDocuments()->get();

        return $documents->whereNull('project_id')->values();
    }

    /**
     * Checklist complète du dossier de l'opérateur (état, péremption, hints).
     *
     * @return array<int, array<string, mixed>>
     */
    public function kycChecklist(): array
    {
        return DossierChecklist::build(
            $this->operatorDocuments(),
            $this->requiredKycDocuments(),
            $this->optionalKycDocuments(),
        );
    }

    /**
     * État de chaque pièce exigée, indexé par type de document. Conservé pour
     * les consommateurs qui n'ont besoin que du statut brut (console admin).
     * Une pièce validée mais périmée n'est pas un état de `DocumentStatus` :
     * elle est rendue par la chaîne `expired`.
     *
     * @return array<string, string|null> `null` = pièce manquante
     */
    public function kycDocumentStates(): array
    {
        $states = [];

        foreach ($this->kycChecklist() as $item) {
            if ($item['required']) {
                $states[$item['type']] = $item['status'];
            }
        }

        return $states;
    }

    /**
     * Recalcule le statut KYC à partir des pièces déposées et le persiste.
     * Appelé après chaque dépôt, suppression ou décision d'un administrateur.
     */
    public function recomputeKycStatus(): KycStatus
    {
        // L'administrateur n'est pas soumis au KYC : son statut reste inchangé.
        if ($this->requiredKycDocuments() === []) {
            return $this->kyc_status;
        }

        $states = $this->kycDocumentStates();

        $allApproved = $states !== []
            && ! in_array(DocumentStatus::Pending->value, $states, true)
            && ! in_array(DocumentStatus::Rejected->value, $states, true)
            && ! in_array('expired', $states, true)
            && ! in_array(null, $states, true);

        $status = match (true) {
            // Toutes les pièces exigées sont validées.
            $allApproved => KycStatus::Verified,
            // Une pièce a été rejetée sans nouveau dépôt : l'utilisateur doit agir.
            in_array(DocumentStatus::Rejected->value, $states, true) => KycStatus::Rejected,
            // Au moins une pièce est en cours d'examen.
            in_array(DocumentStatus::Pending->value, $states, true) => KycStatus::Pending,
            // Pièce périmée à renouveler, ou aucune pièce déposée : dans les
            // deux cas le dossier n'est pas constitué.
            default => KycStatus::None,
        };

        if ($status !== $this->kyc_status) {
            $this->forceFill(['kyc_status' => $status->value])->save();
        }

        return $status;
    }

    /** Le promoteur a-t-il un abonnement actif (requis pour publier) ? */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::Active->value)
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();
    }

    /** Abonnement actif du promoteur (le plus récent), ou `null`. */
    public function activeSubscription(): ?PromoterSubscription
    {
        return $this->subscriptions()
            ->where('status', SubscriptionStatus::Active->value)
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest()
            ->first();
    }

    /** Palier de l'abonnement actif — détermine les quotas (§16.2.a). */
    public function activeSubscriptionTier(): ?SubscriptionTier
    {
        return $this->activeSubscription()?->tier;
    }

    // --- Relations --------------------------------------------------------

    /** Projets publiés par ce promoteur. */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'promoter_id');
    }

    /** Contributions réalisées par cet investisseur. */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class, 'investor_id');
    }

    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(VerificationDocument::class);
    }

    /**
     * Pièces en attente d'examen — relation dédiée pour que la file de
     * modération administrateur puisse en compter le nombre par utilisateur
     * (`withCount('pendingDocuments')`) sans charger les pièces elles-mêmes.
     */
    public function pendingDocuments(): HasMany
    {
        return $this->hasMany(VerificationDocument::class)
            ->where('status', DocumentStatus::Pending->value);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(PromoterSubscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Notifications applicatives (entité §12, distincte des notifications Laravel). */
    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }
}
