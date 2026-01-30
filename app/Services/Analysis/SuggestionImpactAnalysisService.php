<?php

namespace App\Services\Analysis;

use App\Models\Store;
use App\Models\Suggestion;
use Carbon\Carbon;

class SuggestionImpactAnalysisService
{
    private const ANALYSIS_PERIOD_DAYS = 30;

    private const TREND_PERIOD_DAYS = 60;

    public function getImpactDashboard(Store $store): array
    {
        return [
            'summary' => $this->getConsolidatedSummary($store),
            'by_category' => $this->getImpactByCategory($store),
            'timeline' => $this->getTimelineData($store),
            'trend_analysis' => $this->getTrendAnalysis($store),
        ];
    }

    private function getConsolidatedSummary(Store $store): array
    {
        // Buscar sugestões que estão em andamento ou concluídas
        $suggestions = $store->suggestions()
            ->whereIn('status', [Suggestion::STATUS_IN_PROGRESS, Suggestion::STATUS_COMPLETED])
            ->where(function ($query) {
                $query->whereNotNull('in_progress_at')
                    ->orWhereNotNull('accepted_at');
            })
            ->get();

        if ($suggestions->isEmpty()) {
            return ['has_data' => false];
        }

        // Usar in_progress_at se disponível, senão accepted_at
        $firstActionDate = $suggestions->map(function ($s) {
            return $s->in_progress_at ?? $s->accepted_at;
        })->filter()->min();

        if (! $firstActionDate) {
            return ['has_data' => false];
        }

        $firstActionDate = Carbon::parse($firstActionDate);

        // Período ANTES: 30 dias antes da primeira ação
        $beforeStart = $firstActionDate->copy()->subDays(self::ANALYSIS_PERIOD_DAYS * 2);
        $beforeEnd = $firstActionDate->copy()->subDay();

        // Período DEPOIS: da primeira ação até hoje
        $afterStart = $firstActionDate->copy();
        $afterEnd = now();

        return [
            'has_data' => true,
            'suggestions_in_progress' => $suggestions->where('status', Suggestion::STATUS_IN_PROGRESS)->count(),
            'suggestions_completed' => $suggestions->where('status', Suggestion::STATUS_COMPLETED)->count(),
            'before' => $this->getMetrics($store, $beforeStart, $beforeEnd),
            'after' => $this->getMetrics($store, $afterStart, $afterEnd),
            'period' => [
                'before' => [
                    'start' => $beforeStart->toDateString(),
                    'end' => $beforeEnd->toDateString(),
                ],
                'after' => [
                    'start' => $afterStart->toDateString(),
                    'end' => $afterEnd->toDateString(),
                ],
            ],
        ];
    }

    private function getMetrics(Store $store, Carbon $start, Carbon $end): array
    {
        $orders = $store->syncedOrders()
            ->paid()
            ->inPeriod($start, $end)
            ->get();

        $revenue = (float) $orders->sum('total');
        $count = $orders->count();
        $avgTicket = $count > 0 ? round($revenue / $count, 2) : 0;
        $days = max(1, $start->diffInDays($end));

        return [
            'revenue' => $revenue,
            'orders' => $count,
            'avg_ticket' => $avgTicket,
            'days' => $days,
            'daily_revenue' => round($revenue / $days, 2),
            'daily_orders' => round($count / $days, 2),
        ];
    }

