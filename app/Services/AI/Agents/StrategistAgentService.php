<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AIManager;
use App\Services\AI\JsonExtractor;
use App\Services\AI\Prompts\StrategistAgentPrompt;
use Illuminate\Support\Facades\Log;

class StrategistAgentService
{
    public function __construct(
        private AIManager $aiManager
    ) {}

    /**
     * Execute the strategist agent.
     */
    public function execute(array $context): array
    {
        $prompt = StrategistAgentPrompt::get($context);

        $response = $this->aiManager->chat([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'temperature' => 0.7, // Higher temperature for creative suggestions
            'max_tokens' => 8192, // Ensure enough tokens for 9 detailed suggestions
        ]);

        return $this->parseResponse($response);
    }

    /**
     * Parse the AI response into structured data.
     */
    private function parseResponse(string $response): array
    {
        Log::debug('Strategist raw response (first 2000 chars): '.substr($response, 0, 2000));

        $json = $this->extractJson($response);

        if ($json === null) {
            Log::warning('Strategist: Could not extract JSON from response');

            return [
                'suggestions' => [],
                'general_observations' => 'Could not generate suggestions',
            ];
        }

        Log::debug('Strategist JSON extracted', ['has_suggestions' => isset($json['suggestions']), 'keys' => array_keys($json)]);

        // Validate suggestions structure
        $suggestions = $json['suggestions'] ?? [];
        $validatedSuggestions = [];

        Log::info('Strategist: Found '.count($suggestions).' suggestions in JSON');

        foreach ($suggestions as $index => $suggestion) {
            if ($this->isValidSuggestion($suggestion)) {
                $validatedSuggestions[] = $this->normalizeSuggestion($suggestion);
            } else {
                Log::warning("Strategist: Suggestion {$index} failed validation", [
                    'has_category' => ! empty($suggestion['category']),
                    'has_title' => ! empty($suggestion['title']),
                    'has_description' => ! empty($suggestion['description']),
                    'has_recommended_action' => ! empty($suggestion['recommended_action']),
                    'has_expected_impact' => ! empty($suggestion['expected_impact']),
                ]);
            }
        }

        Log::info('Strategist: '.count($validatedSuggestions).' suggestions passed validation');

        return [
            'suggestions' => $validatedSuggestions,
            'general_observations' => $json['general_observations'] ?? '',
        ];
    }

    /**
     * Extract JSON from response text.
     */
    private function extractJson(string $content): ?array
    {
        return JsonExtractor::extract($content, 'Strategist');
    }

    /**
     * Check if a suggestion has required fields.
     */
    private function isValidSuggestion(array $suggestion): bool
    {
        $required = ['category', 'title', 'description', 'recommended_action', 'expected_impact'];

        foreach ($required as $field) {
            if (empty($suggestion[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalize suggestion structure.
     */
    private function normalizeSuggestion(array $suggestion): array
    {
        return [
            'category' => $suggestion['category'],
            'title' => substr($suggestion['title'], 0, 255),
            'description' => $suggestion['description'],
            'recommended_action' => $suggestion['recommended_action'],
            'expected_impact' => $this->normalizeImpact($suggestion['expected_impact']),
            'target_metrics' => $suggestion['target_metrics'] ?? [],
            'implementation_time' => $suggestion['implementation_time'] ?? 'immediate',
            'specific_data' => $suggestion['specific_data'] ?? [],
            'data_justification' => $suggestion['data_justification'] ?? '',
        ];
    }

    /**
     * Normalize impact value.
     */
    private function normalizeImpact(string $impact): string
    {
        $impact = strtolower(trim($impact));

        if (in_array($impact, ['high', 'alto'])) {
            return 'high';
        }
        if (in_array($impact, ['medium', 'medio', 'médio'])) {
            return 'medium';
        }

        return 'low';
    }
}
