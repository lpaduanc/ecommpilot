<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

/**
 * DTO para dados completos da loja usados em análises
 */
readonly class StoreDataDTO
{
    public function __construct(
        public StoreInfoDTO $store,
        public MetricsDTO $metrics,
        public Collection $ordersByStatus,
        public Collection $ordersByPayment,
        public int $lowStockProducts,
        public int $outOfStockProducts,
        public Collection $topProducts,
        public \DateTimeInterface $periodStart,
        public \DateTimeInterface $periodEnd,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            store: $data['store'] instanceof StoreInfoDTO
                ? $data['store']
                : StoreInfoDTO::fromModel($data['store']),
            metrics: $data['metrics'] instanceof MetricsDTO
                ? $data['metrics']
                : MetricsDTO::fromArray($data['metrics']),
            ordersByStatus: collect($data['orders_by_status'] ?? []),
            ordersByPayment: collect($data['orders_by_payment'] ?? []),
            lowStockProducts: (int) ($data['low_stock_products'] ?? 0),
            outOfStockProducts: (int) ($data['out_of_stock_products'] ?? 0),
            topProducts: collect($data['top_products'] ?? []),
            periodStart: $data['period_start'] instanceof \DateTimeInterface
                ? $data['period_start']
                : new \DateTimeImmutable($data['period_start']),
            periodEnd: $data['period_end'] instanceof \DateTimeInterface
                ? $data['period_end']
                : new \DateTimeImmutable($data['period_end']),
        );
    }

    public function toArray(): array
    {
        return [
            'store' => $this->store->toArray(),
            'metrics' => $this->metrics->toArray(),
            'orders_by_status' => $this->ordersByStatus->toArray(),
            'orders_by_payment' => $this->ordersByPayment->toArray(),
            'low_stock_products' => $this->lowStockProducts,
            'out_of_stock_products' => $this->outOfStockProducts,
            'top_products' => $this->topProducts->toArray(),
            'period_start' => $this->periodStart->format('Y-m-d'),
            'period_end' => $this->periodEnd->format('Y-m-d'),
        ];
    }

    /**
     * Prepara os dados para envio à API de IA
     */
    public function toAIPromptData(): array
    {
        return [
            'loja' => [
                'nome' => $this->store->name,
                'dominio' => $this->store->domain,
            ],
            'periodo' => [
                'inicio' => $this->periodStart->format('d/m/Y'),
                'fim' => $this->periodEnd->format('d/m/Y'),
            ],
            'metricas' => [
                'faturamento_total' => number_format($this->metrics->totalRevenue, 2, ',', '.'),
                'total_pedidos' => $this->metrics->totalOrders,
                'ticket_medio' => number_format($this->metrics->averageTicket, 2, ',', '.'),
                'total_produtos' => $this->metrics->totalProducts,
                'total_clientes' => $this->metrics->totalCustomers,
            ],
            'estoque' => [
                'produtos_baixo_estoque' => $this->lowStockProducts,
                'produtos_sem_estoque' => $this->outOfStockProducts,
            ],
            'pedidos_por_status' => $this->ordersByStatus->toArray(),
            'pedidos_por_pagamento' => $this->ordersByPayment->toArray(),
            'produtos_mais_vendidos' => $this->topProducts->take(10)->toArray(),
        ];
    }
}
