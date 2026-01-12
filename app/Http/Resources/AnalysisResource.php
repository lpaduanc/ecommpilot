<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Load persistent suggestions from the relationship
        $suggestions = $this->persistentSuggestions()
            ->orderBy('priority')
            ->get()
            ->map(function ($suggestion) {
                return [
                    'id' => $suggestion->id,
                    'category' => $suggestion->category,
                    'title' => $suggestion->title,
                    'description' => $suggestion->description,
                    'recommended_action' => $suggestion->recommended_action,
                    'expected_impact' => $suggestion->expected_impact,
                    'priority' => $suggestion->expected_impact, // Map expected_impact to priority for frontend compatibility
                    'priority_order' => $suggestion->priority,
                    'status' => $suggestion->status,
                    'target_metrics' => $suggestion->target_metrics,
                    'specific_data' => $suggestion->specific_data,
                    'data_justification' => $suggestion->data_justification,
                    'is_done' => $suggestion->status === 'completed',
                    'created_at' => $suggestion->created_at?->toISOString(),
                ];
            })
            ->toArray();

        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'summary' => $this->summary,
            'suggestions' => $suggestions,
            'alerts' => $this->alerts ?? [],
            'opportunities' => $this->opportunities ?? [],
            'period_start' => $this->period_start?->format('Y-m-d'),
            'period_end' => $this->period_end?->format('Y-m-d'),
            'credits_used' => $this->credits_used,
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
