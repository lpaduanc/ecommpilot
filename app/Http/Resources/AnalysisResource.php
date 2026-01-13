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
            'id' => $this->id,
            'status' => $this->status?->value,
            'error_message' => $this->error_message,
            'summary' => $this->summary,
            'suggestions' => $formattedSuggestions,
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
