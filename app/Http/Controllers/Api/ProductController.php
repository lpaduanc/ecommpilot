<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\SyncedProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (!$store) {
            return response()->json([
                'data' => [],
                'total' => 0,
                'last_page' => 1,
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

        $products = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => ProductResource::collection($products),
            'total' => $products->total(),
            'last_page' => $products->lastPage(),
            'current_page' => $products->currentPage(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (!$store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        $product = SyncedProduct::where('store_id', $store->id)
            ->where('id', $id)
            ->first();

        if (!$product) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        return response()->json(new ProductResource($product));
    }

    public function performance(Request $request, int $id): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (!$store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        $product = SyncedProduct::where('store_id', $store->id)
            ->where('id', $id)
            ->first();

        if (!$product) {
            return response()->json(['message' => 'Produto não encontrado.'], 404);
        }

        // Get sales data from orders (last 30 days)
        $orders = $store->orders()
            ->paid()
            ->where('external_created_at', '>=', now()->subDays(30))
            ->get();

        $quantitySold = 0;
        $revenueGenerated = 0;

        foreach ($orders as $order) {
            $items = $order->items ?? [];
            foreach ($items as $item) {
                $itemProductId = $item['product_id'] ?? null;
                $itemSku = $item['sku'] ?? null;
                
                // Match by external_id or SKU
                if ($itemProductId === $product->external_id || $itemSku === $product->sku) {
                    $quantitySold += $item['quantity'] ?? 1;
                    $revenueGenerated += $item['total'] ?? 0;
                }
            }
        }

        $daysInPeriod = 30;
        $averagePerDay = $daysInPeriod > 0 ? round($quantitySold / $daysInPeriod, 2) : 0;

        return response()->json([
            'product_id' => $product->id,
            'period_days' => $daysInPeriod,
            'quantity_sold' => $quantitySold,
            'revenue_generated' => $revenueGenerated,
            'average_per_day' => $averagePerDay,
        ]);
    }
}

