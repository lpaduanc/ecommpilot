<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnalysisResource;
use App\Jobs\ProcessAnalysisJob;
use App\Models\ActivityLog;
use App\Models\Analysis;
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

        if (!$store) {
            return response()->json([
                'analysis' => null,
                'next_available_at' => null,
                'credits' => $user->ai_credits,
            ]);
        }

        $analysis = Analysis::where('user_id', $user->id)
            ->where('store_id', $store->id)
            ->completed()
            ->latest()
            ->first();

        $nextAvailableAt = $this->analysisService->getNextAvailableAt($user);

        return response()->json([
            'analysis' => $analysis ? new AnalysisResource($analysis) : null,
            'next_available_at' => $nextAvailableAt?->toISOString(),
            'credits' => $user->ai_credits,
        ]);
    }

    public function request(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (!$store) {
            return response()->json([
                'message' => 'Nenhuma loja conectada.',
            ], 400);
        }

        // Check rate limit
        if (!$this->analysisService->canRequestAnalysis($user)) {
            $nextAvailableAt = $this->analysisService->getNextAvailableAt($user);

            return response()->json([
                'message' => 'Aguarde para solicitar uma nova análise.',
                'next_available_at' => $nextAvailableAt?->toISOString(),
            ], 429);
        }

        // Check credits
        if (!$user->hasCredits()) {
            return response()->json([
                'message' => 'Créditos insuficientes.',
                'credits' => $user->ai_credits,
            ], 402);
        }

        // Deduct credit
        $user->deductCredits();

        // Create analysis
        $analysis = Analysis::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'period_start' => now()->subDays(30),
            'period_end' => now(),
        ]);

        ActivityLog::log('analysis.requested', $analysis);

        // Process analysis
        ProcessAnalysisJob::dispatch($analysis);

        return response()->json([
            'message' => 'Análise solicitada com sucesso.',
            'analysis' => new AnalysisResource($analysis),
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

        if (!$analysis) {
            return response()->json(['message' => 'Análise não encontrada.'], 404);
        }

        return response()->json(new AnalysisResource($analysis));
    }

    public function markSuggestionDone(Request $request, int $analysisId, string $suggestionId): JsonResponse
    {
        $analysis = Analysis::where('id', $analysisId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$analysis) {
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
}

