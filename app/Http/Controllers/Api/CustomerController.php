<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\SyncedCustomer;
use App\Models\SyncedOrder;
use App\Services\CustomerRfmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CustomerController extends Controller
{
    /**
     * Order statuses that represent a completed/valid sale.
     * Cancelled orders are excluded from aggregation.
     */
    private const VALID_ORDER_STATUSES = ['pending', 'paid', 'shipped', 'delivered'];

    public function __construct(
        private CustomerRfmService $rfmService
    ) {}

    /**
     * Build a subquery that aggregates order data per (customer_email, store_id).
     * Only orders with a valid status (non-cancelled) are counted.
     */
    private function buildOrdersSubquery(int $storeId)
    {
        return SyncedOrder::selectRaw(
            'LOWER(TRIM(customer_email)) AS agg_email,
             COUNT(*) AS real_orders_count,
             SUM(total) AS real_orders_total,
             MIN(external_created_at) AS real_first_order,
             MAX(external_created_at) AS real_last_order'
        )
            ->where('store_id', $storeId)
            ->whereNotNull('customer_email')
            ->where('customer_email', '!=', '')
            ->whereIn('status', self::VALID_ORDER_STATUSES)
            ->whereNull('deleted_at')
            ->groupByRaw('LOWER(TRIM(customer_email))');
    }

    /**
     * List customers with optional filters, sorting, and RFM enrichment.
     * Order data from synced_orders is preferred over the Nuvemshop-provided
     * totals when a matching email is found (COALESCE with real aggregates).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Nenhuma loja ativa encontrada.'], 404);
        }

        $ordersSubquery = $this->buildOrdersSubquery($store->id);

        // Base query: left join with the orders aggregate subquery.
        // We do NOT alias synced_customers because SoftDeletes adds
        // "synced_customers"."deleted_at" is null automatically.
        $query = SyncedCustomer::query()
            ->leftJoinSub(
                $ordersSubquery,
                'ord',
                fn ($join) => $join->whereRaw('LOWER(TRIM(synced_customers.email)) = ord.agg_email')
            )
            ->where('synced_customers.store_id', $store->id)
            ->selectRaw(
                'synced_customers.*,
                 COALESCE(ord.real_orders_count, synced_customers.total_orders)      AS effective_total_orders,
                 COALESCE(ord.real_orders_total, synced_customers.total_spent)        AS effective_total_spent,
                 COALESCE(ord.real_first_order, synced_customers.first_order_at)      AS effective_first_order_at,
                 COALESCE(ord.real_last_order,  synced_customers.last_order_at)       AS effective_last_order_at,
                 (ord.real_orders_count IS NOT NULL)                                  AS has_real_orders'
            );

        // Apply text search — sanitize to prevent wildcard injection in ILIKE
        if ($request->filled('search')) {
            $search = $request->input('search');
            $sanitized = str_replace(['%', '_'], ['\\%', '\\_'], mb_substr(
                preg_replace('/[\x00-\x1F\x7F]/u', '', $search) ?? '',
                0,
                255
            ));
            $pattern = '%'.$sanitized.'%';
            $query->where(function ($q) use ($pattern) {
                $q->where('synced_customers.name', 'ILIKE', $pattern)
                    ->orWhere('synced_customers.email', 'ILIKE', $pattern)
                    ->orWhere('synced_customers.phone', 'ILIKE', $pattern);
            });
        }

        // --- Filters ---

        // RFM segment: resolve customer IDs from the cached segment->IDs map
        if ($request->filled('rfm_segment')) {
            $segment = $request->input('rfm_segment');
            $ids = $this->rfmService->getCustomerIdsForSegment($store, $segment);

            if (empty($ids)) {
                return response()->json([
                    'data' => [],
                    'total' => 0,
                    'last_page' => 1,
                    'current_page' => 1,
                ]);
            }

            $query->whereIn('synced_customers.id', $ids);
        }

        // First-order date range — uses effective column (real data preferred)
        if ($request->filled('first_order_start')) {
            $query->whereRaw('COALESCE(ord.real_first_order, synced_customers.first_order_at) >= ?', [$request->input('first_order_start')]);
        }
        if ($request->filled('first_order_end')) {
            $query->whereRaw('COALESCE(ord.real_first_order, synced_customers.first_order_at) <= ?', [$request->input('first_order_end')]);
        }

        // Last-order date range — uses effective column
        if ($request->filled('last_order_start')) {
            $query->whereRaw('COALESCE(ord.real_last_order, synced_customers.last_order_at) >= ?', [$request->input('last_order_start')]);
        }
        if ($request->filled('last_order_end')) {
            $query->whereRaw('COALESCE(ord.real_last_order, synced_customers.last_order_at) <= ?', [$request->input('last_order_end')]);
        }

        // Orders count range — uses effective column
        if ($request->filled('min_orders')) {
            $query->whereRaw('COALESCE(ord.real_orders_count, synced_customers.total_orders) >= ?', [(int) $request->input('min_orders')]);
        }
        if ($request->filled('max_orders')) {
            $query->whereRaw('COALESCE(ord.real_orders_count, synced_customers.total_orders) <= ?', [(int) $request->input('max_orders')]);
        }

        // Spent range — uses effective column
        if ($request->filled('min_spent')) {
            $query->whereRaw('COALESCE(ord.real_orders_total, synced_customers.total_spent) >= ?', [(float) $request->input('min_spent')]);
        }
        if ($request->filled('max_spent')) {
            $query->whereRaw('COALESCE(ord.real_orders_total, synced_customers.total_spent) <= ?', [(float) $request->input('max_spent')]);
        }

        // Days without purchase range — convert to last_order_at boundaries using effective column
        if ($request->filled('days_without_purchase_min')) {
            $maxDate = now()->subDays((int) $request->input('days_without_purchase_min'));
            $query->whereRaw('COALESCE(ord.real_last_order, synced_customers.last_order_at) <= ?', [$maxDate]);
        }
        if ($request->filled('days_without_purchase_max')) {
            $minDate = now()->subDays((int) $request->input('days_without_purchase_max'));
            $query->whereRaw('COALESCE(ord.real_last_order, synced_customers.last_order_at) >= ?', [$minDate]);
        }

        // --- Sorting ---
        // Map frontend column names to the effective (COALESCE'd) expressions
        $sortColumnMap = [
            'name' => 'synced_customers.name',
            'total_orders' => 'effective_total_orders',
            'total_spent' => 'effective_total_spent',
            'last_order_at' => 'effective_last_order_at',
            'first_order_at' => 'effective_first_order_at',
            'external_created_at' => 'synced_customers.external_created_at',
            'created_at' => 'synced_customers.created_at',
        ];

        $requestedSort = $request->input('sort_by', 'last_order_at');
        $sortColumn = $sortColumnMap[$requestedSort] ?? 'effective_last_order_at';
        $sortOrder = strtolower($request->input('sort_order', $request->has('sort_by') ? 'asc' : 'desc')) === 'desc' ? 'desc' : 'asc';

        // For aliased columns (effective_*) we must use orderByRaw
        if (str_starts_with($sortColumn, 'effective_')) {
            $direction = strtoupper($sortOrder);
            $query->orderByRaw("{$sortColumn} {$direction} NULLS LAST");
        } else {
            $query->orderBy($sortColumn, $sortOrder);
        }

        // --- Pagination ---
        $perPage = min((int) $request->input('per_page', 10), 100);
        $paginator = $query->paginate($perPage);

        // --- Overwrite model attributes with effective (real) values ---
        // This allows CustomerResource and model accessors to use enriched data
        // transparently without knowing about the join.
        $paginator->getCollection()->each(function ($customer) {
            $customer->total_orders = (int) $customer->effective_total_orders;
            $customer->total_spent = (float) $customer->effective_total_spent;
            $customer->first_order_at = $customer->effective_first_order_at
                ? Carbon::parse($customer->effective_first_order_at)
                : null;
            $customer->last_order_at = $customer->effective_last_order_at
                ? Carbon::parse($customer->effective_last_order_at)
                : null;
        });

        // --- Enrich with RFM data (only current page, using cached boundaries) ---
        $rfmScores = $this->rfmService->scoreCustomers($store, $paginator->getCollection());

        $paginator->getCollection()->each(function ($customer) use ($rfmScores) {
            $rfm = $rfmScores[$customer->id] ?? null;
            $customer->rfm_segment = $rfm['segment'] ?? null;
            $customer->rfm_scores = $rfm['scores'] ?? null;
        });

        return response()->json([
            'data' => CustomerResource::collection($paginator->getCollection()),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'current_page' => $paginator->currentPage(),
        ]);
    }

    /**
     * Return available filter options for the customer listing.
     */
    public function filters(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Nenhuma loja ativa encontrada.'], 404);
        }

        return response()->json($this->rfmService->getFilters($store));
    }

    /**
     * Return RFM summary (segment distribution, monetary data, totals).
     */
    public function rfmSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Nenhuma loja ativa encontrada.'], 404);
        }

        return response()->json($this->rfmService->getRfmSummary($store));
    }
}
