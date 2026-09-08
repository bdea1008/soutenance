<?php

namespace App\Models;

use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiScore extends Model
{
    protected $fillable = [
        'project_id',
        'confidence_score',
        'roi_estimate',
        'payback_months',
        'risk_level',
        'factors',
        'model_version',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'risk_level' => RiskLevel::class,
            'confidence_score' => 'decimal:2',
            'roi_estimate' => 'decimal:2',
            'payback_months' => 'integer',
            'factors' => 'array',
            'computed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
