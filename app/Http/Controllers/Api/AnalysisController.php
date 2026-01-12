<?php

namespace App\Http\Controllers\Api;

use App\Enums\AnalysisStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnalysisResource;
use App\Jobs\ProcessAnalysisJob;
use App\Models\ActivityLog;
use App\Models\Analysis;
use App\Models\Suggestion;
use App\Models\SuggestionResult;
use App\Services\AI\Memory\FeedbackLoopService;
use App\Services\AnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalysisController extends Controller
{
    public function __construct(
        private AnalysisService $analysisService
    ) {}

    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        // Skip rate limit in local/dev environment
        $isLocalEnv = app()->isLocal() || app()->environment('testing', 'dev', 'development');

        if (! $store) {
            return response()->json([
                'analysis' => null,
                'pending_analysis' => null,
                'next_available_at' => null,
                'credits' => $user->ai_credits,
            ]);
        }

        $analysis = Analysis::where('user_id', $user->id)
            ->where('store_id', $store->id)
            ->completed()
            ->latest()
            ->first();

        $pendingAnalysis = $this->analysisService->getPendingAnalysis($user, $store);

        // In local env, don't return next_available_at to allow unlimited requests
        $nextAvailableAt = $isLocalEnv ? null : $this->analysisService->getNextAvailableAt($user, $store);

        return response()->json([
            'analysis' => $analysis ? new AnalysisResource($analysis) : null,
            'pending_analysis' => $pendingAnalysis ? [
                'id' => $pendingAnalysis->id,
                'status' => $pendingAnalysis->status->value,
                'created_at' => $pendingAnalysis->created_at->toISOString(),
            ] : null,
            'next_available_at' => $nextAvailableAt?->toISOString(),
            'credits' => $user->ai_credits,
        ]);
    }

    public function request(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json([
                'message' => 'Nenhuma loja conectada.',
            ], 400);
        }

        // Check if there's already a pending analysis for this store
        $pendingAnalysis = $this->analysisService->getPendingAnalysis($user, $store);
        if ($pendingAnalysis) {
            return response()->json([
                'message' => 'Já existe uma análise em andamento para esta loja.',
                'pending_analysis' => [
                    'id' => $pendingAnalysis->id,
                    'status' => $pendingAnalysis->status->value,
                    'created_at' => $pendingAnalysis->created_at->toISOString(),
                ],
            ], 409);
        }

        // Skip rate limit and credits check in local/dev environment
        $isLocalEnv = app()->isLocal() || app()->environment('testing', 'dev', 'development');

        // Check rate limit (per store)
        if (! $isLocalEnv && ! $this->analysisService->canRequestAnalysis($user, $store)) {
            $nextAvailableAt = $this->analysisService->getNextAvailableAt($user, $store);

            return response()->json([
                'message' => 'Aguarde para solicitar uma nova análise para esta loja.',
                'next_available_at' => $nextAvailableAt?->toISOString(),
            ], 429);
        }

        // Check credits
        if (! $isLocalEnv && ! $user->hasCredits()) {
            return response()->json([
                'message' => 'Créditos insuficientes.',
                'credits' => $user->ai_credits,
            ], 402);
        }

        // Deduct credit (skip in local env)
        if (! $isLocalEnv) {
            $user->deductCredits();
        }

        // Create analysis
        $analysis = Analysis::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'status' => AnalysisStatus::Pending,
            'period_start' => now()->subDays(15),
            'period_end' => now(),
        ]);

        ActivityLog::log('analysis.requested', $analysis);

        // Process analysis
        ProcessAnalysisJob::dispatch($analysis);

        return response()->json([
            'message' => 'Análise solicitada com sucesso.',
            'analysis' => new AnalysisResource($analysis),
            'pending_analysis' => [
                'id' => $analysis->id,
                'status' => $analysis->status->value,
                'created_at' => $analysis->created_at->toISOString(),
            ],
            'credits' => $user->fresh()->ai_credits,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        $analyses = Analysis::where('user_id', $user->id)
            ->completed()
            ->latest()
            ->limit(10)
            ->get();

        return response()->json(AnalysisResource::collection($analyses));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $analysis = Analysis::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $analysis) {
            return response()->json(['message' => 'Análise não encontrada.'], 404);
        }

        return response()->json(new AnalysisResource($analysis));
    }

    public function markSuggestionDone(Request $request, int $analysisId, string $suggestionId): JsonResponse
    {
        $analysis = Analysis::where('id', $analysisId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $analysis) {
            return response()->json(['message' => 'Análise não encontrada.'], 404);
        }

        $suggestions = $analysis->suggestions ?? [];

        foreach ($suggestions as &$suggestion) {
            if ($suggestion['id'] === $suggestionId) {
                $suggestion['is_done'] = true;
                break;
            }
        }

        $analysis->update(['suggestions' => $suggestions]);

        return response()->json(['message' => 'Sugestão marcada como concluída.']);
    }

    /**
     * List persistent suggestions for the active store.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['suggestions' => [], 'total' => 0]);
        }

        $query = Suggestion::where('store_id', $store->id);

        // Filter by status
        if ($request->has('status') && in_array($request->status, Suggestion::getStatuses())) {
            $query->where('status', $request->status);
        }

        // Filter by category
        if ($request->has('category') && in_array($request->category, Suggestion::getCategories())) {
            $query->where('category', $request->category);
        }

        // Filter by impact
        if ($request->has('impact') && in_array($request->impact, ['high', 'medium', 'low'])) {
            $query->where('expected_impact', $request->impact);
        }

        $suggestions = $query->orderBy('priority')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'suggestions' => $suggestions->items(),
            'total' => $suggestions->total(),
            'current_page' => $suggestions->currentPage(),
            'last_page' => $suggestions->lastPage(),
        ]);
    }

    /**
     * Update a suggestion status.
     */
    public function updateSuggestion(Request $request, int $suggestionId): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,in_progress,completed,ignored',
        ]);

        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Nenhuma loja ativa.'], 400);
        }

        $suggestion = Suggestion::where('id', $suggestionId)
            ->where('store_id', $store->id)
            ->first();

        if (! $suggestion) {
            return response()->json(['message' => 'Sugestão não encontrada.'], 404);
        }

        $oldStatus = $suggestion->status;
        $newStatus = $request->status;

        // Update status
        $suggestion->status = $newStatus;

        if ($newStatus === Suggestion::STATUS_COMPLETED) {
            $suggestion->completed_at = now();

            // Create result record for feedback loop
            $this->createSuggestionResult($suggestion);
        }

        $suggestion->save();

        ActivityLog::log('suggestion.status_changed', $suggestion, [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        return response()->json([
            'message' => 'Status da sugestão atualizado.',
            'suggestion' => $suggestion,
        ]);
    }

    /**
     * Get a single suggestion details.
     */
    public function showSuggestion(Request $request, int $suggestionId): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Nenhuma loja ativa.'], 400);
        }

        $suggestion = Suggestion::where('id', $suggestionId)
            ->where('store_id', $store->id)
            ->with('result')
            ->first();

        if (! $suggestion) {
            return response()->json(['message' => 'Sugestão não encontrada.'], 404);
        }

        return response()->json(['suggestion' => $suggestion]);
    }

    /**
     * Get suggestion statistics for the active store.
     */
    public function suggestionStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['stats' => null]);
        }

        $suggestions = Suggestion::where('store_id', $store->id)->get();

        $stats = [
            'total' => $suggestions->count(),
            'by_status' => [
                'pending' => $suggestions->where('status', 'pending')->count(),
                'in_progress' => $suggestions->where('status', 'in_progress')->count(),
                'completed' => $suggestions->where('status', 'completed')->count(),
                'ignored' => $suggestions->where('status', 'ignored')->count(),
            ],
            'by_impact' => [
                'high' => $suggestions->where('expected_impact', 'high')->count(),
                'medium' => $suggestions->where('expected_impact', 'medium')->count(),
                'low' => $suggestions->where('expected_impact', 'low')->count(),
            ],
            'by_category' => $suggestions->groupBy('category')->map->count()->toArray(),
            'completion_rate' => $suggestions->count() > 0
                ? round(($suggestions->where('status', 'completed')->count() / $suggestions->count()) * 100, 1)
                : 0,
        ];

        return response()->json(['stats' => $stats]);
    }

    /**
     * Create a suggestion result for feedback loop.
     */
    private function createSuggestionResult(Suggestion $suggestion): void
    {
        // Check if result already exists
        if ($suggestion->result()->exists()) {
            return;
        }

        $feedbackLoop = app(FeedbackLoopService::class);
        $feedbackLoop->captureMetricsBefore($suggestion);
    }
}
