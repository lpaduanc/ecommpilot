<?php

namespace App\Http\Resources;

use App\Services\AnalysisLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAnalysisDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array with full details.
     */
    public function toArray(Request $request): array
    {
        $logService = app(AnalysisLogService::class);

        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
            'store' => [
                'id' => $this->store?->id,
                'name' => $this->store?->name,
                'platform' => $this->store?->platform?->value,
                'platform_label' => $this->store?->platform?->label(),
            ],
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'summary' => $this->summary,
            'alerts' => $this->alerts,
            'opportunities' => $this->opportunities,
            'suggestions' => $this->persistentSuggestions->map(fn ($s) => [
                'id' => $s->id,
                'category' => $s->category,
                'title' => $s->title,
                'description' => $s->description,
                'expected_impact' => $s->expected_impact,
                'priority' => $s->priority,
                'status' => $s->status,
            ])->toArray(),
            'execution_logs' => $logService->getFormattedLogs($this->resource),
            'credits_used' => $this->credits_used,
            'error_message' => $this->error_message,
            'period_start' => $this->period_start?->toIso8601String(),
            'period_end' => $this->period_end?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'email_sent_at' => $this->email_sent_at?->toIso8601String(),
            'email_error' => $this->email_error,
            'duration_seconds' => $this->completed_at && $this->created_at
                ? $this->created_at->diffInSeconds($this->completed_at)
                : null,
            // Progress tracking
            'current_stage' => $this->current_stage ?? 0,
            'total_stages' => $this->total_stages ?? 9,
            'progress_percentage' => $this->getProgressPercentage(),
            'current_stage_name' => $this->getCurrentStageName(),
        ];
    }
}
