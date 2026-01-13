<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Suggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'analysis_id',
        'store_id',
        'category',
        'title',
        'description',
        'recommended_action',
        'expected_impact',
        'priority',
        'status',
        'completed_at',
        'target_metrics',
        'specific_data',
        'data_justification',
        'embedding',
    ];

    protected $casts = [
        'target_metrics' => 'array',
        'specific_data' => 'array',
        'completed_at' => 'datetime',
        'priority' => 'integer',
    ];

    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_IGNORED = 'ignored';

    /**
     * Impact constants
     */
    public const IMPACT_HIGH = 'high';

    public const IMPACT_MEDIUM = 'medium';

    public const IMPACT_LOW = 'low';

    /**
     * Category constants
     */
    public const CATEGORY_INVENTORY = 'inventory';

    public const CATEGORY_COUPON = 'coupon';

    public const CATEGORY_PRODUCT = 'product';

    public const CATEGORY_MARKETING = 'marketing';

    public const CATEGORY_OPERATIONAL = 'operational';

    public const CATEGORY_CUSTOMER = 'customer';

    public const CATEGORY_CONVERSION = 'conversion';

    public const CATEGORY_PRICING = 'pricing';

    /**
     * Get the analysis that owns the suggestion.
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    /**
     * Get the store that owns the suggestion.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the result for the suggestion.
     */
    public function result(): HasOne
    {
        return $this->hasOne(SuggestionResult::class);
    }

    /**
     * Mark suggestion as in progress.
     */
    public function markAsInProgress(): void
    {
        $this->update(['status' => self::STATUS_IN_PROGRESS]);
    }

    /**
     * Mark suggestion as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark suggestion as ignored.
     */
    public function markAsIgnored(): void
    {
        $this->update(['status' => self::STATUS_IGNORED]);
    }

    /**
     * Check if suggestion is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if suggestion is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if suggestion is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if suggestion is ignored.
     */
    public function isIgnored(): bool
    {
        return $this->status === self::STATUS_IGNORED;
    }

    /**
     * Check if suggestion has high impact.
     */
    public function hasHighImpact(): bool
    {
        return $this->expected_impact === self::IMPACT_HIGH;
    }

    /**
     * Scope for pending suggestions.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for completed suggestions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for suggestions by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for suggestions by impact.
     */
    public function scopeByImpact($query, string $impact)
    {
        return $query->where('expected_impact', $impact);
    }

    /**
     * Scope for high priority suggestions.
     */
    public function scopeHighPriority($query)
    {
        return $query->where('priority', '<=', 3);
    }

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_IGNORED,
        ];
    }

    /**
     * Get all available categories.
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_INVENTORY,
            self::CATEGORY_COUPON,
            self::CATEGORY_PRODUCT,
            self::CATEGORY_MARKETING,
            self::CATEGORY_OPERATIONAL,
            self::CATEGORY_CUSTOMER,
            self::CATEGORY_CONVERSION,
            self::CATEGORY_PRICING,
        ];
    }
}
