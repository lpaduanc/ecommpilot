<?php

namespace App\Jobs;

use App\Models\Analysis;
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

    public int $timeout = 120;

    public function __construct(
        private Analysis $analysis
    ) {}

    public function handle(AnalysisService $analysisService): void
    {
        Log::info("Processing analysis: {$this->analysis->id}");

        try {
            $analysisService->processAnalysis($this->analysis);
            Log::info("Analysis completed: {$this->analysis->id}");
        } catch (\Exception $e) {
            Log::error("Analysis failed {$this->analysis->id}: {$e->getMessage()}");
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
