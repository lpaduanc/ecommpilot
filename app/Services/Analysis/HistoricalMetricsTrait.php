<?php

namespace App\Services\Analysis;

use App\Models\Analysis;
use App\Models\Store;

trait HistoricalMetricsTrait
{
    /**
     * Busca métricas históricas da própria loja para comparação
     */
    public function getHistoricalMetrics(Store $store, int $monthsBack = 3): array
    {
        $analyses = Analysis::where('store_id', $store->id)
            ->where('created_at', '>=', now()->subMonths($monthsBack))
            ->orderBy('created_at', 'desc')
            ->get();

        if ($analyses->isEmpty()) {
            return ['available' => false, 'reason' => 'Sem histórico suficiente'];
        }

        $metrics = [
            'available' => true,
            'period_months' => $monthsBack,
            'analyses_count' => $analyses->count(),
            'averages' => [],
            'trends' => [],
        ];

        $ticketValues = [];
        $ordersValues = [];
        $conversionValues = [];
        $cancellationValues = [];

        foreach ($analyses as $analysis) {
            $data = $analysis->data ?? [];
            if (isset($data['ticket_medio'])) $ticketValues[] = $data['ticket_medio'];
            if (isset($data['pedidos_mes'])) $ordersValues[] = $data['pedidos_mes'];
            if (isset($data['taxa_conversao'])) $conversionValues[] = $data['taxa_conversao'];
            if (isset($data['taxa_cancelamento'])) $cancellationValues[] = $data['taxa_cancelamento'];
        }

        $metrics['averages'] = [
            'ticket_medio' => count($ticketValues) > 0 ? round(array_sum($ticketValues) / count($ticketValues), 2) : null,
            'pedidos_mes' => count($ordersValues) > 0 ? round(array_sum($ordersValues) / count($ordersValues), 0) : null,
            'taxa_conversao' => count($conversionValues) > 0 ? round(array_sum($conversionValues) / count($conversionValues), 2) : null,
            'taxa_cancelamento' => count($cancellationValues) > 0 ? round(array_sum($cancellationValues) / count($cancellationValues), 2) : null,
        ];

        // Calcular tendência
        if ($analyses->count() >= 2) {
            $midpoint = (int) floor($analyses->count() / 2);
            $recent = $analyses->take($midpoint);
            $older = $analyses->skip($midpoint);

            $recentTicket = $recent->avg(fn($a) => $a->data['ticket_medio'] ?? 0);
            $olderTicket = $older->avg(fn($a) => $a->data['ticket_medio'] ?? 0);

            if ($olderTicket > 0) {
                $ticketTrend = (($recentTicket - $olderTicket) / $olderTicket) * 100;
                $metrics['trends']['ticket_medio'] = [
                    'direction' => $ticketTrend > 5 ? 'up' : ($ticketTrend < -5 ? 'down' : 'stable'),
                    'change_percent' => round($ticketTrend, 1),
                ];
            }
        }

        return $metrics;
    }
}
