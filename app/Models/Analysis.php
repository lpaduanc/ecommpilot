<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use App\Enums\AnalysisType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Analysis extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'uuid',
        'user_id',
        'store_id',
        'status',
        'analysis_type',
        'error_message',
        'summary',
        'suggestions',
        'alerts',
        'opportunities',
        'period_start',
        'period_end',
        'credits_used',
        'raw_response',
        'raw_agent_outputs',
        'completed_at',
        'email_sent_at',
        'email_error',
        // Progress tracking fields
        'current_stage',
        'total_stages',
        'stage_data',
        'stage_retry_count',
        'last_error_stage',
        'is_resuming',
        'last_progress_at',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'status' => AnalysisStatus::class,
            'analysis_type' => AnalysisType::class,
            'summary' => 'array',
            'raw_agent_outputs' => 'array',
            'suggestions' => 'array',
            'alerts' => 'array',
            'opportunities' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
            'credits_used' => 'integer',
            'completed_at' => 'datetime',
            'email_sent_at' => 'datetime',
            // Progress tracking casts
            'stage_data' => 'array',
            'current_stage' => 'integer',
            'total_stages' => 'integer',
            'stage_retry_count' => 'integer',
            'last_error_stage' => 'integer',
            'is_resuming' => 'boolean',
            'last_progress_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the persistent suggestions for this analysis.
     */
    public function persistentSuggestions(): HasMany
    {
        return $this->hasMany(Suggestion::class);
    }

    /**
     * Get the execution logs for this analysis.
     */
    public function executionLogs(): HasMany
    {
        return $this->hasMany(AnalysisExecutionLog::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === AnalysisStatus::Completed;
    }

    public function isPending(): bool
    {
        return $this->status === AnalysisStatus::Pending;
    }

    public function isProcessing(): bool
    {
        return $this->status === AnalysisStatus::Processing;
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => AnalysisStatus::Processing,
            'current_stage' => 0,
            'total_stages' => 9,
            'last_progress_at' => now(),
        ]);
    }

    public function markAsCompleted(array $data): void
    {
        $this->update([
            'status' => AnalysisStatus::Completed,
            'summary' => $data['summary'] ?? null,
            'suggestions' => $data['suggestions'] ?? [],
            'alerts' => $data['alerts'] ?? [],
            'opportunities' => $data['opportunities'] ?? [],
            'raw_response' => json_encode($data),
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(?string $errorMessage = null): void
    {
        $this->update([
            'status' => AnalysisStatus::Failed,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Save progress after a stage completes successfully.
     */
    public function saveStageProgress(int $stage, string $stageName, array $stageResult): void
    {
        $stageData = $this->stage_data ?? [];
        $stageData[$stageName] = $stageResult;

        $this->update([
            'current_stage' => $stage,
            'stage_data' => $stageData,
            'stage_retry_count' => 0, // Reset retry count on success
            'last_progress_at' => now(),
        ]);
    }

    /**
     * Mark a stage as failed for potential retry.
     */
    public function markStageFailed(int $stage, string $errorMessage): void
    {
        $this->update([
            'last_error_stage' => $stage,
            'stage_retry_count' => ($this->stage_retry_count ?? 0) + 1,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Check if the analysis can be resumed from a partial state.
     */
    public function canResume(): bool
    {
        // Can resume if:
        // 1. Has partial progress (current_stage > 0)
        // 2. Not already completed
        // 3. Not permanently failed (retry count < max)
        return $this->current_stage > 0
            && $this->status !== AnalysisStatus::Completed
            && ($this->stage_retry_count ?? 0) < 3;
    }

    /**
     * Get data from a specific completed stage.
     */
    public function getStageData(string $stageName): ?array
    {
        return $this->stage_data[$stageName] ?? null;
    }

    /**
     * Check if a specific stage has been completed.
     */
    public function isStageCompleted(int $stage): bool
    {
        return ($this->current_stage ?? 0) >= $stage;
    }

    /**
     * Mark the analysis as resuming from a previous state.
     */
    public function markAsResuming(): void
    {
        $this->update([
            'is_resuming' => true,
            'status' => AnalysisStatus::Processing,
        ]);
    }

    /**
     * Reset the analysis for a fresh retry (clears all partial progress).
     */
    public function resetForRetry(): void
    {
        $this->update([
            'current_stage' => 0,
            'stage_data' => null,
            'stage_retry_count' => 0,
            'last_error_stage' => null,
            'is_resuming' => false,
            'error_message' => null,
            'status' => AnalysisStatus::Pending,
        ]);
    }

    /**
     * Get the progress percentage (0-100).
     */
    public function getProgressPercentage(): int
    {
        $totalStages = $this->total_stages ?? 9;
        $currentStage = $this->current_stage ?? 0;

        if ($this->status === AnalysisStatus::Completed) {
            return 100;
        }

        if ($totalStages === 0) {
            return 0;
        }

        return (int) round(($currentStage / $totalStages) * 100);
    }

    /**
     * Get a human-readable stage name.
     */
    public function getCurrentStageName(): string
    {
        $stageNames = [
            0 => 'Aguardando',
            1 => 'Identificando nicho',
            2 => 'Carregando histórico',
            3 => 'Buscando benchmarks',
            4 => 'Coletando dados externos',
            5 => 'Executando Collector',
            6 => 'Executando Analyst',
            7 => 'Executando Strategist',
            8 => 'Executando Critic',
            9 => 'Filtrando sugestões',
        ];

        return $stageNames[$this->current_stage ?? 0] ?? 'Processando';
    }

    public function healthScore(): ?int
    {
        return $this->summary['health_score'] ?? null;
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', AnalysisStatus::Completed);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('analysis_type', $type);
    }

    /**
     * Check if this analysis is a specialized (non-general) type.
     */
    public function isSpecialized(): bool
    {
        return $this->analysis_type !== AnalysisType::General;
    }

    /**
     * Get the type configuration (label, description, available).
     */
    public function getTypeConfig(): array
    {
        $type = $this->analysis_type ?? AnalysisType::General;

        return [
            'key' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
            'available' => $type->available(),
            'is_default' => $type->isDefault(),
        ];
    }
}
