<?php

namespace App\Http\Controllers\Api;

use App\Enums\AnalysisStatus;
use App\Enums\AnalysisType;
use App\Http\Controllers\Controller;
use App\Http\Resources\AnalysisResource;
use App\Http\Resources\SuggestionResource;
use App\Jobs\ProcessAnalysisJob;
use App\Mail\AnalysisCompletedMail;
use App\Models\ActivityLog;
use App\Models\Analysis;
use App\Models\Suggestion;
use App\Services\AI\Memory\FeedbackLoopService;
use App\Services\AnalysisService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AnalysisController extends Controller
{
    public function __construct(
        private AnalysisService $analysisService,
        private PlanLimitService $planLimitService
    ) {}

    /**
     * Safe log to mail channel - silently ignores permission errors.
     */
    private function safeMailLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('mail')->{$level}($message, $context);
        } catch (\Throwable) {
            // Fallback to default log channel if mail channel fails
            try {
                Log::{$level}('[mail] '.$message, $context);
            } catch (\Throwable) {
                // Silently ignore
            }
        }
    }

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
                'plan_limits' => null,
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

        // Get plan limits info
        $plan = $user->currentPlan();
        $planLimits = $plan ? [
            'analysis_per_day' => $plan->analysis_per_day,
            'remaining_today' => $this->planLimitService->getRemainingAnalysesToday($user, $store),
            'used_today' => $this->planLimitService->getAnalysesUsedToday($user, $store),
        ] : null;

        return response()->json([
            'analysis' => $analysis ? new AnalysisResource($analysis) : null,
            'pending_analysis' => $pendingAnalysis ? [
                'id' => $pendingAnalysis->id,
                'status' => $pendingAnalysis->status->value,
                'created_at' => $pendingAnalysis->created_at->toISOString(),
                'current_stage' => $pendingAnalysis->current_stage ?? 0,
                'total_stages' => $pendingAnalysis->total_stages ?? 9,
                'progress_percentage' => $pendingAnalysis->getProgressPercentage(),
                'current_stage_name' => $pendingAnalysis->getCurrentStageName(),
                'error_message' => $pendingAnalysis->error_message,
            ] : null,
            'next_available_at' => $nextAvailableAt?->toISOString(),
            'plan_limits' => $planLimits,
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

        // Check if there's already a pending/processing analysis for this store
        $pendingAnalysis = $this->analysisService->getPendingAnalysis($user, $store);
        if ($pendingAnalysis && $pendingAnalysis->status !== \App\Enums\AnalysisStatus::Failed) {
            return response()->json([
                'message' => 'Já existe uma análise em andamento para esta loja.',
                'pending_analysis' => [
                    'id' => $pendingAnalysis->id,
                    'status' => $pendingAnalysis->status->value,
                    'created_at' => $pendingAnalysis->created_at->toISOString(),
                    'current_stage' => $pendingAnalysis->current_stage ?? 0,
                    'total_stages' => $pendingAnalysis->total_stages ?? 9,
                    'progress_percentage' => $pendingAnalysis->getProgressPercentage(),
                    'current_stage_name' => $pendingAnalysis->getCurrentStageName(),
                    'error_message' => $pendingAnalysis->error_message,
                ],
            ], 409);
        }

        // Skip rate limit and credits check in local/dev environment
        $isLocalEnv = app()->isLocal() || app()->environment('testing', 'dev', 'development');

        // Check plan access to AI Analysis (skip in local/dev environment)
        if (! $isLocalEnv && ! $this->planLimitService->canAccessAnalysis($user)) {
            return response()->json([
                'message' => 'Seu plano não inclui acesso às Análises IA.',
                'upgrade_required' => true,
            ], 403);
        }

        // Check plan daily limit
        if (! $isLocalEnv && ! $this->planLimitService->canRequestAnalysis($user, $store)) {
            $plan = $user->currentPlan();
            $remaining = $this->planLimitService->getRemainingAnalysesToday($user, $store);

            return response()->json([
                'message' => 'Você atingiu o limite de análises diárias do seu plano.',
                'remaining_today' => $remaining,
                'daily_limit' => $plan?->analysis_per_day ?? 0,
                'upgrade_required' => true,
            ], 429);
        }

        // Check rate limit (per store) - still applies within daily limit
        if (! $isLocalEnv && ! $this->analysisService->canRequestAnalysis($user, $store)) {
            $nextAvailableAt = $this->analysisService->getNextAvailableAt($user, $store);

            return response()->json([
                'message' => 'Aguarde para solicitar uma nova análise para esta loja.',
                'next_available_at' => $nextAvailableAt?->toISOString(),
            ], 429);
        }

        // Record analysis usage for plan limits
        if (! $isLocalEnv) {
            $this->planLimitService->recordAnalysisUsage($user, $store);
        }

        // Validate analysis type (only allow implemented types)
        $availableValues = array_map(fn ($type) => $type->value, AnalysisType::availableTypes());
        $validated = $request->validate([
            'analysis_type' => ['sometimes', 'string', 'in:'.implode(',', $availableValues)],
        ]);

        // Create analysis
        $analysis = Analysis::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'status' => AnalysisStatus::Pending,
            'analysis_type' => $validated['analysis_type'] ?? 'general',
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
                'current_stage' => $analysis->current_stage ?? 0,
                'total_stages' => $analysis->total_stages ?? 9,
                'progress_percentage' => $analysis->getProgressPercentage(),
                'current_stage_name' => $analysis->getCurrentStageName(),
                'error_message' => $analysis->error_message,
            ],
            'remaining_today' => $this->planLimitService->getRemainingAnalysesToday($user, $store),
        ]);
    }

    /**
     * List available analysis types.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'types' => AnalysisType::toApiArray(),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json([]);
        }

        $analyses = Analysis::where('user_id', $user->id)
            ->where('store_id', $store->id)
            ->completed()
            ->latest()
            ->limit(10)
            ->get();

        return response()->json(AnalysisResource::collection($analyses));
    }

    public function show(Request $request, Analysis $analysis): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Nenhuma loja ativa.'], 400);
        }

        // Verify analysis ownership
        if ($analysis->user_id !== $user->id || $analysis->store_id !== $store->id) {
            return response()->json(['message' => 'Análise não encontrada.'], 404);
        }

        return response()->json(new AnalysisResource($analysis));
    }

    public function markSuggestionDone(Request $request, Analysis $analysis, string $suggestionId): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Nenhuma loja ativa.'], 400);
        }

        // Verify analysis ownership
        if ($analysis->user_id !== $user->id || $analysis->store_id !== $store->id) {
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
            'suggestions' => SuggestionResource::collection($suggestions->items()),
            'total' => $suggestions->total(),
            'current_page' => $suggestions->currentPage(),
            'last_page' => $suggestions->lastPage(),
        ]);
    }

    /**
     * Update a suggestion status.
     */
    public function updateSuggestion(Request $request, Suggestion $suggestion): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:new,rejected,accepted,in_progress,completed,pending,ignored',
        ]);

        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Nenhuma loja ativa.'], 400);
        }

        // Verify suggestion ownership
        if ($suggestion->store_id !== $store->id) {
            return response()->json(['message' => 'Sugestão não encontrada.'], 404);
        }

        $oldStatus = $suggestion->status;
        $newStatus = $request->status;

        // Map legacy status to new status
        if ($newStatus === 'pending') {
            $newStatus = Suggestion::STATUS_NEW;
        } elseif ($newStatus === 'ignored') {
            $newStatus = Suggestion::STATUS_REJECTED;
        }

        // Update status
        $suggestion->status = $newStatus;

        // Handle accepted_at for accepted status
        if ($newStatus === Suggestion::STATUS_ACCEPTED && ! $suggestion->accepted_at) {
            $suggestion->accepted_at = now();
        }

        // Handle rejection - clear accepted_at
        if ($newStatus === Suggestion::STATUS_REJECTED) {
            $suggestion->accepted_at = null;
        }

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
            'suggestion' => new SuggestionResource($suggestion),
        ]);
    }

    /**
     * Accept a suggestion - moves to tracking page.
     */
    public function acceptSuggestion(Request $request, Suggestion $suggestion): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Nenhuma loja ativa.'], 400);
        }

        // Verify suggestion ownership
        if ($suggestion->store_id !== $store->id) {
            return response()->json(['message' => 'Sugestão não encontrada.'], 404);
        }

        $oldStatus = $suggestion->status;
        $suggestion->accept();

        ActivityLog::log('suggestion.accepted', $suggestion, [
            'old_status' => $oldStatus,
        ]);

        return response()->json([
            'message' => 'Sugestão aceita e movida para acompanhamento.',
            'suggestion' => new SuggestionResource($suggestion->fresh()),
        ]);
    }

    /**
     * Reject a suggestion - stays on analysis page.
     */
    public function rejectSuggestion(Request $request, Suggestion $suggestion): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Nenhuma loja ativa.'], 400);
        }

        // Verify suggestion ownership
        if ($suggestion->store_id !== $store->id) {
            return response()->json(['message' => 'Sugestão não encontrada.'], 404);
        }

        $oldStatus = $suggestion->status;
        $suggestion->reject();

        ActivityLog::log('suggestion.rejected', $suggestion, [
            'old_status' => $oldStatus,
        ]);

        return response()->json([
            'message' => 'Sugestão rejeitada.',
            'suggestion' => new SuggestionResource($suggestion->fresh()),
        ]);
    }

    /**
     * List accepted suggestions for tracking page (grouped by analysis).
     */
    public function trackingSuggestions(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['analyses' => [], 'total' => 0]);
        }

        $query = Suggestion::where('store_id', $store->id)
            ->onTrackingPage()
            ->with(['analysis' => function ($q) {
                $q->select('id', 'created_at', 'period_start', 'period_end');
            }]);

        // Filter by status (only tracking statuses)
        if ($request->has('status') && in_array($request->status, Suggestion::getTrackingPageStatuses())) {
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

        $suggestions = $query->orderBy('analysis_id', 'desc')
            ->orderBy('priority')
            ->get();

        // Group by analysis
        $groupedByAnalysis = $suggestions->groupBy('analysis_id')->map(function ($analysisSuggestions) {
            $analysis = $analysisSuggestions->first()->analysis;

            return [
                'analysis_id' => $analysis->id,
                'analysis_date' => $analysis->created_at->toISOString(),
                'period_start' => $analysis->period_start?->toISOString(),
                'period_end' => $analysis->period_end?->toISOString(),
                'suggestions' => SuggestionResource::collection($analysisSuggestions),
                'stats' => [
                    'total' => $analysisSuggestions->count(),
                    'accepted' => $analysisSuggestions->where('status', Suggestion::STATUS_ACCEPTED)->count(),
                    'in_progress' => $analysisSuggestions->where('status', Suggestion::STATUS_IN_PROGRESS)->count(),
                    'completed' => $analysisSuggestions->where('status', Suggestion::STATUS_COMPLETED)->count(),
                ],
            ];
        })->values();

        return response()->json([
            'analyses' => $groupedByAnalysis,
            'total' => $suggestions->count(),
            'stats' => [
                'accepted' => $suggestions->where('status', Suggestion::STATUS_ACCEPTED)->count(),
                'in_progress' => $suggestions->where('status', Suggestion::STATUS_IN_PROGRESS)->count(),
                'completed' => $suggestions->where('status', Suggestion::STATUS_COMPLETED)->count(),
            ],
        ]);
    }

    /**
     * Submit feedback for a completed suggestion (V4 Feedback Loop).
     */
    public function submitFeedback(Request $request, Suggestion $suggestion): JsonResponse
    {
        $request->validate([
            'was_successful' => 'required|boolean',
            'feedback' => 'nullable|string|max:1000',
            'metrics_impact' => 'nullable|array',
        ]);

        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Nenhuma loja ativa.'], 400);
        }

        // Verify suggestion ownership
        if ($suggestion->store_id !== $store->id) {
            return response()->json(['message' => 'Sugestão não encontrada.'], 404);
        }

        // Update suggestion with feedback
        $suggestion->update([
            'was_successful' => $request->was_successful,
            'feedback' => $request->feedback,
            'metrics_impact' => $request->metrics_impact,
        ]);

        // Process feedback for learning (using the trait from StoreAnalysisService)
        try {
            $analysisService = app(\App\Services\AI\Agents\StoreAnalysisService::class);
            $analysisService->processSuggestionFeedback(
                $suggestion,
                $request->was_successful,
                $request->metrics_impact
            );
        } catch (\Exception $e) {
            Log::error('Erro ao processar feedback da sugestão', [
                'suggestion_id' => $suggestion->id,
                'error' => $e->getMessage(),
            ]);
        }

        ActivityLog::log('suggestion.feedback_submitted', $suggestion, [
            'was_successful' => $request->was_successful,
            'has_feedback' => ! empty($request->feedback),
            'has_metrics' => ! empty($request->metrics_impact),
        ]);

        return response()->json([
            'message' => 'Feedback registrado com sucesso.',
            'suggestion' => new SuggestionResource($suggestion),
        ]);
    }

    /**
     * Get a single suggestion details.
     */
    public function showSuggestion(Request $request, Suggestion $suggestion): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Nenhuma loja ativa.'], 400);
        }

        // Verify suggestion ownership
        if ($suggestion->store_id !== $store->id) {
            return response()->json(['message' => 'Sugestão não encontrada.'], 404);
        }

        // Load result relationship
        $suggestion->load('result');

        return response()->json(['suggestion' => new SuggestionResource($suggestion)]);
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

        // Count by new status system
        $newCount = $suggestions->whereIn('status', [Suggestion::STATUS_NEW, Suggestion::STATUS_PENDING])->count();
        $rejectedCount = $suggestions->whereIn('status', [Suggestion::STATUS_REJECTED, Suggestion::STATUS_IGNORED])->count();
        $acceptedCount = $suggestions->where('status', Suggestion::STATUS_ACCEPTED)->count();
        $inProgressCount = $suggestions->where('status', Suggestion::STATUS_IN_PROGRESS)->count();
        $completedCount = $suggestions->where('status', Suggestion::STATUS_COMPLETED)->count();

        // Tracking page suggestions (accepted + in_progress + completed)
        $trackingTotal = $acceptedCount + $inProgressCount + $completedCount;

        $stats = [
            'total' => $suggestions->count(),
            'by_status' => [
                // New status names
                'new' => $newCount,
                'rejected' => $rejectedCount,
                'accepted' => $acceptedCount,
                'in_progress' => $inProgressCount,
                'completed' => $completedCount,
                // Legacy aliases for backward compatibility
                'pending' => $newCount,
                'ignored' => $rejectedCount,
            ],
            'by_page' => [
                'analysis' => $newCount + $rejectedCount,
                'tracking' => $trackingTotal,
            ],
            'by_impact' => [
                'high' => $suggestions->where('expected_impact', 'high')->count(),
                'medium' => $suggestions->where('expected_impact', 'medium')->count(),
                'low' => $suggestions->where('expected_impact', 'low')->count(),
            ],
            'by_category' => $suggestions->groupBy('category')->map->count()->toArray(),
            'completion_rate' => $trackingTotal > 0
                ? round(($completedCount / $trackingTotal) * 100, 1)
                : 0,
            'acceptance_rate' => $suggestions->count() > 0
                ? round(($trackingTotal / $suggestions->count()) * 100, 1)
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

    /**
     * Resend completion email for an analysis.
     */
    public function resendEmail(Request $request, Analysis $analysis): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Nenhuma loja ativa.'], 400);
        }

        // Verify analysis ownership and status
        if ($analysis->user_id !== $user->id || $analysis->store_id !== $store->id) {
            return response()->json(['message' => 'Análise não encontrada ou ainda não foi concluída.'], 404);
        }

        if ($analysis->status !== AnalysisStatus::Completed) {
            return response()->json(['message' => 'Análise não encontrada ou ainda não foi concluída.'], 404);
        }

        // Verificar se usuário tem e-mail
        if (empty($user->email)) {
            return response()->json(['message' => 'Usuário sem e-mail cadastrado.'], 400);
        }

        try {
            // Carregar relacionamentos necessários
            $analysis->load(['store', 'persistentSuggestions']);

            // Logar tentativa (safe log)
            $this->safeMailLog('info', 'Tentando reenviar email de conclusao de analise', [
                'analysis_id' => $analysis->id,
                'user_email' => $user->email,
                'store_name' => $analysis->store->name ?? 'N/A',
            ]);

            // Usar mesmo método do job para consistência
            $mailData = new AnalysisCompletedMail($analysis);
            $htmlContent = \Illuminate\Support\Facades\View::make('emails.analysis-completed', [
                'userName' => $mailData->userName,
                'storeName' => $mailData->storeName,
                'periodStart' => $mailData->periodStart,
                'periodEnd' => $mailData->periodEnd,
                'healthScore' => $mailData->healthScore,
                'healthStatus' => $mailData->healthStatus,
                'mainInsight' => $mailData->mainInsight,
                'suggestions' => $mailData->suggestions,
            ])->render();

            // Enviar via EmailConfigurationService
            $emailService = app(\App\Services\EmailConfigurationService::class);
            $result = $emailService->sendHtmlEmail(
                'ai-analysis',
                $user->email,
                $user->name ?? '',
                "Análise de IA Concluída - {$mailData->storeName}",
                $htmlContent
            );

            if (! $result['success']) {
                throw new \RuntimeException($result['message']);
            }

            // Atualizar email_sent_at e limpar email_error
            $analysis->update([
                'email_sent_at' => now(),
                'email_error' => null,
            ]);

            // Logar sucesso (safe log)
            $this->safeMailLog('info', 'Email de conclusao de analise reenviado com sucesso', [
                'analysis_id' => $analysis->id,
                'user_email' => $user->email,
                'store_name' => $analysis->store->name ?? 'N/A',
            ]);

            // Criar ActivityLog
            ActivityLog::log('email.analysis_resent', $analysis, [
                'user_email' => $user->email,
                'store_name' => $analysis->store->name ?? 'N/A',
            ]);

            return response()->json(['message' => 'E-mail reenviado com sucesso.']);
        } catch (\Throwable $e) {
            // Salvar email_error
            $analysis->update([
                'email_error' => $e->getMessage(),
            ]);

            // Logar erro (safe log)
            $this->safeMailLog('error', 'Falha ao reenviar email de conclusao de analise', [
                'analysis_id' => $analysis->id,
                'user_email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao enviar e-mail: '.$e->getMessage(),
            ], 500);
        }
    }
}
