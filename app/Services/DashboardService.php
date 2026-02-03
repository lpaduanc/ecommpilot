<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Store;
use App\Models\SyncedOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getStats(Store $store, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);
        $previousRange = $this->getPreviousDateRange($dateRange);

        // Consolidated query for current and previous period stats + product count in a single query
        $stats = DB::table('synced_orders')
            ->selectRaw('
                -- Current period (paid orders for revenue)
                SUM(CASE WHEN external_created_at BETWEEN ? AND ? AND payment_status = ? THEN total ELSE 0 END) as current_revenue,
                COUNT(CASE WHEN external_created_at BETWEEN ? AND ? THEN 1 END) as current_orders,
                COUNT(CASE WHEN external_created_at BETWEEN ? AND ? AND payment_status = ? THEN 1 END) as current_paid_orders,
                COUNT(DISTINCT CASE WHEN external_created_at BETWEEN ? AND ? THEN customer_email END) as current_customers,
                -- Previous period (paid orders for revenue)
                SUM(CASE WHEN external_created_at BETWEEN ? AND ? AND payment_status = ? THEN total ELSE 0 END) as previous_revenue,
                COUNT(CASE WHEN external_created_at BETWEEN ? AND ? THEN 1 END) as previous_orders,
                COUNT(CASE WHEN external_created_at BETWEEN ? AND ? AND payment_status = ? THEN 1 END) as previous_paid_orders,
                COUNT(DISTINCT CASE WHEN external_created_at BETWEEN ? AND ? THEN customer_email END) as previous_customers,
                -- Products count (scalar subquery - same store_id)
                (SELECT COUNT(*) FROM synced_products WHERE store_id = ? AND is_active = true AND deleted_at IS NULL) as total_products
            ', [
                // Current period bindings
                $dateRange['start'], $dateRange['end'], 'paid',
                $dateRange['start'], $dateRange['end'],
                $dateRange['start'], $dateRange['end'], 'paid',
                $dateRange['start'], $dateRange['end'],
                // Previous period bindings
                $previousRange['start'], $previousRange['end'], 'paid',
                $previousRange['start'], $previousRange['end'],
                $previousRange['start'], $previousRange['end'], 'paid',
                $previousRange['start'], $previousRange['end'],
                // Product count binding
                $store->id,
            ])
            ->where('store_id', $store->id)
            ->whereNull('deleted_at')
            ->first();

        // Extract product count from the consolidated query
        $totalProducts = (int) ($stats->total_products ?? 0);

        // Extract values and convert to proper types
        $currentRevenue = (float) ($stats->current_revenue ?? 0);
        $currentOrders = (int) ($stats->current_orders ?? 0);
        $currentPaidOrders = (int) ($stats->current_paid_orders ?? 0);
        $currentCustomers = (int) ($stats->current_customers ?? 0);
        $previousRevenue = (float) ($stats->previous_revenue ?? 0);
        $previousOrders = (int) ($stats->previous_orders ?? 0);
        $previousPaidOrders = (int) ($stats->previous_paid_orders ?? 0);
        $previousCustomers = (int) ($stats->previous_customers ?? 0);

        // Calculate changes
        $revenueChange = $this->calculateChange($currentRevenue, $previousRevenue);
        $ordersChange = $this->calculateChange($currentOrders, $previousOrders);
        $customersChange = $this->calculateChange($currentCustomers, $previousCustomers);

        // Average ticket (based on paid orders only for accuracy)
        $currentTicket = $currentPaidOrders > 0 ? $currentRevenue / $currentPaidOrders : 0;
        $previousTicket = $previousPaidOrders > 0 ? $previousRevenue / $previousPaidOrders : 0;
        $ticketChange = $this->calculateChange($currentTicket, $previousTicket);

        // Conversion rate: percentage of orders that were paid
        $currentConversionRate = $currentOrders > 0 ? ($currentPaidOrders / $currentOrders) * 100 : 0;
        $previousConversionRate = $previousOrders > 0 ? ($previousPaidOrders / $previousOrders) * 100 : 0;
        $conversionChange = $this->calculateChange($currentConversionRate, $previousConversionRate);

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
            'conversion_rate' => round($currentConversionRate, 2),
            'conversion_change' => $conversionChange,
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

        // Use payment_status for better visibility of order states
        // payment_status shows: pending, paid, refunded, voided, failed
        $results = DB::table('synced_orders')
            ->select('payment_status as status', DB::raw('COUNT(*) as count'))
            ->where('store_id', $store->id)
            ->whereBetween('external_created_at', [$dateRange['start'], $dateRange['end']])
            ->whereNull('deleted_at')
            ->whereNotNull('payment_status')
            ->groupBy('payment_status')
            ->get();

        return $results->map(fn ($item) => [
            'status' => $item->status,
            'count' => (int) $item->count,
        ])->toArray();
    }

    public function getTopProducts(Store $store, array $filters): array
    {
        $dateRange = $this->getDateRange($filters);

        // Use PostgreSQL jsonb_array_elements to extract items and aggregate at database level
        $results = DB::select("
            WITH order_items AS (
                SELECT
                    jsonb_array_elements(items::jsonb) AS item
                FROM synced_orders
                WHERE store_id = ?
                    AND payment_status = ?
                    AND external_created_at BETWEEN ? AND ?
                    AND deleted_at IS NULL
                    AND items IS NOT NULL
            )
            SELECT
                COALESCE(
                    item->>'product_name',
                    item->>'name',
                    'Produto'
                ) as name,
                SUM(COALESCE((item->>'quantity')::numeric, 1)) as quantity_sold,
                SUM(
                    COALESCE(
                        (item->>'total')::numeric,
                        COALESCE((item->>'unit_price')::numeric, 0) * COALESCE((item->>'quantity')::numeric, 1)
                    )
                ) as revenue
            FROM order_items
            GROUP BY name
            ORDER BY quantity_sold DESC
            LIMIT 10
        ", [
            $store->id,
            PaymentStatus::Paid->value,
            $dateRange['start']->toDateTimeString(),
            $dateRange['end']->toDateTimeString(),
        ]);

        // Convert stdClass to array and format numbers
        return array_map(fn ($item) => [
            'name' => $item->name,
            'quantity_sold' => (int) $item->quantity_sold,
            'revenue' => (float) $item->revenue,
        ], $results);
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
            ->excludeGifts()  // Exclude gifts/brindes
            ->where('is_active', true)  // Explicitly filter active products only
            ->where('stock_quantity', '<=', $threshold)
            ->where('stock_quantity', '>=', 0)  // Exclude negative stock
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

    /**
     * Get all dashboard data in a single call with caching.
     */
    public function getBulkDashboardData(Store $store, array $filters): array
    {
        // Generate cache key based on store, filters, and data freshness
        $cacheKey = $this->generateCacheKey($store, $filters);

        // Cache for 5 minutes
        return Cache::remember($cacheKey, 300, function () use ($store, $filters) {
            return [
                'stats' => $this->getStats($store, $filters),
                'revenue_chart' => $this->getRevenueChart($store, $filters),
                'orders_status_chart' => $this->getOrdersStatusChart($store, $filters),
                'top_products' => $this->getTopProducts($store, $filters),
                'payment_methods_chart' => $this->getPaymentMethodsChart($store, $filters),
                'categories_chart' => $this->getCategoriesChart($store, $filters),
                'low_stock_products' => $this->getLowStockProducts($store),
            ];
        });
    }

    /**
     * Clear dashboard cache for a specific store.
     */
    public function clearCache(Store $store): void
    {
        // Clear all cache entries for this store
        $patterns = [
            "dashboard:{$store->id}:*",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Generate a cache key based on store and filters.
     */
    private function generateCacheKey(Store $store, array $filters): string
    {
        $period = $filters['period'] ?? 'last_15_days';
        $startDate = ! empty($filters['start_date']) ? $filters['start_date'] : null;
        $endDate = ! empty($filters['end_date']) ? $filters['end_date'] : null;

        // Normalize cache key for consistency
        $filterHash = md5(json_encode([
            'period' => $period,
            'start_date' => $period === 'custom' ? $startDate : null,
            'end_date' => $period === 'custom' ? $endDate : null,
        ]));

        return "dashboard:{$store->id}:{$filterHash}";
    }

    private function getDateRange(array $filters): array
    {
        $period = $filters['period'] ?? 'last_15_days';

        // Handle custom period with explicit date range
        if ($period === 'custom') {
            $startDate = $filters['start_date'] ?? null;
            $endDate = $filters['end_date'] ?? null;

            // Only use custom dates if both are provided and not empty
            if (! empty($startDate) && ! empty($endDate)) {
                return [
                    'start' => Carbon::parse($startDate)->startOfDay(),
                    'end' => Carbon::parse($endDate)->endOfDay(),
                ];
            }

            // Fall back to default if custom dates are missing
            $period = 'last_15_days';
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
            'last_15_days' => [
                'start' => now()->subDays(15)->startOfDay(),
                'end' => now()->endOfDay(),
            ],
            'last_30_days' => [
                'start' => now()->subDays(30)->startOfDay(),
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
            'all_time' => [
                'start' => Carbon::create(2000, 1, 1)->startOfDay(),
                'end' => now()->endOfDay(),
            ],
            default => [
                'start' => now()->subDays(15)->startOfDay(),
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

    private function calculateChange(float $current, float $previous): float
    {
        if ($previous === 0.0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}
