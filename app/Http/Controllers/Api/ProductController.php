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

        $query = SyncedProduct::where('store_id', $store->id)
            ->search($request->input('search'))
            ->orderBy('name');

        if ($request->has('status')) {
            if ($request->input('status') === 'low_stock') {
                $query->lowStock();
            } elseif ($request->input('status') === 'out_of_stock') {
                $query->outOfStock();
            }
        }

        // Get all products for analytics calculation (before pagination)
        $allProducts = $query->get();

        // Calculate analytics for all products
        $analyticsData = $this->analyticsService->calculateProductAnalytics($store, $allProducts);

        // Attach analytics data to products
        foreach ($allProducts as $product) {
            if (isset($analyticsData['products'][$product->id])) {
                $product->analytics = $analyticsData['products'][$product->id];
            }
        }

        // Apply ABC category filter (after analytics calculation)
        $abcCategory = $request->input('abc_category');
        if ($abcCategory) {
            $allProducts = $allProducts->filter(function ($product) use ($abcCategory) {
                return isset($product->analytics['abc_category'])
                    && strtoupper($product->analytics['abc_category']) === strtoupper($abcCategory);
            });
        }

        // Apply stock health filter (after analytics calculation)
        $stockHealth = $request->input('stock_health');
        if ($stockHealth) {
            $allProducts = $allProducts->filter(function ($product) use ($stockHealth) {
                $productHealth = $product->analytics['stock_health'] ?? null;

                return $productHealth === $stockHealth;
            });
        }

        // Apply pagination manually
        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        // Get paginated subset
        $paginatedProducts = $allProducts->slice($offset, $perPage)->values();
        $total = $allProducts->count();
        $lastPage = (int) ceil($total / $perPage) ?: 1;

        return response()->json([
            'data' => ProductResource::collection($paginatedProducts),
            'total' => $total,
            'last_page' => $lastPage,
            'current_page' => $page,
            'totals' => $analyticsData['totals'],
            'abc_analysis' => $analyticsData['abc_analysis'],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        $product = SyncedProduct::where('store_id', $store->id)
            ->where('id', $id)
            ->first();

        if (! $product) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        return response()->json(new ProductResource($product));
    }

    public function performance(Request $request, int $id): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        $product = SyncedProduct::where('store_id', $store->id)
            ->where('id', $id)
            ->first();

        if (! $product) {
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
}
