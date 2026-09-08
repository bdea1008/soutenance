<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\VerificationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class VerificationDocument extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'context',
        'type',
        'file_path',
        'original_name',
        'issued_at',
        'expires_at',
        'status',
        'reviewed_by',
        'review_note',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => VerificationContext::class,
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'issued_at' => 'date',
            'expires_at' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * La pièce a-t-elle dépassé sa durée de validité ? Une pièce sans date
     * d'expiration ne périme jamais (statuts, plans, permis…), et les pièces
     * déposées avant l'introduction de la péremption n'en ont pas.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Date d'expiration déduite de la date d'émission déclarée et de la durée
     * de validité du type. Point unique de calcul : le contrôleur ne doit
     * jamais poser `expires_at` lui-même.
     */
    public static function expiryFor(DocumentType $type, ?string $issuedAt): ?string
    {
        $months = $type->validityMonths();

        if ($months === null || $issuedAt === null) {
            return null;
        }

        return Carbon::parse($issuedAt)->addMonths($months)->toDateString();
    }

    /** `withTrashed` : la pièce reste attribuée même après suppression du compte déposant. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** `withTrashed` : la décision reste attribuée même après suppression du compte de l'examinateur. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