    private function getTrendAnalysis(Store $store): array
    {
        $suggestions = $store->suggestions()
            ->whereIn('status', [Suggestion::STATUS_IN_PROGRESS, Suggestion::STATUS_COMPLETED])
            ->where(function ($query) {
                $query->whereNotNull('in_progress_at')
                    ->orWhereNotNull('accepted_at');
            })
            ->get();

        if ($suggestions->isEmpty()) {
            return ['has_data' => false];
        }

        $firstActionDate = $suggestions->map(function ($s) {
            return $s->in_progress_at ?? $s->accepted_at;
        })->filter()->min();

        if (! $firstActionDate) {
            return ['has_data' => false];
        }

        $firstActionDate = Carbon::parse($firstActionDate);

        // Período 1: 60-31 dias antes (tendência prévia 1)
        $period1Start = $firstActionDate->copy()->subDays(self::TREND_PERIOD_DAYS);
        $period1End = $firstActionDate->copy()->subDays(31);

        // Período 2: 30-1 dias antes (tendência prévia 2)
        $period2Start = $firstActionDate->copy()->subDays(30);
        $period2End = $firstActionDate->copy()->subDay();

        $metrics1 = $this->getMetrics($store, $period1Start, $period1End);
        $metrics2 = $this->getMetrics($store, $period2Start, $period2End);

        // Calcular tendência pré-sugestões (crescimento entre período 1 e 2)
        $preTrend = $metrics1['revenue'] > 0
            ? round((($metrics2['revenue'] - $metrics1['revenue']) / $metrics1['revenue']) * 100, 1)
            : 0;

        // Calcular tendência pós-sugestões
        $afterStart = $firstActionDate->copy();
        $afterEnd = now();
        $metricsAfter = $this->getMetrics($store, $afterStart, $afterEnd);

        $postTrend = $metrics2['revenue'] > 0
            ? round((($metricsAfter['revenue'] - $metrics2['revenue']) / $metrics2['revenue']) * 100, 1)
            : 0;

        // Aceleração = diferença entre tendência pós e pré
        $acceleration = round($postTrend - $preTrend, 1);

        return [
            'has_data' => true,
            'pre_trend' => $preTrend,
            'post_trend' => $postTrend,
            'acceleration' => $acceleration,
            'interpretation' => $this->interpretTrend($acceleration),
        ];
    }

    private function interpretTrend(float $acceleration): string
    {
        if ($acceleration > 10) {
            return 'significant_improvement';
        } elseif ($acceleration > 0) {
            return 'slight_improvement';
        } elseif ($acceleration > -10) {
            return 'stable';
        } else {
            return 'decline';
        }
    }

    private function getImpactByCategory(Store $store): array
    {
        $suggestions = $store->suggestions()
            ->whereIn('status', [Suggestion::STATUS_IN_PROGRESS, Suggestion::STATUS_COMPLETED])
            ->get()
            ->groupBy('category');

        $result = [];
        foreach ($suggestions as $category => $categorySuggestions) {
            $result[] = [
                'category' => $category,
                'count' => $categorySuggestions->count(),
                'in_progress' => $categorySuggestions->where('status', Suggestion::STATUS_IN_PROGRESS)->count(),
                'completed' => $categorySuggestions->where('status', Suggestion::STATUS_COMPLETED)->count(),
                'successful' => $categorySuggestions->where('was_successful', true)->count(),
            ];
        }

        return $result;
    }

    private function getTimelineData(Store $store): array
    {
        // Buscar sugestões com datas
        $suggestions = $store->suggestions()
            ->whereIn('status', [Suggestion::STATUS_IN_PROGRESS, Suggestion::STATUS_COMPLETED])
            ->orderBy('in_progress_at')
            ->get()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'title' => $s->title,
                    'category' => $s->category,
                    'status' => $s->status,
                    'in_progress_at' => $s->in_progress_at?->toDateString(),
                    'completed_at' => $s->completed_at?->toDateString(),
                ];
            });

        // Buscar dados de receita diária dos últimos 60 dias
        $startDate = now()->subDays(60);
        $endDate = now();

        $dailyRevenue = $store->syncedOrders()
            ->paid()
            ->inPeriod($startDate, $endDate)
            ->selectRaw('DATE(external_created_at) as date, SUM(total) as revenue, COUNT(*) as orders')
            ->groupByRaw('DATE(external_created_at)')
            ->orderBy('date')
            ->get()
            ->map(function ($row) {
                return [
                    'date' => $row->date,
                    'revenue' => (float) $row->revenue,
                    'orders' => (int) $row->orders,
                ];
            });

        return [
            'suggestions' => $suggestions,
            'daily_metrics' => $dailyRevenue,
        ];
    }
}
