<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analysis\SuggestionImpactAnalysisService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestionImpactDashboardController extends Controller
{
    public function __construct(
        private SuggestionImpactAnalysisService $impactService,
        private PlanLimitService $planService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Verificar permissão do plano
        if (! $this->planService->canAccessImpactDashboard($user)) {
            return response()->json([
                'success' => false,
                'error' => 'feature_not_available',
                'message' => 'Faça upgrade para o plano Enterprise para acessar o Dashboard de Impacto nas Vendas.',
                'upgrade_required' => true,
            ], 403);
        }

        $store = $user->activeStore;

        if (! $store) {
            return response()->json([
                'success' => false,
                'error' => 'no_active_store',
                'message' => 'Nenhuma loja ativa selecionada.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $this->impactService->getImpactDashboard($store),
        ]);
    }

    /**
     * Endpoint para atualizar feedback de sucesso da sugestão
     */
    public function updateFeedback(Request $request, string $suggestionId): JsonResponse
    {
        $validated = $request->validate([
            'was_successful' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json([
                'success' => false,
                'error' => 'no_active_store',
            ], 400);
        }

        $suggestion = $store->suggestions()
            ->where('uuid', $suggestionId)
            ->firstOrFail();

        $suggestion->update([
            'was_successful' => $validated['was_successful'],
        ]);

        return response()->json([
            'success' => true,
            'suggestion' => [
                'id' => $suggestion->uuid,
                'was_successful' => $suggestion->was_successful,
            ],
        ]);
    }
}
