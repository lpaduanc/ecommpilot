<?php

namespace App\Jobs;

use App\Models\Analysis;
use App\Models\SystemSetting;
use App\Services\AI\Agents\StoreAnalysisService;
use App\Services\AnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public int $timeout = 300; // Increased to 5 minutes for agent pipeline

    public function __construct(
        private Analysis $analysis
    ) {}

    public function handle(AnalysisService $legacyService, StoreAnalysisService $agentService): void
    {
        Log::info("Processing analysis: {$this->analysis->id}");

        try {
            // Check if we should use the new agent pipeline
            $useAgentPipeline = SystemSetting::get('ai.use_agent_pipeline', true);

            if ($useAgentPipeline) {
                // Use new agent-based analysis
                $this->analysis->markAsProcessing();
                $store = $this->analysis->store;

                if (! $store) {
                    throw new \RuntimeException('Store not found for analysis');
                }

                $result = $agentService->execute($store, $this->analysis);
                Log::info("Agent pipeline completed for analysis {$this->analysis->id}", [
                    'suggestions_count' => $result['suggestions_count'] ?? 0,
                    'niche' => $result['niche'] ?? 'unknown',
                ]);
            } else {
                // Use legacy analysis service
                $legacyService->processAnalysis($this->analysis);
            }

            Log::info("Analysis completed: {$this->analysis->id}");
        } catch (\Exception $e) {
            Log::error("Analysis failed {$this->analysis->id}: {$e->getMessage()}", [
                'exception' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->analysis->markAsFailed();

        // Refund credits
        $this->analysis->user->addCredits($this->analysis->credits_used);

        Log::error("ProcessAnalysisJob failed for analysis {$this->analysis->id}: {$exception->getMessage()}");
    }
}
