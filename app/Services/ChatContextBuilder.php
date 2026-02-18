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
                    'top_products' => $data['top_products'] = $this->fetchTopProducts($store, $days),
                    'products_catalog' => $data['products_catalog'] = $this->fetchProductsCatalog($store),
                    'products_by_coupon' => $data['products_by_coupon'] = $this->fetchProductsByCoupon(
                        $store, $params['codes'] ?? [], $days
                    ),
                    'stock_status' => $data['stock'] = $this->fetchStockData($store),
                    'coupon_stats' => $data['coupons'] = $this->fetchCouponsData($store, $days),
                    'coupon_details' => $data['coupon_details'] = $this->fetchCouponDetails(
                        $store, $params['codes'] ?? [], $days
                    ),
                    'order_status' => $data['order_status'] = $this->fetchOrderStatusBreakdown($store, $days),
                    'top_customers' => $data['top_customers'] = $this->fetchTopCustomers($store, $days),
                    'customer_orders' => $data['customer_orders'] = $this->fetchCustomerOrders(
                        $store, $params['name_or_email'] ?? '', $days
                    ),
                    'revenue_by_product' => $data['product_revenue'] = $this->fetchRevenueByProduct(
                        $store, $params['product_name'] ?? '', $days
                    ),
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
        ], array_slice($results, 0, 10));
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
