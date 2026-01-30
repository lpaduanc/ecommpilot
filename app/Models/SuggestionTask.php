<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuggestionTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'suggestion_id',
        'step_index',
        'title',
        'description',
        'status',
        'due_date',
        'completed_at',
        'completed_by',
        'created_by',
    ];

    protected $casts = [
        'step_index' => 'integer',
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    /**
     * Get the suggestion that owns the task.
     */
    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class);
    }

    /**
     * Get the user who completed the task.
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Get the user who created the task.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Start the task (mark as in progress).
     */
    public function start(): void
    {
        if ($this->status === self::STATUS_PENDING) {
            $this->update(['status' => self::STATUS_IN_PROGRESS]);
        }
    }

    /**
     * Complete the task.
     */
    public function complete(User $user): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $user->id,
        ]);
    }

    /**
     * Mark the task as pending (undo completion).
     */
    public function uncomplete(): void
    {
        $this->update([
            'status' => self::STATUS_PENDING,
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }

    /**
     * Check if the task is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the task is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the task is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if this task is linked to a specific step.
     */
    public function isLinkedToStep(): bool
    {
        return $this->step_index !== null;
    }

    /**
     * Check if this task is general (not linked to a step).
     */
    public function isGeneral(): bool
    {
        return $this->step_index === null;
    }

    /**
     * Scope for pending tasks.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for in progress tasks.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope for completed tasks.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for tasks linked to a specific step.
     */
    public function scopeForStep($query, int $stepIndex)
    {
        return $query->where('step_index', $stepIndex);
    }

    /**
     * Scope for general tasks (not linked to a step).
     */
    public function scopeGeneral($query)
    {
        return $query->whereNull('step_index');
    }

    /**
     * Scope for tasks with step.
     */
    public function scopeWithStep($query)
    {
        return $query->whereNotNull('step_index');
    }
}
