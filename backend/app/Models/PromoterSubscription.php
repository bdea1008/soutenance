<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoterSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'tier',
        'price',
        'currency',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'tier' => SubscriptionTier::class,
            'status' => SubscriptionStatus::class,
            'price' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /** `withTrashed` : l'abonnement reste attribué même après suppression du compte titulaire. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'subscription_id');
    }
}
