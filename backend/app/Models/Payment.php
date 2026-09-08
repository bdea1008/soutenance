<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'subscription_id',
        'contribution_id',
        'project_id',
        'purpose',
        'provider',
        // Coordonnées du moyen employé — voir App\Support\PaymentInstrument,
        // seul endroit qui décide ce qui est conservé (jamais le numéro de
        // carte complet ni le cryptogramme).
        'payer_phone',
        'card_brand',
        'card_last4',
        'amount',
        'currency',
        'status',
        'reference',
        'provider_payload',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'provider' => PaymentProvider::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'provider_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Ce que le titulaire reconnaîtra dans son historique :
     * « Wave · +221 77 123 45 67 » ou « Visa •••• 4242 ».
     *
     * Reconstitué depuis les colonnes plutôt que figé à l'écriture : le
     * libellé est une présentation, il doit pouvoir changer sans migration.
     */
    public function instrumentLabel(): string
    {
        if ($this->provider->isCard()) {
            return trim(($this->card_brand ?? $this->provider->label()).' •••• '.($this->card_last4 ?? '????'));
        }

        return $this->payer_phone
            ? $this->provider->label().' · '.$this->payer_phone
            : $this->provider->label();
    }

    /** `withTrashed` : un paiement reste attribué à son titulaire même si le compte a été supprimé depuis. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PromoterSubscription::class, 'subscription_id');
    }

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
