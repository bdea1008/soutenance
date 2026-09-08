<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Note (1 à 5) et commentaire optionnel laissés par un investisseur ayant
 * réellement investi dans le projet.
 */
class ProjectReview extends Model
{
    protected $fillable = [
        'project_id',
        'investor_id',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** `withTrashed` : l'avis reste attribué même après suppression du compte investisseur. */
    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_id')->withTrashed();
    }
}
