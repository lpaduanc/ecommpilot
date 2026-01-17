<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Models\Analysis;
use App\Models\SystemSetting;
use App\Services\AI\Agents\LiteStoreAnalysisService;
use App\Services\AI\Agents\StoreAnalysisService;
use App\Services\AnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Canal de log para analise
     */
    private string $logChannel = 'analysis';

    /**
     * Number of times to attempt the job.
     */
    public int $tries = 3;

    /**
     * Backoff in seconds between retries (exponential: 60, 120, 240).
     */
    public array $backoff = [60, 120, 240];

    /**
     * Maximum execution time in seconds (10 minutes for AI analysis).
     */
    public int $timeout = 600;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        private Analysis $analysis
    ) {
        // Use dedicated queue for analysis jobs with higher timeout
        $this->onQueue('analysis');
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        // Prevent duplicate analysis jobs for the same analysis
        return [
            (new WithoutOverlapping($this->analysis->id))
                ->releaseAfter(600) // Release lock after 10 minutes
                ->expireAfter(900), // Lock expires after 15 minutes
        ];
    }

    /**
     * Determine if the job should be unique.
     */
    public function uniqueId(): string
    {
        return 'analysis-'.$this->analysis->id;
    }

    public function handle(
        AnalysisService $legacyService,
        StoreAnalysisService $agentService,
        LiteStoreAnalysisService $liteAgentService
    ): void {
        $startTime = microtime(true);

        Log::channel($this->logChannel)->info('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel($this->logChannel)->info('║     PROCESS ANALYSIS - INICIO DA ANALISE AI                     ║');
        Log::channel($this->logChannel)->info('╚══════════════════════════════════════════════════════════════════╝');
        Log::channel($this->logChannel)->info('Configuracao da analise', [
            'analysis_id' => $this->analysis->id,
            'store_id' => $this->analysis->store_id,
            'user_id' => $this->analysis->user_id,
            'attempt' => $this->attempts(),
            'timestamp' => now()->toIso8601String(),
        ]);

        // Refresh the analysis to get latest state
        $this->analysis->refresh();

        // Skip if already completed or failed (avoid duplicate processing)
        if ($this->analysis->status === AnalysisStatus::Completed) {
            Log::channel($this->logChannel)->info('--- Analise ja concluida, ignorando', [
                'analysis_id' => $this->analysis->id,
                'status' => 'completed',
            ]);

            return;
        }

        if ($this->analysis->status === AnalysisStatus::Failed) {
            Log::channel($this->logChannel)->info('--- Analise ja falhou, ignorando', [
                'analysis_id' => $this->analysis->id,
                'status' => 'failed',
            ]);

            return;
        }

        try {
            // Mark as processing
            $this->analysis->markAsProcessing();

            Log::channel($this->logChannel)->info('>>> Status atualizado para PROCESSING', [
                'analysis_id' => $this->analysis->id,
            ]);

            // Check if we should use the new agent pipeline
            $useAgentPipeline = SystemSetting::get('ai.use_agent_pipeline', true);

            if ($useAgentPipeline) {
                $this->processWithAgentPipeline($agentService, $liteAgentService);
            } else {
                Log::channel($this->logChannel)->info('>>> Usando LEGACY pipeline', [
                    'analysis_id' => $this->analysis->id,
                ]);
                // Use legacy analysis service
                $legacyService->processAnalysis($this->analysis);
            }

            $totalTime = round((microtime(true) - $startTime), 2);

            Log::channel($this->logChannel)->info('╔══════════════════════════════════════════════════════════════════╗');
            Log::channel($this->logChannel)->info('║     PROCESS ANALYSIS - ANALISE CONCLUIDA COM SUCESSO            ║');
            Log::channel($this->logChannel)->info('╚══════════════════════════════════════════════════════════════════╝');
            Log::channel($this->logChannel)->info('Estatisticas finais da analise', [
                'analysis_id' => $this->analysis->id,
                'total_time_seconds' => $totalTime,
                'status' => 'success',
                'timestamp_end' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            $totalTime = round((microtime(true) - $startTime), 2);

            Log::channel($this->logChannel)->error('!!! ERRO NA ANALISE AI', [
                'analysis_id' => $this->analysis->id,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'attempt' => $this->attempts(),
                'total_time_seconds' => $totalTime,
            ]);

            // Mark as failed immediately (don't wait for failed() method)
            $this->handleFailure($e);

            throw $e;
        }
    }

    /**
     * Process analysis using the agent pipeline.
     */
    private function processWithAgentPipeline(
        StoreAnalysisService $agentService,
        LiteStoreAnalysisService $liteAgentService
    ): void {
        $store = $this->analysis->store;

        if (! $store) {
            throw new \RuntimeException('Store not found for analysis');
        }

        // Determine which pipeline to use based on provider
        $useLitePipeline = $this->shouldUseLitePipeline();

        if ($useLitePipeline) {
            Log::channel($this->logChannel)->info('>>> Usando LITE pipeline (Anthropic)', [
                'analysis_id' => $this->analysis->id,
                'store_id' => $store->id,
                'store_name' => $store->name,
                'provider' => 'anthropic',
            ]);
            $result = $liteAgentService->execute($store, $this->analysis);
        } else {
            Log::channel($this->logChannel)->info('>>> Usando FULL pipeline (Gemini)', [
                'analysis_id' => $this->analysis->id,
                'store_id' => $store->id,
                'store_name' => $store->name,
                'provider' => 'gemini',
            ]);
            $result = $agentService->execute($store, $this->analysis);
        }

        Log::channel($this->logChannel)->info('<<< Agent pipeline concluido', [
            'analysis_id' => $this->analysis->id,
            'suggestions_count' => $result['suggestions_count'] ?? 0,
            'niche' => $result['niche'] ?? 'unknown',
            'pipeline' => $result['pipeline'] ?? 'full',
        ]);
    }

    /**
     * Determine if the lite pipeline should be used.
     * Uses lite pipeline when the default provider is Anthropic (30k token/min limit).
     */
    private function shouldUseLitePipeline(): bool
    {
        $defaultProvider = SystemSetting::get('ai.provider', config('services.ai.default', 'gemini'));

        return $defaultProvider === 'anthropic';
    }

    /**
     * Handle failure - mark analysis as failed and refund credits.
     */
    private function handleFailure(\Throwable $exception): void
    {
        try {
            // Refresh to get latest state
            $this->analysis->refresh();

            // Only mark as failed if not already completed
            if ($this->analysis->status !== AnalysisStatus::Completed) {
                // Use a user-friendly message instead of technical details
                $userMessage = $this->getUserFriendlyErrorMessage($exception);
                $this->analysis->markAsFailed($userMessage);

                // Refund credits if user exists
                if ($this->analysis->user) {
                    $this->analysis->user->addCredits($this->analysis->credits_used ?? 1);
                    Log::channel($this->logChannel)->info('>>> Creditos reembolsados ao usuario', [
                        'analysis_id' => $this->analysis->id,
                        'user_id' => $this->analysis->user_id,
                        'credits_refunded' => $this->analysis->credits_used ?? 1,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::channel($this->logChannel)->error('!!! Erro ao tratar falha da analise', [
                'analysis_id' => $this->analysis->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get a user-friendly error message based on the exception.
     */
    private function getUserFriendlyErrorMessage(\Throwable $exception): string
    {
        // Log the technical error for debugging
        Log::channel($this->logChannel)->error('Detalhes tecnicos do erro', [
            'analysis_id' => $this->analysis->id,
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        // Return a generic, user-friendly message
        return 'Ocorreu um erro ao processar sua análise. Por favor, tente novamente em alguns minutos.';
    }

    /**
     * Handle a job failure (called by Laravel after all retries exhausted).
     */
    public function failed(\Throwable $exception): void
    {
        Log::channel($this->logChannel)->error('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel($this->logChannel)->error('║     PROCESS ANALYSIS - FALHA PERMANENTE                         ║');
        Log::channel($this->logChannel)->error('╚══════════════════════════════════════════════════════════════════╝');
        Log::channel($this->logChannel)->error('Detalhes da falha permanente', [
            'analysis_id' => $this->analysis->id,
            'error' => $exception->getMessage(),
            'exception_class' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => now()->toIso8601String(),
        ]);

        $this->handleFailure($exception);
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        // Give up after 30 minutes total
        return now()->addMinutes(30);
    }
}
