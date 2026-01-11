<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AIManager;
use App\Services\AI\Prompts\StrategistAgentPrompt;

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
        $json = $this->extractJson($response);

        if ($json === null) {
            return [
                'suggestions' => [],
                'general_observations' => 'Could not generate suggestions',
            ];
        }

        // Validate suggestions structure
        $suggestions = $json['suggestions'] ?? [];
        $validatedSuggestions = [];

        foreach ($suggestions as $suggestion) {
            if ($this->isValidSuggestion($suggestion)) {
                $validatedSuggestions[] = $this->normalizeSuggestion($suggestion);
            }
        }

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
        // Try to find JSON in markdown code blocks
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        // Try direct parse
        $decoded = json_decode($content, true);
        if ($decoded !== null) {
            return $decoded;
        }

        // Try to find JSON object in text
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return null;
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
