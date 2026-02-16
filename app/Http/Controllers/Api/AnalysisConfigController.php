<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalysisConfigRequest;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AnalysisConfigController extends Controller
{
    /**
     * Exibe as configurações de análise da loja
     */
    public function show(Store $store): JsonResponse
    {
        Gate::authorize('view', $store);

        $config = $store->getAnalysisConfig();

        return response()->json([
            'data' => $config,
        ]);
    }

    /**
     * Atualiza as configurações de análise da loja
     */
    public function update(AnalysisConfigRequest $request, Store $store): JsonResponse
    {
        Gate::authorize('update', $store);

        $validated = $request->validated();

        $store->updateAnalysisConfig($validated);

        return response()->json([
            'message' => 'Configurações de análise atualizadas com sucesso.',
            'data' => $store->getAnalysisConfig(),
        ]);
    }

    /**
     * Busca produtos da loja para o autocomplete
     */
    public function searchProducts(Request $request, Store $store): JsonResponse
    {
        Gate::authorize('view', $store);

        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = $request->input('search');

        $products = $store->products()
            ->search($search)
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->price,
                    'stock_quantity' => $product->stock_quantity,
                    'is_active' => $product->is_active,
                    'image' => $product->images[0] ?? null,
                    'is_gift' => $product->isGift(),
                    'is_out_of_stock' => $product->isOutOfStock(),
                ];
            });

        return response()->json([
            'data' => $products,
        ]);
    }
}
