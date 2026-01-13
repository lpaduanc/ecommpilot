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
        Log::info("Processing analysis: {$this->analysis->id}");

        // Refresh the analysis to get latest state
        $this->analysis->refresh();

        // Skip if already completed or failed (avoid duplicate processing)
        if ($this->analysis->status === AnalysisStatus::Completed) {
            Log::info("Analysis {$this->analysis->id} already completed, skipping");

            return;
        }

        if ($this->analysis->status === AnalysisStatus::Failed) {
            Log::info("Analysis {$this->analysis->id} already failed, skipping");

            return;
        }

        try {
            // Mark as processing
            $this->analysis->markAsProcessing();

            // Check if we should use the new agent pipeline
            $useAgentPipeline = SystemSetting::get('ai.use_agent_pipeline', true);

            if ($useAgentPipeline) {
                $this->processWithAgentPipeline($agentService, $liteAgentService);
            } else {
                // Use legacy analysis service
                $legacyService->processAnalysis($this->analysis);
            }

            Log::info("Analysis completed: {$this->analysis->id}");
        } catch (\Throwable $e) {
            Log::error("Analysis failed {$this->analysis->id}: {$e->getMessage()}", [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
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
            Log::info("Using LITE pipeline for analysis {$this->analysis->id} (provider: anthropic)");
            $result = $liteAgentService->execute($store, $this->analysis);
        } else {
            Log::info("Using FULL pipeline for analysis {$this->analysis->id}");
            $result = $agentService->execute($store, $this->analysis);
        }

        Log::info("Agent pipeline completed for analysis {$this->analysis->id}", [
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
                    Log::info("Refunded {$this->analysis->credits_used} credits to user {$this->analysis->user_id}");
                }
            }
        } catch (\Throwable $e) {
            Log::error("Error handling analysis failure: {$e->getMessage()}");
        }
    }

    /**
     * Get a user-friendly error message based on the exception.
     */
    private function getUserFriendlyErrorMessage(\Throwable $exception): string
    {
        // Log the technical error for debugging
        Log::error("Analysis error details: {$exception->getMessage()}");

        // Return a generic, user-friendly message
        return 'Ocorreu um erro ao processar sua análise. Por favor, tente novamente em alguns minutos.';
    }

    /**
     * Handle a job failure (called by Laravel after all retries exhausted).
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessAnalysisJob failed permanently for analysis {$this->analysis->id}: {$exception->getMessage()}");

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
