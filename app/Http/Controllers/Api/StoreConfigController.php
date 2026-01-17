<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConfigRequest;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class StoreConfigController extends Controller
{
    public function getNiches(): JsonResponse
    {
        $niches = config('niches.niches', []);

        $formatted = collect($niches)->map(function ($niche, $key) {
            return [
                'value' => $key,
                'label' => $niche['label'],
                'subcategories' => collect($niche['subcategories'])->map(function ($label, $value) {
                    return [
                        'value' => $value,
                        'label' => $label,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return response()->json([
            'data' => $formatted,
        ]);
    }

    public function show(Store $store): JsonResponse
    {
        Gate::authorize('view', $store);

        return response()->json([
            'data' => [
                'niche' => $store->niche,
                'niche_subcategory' => $store->niche_subcategory,
                'niche_label' => $store->getNicheLabel(),
                'subcategory_label' => $store->getSubcategoryLabel(),
                'monthly_goal' => $store->monthly_goal,
                'annual_goal' => $store->annual_goal,
                'target_ticket' => $store->target_ticket,
                'monthly_revenue' => $store->monthly_revenue,
                'monthly_visits' => $store->monthly_visits,
                'competitors' => $store->competitors ?? [],
            ],
        ]);
    }

    public function update(StoreConfigRequest $request, Store $store): JsonResponse
    {
        Gate::authorize('update', $store);

        $validated = $request->validated();

        $store->update($validated);

        return response()->json([
            'message' => 'Configurações atualizadas com sucesso.',
            'data' => [
                'niche' => $store->niche,
                'niche_subcategory' => $store->niche_subcategory,
                'niche_label' => $store->getNicheLabel(),
                'subcategory_label' => $store->getSubcategoryLabel(),
                'monthly_goal' => $store->monthly_goal,
                'annual_goal' => $store->annual_goal,
                'target_ticket' => $store->target_ticket,
                'monthly_revenue' => $store->monthly_revenue,
                'monthly_visits' => $store->monthly_visits,
                'competitors' => $store->competitors ?? [],
            ],
        ]);
    }
}
