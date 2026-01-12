<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AIManager;
use App\Services\AI\JsonExtractor;
use App\Services\AI\Prompts\CriticAgentPrompt;
use Illuminate\Support\Facades\Log;

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
        Log::info('Critic Agent received '.count($data['suggestions'] ?? []).' suggestions to review');

        $prompt = CriticAgentPrompt::get($data);

        $response = $this->aiManager->chat([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'temperature' => 0.3, // Lower temperature for consistent evaluation
            'max_tokens' => 16384, // Increased for detailed critique with many suggestions
        ]);

        return $this->parseResponse($response);
    }

    /**
     * Parse the AI response into structured data.
     */
    private function parseResponse(string $response): array
    {
        Log::debug('Critic raw response (first 2000 chars): '.substr($response, 0, 2000));

        $json = $this->extractJson($response);

        if ($json === null) {
            Log::warning('Critic: Could not extract JSON from response');

            return $this->getDefaultResponse();
        }

        Log::debug('Critic JSON extracted', ['keys' => array_keys($json)]);

        return $this->normalizeResponse($json);
    }

    /**
     * Extract JSON from response text.
     */
    private function extractJson(string $content): ?array
    {
        return JsonExtractor::extract($content, 'Critic');
    }

    /**
     * Normalize the response structure.
     */
    private function normalizeResponse(array $response): array
    {
        $approvedSuggestions = [];

        $rawApproved = $response['approved_suggestions'] ?? [];
        Log::info('Critic: JSON has '.count($rawApproved).' approved_suggestions in response');

        foreach ($rawApproved as $index => $approved) {
            $finalVersion = $approved['final_version'] ?? $approved['original'] ?? [];

            if (! empty($finalVersion)) {
                $approvedSuggestions[] = [
                    'original' => $approved['original'] ?? $finalVersion,
                    'improvements_applied' => $approved['improvements_applied'] ?? [],
                    'final_version' => $this->normalizeFinalVersion($finalVersion),
                    'quality_score' => floatval($approved['quality_score'] ?? 5.0),
                    'final_priority' => intval($approved['final_priority'] ?? ($index + 1)),
                ];
            } else {
                Log::warning("Critic: Suggestion {$index} has empty final_version", [
                    'has_final_version' => isset($approved['final_version']),
                    'has_original' => isset($approved['original']),
                ]);
            }
        }

        Log::info('Critic: Normalized '.count($approvedSuggestions).' suggestions');
        Log::info('Critic: Removed '.count($response['removed_suggestions'] ?? []).' suggestions');

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
