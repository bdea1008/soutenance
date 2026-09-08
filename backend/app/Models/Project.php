<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\PromoterType;
use App\Support\DossierChecklist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    protected $fillable = [
        'promoter_id',
        'title',
        'slug',
        'summary',
        'description',
        'category',
        'region',
        'city',
        'address',
        'latitude',
        'longitude',
        'funding_goal',
        'amount_raised',
        'min_investment',
        'expected_return_rate',
        'duration_months',
        'cover_image',
        'images',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'images' => 'array',
            'funding_goal' => 'integer',
            'amount_raised' => 'integer',
            'min_investment' => 'integer',
            'expected_return_rate' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'published_at' => 'datetime',
        ];
    }

    /** Pourcentage de financement atteint (0..100). */
    public function fundingProgress(): float
    {
        if ($this->funding_goal <= 0) {
            return 0;
        }

        return round(min(100, $this->amount_raised / $this->funding_goal * 100), 1);
    }

    // --- Dossier de l'opération (niveau 3) --------------------------------

    /**
     * Sous-type du promoteur porteur : c'est lui qui fixe les pièces attendues
     * pour ce projet. Un particulier qui construit sa maison et une société qui
     * monte une résidence ne montent pas le même dossier.
     */
    public function promoterProfile(): PromoterType
    {
        return $this->promoter?->promoterProfile() ?? PromoterType::Company;
    }

    /**
     * Checklist du dossier de l'opération : les pièces refaites à chaque
     * projet, par opposition au dossier de l'opérateur déposé une seule fois.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dossierChecklist(): array
    {
        $profile = $this->promoterProfile();

        $documents = $this->relationLoaded('verificationDocuments')
            ? $this->verificationDocuments
            : $this->verificationDocuments()->get();

        return DossierChecklist::build(
            $documents,
            $profile->requiredProjectDocuments(),
            $profile->optionalProjectDocuments(),
        );
    }

    /** Le dossier de l'opération autorise-t-il la mise en financement ? */
    public function dossierComplete(): bool
    {
        return DossierChecklist::isComplete($this->dossierChecklist());
    }

    // --- Scopes -----------------------------------------------------------

    /** Projets visibles publiquement (aperçu page d'accueil, §2). */
    public function scopePublic($query)
    {
        return $query->whereIn('status', array_map(
            fn (ProjectStatus $s) => $s->value,
            ProjectStatus::publicStatuses()
        ));
    }

    /**
     * Projets occupant le quota de publication du palier d'abonnement :
     * ceux effectivement en ligne. Un projet livré ou annulé libère sa place.
     */
    public function scopeCountingTowardQuota($query)
    {
        return $query->whereIn('status', array_map(
            fn (ProjectStatus $s) => $s->value,
            ProjectStatus::quotaStatuses()
        ));
    }

    // --- Relations --------------------------------------------------------

    /** `withTrashed` : le projet reste attribué à son promoteur même si ce compte a été supprimé depuis. */
    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoter_id')->withTrashed();
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * Investisseurs distincts engagés sur le projet — destinataires naturels
     * des notifications de suivi (§7.5, §7.7).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function contributors(): \Illuminate\Database\Eloquent\Collection
    {
        return User::whereIn('id', $this->contributions()->select('investor_id'))->get();
    }

    /** Pièces rattachées au projet (titre foncier, permis de construire — §7.2). */
    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(VerificationDocument::class);
    }

    public function siteReports(): HasMany
    {
        return $this->hasMany(SiteReport::class);
    }

    /** Dernier rapport de chantier — porte l'avancement courant (§7.5). */
    public function latestReport(): HasOne
    {
        return $this->hasOne(SiteReport::class)->latestOfMany('reported_at');
    }

    /** Dernier score IA calculé pour ce projet. */
    public function latestScore(): HasOne
    {
        return $this->hasOne(AiScore::class)->latestOfMany('computed_at');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(AiScore::class);
    }

    /** Notes et commentaires d'investisseurs ayant réellement investi (§2, extension). */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProjectReview::class);
    }
}
