<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Analysis;
use App\Models\Store;
use App\Models\Suggestion;
use App\Models\SyncedCoupon;
use App\Models\SyncedOrder;
use App\Models\SyncedProduct;
use App\Services\AI\RAG\KnowledgeBaseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatContextBuilder
{
    public function __construct(
        private DashboardService $dashboardService,
        private DiscountAnalyticsService $discountAnalyticsService,
        private KnowledgeBaseService $knowledgeBaseService,
        private ProductAnalyticsService $productAnalyticsService,
    ) {}

    /**
     * Build enriched context data from AI-extracted intents.
     *
     * @param  array  $queries  Structured query intents from AI extraction
     *                          e.g. [['type' => 'products_by_coupon', 'params' => ['codes' => ['PROMO10']]]]
     */
    public function build(Store $store, array $queries, int $days, ?string $userMessage = null): array
    {
        $data = $this->buildBaseData($store, $days);

        foreach ($queries as $query) {
            $type = $query['type'] ?? null;
            $params = $query['params'] ?? [];

            try {
                match ($type) {
                    // Produtos
                    'top_products' => $data['top_products'] = $this->fetchTopProducts($store, $days),
                    'products_catalog' => $data['products_catalog'] = $this->fetchProductsCatalog($store),
                    'product_search' => $data['product_search'] = $this->fetchProductSearch(
                        $store, $params['search'] ?? ''
                    ),
                    'products_by_category' => $data['products_by_category'] = $this->fetchProductsByCategory(
                        $store, $params['category'] ?? ''
                    ),
                    'products_by_coupon' => $data['products_by_coupon'] = $this->fetchProductsByCoupon(
                        $store, $params['codes'] ?? [], $days
                    ),
                    'revenue_by_product' => $data['product_revenue'] = $this->fetchRevenueByProduct(
                        $store, $params['product_name'] ?? '', $days
                    ),
                    'new_products' => $data['new_products'] = $this->fetchNewProducts($store),
                    'discounted_products' => $data['discounted_products'] = $this->fetchDiscountedProducts($store),
                    'product_margins' => $data['product_margins'] = $this->fetchProductMargins($store),
                    'product_abc' => $data['product_abc'] = $this->fetchProductABC($store, $days),
                    'price_analysis' => $data['price_analysis'] = $this->fetchPriceAnalysis($store),
                    // Estoque
                    'stock_status' => $data['stock'] = $this->fetchStockData($store),
                    'excess_stock' => $data['excess_stock'] = $this->fetchExcessStock($store, $days),
                    'slow_moving_products' => $data['slow_moving'] = $this->fetchSlowMovingProducts($store, $days),
                    'stock_summary' => $data['stock_summary'] = $this->fetchStockSummary($store, $days),
                    // Pedidos
                    'order_status' => $data['order_status'] = $this->fetchOrderStatusBreakdown($store, $days),
                    'recent_orders' => $data['recent_orders'] = $this->fetchRecentOrders($store),
                    'order_search' => $data['order_search'] = $this->fetchOrderSearch(
                        $store, $params['order_number'] ?? ''
                    ),
                    'high_value_orders' => $data['high_value_orders'] = $this->fetchHighValueOrders($store, $days),
                    'cancelled_orders' => $data['cancelled_orders'] = $this->fetchCancelledOrders($store, $days),
                    'payment_methods' => $data['payment_methods'] = $this->fetchPaymentMethods($store, $days),
                    'orders_by_region' => $data['orders_by_region'] = $this->fetchOrdersByRegion($store, $days),
                    'shipping_analysis' => $data['shipping_analysis'] = $this->fetchShippingAnalysis($store, $days),
                    'sales_by_weekday' => $data['sales_by_weekday'] = $this->fetchSalesByWeekday($store, $days),
                    'pending_orders' => $data['pending_orders'] = $this->fetchPendingOrders($store, $days),
                    'best_selling_days' => $data['best_selling_days'] = $this->fetchBestSellingDays($store, $days),
                    'sales_by_hour' => $data['sales_by_hour'] = $this->fetchSalesByHour($store, $days),
                    'discount_impact' => $data['discount_impact'] = $this->fetchDiscountImpact($store, $days),
                    'order_items_analysis' => $data['order_items_analysis'] = $this->fetchOrderItemsAnalysis($store, $days),
                    // Clientes
                    'top_customers' => $data['top_customers'] = $this->fetchTopCustomers($store, $days),
                    'repeat_customers' => $data['repeat_customers'] = $this->fetchRepeatCustomers($store, $days),
                    'customer_orders' => $data['customer_orders'] = $this->fetchCustomerOrders(
                        $store, $params['name_or_email'] ?? '', $days
                    ),
                    'customer_details' => $data['customer_details'] = $this->fetchCustomerDetails(
                        $store, $params['name_or_email'] ?? ''
                    ),
                    'customer_segments' => $data['customer_segments'] = $this->fetchCustomerSegments($store, $days),
                    'new_vs_returning' => $data['new_vs_returning'] = $this->fetchNewVsReturning($store, $days),
                    // Cupons
                    'coupon_stats' => $data['coupons'] = $this->fetchCouponsData($store, $days),
                    'coupon_details' => $data['coupon_details'] = $this->fetchCouponDetails(
                        $store, $params['codes'] ?? [], $days
                    ),
                    'coupon_ranking' => $data['coupon_ranking'] = $this->fetchCouponRanking($store, $days),
                    // KPIs e Comparações
                    'store_overview' => $data['store_overview'] = $this->fetchStoreOverview($store, $days),
                    'period_comparison' => $data['period_comparison'] = $this->fetchPeriodComparison($store, $days),
                    'revenue_by_category' => $data['revenue_by_category'] = $this->fetchRevenueByCategory($store, $days),
                    // Análise AI
                    'analysis_summary' => $data['analysis_summary'] = $this->fetchAnalysisSummary($store),
                    'active_suggestions' => $data['active_suggestions'] = $this->fetchActiveSuggestions($store),
                    'knowledge_base' => $data['knowledge_base'] = $this->fetchKnowledgeBase($store, $userMessage),
                    'cross_domain_analysis' => null,
                    default => null,
                };
            } catch (\Exception $e) {
                Log::warning("ChatContextBuilder: Failed to fetch {$type}", [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $data;
    }

    private function buildBaseData(Store $store, int $days): array
    {
        $endDate = Carbon::now();

        // For "all time" requests (large days value), use the actual first order date
        if ($days >= 3650) {
            $firstOrderDate = SyncedOrder::where('store_id', $store->id)
                ->whereNull('deleted_at')
                ->min('external_created_at');

            $startDate = $firstOrderDate
                ? Carbon::parse($firstOrderDate)->startOfDay()
                : Carbon::now()->subDays(15);

            $days = (int) $startDate->diffInDays($endDate);
        } else {
            $startDate = Carbon::now()->subDays($days);
        }

        try {
            $orders = SyncedOrder::where('store_id', $store->id)
                ->whereBetween('external_created_at', [$startDate, $endDate])
                ->where('payment_status', PaymentStatus::Paid)
                ->get();

            $dailyStats = [];
            foreach ($orders->groupBy(fn ($order) => $order->external_created_at->format('Y-m-d')) as $date => $dayOrders) {
                $revenue = $dayOrders->sum('total');
                $count = $dayOrders->count();
                $avgTicket = $count > 0 ? $revenue / $count : 0;
                $dailyStats[$date] = [
                    'd' => Carbon::parse($date)->format('d/m/Y'),
                    'r' => 'R$ '.number_format($revenue, 2, ',', '.'),
                    'p' => $count,
                    't' => 'R$ '.number_format($avgTicket, 2, ',', '.'),
                ];
            }

            krsort($dailyStats);

            $totalRevenue = $orders->sum('total');
            $totalOrders = $orders->count();
            $averageTicket = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

            return [
                'period' => [
                    'start' => $startDate->format('d/m/Y'),
                    'end' => $endDate->format('d/m/Y'),
                    'days' => $days,
                ],
                'summary' => [
                    'total_revenue' => $totalRevenue,
                    'total_revenue_formatted' => 'R$ '.number_format($totalRevenue, 2, ',', '.'),
                    'total_orders' => $totalOrders,
                    'average_ticket' => $averageTicket,
                    'average_ticket_formatted' => 'R$ '.number_format($averageTicket, 2, ',', '.'),
                ],
                'daily_stats' => array_values($dailyStats),
            ];
        } catch (\Exception $e) {
            Log::warning('ChatContextBuilder: Error building base data', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'period' => [
                    'start' => $startDate->format('d/m/Y'),
                    'end' => $endDate->format('d/m/Y'),
                    'days' => $days,
                ],
                'summary' => [
                    'total_revenue' => 0,
                    'total_revenue_formatted' => 'R$ 0,00',
                    'total_orders' => 0,
                    'average_ticket' => 0,
                    'average_ticket_formatted' => 'R$ 0,00',
                ],
                'daily_stats' => [],
            ];
        }
    }

    private function fetchTopProducts(Store $store, int $days): array
    {
        $filters = [
            'period' => 'custom',
            'start_date' => now()->subDays($days)->toDateString(),
            'end_date' => now()->toDateString(),
        ];

        $results = $this->dashboardService->getTopProducts($store, $filters);

        return array_map(fn ($p) => [
            'n' => $p['name'],
            'q' => $p['quantity_sold'],
            'r' => 'R$ '.number_format($p['revenue'], 2, ',', '.'),
        ], array_slice($results, 0, 25));
    }

    private function fetchProductsCatalog(Store $store): array
    {
        return SyncedProduct::where('store_id', $store->id)
            ->excludeGifts()
            ->active()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->limit(50)
            ->get(['name', 'price', 'stock_quantity'])
            ->map(fn ($p) => [
                'n' => $p->name,
                'p' => 'R$ '.number_format((float) $p->price, 2, ',', '.'),
                'e' => $p->stock_quantity ?? 0,
            ])
            ->toArray();
    }

    /**
     * Fetch products sold with specific coupon codes.
     * Cross-domain query: correlates order items with coupon usage.
     */
    private function fetchProductsByCoupon(Store $store, array $codes, int $days): array
    {
        if (empty($codes)) {
            return [];
        }

        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        // Normalize codes to lowercase for case-insensitive matching
        $lowerCodes = array_map('mb_strtolower', $codes);
        $placeholders = implode(',', array_fill(0, count($lowerCodes), '?'));

        $results = DB::select("
            WITH coupon_orders AS (
                SELECT id, items, coupon
                FROM synced_orders
                WHERE store_id = ?
                    AND payment_status = ?
                    AND external_created_at BETWEEN ? AND ?
                    AND deleted_at IS NULL
                    AND items IS NOT NULL
                    AND coupon IS NOT NULL
                    AND LOWER(coupon->>'code') IN ({$placeholders})
            ),
            order_items AS (
                SELECT
                    jsonb_array_elements(items::jsonb) AS item,
                    coupon->>'code' AS coupon_code
                FROM coupon_orders
            )
            SELECT
                COALESCE(item->>'product_name', item->>'name', 'Produto') AS name,
                coupon_code,
                SUM(COALESCE((item->>'quantity')::integer, 1)) AS qty,
                SUM(COALESCE(
                    (item->>'total')::numeric,
                    COALESCE((item->>'unit_price')::numeric, 0) * COALESCE((item->>'quantity')::numeric, 1)
                )) AS revenue
            FROM order_items
            GROUP BY name, coupon_code
            ORDER BY qty DESC
            LIMIT 20
        ", array_merge(
            [$store->id, PaymentStatus::Paid->value, $startDate->toDateTimeString(), $endDate->toDateTimeString()],
            $lowerCodes
        ));

        return array_map(fn ($r) => [
            'n' => $r->name,
            'cupom' => $r->coupon_code,
            'q' => (int) $r->qty,
            'r' => 'R$ '.number_format((float) $r->revenue, 2, ',', '.'),
        ], $results);
    }

    /**
     * Fetch details and usage of specific coupon codes.
     */
    private function fetchCouponDetails(Store $store, array $codes, int $days): array
    {
        if (empty($codes)) {
            return $this->fetchCouponsData($store, $days);
        }

        $lowerCodes = array_map('mb_strtolower', $codes);

        $placeholders = implode(',', array_fill(0, count($lowerCodes), '?'));

        $coupons = SyncedCoupon::where('store_id', $store->id)
            ->whereNull('deleted_at')
            ->whereRaw("LOWER(code) IN ({$placeholders})", $lowerCodes)
            ->get(['code', 'type', 'value', 'used', 'max_uses', 'start_date', 'end_date', 'valid'])
            ->map(fn ($c) => [
                'code' => $c->code,
                'type' => $c->type,
                'val' => (float) $c->value,
                'used' => $c->used,
                'max' => $c->max_uses,
                'active' => $c->valid,
                'start' => $c->start_date ? Carbon::parse($c->start_date)->format('d/m/Y') : null,
                'exp' => $c->end_date ? Carbon::parse($c->end_date)->format('d/m/Y') : null,
            ])
            ->toArray();

        // Also get order stats for these coupons
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();
        $codePlaceholders = implode(',', array_fill(0, count($lowerCodes), '?'));

        $orderStats = DB::select("
            SELECT
                LOWER(coupon->>'code') AS coupon_code,
                COUNT(*) AS order_count,
                SUM(total) AS total_revenue,
                SUM(discount) AS total_discount,
                AVG(total) AS avg_ticket
            FROM synced_orders
            WHERE store_id = ?
                AND payment_status = ?
                AND external_created_at BETWEEN ? AND ?
                AND deleted_at IS NULL
                AND LOWER(coupon->>'code') IN ({$codePlaceholders})
            GROUP BY LOWER(coupon->>'code')
        ", array_merge(
            [$store->id, PaymentStatus::Paid->value, $startDate->toDateTimeString(), $endDate->toDateTimeString()],
            $lowerCodes
        ));

        $stats = [];
        foreach ($orderStats as $s) {
            $stats[$s->coupon_code] = [
                'orders' => (int) $s->order_count,
                'revenue' => 'R$ '.number_format((float) $s->total_revenue, 2, ',', '.'),
                'discount' => 'R$ '.number_format((float) $s->total_discount, 2, ',', '.'),
                'avg_ticket' => 'R$ '.number_format((float) $s->avg_ticket, 2, ',', '.'),
            ];
        }

        return [
            'coupons' => $coupons,
            'usage_stats' => $stats,
        ];
    }

    /**
     * Fetch order history for a specific customer by name or email.
     */
    private function fetchCustomerOrders(Store $store, string $nameOrEmail, int $days): array
    {
        if (empty($nameOrEmail)) {
            return [];
        }

        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();
        $search = '%'.mb_strtolower($nameOrEmail).'%';

        $orders = SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->where(function ($q) use ($search) {
                $q->whereRaw("LOWER(customer_name) LIKE ? ESCAPE '\\'", [$search])
                    ->orWhereRaw("LOWER(customer_email) LIKE ? ESCAPE '\\'", [$search]);
            })
            ->orderByDesc('external_created_at')
            ->limit(15)
            ->get(['customer_name', 'customer_email', 'total', 'discount', 'payment_status', 'external_created_at', 'items'])
            ->map(function ($o) {
                $itemCount = is_array($o->items) ? count($o->items) : 0;

                return [
                    'client' => $o->customer_name,
                    'email' => $o->customer_email,
                    'total' => 'R$ '.number_format((float) $o->total, 2, ',', '.'),
                    'status' => $o->payment_status,
                    'date' => $o->external_created_at->format('d/m/Y'),
                    'items' => $itemCount,
                ];
            })
            ->toArray();

        return $orders;
    }

    /**
     * Fetch revenue details for a specific product by name.
     */
    private function fetchRevenueByProduct(Store $store, string $productName, int $days): array
    {
        if (empty($productName)) {
            return [];
        }

        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();
        $search = mb_strtolower($productName);

        $results = DB::select("
            WITH order_items AS (
                SELECT
                    jsonb_array_elements(items::jsonb) AS item,
                    external_created_at,
                    coupon
                FROM synced_orders
                WHERE store_id = ?
                    AND payment_status = ?
                    AND external_created_at BETWEEN ? AND ?
                    AND deleted_at IS NULL
                    AND items IS NOT NULL
            )
            SELECT
                COALESCE(item->>'product_name', item->>'name', 'Produto') AS name,
                SUM(COALESCE((item->>'quantity')::integer, 1)) AS total_qty,
                SUM(COALESCE(
                    (item->>'total')::numeric,
                    COALESCE((item->>'unit_price')::numeric, 0) * COALESCE((item->>'quantity')::numeric, 1)
                )) AS total_revenue,
                COUNT(*) AS order_count
            FROM order_items
            WHERE LOWER(COALESCE(item->>'product_name', item->>'name', '')) LIKE ? ESCAPE '\'
            GROUP BY name
            ORDER BY total_revenue DESC
            LIMIT 5
        ", [
            $store->id,
            PaymentStatus::Paid->value,
            $startDate->toDateTimeString(),
            $endDate->toDateTimeString(),
            '%'.$search.'%',
        ]);

        return array_map(fn ($r) => [
            'n' => $r->name,
            'q' => (int) $r->total_qty,
            'r' => 'R$ '.number_format((float) $r->total_revenue, 2, ',', '.'),
            'orders' => (int) $r->order_count,
        ], $results);
    }

    /**
     * Fetch products with excess stock, cross-referenced with sales velocity.
     */
    private function fetchExcessStock(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        // Get products with highest stock, plus their sales in the period
        $results = DB::select("
            WITH product_sales AS (
                SELECT
                    COALESCE(item->>'product_name', item->>'name') AS product_name,
                    SUM(COALESCE((item->>'quantity')::integer, 1)) AS qty_sold
                FROM synced_orders,
                    jsonb_array_elements(items::jsonb) AS item
                WHERE store_id = ?
                    AND payment_status = ?
                    AND external_created_at BETWEEN ? AND ?
                    AND deleted_at IS NULL
                    AND items IS NOT NULL
                GROUP BY product_name
            )
            SELECT
                p.name,
                p.stock_quantity,
                p.price,
                COALESCE(ps.qty_sold, 0) AS qty_sold
            FROM synced_products p
            LEFT JOIN product_sales ps ON LOWER(ps.product_name) = LOWER(p.name)
            WHERE p.store_id = ?
                AND p.is_active = true
                AND p.deleted_at IS NULL
                AND p.stock_quantity > 0
                AND (p.name NOT ILIKE '%gift%' AND p.name NOT ILIKE '%brinde%' AND p.name NOT ILIKE '%presente%')
            ORDER BY p.stock_quantity DESC
            LIMIT 20
        ", [
            $store->id, PaymentStatus::Paid->value,
            $startDate->toDateTimeString(), $endDate->toDateTimeString(),
            $store->id,
        ]);

        return array_map(fn ($r) => [
            'n' => $r->name,
            'e' => (int) $r->stock_quantity,
            'vendidos' => (int) $r->qty_sold,
            'p' => 'R$ '.number_format((float) $r->price, 2, ',', '.'),
        ], $results);
    }

    /**
     * Search products by name or SKU with full details.
     */
    private function fetchProductSearch(Store $store, string $search): array
    {
        if (empty($search)) {
            return [];
        }

        $searchLower = '%'.mb_strtolower($search).'%';

        return SyncedProduct::where('store_id', $store->id)
            ->whereNull('deleted_at')
            ->active()
            ->where(function ($q) use ($searchLower) {
                $q->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$searchLower])
                    ->orWhereRaw("LOWER(sku) LIKE ? ESCAPE '\\'", [$searchLower]);
            })
            ->orderBy('name')
            ->limit(15)
            ->get(['name', 'price', 'stock_quantity', 'sku', 'categories', 'compare_at_price', 'cost'])
            ->map(fn ($p) => [
                'n' => $p->name,
                'p' => 'R$ '.number_format((float) $p->price, 2, ',', '.'),
                'e' => $p->stock_quantity ?? 0,
                'sku' => $p->sku,
                'cat' => is_array($p->categories) ? implode(', ', array_slice($p->categories, 0, 3)) : null,
                'promo' => $p->compare_at_price && $p->compare_at_price > $p->price
                    ? 'R$ '.number_format((float) $p->compare_at_price, 2, ',', '.')
                    : null,
                'custo' => $p->cost ? 'R$ '.number_format((float) $p->cost, 2, ',', '.') : null,
            ])
            ->toArray();
    }

    /**
     * Fetch products with stock but no or very low sales in the period (slow movers / dead stock).
     */
    private function fetchSlowMovingProducts(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $results = DB::select("
            WITH product_sales AS (
                SELECT
                    COALESCE(item->>'product_name', item->>'name') AS product_name,
                    SUM(COALESCE((item->>'quantity')::integer, 1)) AS qty_sold
                FROM synced_orders,
                    jsonb_array_elements(items::jsonb) AS item
                WHERE store_id = ?
                    AND payment_status = ?
                    AND external_created_at BETWEEN ? AND ?
                    AND deleted_at IS NULL
                    AND items IS NOT NULL
                GROUP BY product_name
            )
            SELECT
                p.name,
                p.stock_quantity,
                p.price,
                COALESCE(ps.qty_sold, 0) AS qty_sold
            FROM synced_products p
            LEFT JOIN product_sales ps ON LOWER(ps.product_name) = LOWER(p.name)
            WHERE p.store_id = ?
                AND p.is_active = true
                AND p.deleted_at IS NULL
                AND p.stock_quantity > 0
                AND (p.name NOT ILIKE '%gift%' AND p.name NOT ILIKE '%brinde%' AND p.name NOT ILIKE '%presente%')
                AND COALESCE(ps.qty_sold, 0) = 0
            ORDER BY p.stock_quantity DESC
            LIMIT 20
        ", [
            $store->id, PaymentStatus::Paid->value,
            $startDate->toDateTimeString(), $endDate->toDateTimeString(),
            $store->id,
        ]);

        return [
            'count' => count($results),
            'products' => array_map(fn ($r) => [
                'n' => $r->name,
                'e' => (int) $r->stock_quantity,
                'p' => 'R$ '.number_format((float) $r->price, 2, ',', '.'),
            ], $results),
        ];
    }

    /**
     * Fetch products filtered by category name.
     */
    private function fetchProductsByCategory(Store $store, string $category): array
    {
        if (empty($category)) {
            return [];
        }

        $searchLower = mb_strtolower($category);

        // categories is a JSON array column, search inside it
        return SyncedProduct::where('store_id', $store->id)
            ->whereNull('deleted_at')
            ->active()
            ->excludeGifts()
            ->whereRaw("EXISTS (SELECT 1 FROM jsonb_array_elements_text(categories::jsonb) AS cat WHERE LOWER(cat) LIKE ? ESCAPE '\\')", ['%'.$searchLower.'%'])
            ->orderBy('name')
            ->limit(30)
            ->get(['name', 'price', 'stock_quantity', 'categories'])
            ->map(fn ($p) => [
                'n' => $p->name,
                'p' => 'R$ '.number_format((float) $p->price, 2, ',', '.'),
                'e' => $p->stock_quantity ?? 0,
                'cat' => is_array($p->categories) ? implode(', ', array_slice($p->categories, 0, 3)) : null,
            ])
            ->toArray();
    }

    /**
     * Fetch products currently on sale (compare_at_price > price).
     */
    private function fetchDiscountedProducts(Store $store): array
    {
        return SyncedProduct::where('store_id', $store->id)
            ->whereNull('deleted_at')
            ->active()
            ->excludeGifts()
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->orderByRaw('(compare_at_price - price) DESC')
            ->limit(20)
            ->get(['name', 'price', 'compare_at_price', 'stock_quantity'])
            ->map(function ($p) {
                $discount = $p->compare_at_price > 0
                    ? round((($p->compare_at_price - $p->price) / $p->compare_at_price) * 100, 1)
                    : 0;

                return [
                    'n' => $p->name,
                    'p' => 'R$ '.number_format((float) $p->price, 2, ',', '.'),
                    'de' => 'R$ '.number_format((float) $p->compare_at_price, 2, ',', '.'),
                    'desc' => $discount.'%',
                    'e' => $p->stock_quantity ?? 0,
                ];
            })
            ->toArray();
    }

    /**
     * Fetch revenue and order count breakdown by payment method.
     */
    private function fetchPaymentMethods(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        return SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->where('payment_status', PaymentStatus::Paid)
            ->whereNull('deleted_at')
            ->whereNotNull('payment_method')
            ->selectRaw('payment_method, COUNT(*) as orders, SUM(total) as revenue, AVG(total) as avg_ticket')
            ->groupBy('payment_method')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($r) => [
                'metodo' => $r->payment_method,
                'pedidos' => (int) $r->orders,
                'receita' => 'R$ '.number_format((float) $r->revenue, 2, ',', '.'),
                'ticket' => 'R$ '.number_format((float) $r->avg_ticket, 2, ',', '.'),
            ])
            ->toArray();
    }

    /**
     * Fetch sales distribution by region (state/city).
     */
    private function fetchOrdersByRegion(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $results = DB::select("
            SELECT
                COALESCE(shipping_address->>'province', 'Não informado') AS state,
                COALESCE(shipping_address->>'city', 'Não informado') AS city,
                COUNT(*) AS orders,
                SUM(total) AS revenue
            FROM synced_orders
            WHERE store_id = ?
                AND payment_status = ?
                AND external_created_at BETWEEN ? AND ?
                AND deleted_at IS NULL
                AND shipping_address IS NOT NULL
            GROUP BY state, city
            ORDER BY revenue DESC
            LIMIT 20
        ", [
            $store->id, PaymentStatus::Paid->value,
            $startDate->toDateTimeString(), $endDate->toDateTimeString(),
        ]);

        return array_map(fn ($r) => [
            'estado' => $r->state,
            'cidade' => $r->city,
            'pedidos' => (int) $r->orders,
            'receita' => 'R$ '.number_format((float) $r->revenue, 2, ',', '.'),
        ], $results);
    }

    /**
     * Fetch repeat customers (bought more than once in the period).
     */
    private function fetchRepeatCustomers(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $results = SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->where('payment_status', PaymentStatus::Paid)
            ->whereNull('deleted_at')
            ->whereNotNull('customer_email')
            ->selectRaw('customer_name, customer_email, COUNT(*) as orders, SUM(total) as total_spent')
            ->groupBy('customer_name', 'customer_email')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('orders')
            ->limit(15)
            ->get();

        $totalCustomers = SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->where('payment_status', PaymentStatus::Paid)
            ->whereNull('deleted_at')
            ->whereNotNull('customer_email')
            ->distinct('customer_email')
            ->count('customer_email');

        $repeatCount = $results->count();

        return [
            'total_customers' => $totalCustomers,
            'repeat_count' => $repeatCount,
            'repeat_rate' => $totalCustomers > 0 ? round(($repeatCount / $totalCustomers) * 100, 1) : 0,
            'customers' => $results->map(fn ($r) => [
                'n' => $r->customer_name,
                'email' => $r->customer_email,
                'pedidos' => (int) $r->orders,
                'total' => 'R$ '.number_format((float) $r->total_spent, 2, ',', '.'),
            ])->toArray(),
        ];
    }

    private function fetchStockData(Store $store): array
    {
        $lowStock = $this->dashboardService->getLowStockProducts($store, 10);

        $outOfStockCount = SyncedProduct::where('store_id', $store->id)
            ->excludeGifts()
            ->active()
            ->whereNull('deleted_at')
            ->outOfStock()
            ->count();

        return [
            'low_stock' => array_map(fn ($p) => [
                'n' => $p['name'],
                'e' => $p['stock_quantity'],
            ], array_slice($lowStock, 0, 10)),
            'out_of_stock_count' => $outOfStockCount,
        ];
    }

    private function fetchCouponsData(Store $store, int $days): array
    {
        $stats = $this->discountAnalyticsService->getGeneralStats($store, [
            'start_date' => now()->subDays($days)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $activeCoupons = SyncedCoupon::where('store_id', $store->id)
            ->active()
            ->whereNull('deleted_at')
            ->orderByDesc('used')
            ->limit(10)
            ->get(['code', 'type', 'value', 'used', 'max_uses', 'end_date'])
            ->map(fn ($c) => [
                'code' => $c->code,
                'type' => $c->type,
                'val' => (float) $c->value,
                'used' => $c->used,
                'max' => $c->max_uses,
                'exp' => $c->end_date ? Carbon::parse($c->end_date)->format('d/m/Y') : null,
            ])
            ->toArray();

        return [
            'summary' => [
                'active' => $stats['active_coupons'],
                'total' => $stats['total_coupons'],
                'orders_with_coupon' => $stats['orders_with_coupon'],
                'discount_pct' => $stats['discount_percentage'],
            ],
            'active_coupons' => $activeCoupons,
        ];
    }

    private function fetchOrderStatusBreakdown(Store $store, int $days): array
    {
        $filters = [
            'period' => 'custom',
            'start_date' => now()->subDays($days)->toDateString(),
            'end_date' => now()->toDateString(),
        ];

        return $this->dashboardService->getOrdersStatusChart($store, $filters);
    }

    private function fetchTopCustomers(Store $store, int $days): array
    {
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays($days);

        $orders = SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->where('payment_status', PaymentStatus::Paid)
            ->get();

        return $orders->groupBy('customer_email')
            ->map(function ($customerOrders) {
                return [
                    'n' => $customerOrders->first()->customer_name,
                    'p' => $customerOrders->count(),
                    't' => 'R$ '.number_format($customerOrders->sum('total'), 2, ',', '.'),
                ];
            })
            ->sortByDesc(fn ($c) => $c['p'])
            ->take(7)
            ->values()
            ->toArray();
    }

    /**
     * Fetch latest completed analysis summary for the store.
     */
    private function fetchAnalysisSummary(Store $store): array
    {
        $analysis = Analysis::where('store_id', $store->id)
            ->completed()
            ->latest()
            ->first();

        if (! $analysis) {
            return ['available' => false];
        }

        $summary = $analysis->summary ?? [];
        $premiumSummary = $summary['premium_summary'] ?? null;

        $result = [
            'available' => true,
            'date' => $analysis->completed_at?->format('d/m/Y'),
            'type' => $analysis->analysis_type?->value ?? 'general',
            'health_score' => $summary['health_score'] ?? null,
            'health_status' => $summary['health_status'] ?? null,
            'main_insight' => $summary['main_insight'] ?? null,
            'suggestions_count' => $analysis->persistentSuggestions()->count(),
        ];

        if ($premiumSummary) {
            $executiveSummary = $premiumSummary['executive_summary'] ?? '';
            if (is_array($executiveSummary)) {
                $executiveSummary = implode(' ', array_filter(array_map(
                    fn ($v) => is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE),
                    $executiveSummary
                )));
            }

            $result['premium'] = [
                'executive_summary' => mb_substr((string) $executiveSummary, 0, 300),
                'growth_score' => $premiumSummary['growth_score'] ?? null,
                'strategic_risks' => array_slice($premiumSummary['strategic_risks'] ?? [], 0, 3),
            ];
        }

        return $result;
    }

    /**
     * Fetch active/pending suggestions for the store.
     */
    private function fetchActiveSuggestions(Store $store): array
    {
        return Suggestion::where('store_id', $store->id)
            ->whereIn('status', [
                Suggestion::STATUS_NEW,
                Suggestion::STATUS_ACCEPTED,
                Suggestion::STATUS_IN_PROGRESS,
            ])
            ->orderBy('priority')
            ->limit(10)
            ->get(['title', 'category', 'expected_impact', 'status', 'priority'])
            ->map(fn ($s) => [
                't' => mb_substr($s->title, 0, 80),
                'cat' => $s->category,
                'imp' => $s->expected_impact,
                'st' => $s->status,
            ])
            ->toArray();
    }

    /**
     * Fetch knowledge base strategies and benchmarks for the store's niche.
     */
    private function fetchKnowledgeBase(Store $store, ?string $message): array
    {
        $niche = $store->niche ?? 'general';
        $subcategory = $store->niche_subcategory;
        $results = [];

        try {
            $strategies = $this->knowledgeBaseService->searchStrategies($niche, $subcategory);
            $results['strategies'] = array_slice(
                array_map(fn ($s) => [
                    'title' => $s['title'],
                    'content' => mb_substr($s['content'], 0, 200),
                ], $strategies),
                0, 3
            );
        } catch (\Exception $e) {
            Log::warning('ChatContextBuilder: Strategy search failed', ['error' => $e->getMessage()]);
        }

        try {
            $benchmarks = $this->knowledgeBaseService->searchBenchmarks($niche, $subcategory);
            $results['benchmarks'] = array_slice(
                array_map(fn ($b) => [
                    'title' => $b['title'],
                    'content' => mb_substr($b['content'], 0, 200),
                ], $benchmarks),
                0, 2
            );
        } catch (\Exception $e) {
            Log::warning('ChatContextBuilder: Benchmark search failed', ['error' => $e->getMessage()]);
        }

        if ($message) {
            try {
                $semantic = $this->knowledgeBaseService->search(
                    $message,
                    'strategy',
                    $niche,
                    $subcategory,
                    3
                );
                $results['relevant'] = array_slice(
                    array_map(fn ($r) => [
                        'title' => $r['title'],
                        'content' => mb_substr($r['content'], 0, 200),
                    ], $semantic),
                    0, 3
                );
            } catch (\Exception $e) {
                Log::warning('ChatContextBuilder: Semantic search failed', ['error' => $e->getMessage()]);
            }
        }

        return $results;
    }

    /**
     * Fetch recently added products to the catalog.
     */
    private function fetchNewProducts(Store $store): array
    {
        return SyncedProduct::where('store_id', $store->id)
            ->whereNull('deleted_at')
            ->active()
            ->excludeGifts()
            ->orderByDesc('external_created_at')
            ->limit(15)
            ->get(['name', 'price', 'stock_quantity', 'categories', 'external_created_at'])
            ->map(fn ($p) => [
                'n' => $p->name,
                'p' => 'R$ '.number_format((float) $p->price, 2, ',', '.'),
                'e' => $p->stock_quantity ?? 0,
                'cat' => is_array($p->categories) ? implode(', ', array_slice($p->categories, 0, 3)) : null,
                'criado' => $p->external_created_at?->format('d/m/Y'),
            ])
            ->toArray();
    }

    /**
     * Fetch products with cost data for margin/profit analysis.
     */
    private function fetchProductMargins(Store $store): array
    {
        $products = SyncedProduct::where('store_id', $store->id)
            ->whereNull('deleted_at')
            ->active()
            ->excludeGifts()
            ->whereNotNull('cost')
            ->where('cost', '>', 0)
            ->orderByRaw('(price - cost) DESC')
            ->limit(20)
            ->get(['name', 'price', 'cost', 'stock_quantity']);

        if ($products->isEmpty()) {
            return ['available' => false, 'message' => 'Nenhum produto com dados de custo cadastrados'];
        }

        return [
            'available' => true,
            'products' => $products->map(function ($p) {
                $margin = $p->price > 0 ? round((($p->price - $p->cost) / $p->price) * 100, 1) : 0;

                return [
                    'n' => $p->name,
                    'p' => 'R$ '.number_format((float) $p->price, 2, ',', '.'),
                    'custo' => 'R$ '.number_format((float) $p->cost, 2, ',', '.'),
                    'margem' => $margin.'%',
                    'lucro' => 'R$ '.number_format((float) ($p->price - $p->cost), 2, ',', '.'),
                    'e' => $p->stock_quantity ?? 0,
                ];
            })->toArray(),
        ];
    }

    /**
     * Fetch most recent orders.
     */
    private function fetchRecentOrders(Store $store): array
    {
        return SyncedOrder::where('store_id', $store->id)
            ->whereNull('deleted_at')
            ->orderByDesc('external_created_at')
            ->limit(15)
            ->get(['order_number', 'customer_name', 'customer_email', 'total', 'payment_status', 'payment_method', 'external_created_at', 'items'])
            ->map(fn ($o) => [
                'numero' => $o->order_number,
                'cliente' => $o->customer_name,
                'email' => $o->customer_email,
                'total' => 'R$ '.number_format((float) $o->total, 2, ',', '.'),
                'status' => $o->payment_status instanceof \App\Enums\PaymentStatus ? $o->payment_status->value : $o->payment_status,
                'metodo' => $o->payment_method,
                'data' => $o->external_created_at?->format('d/m/Y H:i'),
                'itens' => is_array($o->items) ? count($o->items) : 0,
            ])
            ->toArray();
    }

    /**
     * Search for a specific order by order number.
     */
    private function fetchOrderSearch(Store $store, string $orderNumber): array
    {
        if (empty($orderNumber)) {
            return [];
        }

        $order = SyncedOrder::where('store_id', $store->id)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($orderNumber) {
                $q->where('order_number', $orderNumber)
                    ->orWhere('order_number', '#'.$orderNumber)
                    ->orWhere('external_id', $orderNumber);
            })
            ->first();

        if (! $order) {
            return ['found' => false];
        }

        $items = is_array($order->items) ? array_map(fn ($item) => [
            'nome' => $item['product_name'] ?? $item['name'] ?? 'Produto',
            'qtd' => $item['quantity'] ?? 1,
            'preco' => 'R$ '.number_format((float) ($item['unit_price'] ?? $item['price'] ?? 0), 2, ',', '.'),
        ], $order->items) : [];

        return [
            'found' => true,
            'numero' => $order->order_number,
            'cliente' => $order->customer_name,
            'email' => $order->customer_email,
            'telefone' => $order->customer_phone,
            'total' => 'R$ '.number_format((float) $order->total, 2, ',', '.'),
            'subtotal' => 'R$ '.number_format((float) $order->subtotal, 2, ',', '.'),
            'desconto' => 'R$ '.number_format((float) $order->discount, 2, ',', '.'),
            'frete' => 'R$ '.number_format((float) $order->shipping, 2, ',', '.'),
            'status' => $order->payment_status instanceof \App\Enums\PaymentStatus ? $order->payment_status->value : $order->payment_status,
            'status_pedido' => $order->status,
            'metodo' => $order->payment_method,
            'data' => $order->external_created_at?->format('d/m/Y H:i'),
            'itens' => $items,
            'cupom' => is_array($order->coupon) ? ($order->coupon['code'] ?? null) : null,
            'endereco' => is_array($order->shipping_address) ? [
                'cidade' => $order->shipping_address['city'] ?? null,
                'estado' => $order->shipping_address['province'] ?? null,
                'cep' => $order->shipping_address['postal_code'] ?? null,
            ] : null,
        ];
    }

    /**
     * Fetch highest value orders in the period.
     */
    private function fetchHighValueOrders(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        return SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->where('payment_status', PaymentStatus::Paid)
            ->whereNull('deleted_at')
            ->orderByDesc('total')
            ->limit(10)
            ->get(['order_number', 'customer_name', 'total', 'payment_method', 'external_created_at', 'items'])
            ->map(fn ($o) => [
                'numero' => $o->order_number,
                'cliente' => $o->customer_name,
                'total' => 'R$ '.number_format((float) $o->total, 2, ',', '.'),
                'itens' => is_array($o->items) ? count($o->items) : 0,
                'metodo' => $o->payment_method,
                'data' => $o->external_created_at?->format('d/m/Y'),
            ])
            ->toArray();
    }

    /**
     * Fetch cancelled and refunded orders.
     */
    private function fetchCancelledOrders(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $cancelled = SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->whereIn('payment_status', [
                PaymentStatus::Refunded,
                PaymentStatus::Voided,
                PaymentStatus::Failed,
            ])
            ->orderByDesc('external_created_at')
            ->limit(15)
            ->get(['order_number', 'customer_name', 'total', 'payment_status', 'external_created_at']);

        $totalCancelled = $cancelled->count();
        $lostRevenue = $cancelled->sum('total');

        return [
            'total_cancelled' => $totalCancelled,
            'lost_revenue' => 'R$ '.number_format((float) $lostRevenue, 2, ',', '.'),
            'orders' => $cancelled->map(fn ($o) => [
                'numero' => $o->order_number,
                'cliente' => $o->customer_name,
                'total' => 'R$ '.number_format((float) $o->total, 2, ',', '.'),
                'status' => $o->payment_status instanceof PaymentStatus ? $o->payment_status->value : $o->payment_status,
                'data' => $o->external_created_at?->format('d/m/Y'),
            ])->toArray(),
        ];
    }

    /**
     * Fetch shipping cost analysis.
     */
    private function fetchShippingAnalysis(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $orders = SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->where('payment_status', PaymentStatus::Paid)
            ->whereNull('deleted_at')
            ->get(['shipping', 'total']);

        if ($orders->isEmpty()) {
            return ['available' => false];
        }

        $totalOrders = $orders->count();
        $totalShipping = $orders->sum('shipping');
        $freeShipping = $orders->where('shipping', 0)->count();
        $paidShipping = $orders->where('shipping', '>', 0);
        $avgShipping = $paidShipping->count() > 0 ? $paidShipping->avg('shipping') : 0;

        // Shipping cost ranges
        $ranges = [
            'gratis' => $freeShipping,
            'ate_15' => $orders->whereBetween('shipping', [0.01, 15])->count(),
            '15_a_30' => $orders->whereBetween('shipping', [15.01, 30])->count(),
            '30_a_50' => $orders->whereBetween('shipping', [30.01, 50])->count(),
            'acima_50' => $orders->where('shipping', '>', 50)->count(),
        ];

        return [
            'available' => true,
            'avg_shipping' => 'R$ '.number_format((float) $avgShipping, 2, ',', '.'),
            'total_shipping' => 'R$ '.number_format((float) $totalShipping, 2, ',', '.'),
            'free_shipping_count' => $freeShipping,
            'free_shipping_pct' => round(($freeShipping / $totalOrders) * 100, 1).'%',
            'shipping_pct_of_revenue' => round(($totalShipping / max($orders->sum('total'), 1)) * 100, 1).'%',
            'ranges' => $ranges,
        ];
    }

    /**
     * Fetch sales distribution by day of the week.
     */
    private function fetchSalesByWeekday(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $results = DB::select('
            SELECT
                EXTRACT(DOW FROM external_created_at) AS dow,
                COUNT(*) AS orders,
                SUM(total) AS revenue,
                AVG(total) AS avg_ticket
            FROM synced_orders
            WHERE store_id = ?
                AND payment_status = ?
                AND external_created_at BETWEEN ? AND ?
                AND deleted_at IS NULL
            GROUP BY dow
            ORDER BY dow
        ', [
            $store->id, PaymentStatus::Paid->value,
            $startDate->toDateTimeString(), $endDate->toDateTimeString(),
        ]);

        $dayNames = [
            0 => 'Domingo', 1 => 'Segunda', 2 => 'Terça',
            3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado',
        ];

        return array_map(fn ($r) => [
            'dia' => $dayNames[(int) $r->dow] ?? 'Dia '.$r->dow,
            'pedidos' => (int) $r->orders,
            'receita' => 'R$ '.number_format((float) $r->revenue, 2, ',', '.'),
            'ticket' => 'R$ '.number_format((float) $r->avg_ticket, 2, ',', '.'),
        ], $results);
    }

    /**
     * Fetch full customer profile from SyncedCustomer model.
     */
    private function fetchCustomerDetails(Store $store, string $nameOrEmail): array
    {
        if (empty($nameOrEmail)) {
            return [];
        }

        $searchLower = '%'.mb_strtolower($nameOrEmail).'%';

        $customer = \App\Models\SyncedCustomer::where('store_id', $store->id)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($searchLower) {
                $q->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$searchLower])
                    ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '\\'", [$searchLower]);
            })
            ->first();

        if (! $customer) {
            return ['found' => false];
        }

        $avgTicket = $customer->total_orders > 0
            ? $customer->total_spent / $customer->total_orders
            : 0;

        return [
            'found' => true,
            'n' => $customer->name,
            'email' => $customer->email,
            'telefone' => $customer->phone,
            'total_pedidos' => $customer->total_orders,
            'total_gasto' => 'R$ '.number_format((float) $customer->total_spent, 2, ',', '.'),
            'ticket_medio' => 'R$ '.number_format((float) $avgTicket, 2, ',', '.'),
            'cliente_desde' => $customer->external_created_at?->format('d/m/Y'),
        ];
    }

    /**
     * Fetch coupon ranking by revenue generated.
     */
    private function fetchCouponRanking(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $results = DB::select("
            SELECT
                coupon->>'code' AS code,
                COUNT(*) AS orders,
                SUM(total) AS revenue,
                AVG(total) AS avg_ticket,
                SUM(discount) AS total_discount
            FROM synced_orders
            WHERE store_id = ?
                AND payment_status = ?
                AND external_created_at BETWEEN ? AND ?
                AND deleted_at IS NULL
                AND coupon IS NOT NULL
                AND coupon->>'code' IS NOT NULL
            GROUP BY coupon->>'code'
            ORDER BY revenue DESC
            LIMIT 15
        ", [
            $store->id, PaymentStatus::Paid->value,
            $startDate->toDateTimeString(), $endDate->toDateTimeString(),
        ]);

        return array_map(fn ($r) => [
            'code' => $r->code,
            'receita' => 'R$ '.number_format((float) $r->revenue, 2, ',', '.'),
            'pedidos' => (int) $r->orders,
            'ticket_medio' => 'R$ '.number_format((float) $r->avg_ticket, 2, ',', '.'),
            'total_desconto' => 'R$ '.number_format((float) $r->total_discount, 2, ',', '.'),
        ], $results);
    }

    /**
     * Fetch ABC classification from ProductAnalyticsService.
     */
    private function fetchProductABC(Store $store, int $days): array
    {
        $products = SyncedProduct::where('store_id', $store->id)
            ->excludeGifts()
            ->active()
            ->whereNull('deleted_at')
            ->get();

        if ($products->isEmpty()) {
            return ['available' => false];
        }

        $analytics = $this->productAnalyticsService->calculateProductAnalytics($store, $products, $days);
        $abcAnalysis = $analytics['abc_analysis'] ?? [];
        $productData = $analytics['products'] ?? [];

        // Get top products per category for context
        $categoryProducts = ['A' => [], 'B' => [], 'C' => []];
        foreach ($productData as $p) {
            $cat = $p['abc_category'] ?? 'C';
            if (count($categoryProducts[$cat]) < 5) {
                $categoryProducts[$cat][] = [
                    'n' => $p['name'],
                    'receita' => 'R$ '.number_format($p['total_sold'], 2, ',', '.'),
                    'pct' => $p['sales_percentage'].'%',
                ];
            }
        }

        return [
            'available' => true,
            'summary' => $abcAnalysis,
            'total_products' => count($productData),
            'total_revenue' => 'R$ '.number_format($analytics['totals']['total_revenue'] ?? 0, 2, ',', '.'),
            'category_a_products' => $categoryProducts['A'],
            'category_b_products' => $categoryProducts['B'],
            'category_c_products' => $categoryProducts['C'],
        ];
    }

    /**
     * Fetch product price distribution analysis.
     */
    private function fetchPriceAnalysis(Store $store): array
    {
        $products = SyncedProduct::where('store_id', $store->id)
            ->whereNull('deleted_at')
            ->active()
            ->excludeGifts()
            ->where('price', '>', 0)
            ->get(['price']);

        if ($products->isEmpty()) {
            return ['available' => false];
        }

        $prices = $products->pluck('price')->map(fn ($p) => (float) $p)->sort()->values();
        $count = $prices->count();
        $median = $count % 2 === 0
            ? ($prices[$count / 2 - 1] + $prices[$count / 2]) / 2
            : $prices[intdiv($count, 2)];

        $ranges = [
            'ate_50' => $prices->filter(fn ($p) => $p <= 50)->count(),
            '50_a_100' => $prices->filter(fn ($p) => $p > 50 && $p <= 100)->count(),
            '100_a_200' => $prices->filter(fn ($p) => $p > 100 && $p <= 200)->count(),
            '200_a_500' => $prices->filter(fn ($p) => $p > 200 && $p <= 500)->count(),
            'acima_500' => $prices->filter(fn ($p) => $p > 500)->count(),
        ];

        return [
            'available' => true,
            'total' => $count,
            'min' => 'R$ '.number_format($prices->min(), 2, ',', '.'),
            'max' => 'R$ '.number_format($prices->max(), 2, ',', '.'),
            'media' => 'R$ '.number_format($prices->avg(), 2, ',', '.'),
            'mediana' => 'R$ '.number_format($median, 2, ',', '.'),
            'ranges' => $ranges,
        ];
    }

    /**
     * Fetch comprehensive stock summary with health and value.
     */
    private function fetchStockSummary(Store $store, int $days): array
    {
        $products = SyncedProduct::where('store_id', $store->id)
            ->whereNull('deleted_at')
            ->active()
            ->excludeGifts()
            ->get(['id', 'name', 'price', 'stock_quantity', 'cost']);

        if ($products->isEmpty()) {
            return ['available' => false];
        }

        $totalProducts = $products->count();
        $totalUnits = $products->sum('stock_quantity');
        $totalValue = $products->sum(fn ($p) => (float) $p->price * ($p->stock_quantity ?? 0));
        $totalCostValue = $products->filter(fn ($p) => $p->cost > 0)->sum(fn ($p) => (float) $p->cost * ($p->stock_quantity ?? 0));
        $outOfStock = $products->where('stock_quantity', '<=', 0)->count();
        $lowStock = $products->filter(fn ($p) => $p->stock_quantity > 0 && $p->stock_quantity <= 10)->count();
        $healthyStock = $products->filter(fn ($p) => $p->stock_quantity > 10 && $p->stock_quantity <= 100)->count();
        $excessStock = $products->filter(fn ($p) => $p->stock_quantity > 100)->count();

        return [
            'available' => true,
            'total_products' => $totalProducts,
            'total_units' => $totalUnits,
            'total_value' => 'R$ '.number_format($totalValue, 2, ',', '.'),
            'total_cost_value' => $totalCostValue > 0 ? 'R$ '.number_format($totalCostValue, 2, ',', '.') : null,
            'avg_stock_per_product' => round($totalUnits / max($totalProducts, 1), 1),
            'out_of_stock' => $outOfStock,
            'low_stock' => $lowStock,
            'healthy_stock' => $healthyStock,
            'excess_stock' => $excessStock,
            'out_of_stock_pct' => round(($outOfStock / max($totalProducts, 1)) * 100, 1).'%',
        ];
    }

    /**
     * Fetch pending/unpaid orders (abandoned checkout).
     */
    private function fetchPendingOrders(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $pending = SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->where('payment_status', \App\Enums\PaymentStatus::Pending)
            ->orderByDesc('external_created_at')
            ->limit(15)
            ->get(['order_number', 'customer_name', 'total', 'payment_status', 'external_created_at']);

        $totalPending = SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->where('payment_status', \App\Enums\PaymentStatus::Pending)
            ->count();

        $lostValue = SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->where('payment_status', \App\Enums\PaymentStatus::Pending)
            ->sum('total');

        return [
            'total' => $totalPending,
            'valor_perdido' => 'R$ '.number_format((float) $lostValue, 2, ',', '.'),
            'orders' => $pending->map(fn ($o) => [
                'numero' => $o->order_number,
                'cliente' => $o->customer_name,
                'total' => 'R$ '.number_format((float) $o->total, 2, ',', '.'),
                'data' => $o->external_created_at?->format('d/m/Y'),
            ])->toArray(),
        ];
    }

    /**
     * Fetch top selling specific dates.
     */
    private function fetchBestSellingDays(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $results = DB::select('
            SELECT
                DATE(external_created_at) AS day,
                COUNT(*) AS orders,
                SUM(total) AS revenue,
                AVG(total) AS avg_ticket
            FROM synced_orders
            WHERE store_id = ?
                AND payment_status = ?
                AND external_created_at BETWEEN ? AND ?
                AND deleted_at IS NULL
            GROUP BY day
            ORDER BY revenue DESC
            LIMIT 10
        ', [
            $store->id, PaymentStatus::Paid->value,
            $startDate->toDateTimeString(), $endDate->toDateTimeString(),
        ]);

        return array_map(fn ($r) => [
            'data' => Carbon::parse($r->day)->format('d/m/Y'),
            'dia_semana' => Carbon::parse($r->day)->locale('pt_BR')->dayName,
            'receita' => 'R$ '.number_format((float) $r->revenue, 2, ',', '.'),
            'pedidos' => (int) $r->orders,
            'ticket' => 'R$ '.number_format((float) $r->avg_ticket, 2, ',', '.'),
        ], $results);
    }

    /**
     * Fetch sales distribution by hour of day.
     */
    private function fetchSalesByHour(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $results = DB::select('
            SELECT
                EXTRACT(HOUR FROM external_created_at) AS hour,
                COUNT(*) AS orders,
                SUM(total) AS revenue
            FROM synced_orders
            WHERE store_id = ?
                AND payment_status = ?
                AND external_created_at BETWEEN ? AND ?
                AND deleted_at IS NULL
            GROUP BY hour
            ORDER BY hour
        ', [
            $store->id, PaymentStatus::Paid->value,
            $startDate->toDateTimeString(), $endDate->toDateTimeString(),
        ]);

        return array_map(fn ($r) => [
            'hora' => str_pad((int) $r->hour, 2, '0', STR_PAD_LEFT).':00',
            'pedidos' => (int) $r->orders,
            'receita' => 'R$ '.number_format((float) $r->revenue, 2, ',', '.'),
        ], $results);
    }

    /**
     * Fetch discount impact analysis (with vs without discount).
     */
    private function fetchDiscountImpact(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $withDiscount = DB::selectOne('
            SELECT COUNT(*) AS orders, COALESCE(SUM(total), 0) AS revenue, COALESCE(AVG(total), 0) AS avg_ticket, COALESCE(SUM(discount), 0) AS total_discount
            FROM synced_orders
            WHERE store_id = ? AND payment_status = ? AND external_created_at BETWEEN ? AND ? AND deleted_at IS NULL AND discount > 0
        ', [$store->id, PaymentStatus::Paid->value, $startDate->toDateTimeString(), $endDate->toDateTimeString()]);

        $withoutDiscount = DB::selectOne('
            SELECT COUNT(*) AS orders, COALESCE(SUM(total), 0) AS revenue, COALESCE(AVG(total), 0) AS avg_ticket
            FROM synced_orders
            WHERE store_id = ? AND payment_status = ? AND external_created_at BETWEEN ? AND ? AND deleted_at IS NULL AND (discount = 0 OR discount IS NULL)
        ', [$store->id, PaymentStatus::Paid->value, $startDate->toDateTimeString(), $endDate->toDateTimeString()]);

        $totalOrders = (int) $withDiscount->orders + (int) $withoutDiscount->orders;

        return [
            'com_desconto' => [
                'pedidos' => (int) $withDiscount->orders,
                'receita' => 'R$ '.number_format((float) $withDiscount->revenue, 2, ',', '.'),
                'ticket_medio' => 'R$ '.number_format((float) $withDiscount->avg_ticket, 2, ',', '.'),
                'total_desconto' => 'R$ '.number_format((float) $withDiscount->total_discount, 2, ',', '.'),
                'pct_pedidos' => $totalOrders > 0 ? round(((int) $withDiscount->orders / $totalOrders) * 100, 1).'%' : '0%',
            ],
            'sem_desconto' => [
                'pedidos' => (int) $withoutDiscount->orders,
                'receita' => 'R$ '.number_format((float) $withoutDiscount->revenue, 2, ',', '.'),
                'ticket_medio' => 'R$ '.number_format((float) $withoutDiscount->avg_ticket, 2, ',', '.'),
                'pct_pedidos' => $totalOrders > 0 ? round(((int) $withoutDiscount->orders / $totalOrders) * 100, 1).'%' : '0%',
            ],
        ];
    }

    /**
     * Fetch order items analysis (average items per order, distribution).
     */
    private function fetchOrderItemsAnalysis(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $results = DB::select('
            SELECT
                jsonb_array_length(items::jsonb) AS items_count,
                COUNT(*) AS orders,
                SUM(total) AS revenue
            FROM synced_orders
            WHERE store_id = ?
                AND payment_status = ?
                AND external_created_at BETWEEN ? AND ?
                AND deleted_at IS NULL
                AND items IS NOT NULL
            GROUP BY items_count
            ORDER BY items_count
        ', [
            $store->id, PaymentStatus::Paid->value,
            $startDate->toDateTimeString(), $endDate->toDateTimeString(),
        ]);

        $totalOrders = array_sum(array_column($results, 'orders'));
        $totalItems = 0;
        $maxItems = 0;
        foreach ($results as $r) {
            $totalItems += (int) $r->items_count * (int) $r->orders;
            $maxItems = max($maxItems, (int) $r->items_count);
        }
        $avgItems = $totalOrders > 0 ? round($totalItems / $totalOrders, 1) : 0;

        $distribution = [];
        foreach ($results as $r) {
            $label = (int) $r->items_count === 1 ? '1 item' : (int) $r->items_count.' itens';
            $distribution[] = [
                'itens' => $label,
                'pedidos' => (int) $r->orders,
                'pct' => $totalOrders > 0 ? round(((int) $r->orders / $totalOrders) * 100, 1).'%' : '0%',
            ];
        }

        return [
            'media_itens' => $avgItems,
            'max_itens' => $maxItems,
            'total_pedidos' => $totalOrders,
            'pedidos_1_item' => $results[0]->orders ?? 0,
            'pedidos_multi_item_pct' => $totalOrders > 0 && isset($results[0])
                ? round((($totalOrders - (int) ($results[0]->orders ?? 0)) / $totalOrders) * 100, 1).'%'
                : '0%',
            'distribution' => array_slice($distribution, 0, 8),
        ];
    }

    /**
     * Fetch customer segments by spending level.
     */
    private function fetchCustomerSegments(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $customers = SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->where('payment_status', PaymentStatus::Paid)
            ->whereNull('deleted_at')
            ->whereNotNull('customer_email')
            ->selectRaw('customer_email, customer_name, COUNT(*) as orders, SUM(total) as total_spent')
            ->groupBy('customer_email', 'customer_name')
            ->get();

        if ($customers->isEmpty()) {
            return ['available' => false];
        }

        $avgSpent = $customers->avg('total_spent');
        $highThreshold = $avgSpent * 2;

        $segments = [
            'vip' => $customers->filter(fn ($c) => (float) $c->total_spent >= $highThreshold),
            'regular' => $customers->filter(fn ($c) => (float) $c->total_spent >= $avgSpent && (float) $c->total_spent < $highThreshold),
            'ocasional' => $customers->filter(fn ($c) => (float) $c->total_spent < $avgSpent),
        ];

        $totalCustomers = $customers->count();

        $result = [];
        foreach ($segments as $name => $group) {
            $result[$name] = [
                'count' => $group->count(),
                'pct' => round(($group->count() / max($totalCustomers, 1)) * 100, 1).'%',
                'receita' => 'R$ '.number_format($group->sum('total_spent'), 2, ',', '.'),
                'ticket_medio' => 'R$ '.number_format($group->count() > 0 ? $group->sum('total_spent') / $group->sum('orders') : 0, 2, ',', '.'),
            ];
        }

        $result['total_customers'] = $totalCustomers;
        $result['threshold_vip'] = 'R$ '.number_format($highThreshold, 2, ',', '.');
        $result['threshold_regular'] = 'R$ '.number_format($avgSpent, 2, ',', '.');

        return $result;
    }

    /**
     * Fetch new vs returning customers analysis.
     */
    private function fetchNewVsReturning(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        // Customers who had orders BEFORE this period
        $returningEmails = DB::select('
            SELECT DISTINCT customer_email
            FROM synced_orders
            WHERE store_id = ?
                AND payment_status = ?
                AND external_created_at < ?
                AND deleted_at IS NULL
                AND customer_email IS NOT NULL
        ', [$store->id, PaymentStatus::Paid->value, $startDate->toDateTimeString()]);

        $returningSet = array_flip(array_column($returningEmails, 'customer_email'));

        // Current period customers
        $currentCustomers = SyncedOrder::where('store_id', $store->id)
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->where('payment_status', PaymentStatus::Paid)
            ->whereNull('deleted_at')
            ->whereNotNull('customer_email')
            ->selectRaw('customer_email, COUNT(*) as orders, SUM(total) as revenue')
            ->groupBy('customer_email')
            ->get();

        $newCount = 0;
        $newRevenue = 0;
        $returningCount = 0;
        $returningRevenue = 0;

        foreach ($currentCustomers as $c) {
            if (isset($returningSet[$c->customer_email])) {
                $returningCount++;
                $returningRevenue += (float) $c->revenue;
            } else {
                $newCount++;
                $newRevenue += (float) $c->revenue;
            }
        }

        $total = $newCount + $returningCount;

        return [
            'new_count' => $newCount,
            'new_pct' => $total > 0 ? round(($newCount / $total) * 100, 1).'%' : '0%',
            'new_revenue' => 'R$ '.number_format($newRevenue, 2, ',', '.'),
            'returning_count' => $returningCount,
            'returning_pct' => $total > 0 ? round(($returningCount / $total) * 100, 1).'%' : '0%',
            'returning_revenue' => 'R$ '.number_format($returningRevenue, 2, ',', '.'),
            'total_customers' => $total,
        ];
    }

    /**
     * Fetch comprehensive store overview with KPIs from DashboardService.
     */
    private function fetchStoreOverview(Store $store, int $days): array
    {
        $filters = [
            'period' => 'custom',
            'start_date' => now()->subDays($days)->toDateString(),
            'end_date' => now()->toDateString(),
        ];

        $stats = $this->dashboardService->getStats($store, $filters);

        return [
            'receita' => 'R$ '.number_format($stats['total_revenue'], 2, ',', '.'),
            'receita_change' => $stats['revenue_change'].'%',
            'pedidos' => $stats['total_orders'],
            'pedidos_change' => $stats['orders_change'].'%',
            'ticket_medio' => 'R$ '.number_format($stats['average_ticket'], 2, ',', '.'),
            'ticket_change' => $stats['ticket_change'].'%',
            'conversao' => $stats['conversion_rate'].'%',
            'conversao_change' => $stats['conversion_change'].'%',
            'clientes' => $stats['total_customers'],
            'clientes_change' => $stats['customers_change'].'%',
            'total_produtos' => $stats['total_products'],
        ];
    }

    /**
     * Fetch period comparison (current vs previous).
     */
    private function fetchPeriodComparison(Store $store, int $days): array
    {
        $filters = [
            'period' => 'custom',
            'start_date' => now()->subDays($days)->toDateString(),
            'end_date' => now()->toDateString(),
        ];

        $stats = $this->dashboardService->getStats($store, $filters);

        $prevRevenue = $stats['revenue_change'] != 0
            ? $stats['total_revenue'] / (1 + $stats['revenue_change'] / 100)
            : $stats['total_revenue'];
        $prevOrders = $stats['orders_change'] != 0
            ? $stats['total_orders'] / (1 + $stats['orders_change'] / 100)
            : $stats['total_orders'];
        $prevTicket = $stats['ticket_change'] != 0
            ? $stats['average_ticket'] / (1 + $stats['ticket_change'] / 100)
            : $stats['average_ticket'];
        $prevCustomers = $stats['customers_change'] != 0
            ? $stats['total_customers'] / (1 + $stats['customers_change'] / 100)
            : $stats['total_customers'];

        return [
            'current' => [
                'periodo' => now()->subDays($days)->format('d/m').' a '.now()->format('d/m'),
                'receita' => 'R$ '.number_format($stats['total_revenue'], 2, ',', '.'),
                'pedidos' => $stats['total_orders'],
                'ticket' => 'R$ '.number_format($stats['average_ticket'], 2, ',', '.'),
                'clientes' => $stats['total_customers'],
                'conversao' => $stats['conversion_rate'].'%',
            ],
            'previous' => [
                'periodo' => now()->subDays($days * 2)->format('d/m').' a '.now()->subDays($days)->format('d/m'),
                'receita' => 'R$ '.number_format($prevRevenue, 2, ',', '.'),
                'pedidos' => round($prevOrders),
                'ticket' => 'R$ '.number_format($prevTicket, 2, ',', '.'),
                'clientes' => round($prevCustomers),
            ],
            'changes' => [
                'receita' => $stats['revenue_change'].'%',
                'pedidos' => $stats['orders_change'].'%',
                'ticket' => $stats['ticket_change'].'%',
                'clientes' => $stats['customers_change'].'%',
                'conversao' => $stats['conversion_change'].'%',
            ],
        ];
    }

    /**
     * Fetch revenue breakdown by product category.
     */
    private function fetchRevenueByCategory(Store $store, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        // Get product-to-category mapping
        $productCategories = SyncedProduct::where('store_id', $store->id)
            ->whereNull('deleted_at')
            ->active()
            ->excludeGifts()
            ->whereNotNull('categories')
            ->get(['name', 'categories'])
            ->mapWithKeys(function ($p) {
                $cats = is_array($p->categories) ? $p->categories : [];

                return [$p->name => $cats[0] ?? 'Sem categoria'];
            })
            ->toArray();

        // Get product sales
        $results = DB::select("
            WITH order_items AS (
                SELECT jsonb_array_elements(items::jsonb) AS item
                FROM synced_orders
                WHERE store_id = ?
                    AND payment_status = ?
                    AND external_created_at BETWEEN ? AND ?
                    AND deleted_at IS NULL
                    AND items IS NOT NULL
            )
            SELECT
                COALESCE(item->>'product_name', item->>'name', 'Produto') AS name,
                SUM(COALESCE((item->>'quantity')::integer, 1)) AS qty,
                SUM(COALESCE(
                    (item->>'total')::numeric,
                    COALESCE((item->>'unit_price')::numeric, 0) * COALESCE((item->>'quantity')::numeric, 1)
                )) AS revenue
            FROM order_items
            GROUP BY name
        ", [
            $store->id, PaymentStatus::Paid->value,
            $startDate->toDateTimeString(), $endDate->toDateTimeString(),
        ]);

        // Aggregate by category
        $categoryData = [];
        $totalRevenue = 0;
        foreach ($results as $r) {
            $category = $productCategories[$r->name] ?? 'Sem categoria';
            if (! isset($categoryData[$category])) {
                $categoryData[$category] = ['receita' => 0, 'pedidos' => 0, 'qtd' => 0];
            }
            $categoryData[$category]['receita'] += (float) $r->revenue;
            $categoryData[$category]['qtd'] += (int) $r->qty;
            $totalRevenue += (float) $r->revenue;
        }

        // Sort by revenue and format
        arsort($categoryData);

        return array_map(function ($cat, $data) use ($totalRevenue) {
            return [
                'cat' => $cat,
                'receita' => 'R$ '.number_format($data['receita'], 2, ',', '.'),
                'qtd' => $data['qtd'],
                'pct' => $totalRevenue > 0 ? round(($data['receita'] / $totalRevenue) * 100, 1).'%' : '0%',
            ];
        }, array_keys(array_slice($categoryData, 0, 15, true)), array_slice($categoryData, 0, 15, true));
    }

    /**
     * Generate proactive insights by detecting notable patterns in store data.
     * Runs lightweight queries and returns alerts for the AI to mention naturally.
     */
    public function generateProactiveInsights(Store $store, int $days = 15): array
    {
        $alerts = [];

        try {
            $alerts = array_merge($alerts, $this->checkTopSellerStockAlerts($store, $days));
        } catch (\Exception $e) {
            Log::warning('Proactive: top-seller stock check failed', ['error' => $e->getMessage()]);
        }

        try {
            $alerts = array_merge($alerts, $this->checkExpiringCoupons($store));
        } catch (\Exception $e) {
            Log::warning('Proactive: expiring coupons check failed', ['error' => $e->getMessage()]);
        }

        try {
            $alerts = array_merge($alerts, $this->checkRevenueTrend($store, $days));
        } catch (\Exception $e) {
            Log::warning('Proactive: revenue trend check failed', ['error' => $e->getMessage()]);
        }

        try {
            $alerts = array_merge($alerts, $this->checkUnusedCoupons($store));
        } catch (\Exception $e) {
            Log::warning('Proactive: unused coupons check failed', ['error' => $e->getMessage()]);
        }

        return $alerts;
    }

    private function checkTopSellerStockAlerts(Store $store, int $days): array
    {
        $topProducts = $this->fetchTopProducts($store, $days);
        $topNames = array_column(array_slice($topProducts, 0, 5), 'n');

        if (empty($topNames)) {
            return [];
        }

        $criticalStock = SyncedProduct::where('store_id', $store->id)
            ->whereIn('name', $topNames)
            ->where('stock_quantity', '<=', 5)
            ->where('stock_quantity', '>=', 0)
            ->active()
            ->whereNull('deleted_at')
            ->get(['name', 'stock_quantity']);

        $alerts = [];
        foreach ($criticalStock as $product) {
            $alerts[] = [
                'type' => 'critical_stock',
                'msg' => "Produto mais vendido \"{$product->name}\" com apenas {$product->stock_quantity} un. em estoque",
            ];
        }

        return $alerts;
    }

    private function checkExpiringCoupons(Store $store): array
    {
        $expiring = SyncedCoupon::where('store_id', $store->id)
            ->active()
            ->whereNull('deleted_at')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays(7)])
            ->limit(3)
            ->get(['code', 'end_date']);

        return $expiring->map(fn ($c) => [
            'type' => 'expiring_coupon',
            'msg' => "Cupom {$c->code} expira em ".Carbon::parse($c->end_date)->format('d/m/Y'),
        ])->toArray();
    }

    private function checkRevenueTrend(Store $store, int $days): array
    {
        $currentStart = now()->subDays($days);
        $previousStart = now()->subDays($days * 2);
        $previousEnd = now()->subDays($days);

        $currentRevenue = (float) SyncedOrder::where('store_id', $store->id)
            ->where('payment_status', PaymentStatus::Paid)
            ->whereBetween('external_created_at', [$currentStart, now()])
            ->whereNull('deleted_at')
            ->sum('total');

        $previousRevenue = (float) SyncedOrder::where('store_id', $store->id)
            ->where('payment_status', PaymentStatus::Paid)
            ->whereBetween('external_created_at', [$previousStart, $previousEnd])
            ->whereNull('deleted_at')
            ->sum('total');

        if ($previousRevenue > 0) {
            $change = (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
            $direction = $change >= 0 ? 'subiu' : 'caiu';
            $changeAbs = abs(round($change, 1));

            if ($changeAbs >= 10) {
                return [[
                    'type' => 'revenue_trend',
                    'msg' => "Receita {$direction} {$changeAbs}% vs período anterior (R$ ".
                        number_format($currentRevenue, 2, ',', '.').' vs R$ '.
                        number_format($previousRevenue, 2, ',', '.').')',
                ]];
            }
        }

        return [];
    }

    private function checkUnusedCoupons(Store $store): array
    {
        $unused = SyncedCoupon::where('store_id', $store->id)
            ->active()
            ->whereNull('deleted_at')
            ->where('used', 0)
            ->limit(3)
            ->get(['code', 'type', 'value']);

        if ($unused->isEmpty()) {
            return [];
        }

        return [[
            'type' => 'unused_coupons',
            'msg' => $unused->count().' cupom(ns) ativo(s) nunca utilizado(s): '.
                $unused->pluck('code')->implode(', '),
        ]];
    }
}
