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
            ->get();

        $formattedSuggestions = SuggestionResource::collection($suggestions)->resolve();

        return [
            'id' => $this->uuid,
            'status' => $this->status?->value,
            'analysis_type' => $this->analysis_type?->value ?? 'general',
            'analysis_type_label' => $this->analysis_type?->label() ?? 'Análise Geral',
            'error_message' => $this->error_message,
            'summary' => $this->summary,
            'suggestions' => $formattedSuggestions,
            'alerts' => $this->alerts ?? [],
            'opportunities' => $this->opportunities ?? [],
            'premium_summary' => $this->summary['premium_summary'] ?? null,
            'period_start' => $this->period_start?->format('Y-m-d'),
            'period_end' => $this->period_end?->format('Y-m-d'),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'email_sent_at' => $this->email_sent_at?->toISOString(),
            'email_error' => $this->email_error,
            // Progress tracking fields
            'current_stage' => $this->current_stage ?? 0,
            'total_stages' => $this->total_stages ?? 9,
            'progress_percentage' => $this->getProgressPercentage(),
            'current_stage_name' => $this->getCurrentStageName(),
        ];
    }
}
