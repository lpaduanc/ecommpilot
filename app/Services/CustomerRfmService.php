<?php

namespace App\Services;

use App\Models\Store;
use App\Models\SyncedCustomer;
use App\Models\SyncedOrder;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CustomerRfmService
{
    private const CACHE_TTL_HOURS = 6;

    /**
     * Colors for each RFM segment (also consumed by the frontend).
     */
    private const SEGMENT_COLORS = [
        'Campeões' => '#22c55e',
        'Clientes Fiéis' => '#16a34a',
        'Potenciais Fiéis' => '#84cc16',
        'Novos Clientes' => '#3b82f6',
        'Promissores' => '#06b6d4',
        'Precisam de Atenção' => '#f59e0b',
        'Quase Dormindo' => '#f97316',
        'Em Risco' => '#ef4444',
        'Não Pode Perder' => '#dc2626',
        'Hibernando' => '#6b7280',
        'Perdidos' => '#374151',
    ];

    /**
     * Segment definitions: [r_min, r_max, f_min, f_max, m_min, m_max].
     * Order matters — more restrictive segments must come first.
     */
    private const SEGMENTS = [
        'Campeões' => [4, 5, 4, 5, 4, 5],
        'Clientes Fiéis' => [3, 5, 3, 5, 3, 5],
        'Não Pode Perder' => [1, 1, 4, 5, 4, 5],
        'Em Risco' => [1, 2, 3, 5, 3, 5],
        'Potenciais Fiéis' => [4, 5, 2, 3, 2, 3],
        'Novos Clientes' => [4, 5, 1, 1, 1, 2],
        'Promissores' => [3, 4, 1, 2, 1, 2],
        'Precisam de Atenção' => [2, 3, 2, 3, 2, 3],
        'Quase Dormindo' => [2, 3, 1, 2, 1, 2],
        'Hibernando' => [1, 2, 1, 2, 1, 2],
        'Perdidos' => [1, 1, 1, 1, 1, 1],
    ];

    // ==========================================
    // PUBLIC API
    // ==========================================

    /**
     * Return pre-computed RFM summary from cache.
     * No extra DB queries — everything was computed during cache warming.
     */
    public function getRfmSummary(Store $store): array
    {
        $this->ensureCacheWarmed($store);

        return Cache::get("rfm_summary:{$store->id}", $this->emptyRfmSummary());
    }

    /**
     * Return pre-computed filter options from cache.
     */
    public function getFilters(Store $store): array
    {
        $this->ensureCacheWarmed($store);

        return Cache::get("rfm_filters:{$store->id}", [
            'segments' => [],
            'orders_range' => ['min' => 0, 'max' => 0],
            'spent_range' => ['min' => 0, 'max' => 0],
        ]);
    }

    /**
     * Score a small collection of customers using cached quintile boundaries.
     * Used by the controller to enrich only the paginated results (e.g. 10 customers)
     * instead of loading the full 50K RFM map.
     *
     * @return array<int, array{segment: string, scores: array{r: int, f: int, m: int}}>
     */
    public function scoreCustomers(Store $store, Collection $customers): array
    {
        // Non-blocking: if cache is not warm, return empty (no RFM) rather than blocking.
        // Cache gets warmed by getFilters() or getRfmSummary() which run in parallel.
        if (! Cache::has("rfm_boundaries:{$store->id}")) {
            return [];
        }

        $boundaries = Cache::get("rfm_boundaries:{$store->id}");

        if (! $boundaries || ($boundaries['r'] === [0, 0, 0, 0] && $boundaries['f'] === [0, 0, 0, 0])) {
            return [];
        }

        $result = [];

        foreach ($customers as $customer) {
            if ($customer->last_order_at === null) {
                $result[$customer->id] = [
                    'segment' => 'Perdidos',
                    'scores' => ['r' => 1, 'f' => 1, 'm' => 1],
                ];

                continue;
            }

            $recencyDays = (float) abs(now()->diffInDays($customer->last_order_at));
            $r = $this->scoreValue($recencyDays, $boundaries['r'], true);
            $f = $this->scoreValue((float) $customer->total_orders, $boundaries['f']);
            $m = $this->scoreValue((float) $customer->total_spent, $boundaries['m']);

            $result[$customer->id] = [
                'segment' => $this->matchSegment($r, $f, $m),
                'scores' => ['r' => $r, 'f' => $f, 'm' => $m],
            ];
        }

        return $result;
    }

    /**
     * Return customer IDs belonging to a specific RFM segment.
     * Used by the controller for segment filtering without loading the full RFM map.
     *
     * @return int[]
     */
    public function getCustomerIdsForSegment(Store $store, string $segment): array
    {
        $this->ensureCacheWarmed($store);

        $segmentIds = Cache::get("rfm_segment_ids:{$store->id}", []);

        return $segmentIds[$segment] ?? [];
    }

    /**
     * Return the full RFM map for a store (using cache).
     * Kept for backward compatibility (e.g. chatbot service).
     */
    public function getCustomerRfmMap(Store $store): array
    {
        return $this->calculateRfmForStore($store);
    }

    /**
     * Calculate RFM scores and segments for every customer in the store.
     * Warning: For large stores this loads all customers into memory.
     * Prefer scoreCustomers() for paginated use.
     *
     * @return array<int, array{segment: string, scores: array{r: int, f: int, m: int}}>
     */
    public function calculateRfmForStore(Store $store): array
    {
        $this->ensureCacheWarmed($store);

        $boundaries = Cache::get("rfm_boundaries:{$store->id}");

        if (! $boundaries || ($boundaries['r'] === [0, 0, 0, 0] && $boundaries['f'] === [0, 0, 0, 0])) {
            return [];
        }

        $rfmMap = [];

        $cursor = $this->buildCustomerJoinQuery($store)
            ->selectRaw(
                'synced_customers.id,
                 COALESCE(ord.real_orders_count, synced_customers.total_orders) AS total_orders,
                 COALESCE(ord.real_orders_total, synced_customers.total_spent) AS total_spent,
                 COALESCE(ord.real_last_order, synced_customers.last_order_at) AS last_order_at'
            )
            ->cursor();

        foreach ($cursor as $customer) {
            $lastOrderAt = $customer->last_order_at ? Carbon::parse($customer->last_order_at) : null;

            if ($lastOrderAt === null) {
                $rfmMap[$customer->id] = ['segment' => 'Perdidos', 'scores' => ['r' => 1, 'f' => 1, 'm' => 1]];
            } else {
                $recencyDays = (float) abs(now()->diffInDays($lastOrderAt));
                $r = $this->scoreValue($recencyDays, $boundaries['r'], true);
                $f = $this->scoreValue((float) $customer->total_orders, $boundaries['f']);
                $m = $this->scoreValue((float) $customer->total_spent, $boundaries['m']);
                $rfmMap[$customer->id] = ['segment' => $this->matchSegment($r, $f, $m), 'scores' => ['r' => $r, 'f' => $f, 'm' => $m]];
            }
        }

        return $rfmMap;
    }

    /**
     * Invalidate all cached RFM data for a store.
     * Call this after customer sync completes.
     */
    public function invalidateCache(Store $store): void
    {
        Cache::forget("rfm_boundaries:{$store->id}");
        Cache::forget("rfm_summary:{$store->id}");
        Cache::forget("rfm_filters:{$store->id}");
        Cache::forget("rfm_segment_ids:{$store->id}");
    }

    // ==========================================
    // SCORING UTILITIES (public for unit tests)
    // ==========================================

    /**
     * Calculate quintile boundaries (p20, p40, p60, p80) for a list of numeric values.
     *
     * @param  float[]  $values
     * @return float[] Four boundary values
     */
    public function calculateQuintiles(array $values): array
    {
        if (empty($values)) {
            return [0, 0, 0, 0];
        }

        sort($values);
        $count = count($values);

        $percentile = function (float $pct) use ($values, $count): float {
            $index = ($pct / 100) * ($count - 1);
            $lower = (int) floor($index);
            $upper = (int) ceil($index);

            if ($lower === $upper) {
                return (float) $values[$lower];
            }

            $fraction = $index - $lower;

            return $values[$lower] + $fraction * ($values[$upper] - $values[$lower]);
        };

        return [
            $percentile(20),
            $percentile(40),
            $percentile(60),
            $percentile(80),
        ];
    }

    /**
     * Score a single value against quintile boundaries, returning 1–5.
     *
     * @param  float[]  $boundaries  Four boundary values [p20, p40, p60, p80]
     * @param  bool  $inverse  When true, lower value = higher score (used for Recency)
     */
    public function scoreValue(float $value, array $boundaries, bool $inverse = false): int
    {
        [$p20, $p40, $p60, $p80] = $boundaries;

        if ($inverse) {
            if ($value <= $p20) {
                return 5;
            }
            if ($value <= $p40) {
                return 4;
            }
            if ($value <= $p60) {
                return 3;
            }
            if ($value <= $p80) {
                return 2;
            }

            return 1;
        }

        if ($value <= $p20) {
            return 1;
        }
        if ($value <= $p40) {
            return 2;
        }
        if ($value <= $p60) {
            return 3;
        }
        if ($value <= $p80) {
            return 4;
        }

        return 5;
    }

    /**
     * Match R, F, M scores to the first matching segment definition.
     * Segment order in SEGMENTS constant matters.
     */
    public function matchSegment(int $r, int $f, int $m): string
    {
        foreach (self::SEGMENTS as $segment => [$rMin, $rMax, $fMin, $fMax, $mMin, $mMax]) {
            if ($r >= $rMin && $r <= $rMax
                && $f >= $fMin && $f <= $fMax
                && $m >= $mMin && $m <= $mMax) {
                return $segment;
            }
        }

        return 'Hibernando';
    }

    // ==========================================
    // INTERNAL CACHE MANAGEMENT
    // ==========================================

    /**
     * Ensure all RFM cache keys are populated.
     * Uses a lock to prevent cache stampede when multiple requests arrive simultaneously.
     */
    private function ensureCacheWarmed(Store $store): void
    {
        // Use boundaries key as sentinel — if it exists, all caches are warm
        if (Cache::has("rfm_boundaries:{$store->id}")) {
            return;
        }

        $lock = Cache::lock("rfm_computing:{$store->id}", 120);

        try {
            // Wait up to 15 seconds for lock
            $lock->block(15);

            // Double-check after acquiring lock (another request may have computed)
            if (Cache::has("rfm_boundaries:{$store->id}")) {
                return;
            }

            $this->computeAndCacheAll($store);
        } catch (LockTimeoutException) {
            // Safety valve: if lock times out, compute independently
            $this->computeAndCacheAll($store);
        } finally {
            if (isset($lock)) {
                $lock->release();
            }
        }
    }

    /**
     * Single-pass computation that populates all 5 cache keys at once:
     * - rfm_data:{id}         Full map (for backward compat / segment filtering fallback)
     * - rfm_boundaries:{id}   Quintile boundaries (~100 bytes)
     * - rfm_summary:{id}      Pre-computed summary (~3 KB)
     * - rfm_filters:{id}      Pre-computed filter options (~200 bytes)
     * - rfm_segment_ids:{id}  Segment → customer IDs map (~800 KB)
     */
    /**
     * Valid order statuses for aggregation (excludes cancelled).
     */
    private const VALID_ORDER_STATUSES = ['pending', 'paid', 'shipped', 'delivered'];

    /**
     * Build the orders aggregate subquery for a store.
     */
    private function buildOrdersSubqueryForRfm(Store $store)
    {
        return SyncedOrder::selectRaw(
            'LOWER(TRIM(customer_email)) AS agg_email,
             COUNT(*) AS real_orders_count,
             SUM(total) AS real_orders_total,
             MIN(external_created_at) AS real_first_order,
             MAX(external_created_at) AS real_last_order'
        )
            ->where('store_id', $store->id)
            ->whereNotNull('customer_email')
            ->where('customer_email', '!=', '')
            ->whereIn('status', self::VALID_ORDER_STATUSES)
            ->whereNull('deleted_at')
            ->groupByRaw('LOWER(TRIM(customer_email))');
    }

    /**
     * Build the base query (join only, no select) for customer + orders aggregation.
     */
    private function buildCustomerJoinQuery(Store $store)
    {
        return SyncedCustomer::query()
            ->leftJoinSub(
                $this->buildOrdersSubqueryForRfm($store),
                'ord',
                fn ($join) => $join->whereRaw('LOWER(TRIM(synced_customers.email)) = ord.agg_email')
            )
            ->where('synced_customers.store_id', $store->id);
    }

    private function computeAndCacheAll(Store $store): void
    {
        $ttl = now()->addHours(self::CACHE_TTL_HOURS);

        // Step 1: Get aggregate stats using SQL (no full load)
        $stats = $this->buildCustomerJoinQuery($store)->selectRaw(
            'COUNT(*) AS total_customers,
             COUNT(COALESCE(ord.real_last_order, synced_customers.last_order_at)) AS total_with_orders,
             MIN(COALESCE(ord.real_orders_count, synced_customers.total_orders)::int) AS min_orders,
             MAX(COALESCE(ord.real_orders_count, synced_customers.total_orders)::int) AS max_orders,
             MIN(COALESCE(ord.real_orders_total, synced_customers.total_spent)::float) AS min_spent,
             MAX(COALESCE(ord.real_orders_total, synced_customers.total_spent)::float) AS max_spent,
             AVG(COALESCE(ord.real_orders_count, synced_customers.total_orders)::float) AS avg_frequency,
             AVG(COALESCE(ord.real_orders_total, synced_customers.total_spent)::float) AS avg_monetary'
        )->first();

        if (! $stats || (int) $stats->total_customers === 0) {
            $emptyBounds = ['r' => [0, 0, 0, 0], 'f' => [0, 0, 0, 0], 'm' => [0, 0, 0, 0]];
            $emptyFilters = ['segments' => [], 'orders_range' => ['min' => 0, 'max' => 0], 'spent_range' => ['min' => 0, 'max' => 0]];

            Cache::put("rfm_boundaries:{$store->id}", $emptyBounds, $ttl);
            Cache::put("rfm_summary:{$store->id}", $this->emptyRfmSummary(), $ttl);
            Cache::put("rfm_filters:{$store->id}", $emptyFilters, $ttl);
            Cache::put("rfm_segment_ids:{$store->id}", [], $ttl);

            return;
        }

        $totalCustomers = (int) $stats->total_customers;
        $totalWithOrders = (int) $stats->total_with_orders;

        // Step 2: Calculate quintile boundaries using SQL percentiles (memory-efficient)
        $rBounds = [0, 0, 0, 0];
        $fBounds = [0, 0, 0, 0];
        $mBounds = [0, 0, 0, 0];

        if ($totalWithOrders > 0) {
            $percentiles = $this->buildCustomerJoinQuery($store)
                ->whereRaw('COALESCE(ord.real_last_order, synced_customers.last_order_at) IS NOT NULL')
                ->selectRaw("
                percentile_cont(0.2) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (NOW() - COALESCE(ord.real_last_order, synced_customers.last_order_at))) / 86400.0) AS r_p20,
                percentile_cont(0.4) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (NOW() - COALESCE(ord.real_last_order, synced_customers.last_order_at))) / 86400.0) AS r_p40,
                percentile_cont(0.6) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (NOW() - COALESCE(ord.real_last_order, synced_customers.last_order_at))) / 86400.0) AS r_p60,
                percentile_cont(0.8) WITHIN GROUP (ORDER BY EXTRACT(EPOCH FROM (NOW() - COALESCE(ord.real_last_order, synced_customers.last_order_at))) / 86400.0) AS r_p80,
                percentile_cont(0.2) WITHIN GROUP (ORDER BY COALESCE(ord.real_orders_count, synced_customers.total_orders)::float) AS f_p20,
                percentile_cont(0.4) WITHIN GROUP (ORDER BY COALESCE(ord.real_orders_count, synced_customers.total_orders)::float) AS f_p40,
                percentile_cont(0.6) WITHIN GROUP (ORDER BY COALESCE(ord.real_orders_count, synced_customers.total_orders)::float) AS f_p60,
                percentile_cont(0.8) WITHIN GROUP (ORDER BY COALESCE(ord.real_orders_count, synced_customers.total_orders)::float) AS f_p80,
                percentile_cont(0.2) WITHIN GROUP (ORDER BY COALESCE(ord.real_orders_total, synced_customers.total_spent)::float) AS m_p20,
                percentile_cont(0.4) WITHIN GROUP (ORDER BY COALESCE(ord.real_orders_total, synced_customers.total_spent)::float) AS m_p40,
                percentile_cont(0.6) WITHIN GROUP (ORDER BY COALESCE(ord.real_orders_total, synced_customers.total_spent)::float) AS m_p60,
                percentile_cont(0.8) WITHIN GROUP (ORDER BY COALESCE(ord.real_orders_total, synced_customers.total_spent)::float) AS m_p80,
                AVG(EXTRACT(EPOCH FROM (NOW() - COALESCE(ord.real_last_order, synced_customers.last_order_at))) / 86400.0) AS avg_recency_days
            ")->first();

            $rBounds = [(float) $percentiles->r_p20, (float) $percentiles->r_p40, (float) $percentiles->r_p60, (float) $percentiles->r_p80];
            $fBounds = [(float) $percentiles->f_p20, (float) $percentiles->f_p40, (float) $percentiles->f_p60, (float) $percentiles->f_p80];
            $mBounds = [(float) $percentiles->m_p20, (float) $percentiles->m_p40, (float) $percentiles->m_p60, (float) $percentiles->m_p80];
            $avgRecencyDays = round((float) $percentiles->avg_recency_days, 1);
        } else {
            $avgRecencyDays = null;
        }

        // Step 3: Process customers using cursor (single query, streamed results)
        $segmentIds = [];
        $segmentData = [];

        $cursor = $this->buildCustomerJoinQuery($store)
            ->selectRaw(
                'synced_customers.id,
                 COALESCE(ord.real_orders_count, synced_customers.total_orders) AS total_orders,
                 COALESCE(ord.real_orders_total, synced_customers.total_spent) AS total_spent,
                 COALESCE(ord.real_last_order, synced_customers.last_order_at) AS last_order_at'
            )
            ->cursor();

        foreach ($cursor as $customer) {
            $totalSpent = (float) $customer->total_spent;
            $lastOrderAt = $customer->last_order_at ? Carbon::parse($customer->last_order_at) : null;

            if ($lastOrderAt === null) {
                $segment = 'Perdidos';
            } else {
                $recencyDays = (float) abs(now()->diffInDays($lastOrderAt));
                $r = $this->scoreValue($recencyDays, $rBounds, true);
                $f = $this->scoreValue((float) $customer->total_orders, $fBounds);
                $m = $this->scoreValue($totalSpent, $mBounds);
                $segment = $this->matchSegment($r, $f, $m);
            }

            $segmentIds[$segment][] = $customer->id;

            if (! isset($segmentData[$segment])) {
                $segmentData[$segment] = ['count' => 0, 'total_monetary' => 0.0];
            }
            $segmentData[$segment]['count']++;
            $segmentData[$segment]['total_monetary'] += $totalSpent;
        }

        // --- Build summary ---
        $avgFrequency = round((float) $stats->avg_frequency, 1);
        $avgMonetary = round((float) $stats->avg_monetary, 2);

        $segmentsDistribution = [];
        $monetaryBySegment = [];

        foreach ($segmentData as $segment => $data) {
            $percentage = $totalCustomers > 0
                ? round(($data['count'] / $totalCustomers) * 100, 1)
                : 0.0;

            $segmentsDistribution[] = [
                'segment' => $segment,
                'count' => $data['count'],
                'percentage' => $percentage,
                'total_monetary' => round($data['total_monetary'], 2),
                'color' => self::SEGMENT_COLORS[$segment] ?? '#6b7280',
            ];

            $monetaryBySegment[] = [
                'segment' => $segment,
                'total_spent' => round($data['total_monetary'], 2),
            ];
        }

        usort($segmentsDistribution, fn ($a, $b) => $b['count'] <=> $a['count']);
        usort($monetaryBySegment, fn ($a, $b) => $b['total_spent'] <=> $a['total_spent']);

        $summary = [
            'segments_distribution' => $segmentsDistribution,
            'monetary_by_segment' => $monetaryBySegment,
            'totals' => [
                'total_customers' => $totalCustomers,
                'total_with_orders' => $totalWithOrders,
                'avg_recency_days' => $avgRecencyDays ?? null,
                'avg_frequency' => $avgFrequency,
                'avg_monetary' => $avgMonetary,
            ],
        ];

        // --- Build filter options ---
        $presentSegments = array_values(array_unique(array_keys($segmentData)));
        sort($presentSegments);

        $filters = [
            'segments' => $presentSegments,
            'orders_range' => [
                'min' => (int) $stats->min_orders,
                'max' => (int) $stats->max_orders,
            ],
            'spent_range' => [
                'min' => (float) $stats->min_spent,
                'max' => (float) $stats->max_spent,
            ],
        ];

        // --- Write all cache keys ---
        Cache::put("rfm_boundaries:{$store->id}", ['r' => $rBounds, 'f' => $fBounds, 'm' => $mBounds], $ttl);
        Cache::put("rfm_summary:{$store->id}", $summary, $ttl);
        Cache::put("rfm_filters:{$store->id}", $filters, $ttl);
        Cache::put("rfm_segment_ids:{$store->id}", $segmentIds, $ttl);
    }

    /**
     * Empty RFM summary structure for stores with no customers.
     */
    private function emptyRfmSummary(): array
    {
        return [
            'segments_distribution' => [],
            'monetary_by_segment' => [],
            'totals' => [
                'total_customers' => 0,
                'total_with_orders' => 0,
                'avg_recency_days' => null,
                'avg_frequency' => null,
                'avg_monetary' => null,
            ],
        ];
    }
}
