<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisExecutionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_id',
        'stage',
        'stage_name',
        'status',
        'started_at',
        'completed_at',
        'duration_ms',
        'metrics',
        'agent_used',
        'ai_provider',
        'token_usage',
        'error_message',
        'log_output',
    ];

    protected function casts(): array
    {
        return [
            'stage' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'duration_ms' => 'integer',
            'metrics' => 'array',
            'token_usage' => 'array',
        ];
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    // Scopes
    public function scopeByStage($query, int $stage)
    {
        return $query->where('stage', $stage);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }
}
