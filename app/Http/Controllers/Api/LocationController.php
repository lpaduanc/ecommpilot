<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BrazilLocationsService;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private BrazilLocationsService $locationsService
    ) {}

    /**
     * Get all Brazilian states
     */
    public function states(): JsonResponse
    {
        $states = $this->locationsService->getStates();

        return response()->json([
            'data' => $states,
        ]);
    }

    /**
     * Get cities by state UF
     */
    public function cities(string $uf): JsonResponse
    {
        $uf = strtoupper($uf);

        // Validate UF format (2 uppercase letters)
        if (! preg_match('/^[A-Z]{2}$/', $uf)) {
            return response()->json([
                'message' => 'UF inválida. Use a sigla do estado (ex: SP, RJ).',
            ], 422);
        }

        $cities = $this->locationsService->getCitiesByState($uf);

        return response()->json([
            'data' => $cities,
        ]);
    }

    /**
     * Trigger Brazil locations sync (admin only)
     */
    public function sync(): JsonResponse
    {
        $success = $this->locationsService->sync();

        if (! $success) {
            return response()->json([
                'message' => 'Erro ao iniciar sincronização de localidades.',
            ], 500);
        }

        return response()->json([
            'message' => 'Sincronização de localidades iniciada com sucesso.',
        ]);
    }

    /**
     * Get sync status (admin only)
     */
    public function syncStatus(): JsonResponse
    {
        $status = $this->locationsService->getSyncStatus();

        return response()->json($status);
    }
}
