<?php

namespace App\Services\AI\Memory;

use App\Models\Analysis;
use App\Models\Suggestion;

class HistoryService
{
    /**
     * Get previous analyses for a store.
     */
    public function getPreviousAnalyses(int $storeId, int $limit = 5): array
    {
        return Analysis::where('store_id', $storeId)
            ->completed()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($analysis) {
                return [
                    'id' => $analysis->id,
                    'date' => $analysis->completed_at?->toIso8601String(),
                    'health_score' => $analysis->summary['health_score'] ?? null,
                    'health_status' => $analysis->summary['health_status'] ?? null,
                    'main_insight' => $analysis->summary['main_insight'] ?? null,
                    'suggestions_count' => $analysis->persistentSuggestions()->count(),
                    'period_start' => $analysis->period_start?->toDateString(),
                    'period_end' => $analysis->period_end?->toDateString(),
                ];
            })
            ->toArray();
    }

    /**
     * Get suggestions with their status for a store.
     */
    public function getSuggestionsWithStatus(int $storeId): array
    {
        return Suggestion::where('store_id', $storeId)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($suggestion) {
                return [
                    'id' => $suggestion->id,
                    'category' => $suggestion->category,
                    'title' => $suggestion->title,
                    'description' => $suggestion->description,
                    'expected_impact' => $suggestion->expected_impact,
                    'status' => $suggestion->status,
                    'completed_at' => $suggestion->completed_at?->toIso8601String(),
                    'has_result' => $suggestion->result()->exists(),
                    'was_successful' => $suggestion->result?->success,
                ];
            })
            ->toArray();
    }

    /**
     * Get successful suggestions for learning.
     */
    public function getSuccessfulSuggestions(int $storeId): array
    {
        return Suggestion::where('store_id', $storeId)
            ->where('status', Suggestion::STATUS_COMPLETED)
            ->whereHas('result', function ($query) {
                $query->where('success', true);
            })
            ->with('result')
            ->get()
            ->map(function ($suggestion) {
                return [
                    'category' => $suggestion->category,
                    'title' => $suggestion->title,
                    'description' => $suggestion->description,
                    'expected_impact' => $suggestion->expected_impact,
                    'actual_impact' => [
                        'revenue_variation' => $suggestion->result->revenue_variation,
                        'avg_ticket_variation' => $suggestion->result->avg_ticket_variation,
                        'conversion_variation' => $suggestion->result->conversion_variation,
                    ],
                    'days_to_result' => $suggestion->result->days_to_result,
                ];
            })
            ->toArray();
    }

    /**
     * Get ignored or failed suggestions.
     */
    public function getFailedSuggestions(int $storeId): array
    {
        return Suggestion::where('store_id', $storeId)
            ->where(function ($query) {
                $query->where('status', Suggestion::STATUS_IGNORED)
                    ->orWhereHas('result', function ($q) {
                        $q->where('success', false);
                    });
            })
            ->get()
            ->map(function ($suggestion) {
                return [
                    'category' => $suggestion->category,
                    'title' => $suggestion->title,
                    'status' => $suggestion->status,
                    'failure_reason' => $suggestion->status === Suggestion::STATUS_IGNORED
                        ? 'Ignored by user'
                        : ($suggestion->result?->notes ?? 'No improvement observed'),
                ];
            })
            ->toArray();
    }

    /**
     * Get suggestion patterns by category.
     */
    public function getSuggestionPatterns(int $storeId): array
    {
        $suggestions = Suggestion::where('store_id', $storeId)->get();

        $patterns = [];

        foreach (Suggestion::getCategories() as $category) {
            $categorySuggestions = $suggestions->where('category', $category);

            if ($categorySuggestions->isEmpty()) {
                continue;
            }

            $completed = $categorySuggestions->where('status', Suggestion::STATUS_COMPLETED);
            $successful = $completed->filter(fn ($s) => $s->result?->success === true);

            $patterns[$category] = [
                'total' => $categorySuggestions->count(),
                'completed' => $completed->count(),
                'successful' => $successful->count(),
                'success_rate' => $completed->count() > 0
                    ? round(($successful->count() / $completed->count()) * 100, 1)
                    : 0,
            ];
        }

        return $patterns;
    }

    /**
     * Get the last analysis date for a store.
     */
    public function getLastAnalysisDate(int $storeId): ?\DateTime
    {
        $lastAnalysis = Analysis::where('store_id', $storeId)
            ->completed()
            ->latest()
            ->first();

        return $lastAnalysis?->completed_at;
    }

    /**
     * Get pending suggestions count for a store.
     */
    public function getPendingSuggestionsCount(int $storeId): int
    {
        return Suggestion::where('store_id', $storeId)
            ->pending()
            ->count();
    }
}
