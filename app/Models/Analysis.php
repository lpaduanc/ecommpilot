<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Analysis extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'store_id',
        'status',
        'summary',
        'suggestions',
        'alerts',
        'opportunities',
        'period_start',
        'period_end',
        'credits_used',
        'raw_response',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AnalysisStatus::class,
            'summary' => 'array',
            'suggestions' => 'array',
            'alerts' => 'array',
            'opportunities' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
            'credits_used' => 'integer',
            'completed_at' => 'datetime',
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
        $this->update(['status' => AnalysisStatus::Processing]);
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

    public function markAsFailed(): void
    {
        $this->update(['status' => AnalysisStatus::Failed]);
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
}
