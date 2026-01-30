<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuggestionComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'suggestion_id',
        'step_id',
        'user_id',
        'content',
    ];

    /**
     * Get the suggestion that owns the comment.
     */
    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class);
    }

    /**
     * Get the step that owns the comment (if applicable).
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(SuggestionStep::class, 'step_id');
    }

    /**
     * Get the user who created the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if comment is general (not associated with a step).
     */
    public function isGeneral(): bool
    {
        return $this->step_id === null;
    }

    /**
     * Check if comment is associated with a step.
     */
    public function isStepComment(): bool
    {
        return $this->step_id !== null;
    }

    /**
     * Scope for general comments.
     */
    public function scopeGeneral($query)
    {
        return $query->whereNull('step_id');
    }

    /**
     * Scope for step comments.
     */
    public function scopeStepComments($query)
    {
        return $query->whereNotNull('step_id');
    }
}
