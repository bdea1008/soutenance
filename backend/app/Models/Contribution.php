<?php

namespace App\Models;

use App\Enums\ContributionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contribution extends Model
{
    protected $fillable = [
        'investor_id',
        'project_id',
        'amount',
        'share_percentage',
        'estimated_return',
        'status',
        'is_simulated',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContributionStatus::class,
            'amount' => 'integer',
            'share_percentage' => 'decimal:3',
            'estimated_return' => 'decimal:2',
            'is_simulated' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    /** `withTrashed` : l'historique d'investissement reste attribué même après suppression du compte. */
    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_id')->withTrashed();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Paiement (simulé) qui règle cette contribution — §2, « Effectuer un paiement ». */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
