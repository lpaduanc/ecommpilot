<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service for analyzing discount and coupon performance.
 *
 * This service provides analytics for coupons in two modes:
 * 1. When order->coupon data is available: Full analytics from order data
 * 2. When order->coupon is empty: Basic analytics from coupon usage counts
 *
 * Performance optimized with:
 * - Single batch queries instead of N+1
 * - Indexed columns usage
 * - Query result caching
 * - Minimal data transformation
 */
class DiscountAnalyticsService
{
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Parse period filter to start/end dates.
     */
    private function parsePeriodDates(array $params): array
    {
        $period = $params['period'] ?? 'last_15_days';
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;

        // If custom dates are provided, use them
        if ($startDate && $endDate) {
            return [
                'start' => $startDate,
                'end' => $endDate,
            ];
        }

        // Parse period preset
        $now = now();
        $start = match ($period) {
            'today' => $now->copy()->startOfDay(),
            'last_7_days' => $now->copy()->subDays(6)->startOfDay(),
            'last_15_days' => $now->copy()->subDays(14)->startOfDay(),
            'last_30_days' => $now->copy()->subDays(29)->startOfDay(),
            'this_month' => $now->copy()->startOfMonth(),
            'last_month' => $now->copy()->subMonth()->startOfMonth(),
            'all_time' => null,
            default => $now->copy()->subDays(14)->startOfDay(), // Default: last 15 days
        };

        $end = match ($period) {
            'last_month' => $now->copy()->subMonth()->endOfMonth(),
            default => $now->copy()->endOfDay(),
        };

        return [
            'start' => $start?->toDateString(),
            'end' => $end->toDateString(),
        ];
    }

    /**
     * Apply date filter to query.
     */
    private function applyDateFilter($query, ?string $startDate, ?string $endDate, string $dateColumn = 'external_created_at')
    {
        if ($startDate) {
            $query->where($dateColumn, '>=', $startDate.' 00:00:00');
        }
        if ($endDate) {
            $query->where($dateColumn, '<=', $endDate.' 23:59:59');
        }

        return $query;
    }

    /**
     * Get general discount statistics.
     * Optimized single query for dashboard stats.
     */
    public function getGeneralStats(Store $store, array $params = []): array
    {
        $dates = $this->parsePeriodDates($params);
        $cacheKey = "discount_stats_{$store->id}_".md5(json_encode($dates));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($store, $dates) {
            // Single optimized query for order stats
            $orderQuery = DB::table('synced_orders')
                ->where('store_id', $store->id)
                ->where('payment_status', 'paid')
                ->whereNull('deleted_at');

            // Apply date filter
            $this->applyDateFilter($orderQuery, $dates['start'], $dates['end']);

            $orderStats = $orderQuery->selectRaw('
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN discount > 0 THEN 1 ELSE 0 END) as orders_with_discount,
                    COALESCE(SUM(CASE WHEN discount > 0 THEN total ELSE 0 END), 0) as revenue_with_discount,
                    COALESCE(SUM(discount), 0) as total_discount
                ')
                ->first();

            // Single query for coupon stats (coupons don't filter by date, they're catalog data)
            $couponStats = DB::table('synced_coupons')
                ->where('store_id', $store->id)
                ->whereNull('deleted_at')
                ->selectRaw('
                    COUNT(*) as total_coupons,
                    SUM(used) as total_uses,
                    SUM(CASE WHEN valid = true AND (end_date IS NULL OR end_date >= NOW()) THEN 1 ELSE 0 END) as active_coupons,
                    SUM(CASE WHEN end_date IS NOT NULL AND end_date < NOW() THEN 1 ELSE 0 END) as expired_coupons
                ')
                ->first();

            $totalRevenue = (float) ($orderStats->revenue_with_discount ?? 0);
            $totalDiscount = (float) ($orderStats->total_discount ?? 0);
            $ordersWithDiscount = (int) ($orderStats->orders_with_discount ?? 0);

            return [
                'total_orders' => (int) ($orderStats->total_orders ?? 0),
                'orders_with_discount' => $ordersWithDiscount,
                'orders_with_coupon' => (int) ($couponStats->total_uses ?? 0),
                'total_revenue' => $totalRevenue,
                'total_discount' => $totalDiscount,
                'discount_percentage' => $totalRevenue > 0
                    ? round(($totalDiscount / ($totalRevenue + $totalDiscount)) * 100, 2)
                    : 0,
                'active_coupons' => (int) ($couponStats->active_coupons ?? 0),
                'expired_coupons' => (int) ($couponStats->expired_coupons ?? 0),
                'total_coupons' => (int) ($couponStats->total_coupons ?? 0),
                'period' => [
                    'start' => $dates['start'],
                    'end' => $dates['end'],
                ],
            ];
        });
    }

