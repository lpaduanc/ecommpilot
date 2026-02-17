<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\SyncedProduct;
use App\Services\ProductAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private ProductAnalyticsService $analyticsService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([
                'data' => [],
                'total' => 0,
                'last_page' => 1,
                'totals' => [],
                'abc_analysis' => [],
            ]);
        }

        $periodDays = $this->resolvePeriodDays($request);

        // Build base query with filters that can be applied at database level
        $query = SyncedProduct::where('store_id', $store->id)
            ->search($request->input('search'));

        // Apply stock status filters (database level)
        if ($request->has('status')) {
            if ($request->input('status') === 'low_stock') {
                $query->lowStock();
            } elseif ($request->input('status') === 'out_of_stock') {
                $query->outOfStock();
            }
        }

        // For ABC category filter, use cached categories and filter at DB level
        $abcCategory = $request->input('abc_category');
        if ($abcCategory) {
            $abcCategories = $this->analyticsService->getABCCategories($store, $periodDays);
            $productIdsInCategory = array_keys(array_filter($abcCategories, fn ($cat) => strtoupper($cat) === strtoupper($abcCategory)));

            if (empty($productIdsInCategory)) {
                return response()->json([
                    'data' => [],
                    'total' => 0,
                    'last_page' => 1,
                    'current_page' => 1,
                    'totals' => $this->analyticsService->getEmptyTotals(),
                    'abc_analysis' => $this->analyticsService->calculateABCAnalysisSummary($store, $periodDays),
                ]);
            }

            $query->whereIn('id', $productIdsInCategory);
        }

        // For stock health filter, we need to pre-calculate stock health for all products
        // This is cached and much faster than the previous approach
        $stockHealth = $request->input('stock_health');
        if ($stockHealth) {
            $stockHealthData = $this->analyticsService->getStockHealthMapping($store, $periodDays);
            $productIdsWithHealth = array_keys(array_filter($stockHealthData, fn ($health) => $health === $stockHealth));

            if (empty($productIdsWithHealth)) {
                return response()->json([
                    'data' => [],
                    'total' => 0,
                    'last_page' => 1,
                    'current_page' => 1,
                    'totals' => $this->analyticsService->getEmptyTotals(),
                    'abc_analysis' => $this->analyticsService->calculateABCAnalysisSummary($store, $periodDays),
                ]);
            }

            $query->whereIn('id', $productIdsWithHealth);
        }

        // Paginate at database level
        $perPage = $request->input('per_page', 10);
        $paginator = $query->orderBy('name')->paginate($perPage);

        // Calculate analytics ONLY for products on current page
        $analyticsData = $this->analyticsService->calculateProductAnalytics($store, $paginator->getCollection(), $periodDays);

        // Fix total_products to reflect all matching products, not just current page
        $analyticsData['totals']['total_products'] = $paginator->total();

        // Attach analytics to current page products
        foreach ($paginator as $product) {
            if (isset($analyticsData['products'][$product->id])) {
                $product->analytics = $analyticsData['products'][$product->id];
            }
        }

        return response()->json([
            'data' => ProductResource::collection($paginator->getCollection()),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'current_page' => $paginator->currentPage(),
            'totals' => $analyticsData['totals'],
            'abc_analysis' => $analyticsData['abc_analysis'],
        ]);
    }

    public function show(Request $request, SyncedProduct $product): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        // Verify product belongs to active store
        if ($product->store_id !== $store->id) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        return response()->json(new ProductResource($product));
    }

    public function performance(Request $request, SyncedProduct $product): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        // Verify product belongs to active store
        if ($product->store_id !== $store->id) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        // Get sales data from orders (last 30 days)
        $orders = $store->orders()
            ->paid()
            ->where('external_created_at', '>=', now()->subDays(30))
            ->get();

        $quantitySold = 0;
        $revenueGenerated = 0;

        // Convert product external_id to string for comparison
        $productExternalId = (string) $product->external_id;

        foreach ($orders as $order) {
            $items = $order->items ?? [];
            foreach ($items as $item) {
                // Convert item product_id to string for comparison
                $itemProductId = isset($item['product_id']) ? (string) $item['product_id'] : null;
                $itemName = $item['product_name'] ?? $item['name'] ?? null;

                // Match by external_id or name (case-insensitive)
                $matchesId = $itemProductId !== null && $itemProductId === $productExternalId;
                $matchesName = $itemName !== null && strcasecmp($itemName, $product->name) === 0;

                if ($matchesId || $matchesName) {
                    $quantity = $item['quantity'] ?? 1;
                    // Use 'total' if available, otherwise calculate from unit_price * quantity
                    if (isset($item['total'])) {
                        $itemTotal = (float) $item['total'];
                    } else {
                        $price = $item['unit_price'] ?? $item['price'] ?? 0;
                        $itemTotal = $quantity * $price;
                    }

                    $quantitySold += $quantity;
                    $revenueGenerated += $itemTotal;
                }
            }
        }

        $daysInPeriod = 30;
        $averagePerDay = $daysInPeriod > 0 ? round($quantitySold / $daysInPeriod, 2) : 0;

        return response()->json([
            'product_id' => $product->id,
            'period_days' => $daysInPeriod,
            'quantity_sold' => $quantitySold,
            'revenue_generated' => round($revenueGenerated, 2),
            'average_per_day' => $averagePerDay,
        ]);
    }

    /**
     * Convert named period to days, falling back to period_days param or default.
     */
    private function resolvePeriodDays(Request $request): int
    {
        $period = $request->input('period');

        if ($period === 'custom') {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            if ($startDate && $endDate) {
                return max(1, (int) \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1);
            }

            return 30;
        }

        if ($period) {
            return match ($period) {
                'yesterday' => 1,
                'today' => 1,
                'last_7_days' => 7,
                'last_15_days' => 15,
                'last_30_days' => 30,
                'this_month' => (int) now()->day,
                'last_month' => (int) now()->subMonth()->daysInMonth,
                'all_time' => 3650,
                default => (int) $request->input('period_days', 30),
            };
        }

        return (int) $request->input('period_days', 1); // default: yesterday (1 day)
    }
}
