<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAnalysisResource extends JsonResource
{
    /**
     * Transform the resource into an array for listing.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],
            'store' => [
                'id' => $this->store?->id,
                'name' => $this->store?->name,
            ],
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'health_score' => $this->healthScore(),
            'health_status' => $this->summary['health_status'] ?? null,
            'suggestions_count' => $this->persistent_suggestions_count ?? 0,
            'credits_used' => $this->credits_used,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
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
