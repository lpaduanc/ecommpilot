<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SuggestionStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'suggestion_id',
        'title',
        'description',
        'position',
        'is_custom',
        'status',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_custom' => 'boolean',
        'completed_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    /**
     * Get the suggestion that owns the step.
     */
    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class);
    }

    /**
     * Get the user who completed the step.
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Get the comments for the step.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(SuggestionComment::class, 'step_id');
    }

    /**
     * Complete the step.
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
     * Mark the step as pending.
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
     * Check if the step is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the step is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Scope for pending steps.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for completed steps.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for ordering steps by position.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('position');
    }

    /**
     * Scope for custom steps only.
     */
    public function scopeCustom($query)
    {
        return $query->where('is_custom', true);
    }

    /**
     * Scope for original steps only.
     */
    public function scopeOriginal($query)
    {
        return $query->where('is_custom', false);
    }
}
