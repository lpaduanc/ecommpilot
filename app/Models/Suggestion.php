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
        'was_successful',
        'feedback',
        'metrics_impact',
        'completed_at',
        'target_metrics',
        'specific_data',
        'data_justification',
        'embedding',
        'accepted_at',
    ];

    protected $casts = [
        'recommended_action' => 'array',
        'target_metrics' => 'array',
        'specific_data' => 'array',
        'data_justification' => 'array',
        'metrics_impact' => 'array',
        'completed_at' => 'datetime',
        'accepted_at' => 'datetime',
        'priority' => 'integer',
        'was_successful' => 'boolean',
    ];

    /**
     * Status constants
     */
    // Status para Tela de Análise
    public const STATUS_NEW = 'new';                 // Sugestão nova, ainda não interagida

    public const STATUS_REJECTED = 'rejected';       // Rejeitada pelo cliente (permanece na tela de análise)

    // Status para Página de Acompanhamento (após aceitar)
    public const STATUS_ACCEPTED = 'accepted';       // Aceita, aguardando ação

    public const STATUS_IN_PROGRESS = 'in_progress'; // Em andamento

    public const STATUS_COMPLETED = 'completed';     // Concluída

    // Legacy (mantido para compatibilidade)
    public const STATUS_PENDING = 'pending';         // Alias para new

    public const STATUS_IGNORED = 'ignored';         // Alias para rejected

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
     * Accept suggestion - moves to tracking page.
     */
    public function accept(): void
    {
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Reject suggestion - stays on analysis page.
     */
    public function reject(): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'accepted_at' => null,
        ]);
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
     * Mark suggestion as ignored (legacy alias for reject).
     */
    public function markAsIgnored(): void
    {
        $this->reject();
    }

    /**
     * Check if suggestion is new (not interacted).
     */
    public function isNew(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_PENDING]);
    }

    /**
     * Check if suggestion is pending (alias for isNew).
     */
    public function isPending(): bool
    {
        return $this->isNew();
    }

    /**
     * Check if suggestion is accepted.
     */
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Check if suggestion is rejected.
     */
    public function isRejected(): bool
    {
        return in_array($this->status, [self::STATUS_REJECTED, self::STATUS_IGNORED]);
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
     * Check if suggestion is ignored (alias for isRejected).
     */
    public function isIgnored(): bool
    {
        return $this->isRejected();
    }

    /**
     * Check if suggestion should appear on analysis page.
     */
    public function isOnAnalysisPage(): bool
    {
        return in_array($this->status, [
            self::STATUS_NEW,
            self::STATUS_PENDING,
            self::STATUS_REJECTED,
            self::STATUS_IGNORED,
        ]);
    }

    /**
     * Check if suggestion should appear on tracking page.
     */
    public function isOnTrackingPage(): bool
    {
        return in_array($this->status, [
            self::STATUS_ACCEPTED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
        ]);
    }

    /**
     * Check if suggestion has high impact.
     */
    public function hasHighImpact(): bool
    {
        return $this->expected_impact === self::IMPACT_HIGH;
    }

    /**
     * Scope for new/pending suggestions (analysis page).
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_NEW, self::STATUS_PENDING]);
    }

    /**
     * Scope for suggestions on analysis page.
     */
    public function scopeOnAnalysisPage($query)
    {
        return $query->whereIn('status', [
            self::STATUS_NEW,
            self::STATUS_PENDING,
            self::STATUS_REJECTED,
            self::STATUS_IGNORED,
        ]);
    }

    /**
     * Scope for suggestions on tracking page (accepted).
     */
    public function scopeOnTrackingPage($query)
    {
        return $query->whereIn('status', [
            self::STATUS_ACCEPTED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
        ]);
    }

    /**
     * Scope for accepted suggestions.
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    /**
     * Scope for in progress suggestions.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope for completed suggestions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for rejected suggestions.
     */
    public function scopeRejected($query)
    {
        return $query->whereIn('status', [self::STATUS_REJECTED, self::STATUS_IGNORED]);
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
            self::STATUS_NEW,
            self::STATUS_REJECTED,
            self::STATUS_ACCEPTED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
        ];
    }

    /**
     * Get statuses for analysis page.
     */
    public static function getAnalysisPageStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_PENDING,
            self::STATUS_REJECTED,
            self::STATUS_IGNORED,
        ];
    }

    /**
     * Get statuses for tracking page.
     */
    public static function getTrackingPageStatuses(): array
    {
        return [
            self::STATUS_ACCEPTED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
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
