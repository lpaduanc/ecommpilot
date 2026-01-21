<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingSettingsController extends Controller
{
    /**
     * Retorna as configurações de tracking da loja ativa do usuário
     * (formatadas para uso no frontend)
     */
    public function show(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([
                'data' => null,
                'message' => 'Nenhuma loja ativa encontrada',
            ], 404);
        }

        return response()->json([
            'data' => $store->getTrackingConfigForFrontend(),
        ]);
    }

    /**
     * Retorna as configurações de tracking completas para edição
     */
    public function edit(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([
                'data' => null,
                'message' => 'Nenhuma loja ativa encontrada',
            ], 404);
        }

        return response()->json([
            'data' => $store->getTrackingSettings(),
        ]);
    }

    /**
     * Atualiza as configurações de tracking da loja ativa
     */
    public function update(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([
                'message' => 'Nenhuma loja ativa encontrada',
            ], 404);
        }

        $validated = $request->validate([
            'ga' => 'nullable|array',
            'ga.enabled' => 'boolean',
            'ga.measurement_id' => 'nullable|string|max:50',

            'gtag' => 'nullable|array',
            'gtag.enabled' => 'boolean',
            'gtag.tag_id' => 'nullable|string|max:50',

            'meta_pixel' => 'nullable|array',
            'meta_pixel.enabled' => 'boolean',
            'meta_pixel.pixel_id' => 'nullable|string|max:50',

            'clarity' => 'nullable|array',
            'clarity.enabled' => 'boolean',
            'clarity.project_id' => 'nullable|string|max:50',

            'hotjar' => 'nullable|array',
            'hotjar.enabled' => 'boolean',
            'hotjar.site_id' => 'nullable|string|max:50',
            'hotjar.snippet_version' => 'nullable|integer|min:1|max:10',
        ]);

        $store->update(['tracking_settings' => $validated]);

        return response()->json([
            'message' => 'Configurações de tracking atualizadas com sucesso',
            'data' => $store->getTrackingSettings(),
        ]);
    }

    /**
     * Atualiza configuração de um provider específico
     */
    public function updateProvider(Request $request, string $provider): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([
                'message' => 'Nenhuma loja ativa encontrada',
            ], 404);
        }

        $allowedProviders = ['ga', 'gtag', 'meta_pixel', 'clarity', 'hotjar'];

        if (! in_array($provider, $allowedProviders)) {
            return response()->json([
                'message' => 'Provider de tracking inválido',
            ], 422);
        }

        $rules = match ($provider) {
            'ga' => [
                'enabled' => 'boolean',
                'measurement_id' => 'nullable|string|max:50',
            ],
            'gtag' => [
                'enabled' => 'boolean',
                'tag_id' => 'nullable|string|max:50',
            ],
            'meta_pixel' => [
                'enabled' => 'boolean',
                'pixel_id' => 'nullable|string|max:50',
            ],
            'clarity' => [
                'enabled' => 'boolean',
                'project_id' => 'nullable|string|max:50',
            ],
            'hotjar' => [
                'enabled' => 'boolean',
                'site_id' => 'nullable|string|max:50',
                'snippet_version' => 'nullable|integer|min:1|max:10',
            ],
        };

        $validated = $request->validate($rules);

        $store->updateTrackingSetting($provider, $validated);

        return response()->json([
            'message' => 'Configuração de '.$provider.' atualizada com sucesso',
            'data' => $store->getTrackingSettings()[$provider],
        ]);
    }
}
