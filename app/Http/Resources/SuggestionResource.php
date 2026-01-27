<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'analysis_id' => $this->analysis?->uuid,
            'category' => $this->category,
            'title' => $this->title,
            'description' => $this->description,
            'recommended_action' => $this->recommended_action,
            'expected_impact' => $this->expected_impact,
            'priority' => $this->expected_impact, // Map expected_impact to priority for frontend compatibility
            'priority_order' => $this->priority,
            'status' => $this->status,
            'target_metrics' => $this->target_metrics,
            'specific_data' => $this->specific_data,
            'data_justification' => $this->data_justification,
            'is_done' => $this->status === 'completed',
            'is_accepted' => $this->isOnTrackingPage(),
            'is_rejected' => $this->isRejected(),
            'is_on_analysis_page' => $this->isOnAnalysisPage(),
            'is_on_tracking_page' => $this->isOnTrackingPage(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
