<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuggestionResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'suggestion_id',
        'store_id',
        'metrics_before',
        'metrics_after',
        'revenue_variation',
        'avg_ticket_variation',
        'conversion_variation',
        'days_to_result',
        'success',
        'notes',
    ];

    protected $casts = [
        'metrics_before' => 'array',
        'metrics_after' => 'array',
        'revenue_variation' => 'decimal:2',
        'avg_ticket_variation' => 'decimal:2',
        'conversion_variation' => 'decimal:2',
        'days_to_result' => 'integer',
        'success' => 'boolean',
    ];

    /**
     * Get the suggestion that owns the result.
     */
    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class);
    }

    /**
     * Get the store that owns the result.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Check if the result was successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->success === true;
    }

    /**
     * Check if the result has been measured.
     */
    public function isMeasured(): bool
    {
        return $this->metrics_after !== null;
    }

    /**
     * Calculate total variation (revenue + ticket + conversion).
     */
    public function getTotalVariationAttribute(): float
    {
        return ($this->revenue_variation ?? 0) +
               ($this->avg_ticket_variation ?? 0) +
               ($this->conversion_variation ?? 0);
    }

    /**
     * Update metrics after implementation.
     */
    public function updateMetricsAfter(array $metrics): void
    {
        $before = $this->metrics_before;

        // Calculate variations
        $revenueVariation = null;
        $ticketVariation = null;
        $conversionVariation = null;

        if (isset($before['revenue_30d'], $metrics['revenue_30d']) && $before['revenue_30d'] > 0) {
            $revenueVariation = (($metrics['revenue_30d'] - $before['revenue_30d']) / $before['revenue_30d']) * 100;
        }

        if (isset($before['avg_ticket'], $metrics['avg_ticket']) && $before['avg_ticket'] > 0) {
            $ticketVariation = (($metrics['avg_ticket'] - $before['avg_ticket']) / $before['avg_ticket']) * 100;
        }

        if (isset($before['conversion_rate'], $metrics['conversion_rate']) && $before['conversion_rate'] > 0) {
            $conversionVariation = (($metrics['conversion_rate'] - $before['conversion_rate']) / $before['conversion_rate']) * 100;
        }

        // Determine success based on positive variations
        $success = ($revenueVariation !== null && $revenueVariation > 0) ||
                   ($ticketVariation !== null && $ticketVariation > 0) ||
                   ($conversionVariation !== null && $conversionVariation > 0);

        // Calculate days to result
        $daysToResult = $this->created_at->diffInDays(now());

        $this->update([
            'metrics_after' => $metrics,
            'revenue_variation' => $revenueVariation,
            'avg_ticket_variation' => $ticketVariation,
            'conversion_variation' => $conversionVariation,
            'days_to_result' => $daysToResult,
            'success' => $success,
        ]);
    }

    /**
     * Scope for successful results.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope for measured results.
     */
    public function scopeMeasured($query)
    {
        return $query->whereNotNull('metrics_after');
    }
}
