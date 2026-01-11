<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AIManager;
use App\Services\AI\Prompts\CriticAgentPrompt;

class CriticAgentService
{
    public function __construct(
        private AIManager $aiManager
    ) {}

    /**
     * Execute the critic agent.
     */
    public function execute(array $data): array
    {
        $prompt = CriticAgentPrompt::get($data);

        $response = $this->aiManager->chat([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'temperature' => 0.3, // Lower temperature for consistent evaluation
            'max_tokens' => 8192, // Ensure enough tokens for detailed critique
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
            return $this->getDefaultResponse();
        }

        return $this->normalizeResponse($json);
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
     * Normalize the response structure.
     */
    private function normalizeResponse(array $response): array
    {
        $approvedSuggestions = [];

        foreach ($response['approved_suggestions'] ?? [] as $index => $approved) {
            $finalVersion = $approved['final_version'] ?? $approved['original'] ?? [];

            if (! empty($finalVersion)) {
                $approvedSuggestions[] = [
                    'original' => $approved['original'] ?? $finalVersion,
                    'improvements_applied' => $approved['improvements_applied'] ?? [],
                    'final_version' => $this->normalizeFinalVersion($finalVersion),
                    'quality_score' => floatval($approved['quality_score'] ?? 5.0),
                    'final_priority' => intval($approved['final_priority'] ?? ($index + 1)),
                ];
            }
        }

        return [
            'approved_suggestions' => $approvedSuggestions,
            'removed_suggestions' => $response['removed_suggestions'] ?? [],
            'general_analysis' => array_merge([
                'total_received' => 0,
                'total_approved' => count($approvedSuggestions),
                'total_removed' => count($response['removed_suggestions'] ?? []),
                'average_quality' => $this->calculateAverageQuality($approvedSuggestions),
                'observations' => '',
            ], $response['general_analysis'] ?? []),
        ];
    }

    /**
     * Normalize the final version of a suggestion.
     */
    private function normalizeFinalVersion(array $suggestion): array
    {
        return [
            'category' => $suggestion['category'] ?? 'general',
            'title' => substr($suggestion['title'] ?? '', 0, 255),
            'description' => $suggestion['description'] ?? '',
            'recommended_action' => $suggestion['recommended_action'] ?? '',
            'expected_impact' => $this->normalizeImpact($suggestion['expected_impact'] ?? 'medium'),
            'target_metrics' => $suggestion['target_metrics'] ?? [],
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

    /**
     * Calculate average quality score.
     */
    private function calculateAverageQuality(array $suggestions): float
    {
        if (empty($suggestions)) {
            return 0;
        }

        $total = array_sum(array_column($suggestions, 'quality_score'));

        return round($total / count($suggestions), 1);
    }

    /**
     * Get default response when parsing fails.
     */
    private function getDefaultResponse(): array
    {
        return [
            'approved_suggestions' => [],
            'removed_suggestions' => [],
            'general_analysis' => [
                'total_received' => 0,
                'total_approved' => 0,
                'total_removed' => 0,
                'average_quality' => 0,
                'observations' => 'Could not parse critic response',
            ],
        ];
    }
}
