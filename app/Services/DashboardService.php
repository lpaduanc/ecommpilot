<?php

namespace App\Services;

use App\Models\Store;
use App\Models\SyncedOrder;
use App\Models\SyncedProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getStats(Store $store, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);
        $previousRange = $this->getPreviousDateRange($dateRange);

        // Current period stats
        $currentRevenue = $this->getRevenue($store, $dateRange['start'], $dateRange['end']);
        $currentOrders = $this->getOrdersCount($store, $dateRange['start'], $dateRange['end']);
        $currentCustomers = $this->getCustomersCount($store, $dateRange['start'], $dateRange['end']);

        // Previous period stats
        $previousRevenue = $this->getRevenue($store, $previousRange['start'], $previousRange['end']);
        $previousOrders = $this->getOrdersCount($store, $previousRange['start'], $previousRange['end']);
        $previousCustomers = $this->getCustomersCount($store, $previousRange['start'], $previousRange['end']);

        // Products
        $totalProducts = $store->products()->active()->count();

        // Calculate changes
        $revenueChange = $this->calculateChange($currentRevenue, $previousRevenue);
        $ordersChange = $this->calculateChange($currentOrders, $previousOrders);
        $customersChange = $this->calculateChange($currentCustomers, $previousCustomers);

        // Average ticket
        $currentTicket = $currentOrders > 0 ? $currentRevenue / $currentOrders : 0;
        $previousTicket = $previousOrders > 0 ? $previousRevenue / $previousOrders : 0;
        $ticketChange = $this->calculateChange($currentTicket, $previousTicket);

        return [
            'total_revenue' => $currentRevenue,
            'revenue_change' => $revenueChange,
            'total_orders' => $currentOrders,
            'orders_change' => $ordersChange,
            'total_products' => $totalProducts,
            'total_customers' => $currentCustomers,
            'customers_change' => $customersChange,
            'average_ticket' => $currentTicket,
            'ticket_change' => $ticketChange,
            'conversion_rate' => 0, // Would need visits data
            'conversion_change' => 0,
        ];
    }

    public function getRevenueChart(Store $store, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);
        
        $data = SyncedOrder::where('store_id', $store->id)
            ->paid()
            ->whereBetween('external_created_at', [$dateRange['start'], $dateRange['end']])
            ->select(
                DB::raw('DATE(external_created_at) as date'),
                DB::raw('SUM(total) as value')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $data->map(fn ($item) => [
            'date' => Carbon::parse($item->date)->format('d/m'),
            'value' => (float) $item->value,
        ])->toArray();
    }

    public function getOrdersStatusChart(Store $store, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);

        return SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$dateRange['start'], $dateRange['end']])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($item) => [
                'status' => $item->status?->value ?? $item->status,
                'count' => $item->count,
            ])
            ->toArray();
    }

    public function getTopProducts(Store $store, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);

        // Get orders within the date range
        $orders = SyncedOrder::where('store_id', $store->id)
            ->paid()
            ->whereBetween('external_created_at', [$dateRange['start'], $dateRange['end']])
            ->get();

        // Aggregate product sales from order items
        $productSales = [];
        
        foreach ($orders as $order) {
            $items = $order->items ?? [];
            foreach ($items as $item) {
                $productName = $item['product_name'] ?? $item['name'] ?? 'Produto';
                $quantity = $item['quantity'] ?? 1;
                $total = $item['total'] ?? ($item['unit_price'] ?? 0) * $quantity;
                
                if (!isset($productSales[$productName])) {
                    $productSales[$productName] = [
                        'name' => $productName,
                        'quantity_sold' => 0,
                        'revenue' => 0,
                    ];
                }
                
                $productSales[$productName]['quantity_sold'] += $quantity;
                $productSales[$productName]['revenue'] += $total;
            }
        }

        // Sort by quantity sold and return top 10
        usort($productSales, fn($a, $b) => $b['quantity_sold'] <=> $a['quantity_sold']);
        
        return array_slice(array_values($productSales), 0, 10);
    }

    public function getPaymentMethodsChart(Store $store, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);

        return SyncedOrder::where('store_id', $store->id)
            ->paid()
            ->whereBetween('external_created_at', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('payment_method')
            ->select('payment_method', DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($item) => [
                'method' => $item->payment_method,
                'count' => $item->count,
            ])
            ->toArray();
    }

    public function getCategoriesChart(Store $store, array $filters): array
    {
        // Would need category-level sales data
        return [];
    }

    public function getLowStockProducts(Store $store, int $threshold = 10): array
    {
        return $store->products()
            ->active()
            ->where('stock_quantity', '<=', $threshold)
            ->orderBy('stock_quantity')
            ->limit(20)
            ->get()
            ->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock_quantity' => $product->stock_quantity,
                'images' => $product->images,
            ])
            ->toArray();
    }

    private function getDateRange(array $filters): array
    {
        $period = $filters['period'] ?? 'last_30_days';

        if ($period === 'custom' && $filters['start_date'] && $filters['end_date']) {
            return [
                'start' => Carbon::parse($filters['start_date'])->startOfDay(),
                'end' => Carbon::parse($filters['end_date'])->endOfDay(),
            ];
        }

        return match ($period) {
            'today' => [
                'start' => now()->startOfDay(),
                'end' => now()->endOfDay(),
            ],
            'last_7_days' => [
                'start' => now()->subDays(7)->startOfDay(),
                'end' => now()->endOfDay(),
            ],
            'this_month' => [
                'start' => now()->startOfMonth(),
                'end' => now()->endOfDay(),
            ],
            'last_month' => [
                'start' => now()->subMonth()->startOfMonth(),
                'end' => now()->subMonth()->endOfMonth(),
            ],
            default => [
                'start' => now()->subDays(30)->startOfDay(),
                'end' => now()->endOfDay(),
            ],
        };
    }

    private function getPreviousDateRange(array $currentRange): array
    {
        $diff = $currentRange['start']->diffInDays($currentRange['end']);

        return [
            'start' => $currentRange['start']->copy()->subDays($diff + 1),
            'end' => $currentRange['start']->copy()->subDay(),
        ];
    }

    private function getRevenue(Store $store, Carbon $start, Carbon $end): float
    {
        return (float) SyncedOrder::where('store_id', $store->id)
            ->paid()
            ->whereBetween('external_created_at', [$start, $end])
            ->sum('total');
    }

    private function getOrdersCount(Store $store, Carbon $start, Carbon $end): int
    {
        return SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$start, $end])
            ->count();
    }

    private function getCustomersCount(Store $store, Carbon $start, Carbon $end): int
    {
        return SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$start, $end])
            ->distinct('customer_email')
            ->count('customer_email');
    }

    private function calculateChange(float $current, float $previous): float
    {
        if ($previous === 0.0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}

