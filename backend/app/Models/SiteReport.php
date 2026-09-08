<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteReport extends Model
{
    protected $fillable = [
        'project_id',
        'author_id',
        'title',
        'description',
        'progress_percentage',
        'photos',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_percentage' => 'integer',
            'photos' => 'array',
            'reported_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** `withTrashed` : le journal de chantier reste attribué même après suppression du compte auteur. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }
}
