<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\AnalysisExecutionLog;
use Illuminate\Support\Facades\Log;

class AnalysisLogService
{
    /**
     * Start logging for a specific stage.
     */
    public function startStage(Analysis $analysis, int $stage, string $stageName): AnalysisExecutionLog
    {
        try {
            return AnalysisExecutionLog::create([
                'analysis_id' => $analysis->id,
                'stage' => $stage,
                'stage_name' => $stageName,
                'status' => 'running',
                'started_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to start stage log', [
                'analysis_id' => $analysis->id,
                'stage' => $stage,
                'stage_name' => $stageName,
                'error' => $e->getMessage(),
            ]);

            // Fallback - criar log sem falhar o pipeline
            return new AnalysisExecutionLog([
                'analysis_id' => $analysis->id,
                'stage' => $stage,
                'stage_name' => $stageName,
                'status' => 'running',
                'started_at' => now(),
            ]);
        }
    }

    /**
     * Mark stage as completed with metrics and metadata.
     */
    public function completeStage(
        Analysis $analysis,
        int $stage,
        array $metrics = [],
        ?string $agent = null,
        ?string $provider = null,
        ?array $tokenUsage = null
    ): void {
        try {
            $log = AnalysisExecutionLog::where('analysis_id', $analysis->id)
                ->where('stage', $stage)
                ->latest()
                ->first();

            if (! $log) {
                Log::warning('No log found to complete', [
                    'analysis_id' => $analysis->id,
                    'stage' => $stage,
                ]);

                return;
            }

            $completedAt = now();
            $durationMs = $log->started_at
                ? (int) ($log->started_at->diffInMilliseconds($completedAt))
                : null;

            $log->update([
                'status' => 'completed',
                'completed_at' => $completedAt,
                'duration_ms' => $durationMs,
                'metrics' => $metrics,
                'agent_used' => $agent,
                'ai_provider' => $provider,
                'token_usage' => $tokenUsage,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to complete stage log', [
                'analysis_id' => $analysis->id,
                'stage' => $stage,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark stage as failed with error message.
     */
    public function failStage(Analysis $analysis, int $stage, string $error): void
    {
        try {
            $log = AnalysisExecutionLog::where('analysis_id', $analysis->id)
                ->where('stage', $stage)
                ->latest()
                ->first();

            if (! $log) {
                Log::warning('No log found to mark as failed', [
                    'analysis_id' => $analysis->id,
                    'stage' => $stage,
                ]);

                return;
            }

            $completedAt = now();
            $durationMs = $log->started_at
                ? (int) ($log->started_at->diffInMilliseconds($completedAt))
                : null;

            $log->update([
                'status' => 'failed',
                'completed_at' => $completedAt,
                'duration_ms' => $durationMs,
                'error_message' => $error,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to mark stage as failed', [
                'analysis_id' => $analysis->id,
                'stage' => $stage,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get formatted logs for display in admin panel.
     */
    public function getFormattedLogs(Analysis $analysis): array
    {
        $logs = AnalysisExecutionLog::where('analysis_id', $analysis->id)
            ->orderBy('stage')
            ->get();

        return $logs->map(function ($log) {
            return [
                'stage' => $log->stage,
                'stage_name' => $log->stage_name,
                'stage_label' => $this->formatStageLabel($log->stage_name),
                'status' => $log->status,
                'started_at' => $log->started_at?->toIso8601String(),
                'completed_at' => $log->completed_at?->toIso8601String(),
                'duration_ms' => $log->duration_ms,
                'duration_human' => $log->duration_ms ? $this->formatDuration($log->duration_ms) : null,
                'metrics' => $log->metrics,
                'agent_used' => $log->agent_used,
                'ai_provider' => $log->ai_provider,
                'token_usage' => $log->token_usage,
                'error_message' => $log->error_message,
            ];
        })->toArray();
    }

    /**
     * Format stage name to human-readable label.
     */
    private function formatStageLabel(string $stageName): string
    {
        $labels = [
            'niche_detection' => 'Detecção de Nicho',
            'historical_context' => 'Contexto Histórico',
            'rag_benchmarks' => 'Busca de Benchmarks (RAG)',
            'external_data' => 'Coleta de Dados Externos',
            'collector_agent' => 'Collector Agent',
            'analyst_agent' => 'Analyst Agent',
            'strategist_agent' => 'Strategist Agent',
            'critic_agent' => 'Critic Agent',
            'similarity_filtering' => 'Filtro de Similaridade',
        ];

        return $labels[$stageName] ?? ucwords(str_replace('_', ' ', $stageName));
    }

    /**
     * Format duration in milliseconds to human-readable format.
     */
    private function formatDuration(int $ms): string
    {
        if ($ms < 1000) {
            return "{$ms}ms";
        }

        $seconds = round($ms / 1000, 1);
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = round($seconds % 60, 1);

        return "{$minutes}m {$remainingSeconds}s";
    }
}
