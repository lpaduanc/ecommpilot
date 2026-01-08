<?php

namespace App\DTOs;

/**
 * DTO para métricas do dashboard
 */
readonly class MetricsDTO
{
    public function __construct(
        public float $totalRevenue,
        public int $totalOrders,
        public float $averageTicket,
        public int $totalProducts,
        public int $totalCustomers,
        public ?float $revenueChange = null,
        public ?float $ordersChange = null,
        public ?float $ticketChange = null,
        public ?float $customersChange = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            totalRevenue: (float) ($data['total_revenue'] ?? 0),
            totalOrders: (int) ($data['total_orders'] ?? 0),
            averageTicket: (float) ($data['average_ticket'] ?? 0),
            totalProducts: (int) ($data['total_products'] ?? 0),
            totalCustomers: (int) ($data['total_customers'] ?? 0),
            revenueChange: isset($data['revenue_change']) ? (float) $data['revenue_change'] : null,
            ordersChange: isset($data['orders_change']) ? (float) $data['orders_change'] : null,
            ticketChange: isset($data['ticket_change']) ? (float) $data['ticket_change'] : null,
            customersChange: isset($data['customers_change']) ? (float) $data['customers_change'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'total_revenue' => $this->totalRevenue,
            'total_orders' => $this->totalOrders,
            'average_ticket' => $this->averageTicket,
            'total_products' => $this->totalProducts,
            'total_customers' => $this->totalCustomers,
            'revenue_change' => $this->revenueChange,
            'orders_change' => $this->ordersChange,
            'ticket_change' => $this->ticketChange,
            'customers_change' => $this->customersChange,
        ];
    }

    public function withChanges(
        ?float $revenueChange,
        ?float $ordersChange,
        ?float $ticketChange,
        ?float $customersChange
    ): self {
        return new self(
            totalRevenue: $this->totalRevenue,
            totalOrders: $this->totalOrders,
            averageTicket: $this->averageTicket,
            totalProducts: $this->totalProducts,
            totalCustomers: $this->totalCustomers,
            revenueChange: $revenueChange,
            ordersChange: $ordersChange,
            ticketChange: $ticketChange,
            customersChange: $customersChange,
        );
    }
}