    /**
     * Get paginated coupons with analytics.
     * Optimized with batch queries and minimal N+1.
     */
    public function getCouponsWithAnalytics(Store $store, array $params = []): array
    {
        $dates = $this->parsePeriodDates($params);

        // Build cache key with only essential params (not the entire params array)
        $search = $params['search'] ?? '';
        $status = $params['status'] ?? '';
        $type = $params['type'] ?? '';
        $page = max((int) ($params['page'] ?? 1), 1);
        $perPage = min((int) ($params['per_page'] ?? 10), 100);
        $sortBy = $params['sort_by'] ?? 'used';
        $sortOrder = strtolower($params['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $cacheKey = "discount_coupons_{$store->id}_{$dates['start']}_{$dates['end']}_{$search}_{$status}_{$type}_{$page}_{$perPage}_{$sortBy}_{$sortOrder}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($store, $dates, $search, $status, $type, $page, $perPage, $sortBy, $sortOrder) {
            // Build optimized query
            $query = DB::table('synced_coupons')
                ->where('store_id', $store->id)
                ->whereNull('deleted_at');

            // Apply filters
            if ($search) {
                $query->where('code', 'ilike', "%{$search}%");
            }

            if ($status === 'active') {
                $query->where('valid', true)
                    ->where(function ($q) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', now());
                    });
            } elseif ($status === 'expired') {
                $query->whereNotNull('end_date')
                    ->where('end_date', '<', now());
            } elseif ($status === 'invalid') {
                $query->where('valid', false);
            }

            if ($type) {
                $query->where('type', $type);
            }

            // Get total count
            $total = $query->count();
            $lastPage = max(1, (int) ceil($total / $perPage));
            $currentPage = min($page, $lastPage);

            // Sort mapping
            $sortColumn = match ($sortBy) {
                'code' => 'code',
                'value' => 'value',
                'used' => 'used',
                'type' => 'type',
                'created_at' => 'created_at',
                'end_date' => 'end_date',
                default => 'used',
            };

            // Get paginated coupons with all needed data
            $coupons = $query->select([
                'id',
                'external_id',
                'code',
                'type',
                'value',
                'valid',
                'used',
                'max_uses',
                'start_date',
                'end_date',
                'min_price',
                'categories',
                'created_at',
            ])
                ->orderBy($sortColumn, $sortOrder)
                ->orderBy('id', 'desc')
                ->offset(($currentPage - 1) * $perPage)
                ->limit($perPage)
                ->get();

            if ($coupons->isEmpty()) {
                return [
                    'data' => [],
                    'total' => 0,
                    'last_page' => 1,
                    'current_page' => $currentPage,
                    'totals' => $this->getEmptyTotals(),
                ];
            }

            // Try to get order-based analytics (filtered by period)
            $couponCodes = $coupons->pluck('code')->toArray();
            $orderAnalytics = $this->getOrderBasedAnalytics($store, $couponCodes, $dates);
            $hasOrderData = ! empty($orderAnalytics);

            // Only calculate store average ticket if we need it (lazy loading)
            $storeAvgTicket = null;

            // Transform coupons with analytics
            $couponsWithAnalytics = $coupons->map(function ($coupon) use ($orderAnalytics, &$storeAvgTicket, $store, $dates) {
                $key = strtolower($coupon->code);
                $analytics = $orderAnalytics[$key] ?? null;

                // If we have order-based analytics, use them
                if ($analytics) {
                    return $this->formatCouponWithOrderAnalytics($coupon, $analytics);
                }

                // Lazy load store average ticket only when needed
                if ($storeAvgTicket === null) {
                    $storeAvgTicket = $this->getStoreAverageTicket($store, $dates);
                }

                // Estimate from coupon usage count
                return $this->formatCouponWithEstimatedAnalytics($coupon, $storeAvgTicket);
            });

            // Calculate page totals
            $totals = $this->calculatePageTotals($couponsWithAnalytics);

            return [
                'data' => $couponsWithAnalytics->values()->all(),
                'total' => $total,
                'last_page' => $lastPage,
                'current_page' => $currentPage,
                'totals' => $totals,
                'has_order_data' => $hasOrderData,
                'period' => [
                    'start' => $dates['start'],
                    'end' => $dates['end'],
                ],
            ];
        });
    }

    /**
     * Get order-based analytics for coupons.
     * Only returns data if orders have coupon->code populated.
     * Optimized: Single query instead of exists() + data query.
     */
    private function getOrderBasedAnalytics(Store $store, array $couponCodes, array $dates = []): array
    {
        if (empty($couponCodes)) {
            return [];
        }

        // Build case-insensitive code matching
        $lowerCodes = array_map('strtolower', $couponCodes);

        // Single query - if no results, we know there's no data
        $query = DB::table('synced_orders')
            ->where('store_id', $store->id)
            ->where('payment_status', 'paid')
            ->whereNull('deleted_at')
            ->whereRaw("coupon->>'code' IS NOT NULL AND coupon->>'code' != ''")
            ->whereRaw("LOWER(coupon->>'code') = ANY(?)", ['{'.implode(',', $lowerCodes).'}']);

        // Apply date filter
        $this->applyDateFilter($query, $dates['start'] ?? null, $dates['end'] ?? null);

        $results = $query
            ->selectRaw("
                LOWER(coupon->>'code') as coupon_code,
                COUNT(*) as number_of_orders,
                COUNT(DISTINCT customer_email) as unique_customers,
                COALESCE(SUM(subtotal), 0) as revenue_products,
                COALESCE(SUM(shipping), 0) as revenue_shipping,
                COALESCE(SUM(total), 0) as total_revenue,
                COALESCE(SUM(discount), 0) as total_discount
            ")
            ->groupByRaw("LOWER(coupon->>'code')")
            ->get();

        $analytics = [];
        foreach ($results as $row) {
            $analytics[strtolower($row->coupon_code)] = [
                'number_of_orders' => (int) $row->number_of_orders,
                'unique_customers' => (int) $row->unique_customers,
                'revenue_products' => (float) $row->revenue_products,
                'revenue_shipping' => (float) $row->revenue_shipping,
                'total_revenue' => (float) $row->total_revenue,
                'total_discount' => (float) $row->total_discount,
            ];
        }

        return $analytics;
    }

    /**
     * Get store average ticket for estimation.
     */
    private function getStoreAverageTicket(Store $store, array $dates = []): float
    {
        $cacheKey = "store_avg_ticket_{$store->id}_".md5(json_encode($dates));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($store, $dates) {
            $query = DB::table('synced_orders')
                ->where('store_id', $store->id)
                ->where('payment_status', 'paid')
                ->whereNull('deleted_at')
                ->where('total', '>', 0);

            // Apply date filter
            $this->applyDateFilter($query, $dates['start'] ?? null, $dates['end'] ?? null);

            $result = $query->selectRaw('AVG(total) as avg_ticket')->first();

            return round((float) ($result->avg_ticket ?? 0), 2);
        });
    }

    /**
     * Format coupon with order-based analytics.
     */
    private function formatCouponWithOrderAnalytics(object $coupon, array $analytics): array
    {
        $numberOfOrders = $analytics['number_of_orders'];
        $totalRevenue = $analytics['total_revenue'];
        $totalDiscount = $analytics['total_discount'];

        return [
            'id' => $coupon->id,
            'external_id' => $coupon->external_id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => (float) $coupon->value,
            'valid' => (bool) $coupon->valid,
            'used' => (int) $coupon->used,
            'max_uses' => $coupon->max_uses ? (int) $coupon->max_uses : null,
            'start_date' => $coupon->start_date,
            'end_date' => $coupon->end_date,
            'min_price' => $coupon->min_price ? (float) $coupon->min_price : null,
            'categories' => json_decode($coupon->categories ?? '[]', true),
            'created_at' => $coupon->created_at,
            'is_active' => $this->isCouponActive($coupon),
            'analytics' => [
                'revenue_products' => $analytics['revenue_products'],
                'revenue_shipping' => $analytics['revenue_shipping'],
                'total_revenue' => $totalRevenue,
                'total_discount' => $totalDiscount,
                'number_of_orders' => $numberOfOrders,
                'discount_percentage' => $totalRevenue > 0
                    ? round(($totalDiscount / $totalRevenue) * 100, 2)
                    : 0,
                'average_discount_per_order' => $numberOfOrders > 0
                    ? round($totalDiscount / $numberOfOrders, 2)
                    : 0,
                'average_ticket' => $numberOfOrders > 0
                    ? round($totalRevenue / $numberOfOrders, 2)
                    : 0,
                'total_orders_media' => $numberOfOrders > 0
                    ? round($totalRevenue / $numberOfOrders, 2)
                    : 0,
                'new_customers' => $analytics['unique_customers'] ?? 0,
                'repurchase_rate' => 0, // Would need more complex query
                'data_source' => 'orders',
            ],
        ];
    }

    /**
     * Format coupon with estimated analytics based on usage count.
     */
    private function formatCouponWithEstimatedAnalytics(object $coupon, float $storeAvgTicket): array
    {
        $used = (int) $coupon->used;
        $value = (float) $coupon->value;
        $type = $coupon->type;

        // Estimate discount per use
        $estimatedDiscountPerUse = match ($type) {
            'percentage' => $storeAvgTicket * ($value / 100),
            'absolute' => $value,
            'shipping' => 15.0, // Average shipping cost estimate
            default => $value,
        };

        $estimatedTotalDiscount = $used * $estimatedDiscountPerUse;
        $estimatedRevenue = $used * $storeAvgTicket;

        return [
            'id' => $coupon->id,
            'external_id' => $coupon->external_id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => $value,
            'valid' => (bool) $coupon->valid,
            'used' => $used,
            'max_uses' => $coupon->max_uses ? (int) $coupon->max_uses : null,
            'start_date' => $coupon->start_date,
            'end_date' => $coupon->end_date,
            'min_price' => $coupon->min_price ? (float) $coupon->min_price : null,
            'categories' => json_decode($coupon->categories ?? '[]', true),
            'created_at' => $coupon->created_at,
            'is_active' => $this->isCouponActive($coupon),
            'analytics' => [
                'revenue_products' => round($estimatedRevenue * 0.9, 2), // 90% products
                'revenue_shipping' => round($estimatedRevenue * 0.1, 2), // 10% shipping
                'total_revenue' => round($estimatedRevenue, 2),
                'total_discount' => round($estimatedTotalDiscount, 2),
                'number_of_orders' => $used,
                'discount_percentage' => $estimatedRevenue > 0
                    ? round(($estimatedTotalDiscount / $estimatedRevenue) * 100, 2)
                    : 0,
                'average_discount_per_order' => round($estimatedDiscountPerUse, 2),
                'average_ticket' => round($storeAvgTicket, 2),
                'total_orders_media' => round($storeAvgTicket, 2),
                'new_customers' => 0,
                'repurchase_rate' => 0,
                'data_source' => 'estimated',
            ],
        ];
    }

    /**
     * Check if coupon is currently active.
     */
    private function isCouponActive(object $coupon): bool
    {
        if (! $coupon->valid) {
            return false;
        }

        $now = now();

        if ($coupon->start_date && $now->lt($coupon->start_date)) {
            return false;
        }

        if ($coupon->end_date && $now->gt($coupon->end_date)) {
            return false;
        }

        if ($coupon->max_uses && $coupon->used >= $coupon->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Calculate page totals from coupons.
     */
    private function calculatePageTotals(object $coupons): array
    {
        $totals = [
            'revenue_products' => 0,
            'revenue_shipping' => 0,
            'total_revenue' => 0,
            'total_discount' => 0,
            'number_of_orders' => 0,
            'new_customers' => 0,
        ];

        foreach ($coupons as $coupon) {
            $analytics = $coupon['analytics'] ?? [];
            $totals['revenue_products'] += $analytics['revenue_products'] ?? 0;
            $totals['revenue_shipping'] += $analytics['revenue_shipping'] ?? 0;
            $totals['total_revenue'] += $analytics['total_revenue'] ?? 0;
            $totals['total_discount'] += $analytics['total_discount'] ?? 0;
            $totals['number_of_orders'] += $analytics['number_of_orders'] ?? 0;
            $totals['new_customers'] += $analytics['new_customers'] ?? 0;
        }

        return $totals;
    }

    /**
     * Get empty totals structure.
     */
    private function getEmptyTotals(): array
    {
        return [
            'revenue_products' => 0,
            'revenue_shipping' => 0,
            'total_revenue' => 0,
            'total_discount' => 0,
            'number_of_orders' => 0,
            'new_customers' => 0,
        ];
    }

    /**
     * Get filter options for coupons.
     */
    public function getFilterOptions(Store $store): array
    {
        $cacheKey = "discount_filters_{$store->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($store) {
            $types = DB::table('synced_coupons')
                ->where('store_id', $store->id)
                ->whereNull('deleted_at')
                ->select('type')
                ->distinct()
                ->pluck('type')
                ->toArray();

            return [
                'types' => $types,
                'statuses' => ['active', 'expired', 'invalid'],
            ];
        });
    }

    /**
     * Clear cache for a store.
     */
    public function clearCache(Store $store): void
    {
        Cache::forget("discount_stats_{$store->id}");
        Cache::forget("discount_filters_{$store->id}");
        Cache::forget("store_avg_ticket_{$store->id}");
    }

    /**
     * Get bulk data (stats + coupons + filters) in a single call.
     */
    public function getBulkData(Store $store, array $params = []): array
    {
        return [
            'stats' => $this->getGeneralStats($store, $params),
            'coupons' => $this->getCouponsWithAnalytics($store, $params),
            'filters' => $this->getFilterOptions($store),
        ];
    }
}
