<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Store;
use App\Models\SyncedOrder;
use App\Models\SyncedProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductAnalyticsService
{
    /**
     * Calculate analytics for products in a store
     */
    public function calculateProductAnalytics(Store $store, Collection $products, int $periodDays = 30): array
    {
        if ($products->isEmpty()) {
            return [
                'products' => [],
                'totals' => $this->getEmptyTotals(),
                'abc_analysis' => $this->getEmptyABCAnalysis(),
            ];
        }

        // Get aggregated sales metrics using database queries (much faster than PHP loops)
        $salesMetrics = $this->calculateSalesMetricsFromDatabase($store, $products, $periodDays);

        // Get ABC categories from cache (or calculate if needed)
        $abcCategories = $this->getABCCategories($store, $periodDays);

        // Get total orders count for conversion rate calculation
        $totalOrders = $this->getTotalOrdersCount($store, $periodDays);

        // Build product data array
        $productData = [];
        $totalRevenue = 0;

        foreach ($products as $product) {
            $productId = $product->id;
            $metrics = $salesMetrics[$productId] ?? null;

            $data = $this->buildProductData($product, $metrics, $totalOrders, $periodDays);
            $productData[$productId] = $data;
            $totalRevenue += $data['total_sold'];
        }

        // Calculate sales percentage for each product
        foreach ($productData as &$data) {
            $data['sales_percentage'] = $totalRevenue > 0
                ? round(($data['total_sold'] / $totalRevenue) * 100, 2)
                : 0;

            // Assign ABC category from cache
            $data['abc_category'] = $abcCategories[$data['product_id']] ?? 'C';
        }

        // Calculate BCG classification
        $this->assignBCGClassification($productData);

        // Calculate totals and ABC analysis
        $totals = $this->calculateTotals($productData);
        $abcAnalysis = $this->calculateABCAnalysisFromCache($store, $periodDays);

        return [
            'products' => $productData,
            'totals' => $totals,
            'abc_analysis' => $abcAnalysis,
        ];
    }

    /**
     * Calculate sales metrics from database using SQL aggregation
     * This replaces the O(n*m) PHP loops with efficient database queries.
     * Now with caching for improved performance.
     */
    private function calculateSalesMetricsFromDatabase(Store $store, Collection $products, int $periodDays): array
    {
        // Use cached store-wide sales data and filter to requested products
        $allSalesMetrics = $this->getStoreSalesMetricsFromCache($store, $periodDays);

        // Filter to only requested products
        $productIds = $products->pluck('id')->toArray();
        $productIdsSet = array_flip($productIds);

        return array_filter($allSalesMetrics, function ($key) use ($productIdsSet) {
            return isset($productIdsSet[$key]);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Get store-wide sales metrics from cache
     */
    private function getStoreSalesMetricsFromCache(Store $store, int $periodDays): array
    {
        $cacheKey = "sales_metrics:{$store->id}:{$periodDays}";

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($store, $periodDays) {
            // Get all products for this store
            $products = SyncedProduct::where('store_id', $store->id)->get(['id', 'external_id', 'name']);

            if ($products->isEmpty()) {
                return [];
            }

            // Create lookup maps
            $externalIdToId = [];
            $nameToId = [];
            foreach ($products as $product) {
                $externalIdToId[(string) $product->external_id] = $product->id;
                $nameToId[$product->name] = $product->id;
            }

            // Get database driver
            $driver = DB::connection()->getDriverName();

            if ($driver === 'pgsql') {
                return $this->calculateSalesMetricsPostgresOptimized($store, $periodDays, $externalIdToId, $nameToId);
            } else {
                return $this->calculateSalesMetricsFallback($store, $products, $periodDays);
            }
        });
    }

    /**
     * PostgreSQL optimized query using JSON operators
     * Optimized version that gets all products at once
     */
    private function calculateSalesMetricsPostgresOptimized(Store $store, int $periodDays, array $externalIdToId, array $nameToId): array
    {
        // Query to aggregate sales by product from JSON items array
        $salesData = DB::select("
            WITH order_items AS (
                SELECT
                    id as order_id,
                    jsonb_array_elements(items) as item
                FROM synced_orders
                WHERE store_id = ?
                    AND payment_status = ?
                    AND external_created_at >= ?
                    AND deleted_at IS NULL
            )
            SELECT
                item->>'product_id' as external_product_id,
                item->>'name' as product_name,
                COUNT(DISTINCT order_id) as order_count,
                SUM(COALESCE((item->>'quantity')::integer, 1)) as units_sold,
                SUM(
                    CASE
                        WHEN item->>'total' IS NOT NULL THEN (item->>'total')::numeric
                        ELSE COALESCE((item->>'quantity')::integer, 1) * COALESCE((item->>'unit_price')::numeric, (item->>'price')::numeric, 0)
                    END
                ) as total_revenue
            FROM order_items
            GROUP BY item->>'product_id', item->>'name'
        ", [$store->id, PaymentStatus::Paid->value, now()->subDays($periodDays)]);

        // Map results to product IDs
        $metrics = [];
        foreach ($salesData as $row) {
            $externalId = $row->external_product_id;
            $name = $row->product_name;

            // Try to match by external_id first, then by name
            $productId = null;
            if ($externalId && isset($externalIdToId[$externalId])) {
                $productId = $externalIdToId[$externalId];
            } elseif ($name && isset($nameToId[$name])) {
                $productId = $nameToId[$name];
            }

            if ($productId) {
                $metrics[$productId] = [
                    'order_count' => (int) $row->order_count,
                    'units_sold' => (int) $row->units_sold,
                    'total_revenue' => (float) $row->total_revenue,
                ];
            }
        }

        return $metrics;
    }

    /**
     * Fallback for SQLite and other databases
     */
    private function calculateSalesMetricsFallback(Store $store, Collection $products, int $periodDays): array
    {
        $orders = SyncedOrder::where('store_id', $store->id)
            ->paid()
            ->where('external_created_at', '>=', now()->subDays($periodDays))
            ->get(['id', 'items']);

        $metrics = [];
        $productsById = $products->keyBy('id');
        $productsByExternalId = $products->keyBy(fn ($p) => (string) $p->external_id);
        $productsByName = $products->keyBy('name');

        foreach ($orders as $order) {
            $items = $order->items ?? [];

            foreach ($items as $item) {
                $product = null;
                $itemProductId = isset($item['product_id']) ? (string) $item['product_id'] : null;
                $itemName = $item['product_name'] ?? $item['name'] ?? null;

                // Try to find product by external_id or name
                if ($itemProductId && isset($productsByExternalId[$itemProductId])) {
                    $product = $productsByExternalId[$itemProductId];
                } elseif ($itemName && isset($productsByName[$itemName])) {
                    $product = $productsByName[$itemName];
                }

                if ($product) {
                    $productId = $product->id;

                    if (! isset($metrics[$productId])) {
                        $metrics[$productId] = [
                            'order_count' => 0,
                            'units_sold' => 0,
                            'total_revenue' => 0.0,
                            'order_ids' => [],
                        ];
                    }

                    $quantity = $item['quantity'] ?? 1;
                    $itemTotal = isset($item['total'])
                        ? (float) $item['total']
                        : $quantity * ($item['unit_price'] ?? $item['price'] ?? 0);

                    $metrics[$productId]['units_sold'] += $quantity;
                    $metrics[$productId]['total_revenue'] += $itemTotal;

                    // Track unique orders
                    if (! in_array($order->id, $metrics[$productId]['order_ids'])) {
                        $metrics[$productId]['order_ids'][] = $order->id;
                        $metrics[$productId]['order_count']++;
                    }
                }
            }
        }

        // Remove temporary order_ids array
        foreach ($metrics as &$metric) {
            unset($metric['order_ids']);
        }

        return $metrics;
    }

    /**
     * Build product data array from metrics
     */
    private function buildProductData(SyncedProduct $product, ?array $metrics, int $totalOrders, int $periodDays): array
    {
        $unitsSold = $metrics['units_sold'] ?? 0;
        $totalSold = $metrics['total_revenue'] ?? 0.0;
        $ordersWithProduct = $metrics['order_count'] ?? 0;

        // Calculate conversion rate
        $conversionRate = $totalOrders > 0
            ? round(($ordersWithProduct / $totalOrders) * 100, 2)
            : 0;

        // Calculate average price
        $averagePrice = $unitsSold > 0 ? $totalSold / $unitsSold : (float) $product->price;

        // Calculate cost and profit
        $cost = $product->cost !== null ? (float) $product->cost : 0.0;
        $totalCost = $cost * $unitsSold;
        $totalProfit = $totalSold - $totalCost;

        // Calculate margin
        $margin = $totalSold > 0
            ? (($totalSold - $totalCost) / $totalSold) * 100
            : 0;

        // Calculate stock health
        $avgSalesPerDay = $periodDays > 0 ? $unitsSold / $periodDays : 0;
        $daysOfStock = $avgSalesPerDay > 0
            ? $product->stock_quantity / $avgSalesPerDay
            : ($product->stock_quantity > 0 ? 999 : 0);

        $stockHealth = match (true) {
            $daysOfStock > 30 => 'Alto',
            $daysOfStock >= 14 => 'Adequado',
            $daysOfStock >= 7 => 'Baixo',
            default => 'Crítico',
        };

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'sessions' => 0,
            'units_sold' => $unitsSold,
            'conversion_rate' => $conversionRate,
            'total_sold' => round($totalSold, 2),
            'sales_percentage' => 0,
            'total_profit' => round($totalProfit, 2),
            'average_price' => round($averagePrice, 2),
            'cost' => round($cost, 2),
            'margin' => round($margin, 2),
            'stock' => $product->stock_quantity,
            'stock_health' => $stockHealth,
            'days_of_stock' => round($daysOfStock, 1),
            'abc_category' => null,
            'classification' => null,
            'orders_with_product' => $ordersWithProduct,
        ];
    }

    /**
     * Get total orders count for conversion rate calculation
     */
    private function getTotalOrdersCount(Store $store, int $periodDays): int
    {
        return SyncedOrder::where('store_id', $store->id)
            ->paid()
            ->where('external_created_at', '>=', now()->subDays($periodDays))
            ->count();
    }

    /**
     * Get or calculate ABC categories with caching (public for controller access)
     */
    public function getABCCategories(Store $store, int $periodDays): array
    {
        $cacheKey = "abc_categories:{$store->id}:{$periodDays}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($store, $periodDays) {
            return $this->calculateABCCategoriesForAllProducts($store, $periodDays);
        });
    }

    /**
     * Calculate ABC categories for all products in the store
     */
    private function calculateABCCategoriesForAllProducts(Store $store, int $periodDays): array
    {
        // Get all products with sales data
        $products = SyncedProduct::where('store_id', $store->id)->get();

        if ($products->isEmpty()) {
            return [];
        }

        $salesMetrics = $this->calculateSalesMetricsFromDatabase($store, $products, $periodDays);

        // Create array of [product_id => revenue]
        $productRevenues = [];
        foreach ($products as $product) {
            $revenue = $salesMetrics[$product->id]['total_revenue'] ?? 0;
            $productRevenues[$product->id] = $revenue;
        }

        // Sort by revenue descending
        arsort($productRevenues);

        $totalRevenue = array_sum($productRevenues);

        if ($totalRevenue <= 0) {
            return array_fill_keys(array_keys($productRevenues), 'C');
        }

        // Assign ABC categories
        $categories = [];
        $cumulativeRevenue = 0;

        foreach ($productRevenues as $productId => $revenue) {
            $cumulativeRevenue += $revenue;
            $cumulativePercentage = ($cumulativeRevenue / $totalRevenue) * 100;

            if ($cumulativePercentage <= 80) {
                $categories[$productId] = 'A';
            } elseif ($cumulativePercentage <= 95) {
                $categories[$productId] = 'B';
            } else {
                $categories[$productId] = 'C';
            }
        }

        return $categories;
    }

    /**
     * Get or calculate stock health mapping with caching
     * Returns [product_id => 'Alto'|'Adequado'|'Baixo'|'Crítico']
     */
    public function getStockHealthMapping(Store $store, int $periodDays): array
    {
        $cacheKey = "stock_health:{$store->id}:{$periodDays}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($store, $periodDays) {
            return $this->calculateStockHealthForAllProducts($store, $periodDays);
        });
    }

    /**
     * Calculate stock health for all products
     */
    private function calculateStockHealthForAllProducts(Store $store, int $periodDays): array
    {
        $products = SyncedProduct::where('store_id', $store->id)->get(['id', 'stock_quantity']);

        if ($products->isEmpty()) {
            return [];
        }

        $salesMetrics = $this->calculateSalesMetricsFromDatabase($store, $products, $periodDays);

        $stockHealthMap = [];

        foreach ($products as $product) {
            $unitsSold = $salesMetrics[$product->id]['units_sold'] ?? 0;
            $avgSalesPerDay = $periodDays > 0 ? $unitsSold / $periodDays : 0;
            $daysOfStock = $avgSalesPerDay > 0
                ? $product->stock_quantity / $avgSalesPerDay
                : ($product->stock_quantity > 0 ? 999 : 0);

            $stockHealthMap[$product->id] = match (true) {
                $daysOfStock > 30 => 'Alto',
                $daysOfStock >= 14 => 'Adequado',
                $daysOfStock >= 7 => 'Baixo',
                default => 'Crítico',
            };
        }

        return $stockHealthMap;
    }

    /**
     * Invalidate all product analytics cache (call after order sync)
     */
    public function invalidateABCCache(Store $store): void
    {
        $periods = [7, 30, 90];
        foreach ($periods as $period) {
            Cache::forget("abc_categories:{$store->id}:{$period}");
            Cache::forget("abc_analysis:{$store->id}:{$period}");
            Cache::forget("stock_health:{$store->id}:{$period}");
            Cache::forget("sales_metrics:{$store->id}:{$period}");
        }
    }

    /**
     * Assign BCG Matrix classification
     * - Estrela (Star): High growth, high market share
     * - Vaca Leiteira (Cash Cow): Low growth, high market share
     * - Interrogação (Question Mark): High growth, low market share
     * - Abacaxi (Dog): Low growth, low market share
     */
    private function assignBCGClassification(array &$productData): void
    {
        if (empty($productData)) {
            return;
        }

        // Calculate median sales and margin
        $sales = array_column($productData, 'total_sold');
        $margins = array_column($productData, 'margin');

        sort($sales);
        sort($margins);

        $medianSales = $this->getMedian($sales);
        $medianMargin = $this->getMedian($margins);

        foreach ($productData as &$data) {
            $highSales = $data['total_sold'] >= $medianSales;
            $highMargin = $data['margin'] >= $medianMargin;

            if ($highSales && $highMargin) {
                $data['classification'] = 'Estrela';
            } elseif (! $highSales && $highMargin) {
                $data['classification'] = 'Vaca Leiteira';
            } elseif ($highSales && ! $highMargin) {
                $data['classification'] = 'Interrogação';
            } else {
                $data['classification'] = 'Abacaxi';
            }
        }
    }

    /**
     * Calculate median value from array
     */
    private function getMedian(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0;
        }

        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * Calculate totals for all products
     */
    private function calculateTotals(array $productData): array
    {
        return [
            'total_products' => count($productData),
            'total_sessions' => array_sum(array_column($productData, 'sessions')),
            'total_units_sold' => array_sum(array_column($productData, 'units_sold')),
            'total_revenue' => round(array_sum(array_column($productData, 'total_sold')), 2),
            'total_profit' => round(array_sum(array_column($productData, 'total_profit')), 2),
            'average_margin' => count($productData) > 0
                ? round(array_sum(array_column($productData, 'margin')) / count($productData), 2)
                : 0,
        ];
    }

    /**
     * Calculate ABC analysis summary from cache (public alias for controller access)
     */
    public function calculateABCAnalysisSummary(Store $store, int $periodDays): array
    {
        return $this->calculateABCAnalysisFromCache($store, $periodDays);
    }

    /**
     * Calculate ABC analysis summary from cache
     */
    private function calculateABCAnalysisFromCache(Store $store, int $periodDays): array
    {
        $cacheKey = "abc_analysis:{$store->id}:{$periodDays}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($store, $periodDays) {
            $categories = $this->getABCCategories($store, $periodDays);

            $total = count($categories);
            $categoryA = count(array_filter($categories, fn ($cat) => $cat === 'A'));
            $categoryB = count(array_filter($categories, fn ($cat) => $cat === 'B'));
            $categoryC = count(array_filter($categories, fn ($cat) => $cat === 'C'));

            return [
                'category_a' => [
                    'count' => $categoryA,
                    'percentage' => $total > 0 ? round(($categoryA / $total) * 100, 2) : 0,
                ],
                'category_b' => [
                    'count' => $categoryB,
                    'percentage' => $total > 0 ? round(($categoryB / $total) * 100, 2) : 0,
                ],
                'category_c' => [
                    'count' => $categoryC,
                    'percentage' => $total > 0 ? round(($categoryC / $total) * 100, 2) : 0,
                ],
            ];
        });
    }

    /**
     * Get empty totals structure (public for controller access)
     */
    public function getEmptyTotals(): array
    {
        return [
            'total_products' => 0,
            'total_sessions' => 0,
            'total_units_sold' => 0,
            'total_revenue' => 0,
            'total_profit' => 0,
            'average_margin' => 0,
        ];
    }

    /**
     * Get empty ABC analysis structure
     */
    private function getEmptyABCAnalysis(): array
    {
        return [
            'category_a' => ['count' => 0, 'percentage' => 0],
            'category_b' => ['count' => 0, 'percentage' => 0],
            'category_c' => ['count' => 0, 'percentage' => 0],
        ];
    }
}
