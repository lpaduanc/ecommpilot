<?php

namespace App\Services\AI\Memory;

use App\Models\Store;
use App\Models\Suggestion;
use App\Models\SuggestionResult;
use Illuminate\Support\Facades\Log;

class FeedbackLoopService
{
    /**
     * Capture metrics before implementing a suggestion.
     */
    public function captureMetricsBefore(Suggestion $suggestion): SuggestionResult
    {
        $store = $suggestion->store;
        $metrics = $this->getCurrentMetrics($store);

        return SuggestionResult::create([
            'suggestion_id' => $suggestion->id,
            'store_id' => $suggestion->store_id,
            'metrics_before' => $metrics,
        ]);
    }

    /**
     * Measure results after implementing a suggestion.
     */
    public function measureResults(Suggestion $suggestion, int $waitDays = 14): void
    {
        $result = $suggestion->result;

        if (! $result) {
            Log::warning("No result record found for suggestion {$suggestion->id}");

            return;
        }

        if ($result->metrics_after) {
            Log::info("Results already measured for suggestion {$suggestion->id}");

            return;
        }

        $store = $suggestion->store;
        $metrics = $this->getCurrentMetrics($store);

        $result->updateMetricsAfter($metrics);

        Log::info("Measured results for suggestion {$suggestion->id}", [
            'success' => $result->success,
            'revenue_variation' => $result->revenue_variation,
        ]);
    }

    /**
     * Get current metrics for a store.
     */
    public function getCurrentMetrics(Store $store): array
    {
        $orders = $store->orders()
            ->where('external_created_at', '>=', now()->subDays(30))
            ->get();

        $paidOrders = $orders->filter(fn ($o) => $o->isPaid());

        return [
            'revenue_30d' => $paidOrders->sum('total'),
            'orders_30d' => $paidOrders->count(),
            'avg_ticket' => $paidOrders->avg('total') ?? 0,
            'conversion_rate' => null, // Would need visitor data
            'cancellation_rate' => $orders->count() > 0
                ? round(($orders->filter(fn ($o) => $o->isCancelled())->count() / $orders->count()) * 100, 2)
                : 0,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Analyze successful suggestions to improve knowledge base.
     */
    public function analyzeSuccessfulSuggestions(int $storeId): array
    {
        $successfulResults = SuggestionResult::where('store_id', $storeId)
            ->where('success', true)
            ->with('suggestion')
            ->get();

        $insights = [];

        foreach ($successfulResults as $result) {
            $suggestion = $result->suggestion;

            $insights[] = [
                'category' => $suggestion->category,
                'title' => $suggestion->title,
                'impact' => [
                    'revenue' => $result->revenue_variation,
                    'ticket' => $result->avg_ticket_variation,
                    'conversion' => $result->conversion_variation,
                ],
                'days_to_result' => $result->days_to_result,
                'effective_action' => $suggestion->recommended_action,
            ];
        }

        return $insights;
    }

    /**
     * Get suggestions pending result measurement.
     */
    public function getSuggestionsPendingMeasurement(int $storeId, int $minDays = 14): array
    {
        return Suggestion::where('store_id', $storeId)
            ->where('status', Suggestion::STATUS_COMPLETED)
            ->where('completed_at', '<=', now()->subDays($minDays))
            ->whereHas('result', function ($query) {
                $query->whereNull('metrics_after');
            })
            ->get()
            ->toArray();
    }

    /**
     * Process pending measurements for a store.
     */
    public function processPendingMeasurements(int $storeId): int
    {
        $pending = Suggestion::where('store_id', $storeId)
            ->where('status', Suggestion::STATUS_COMPLETED)
            ->where('completed_at', '<=', now()->subDays(14))
            ->whereHas('result', function ($query) {
                $query->whereNull('metrics_after');
            })
            ->get();

        $processed = 0;

        foreach ($pending as $suggestion) {
            try {
                $this->measureResults($suggestion);
                $processed++;
            } catch (\Exception $e) {
                Log::error("Failed to measure results for suggestion {$suggestion->id}: ".$e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * Get overall suggestion effectiveness for a store.
     */
    public function getOverallEffectiveness(int $storeId): array
    {
        $results = SuggestionResult::where('store_id', $storeId)
            ->whereNotNull('metrics_after')
            ->with('suggestion')
            ->get();

        if ($results->isEmpty()) {
            return [
                'total_measured' => 0,
                'success_rate' => 0,
                'avg_revenue_impact' => 0,
                'avg_days_to_result' => 0,
                'best_category' => null,
            ];
        }

        $successful = $results->where('success', true);

        // Find best performing category
        $categoryPerformance = $results->groupBy('suggestion.category')
            ->map(function ($categoryResults) {
                $successCount = $categoryResults->where('success', true)->count();

                return [
                    'count' => $categoryResults->count(),
                    'success_rate' => $categoryResults->count() > 0
                        ? round(($successCount / $categoryResults->count()) * 100, 1)
                        : 0,
                ];
            })
            ->sortByDesc('success_rate');

        return [
            'total_measured' => $results->count(),
            'success_rate' => round(($successful->count() / $results->count()) * 100, 1),
            'avg_revenue_impact' => round($successful->avg('revenue_variation') ?? 0, 2),
            'avg_days_to_result' => round($results->avg('days_to_result') ?? 0),
            'best_category' => $categoryPerformance->keys()->first(),
            'category_breakdown' => $categoryPerformance->toArray(),
        ];
    }
}
