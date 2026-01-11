<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AIManager;
use App\Services\AI\Prompts\AnalystAgentPrompt;

class AnalystAgentService
{
    public function __construct(
        private AIManager $aiManager
    ) {}

    /**
     * Execute the analyst agent.
     */
    public function execute(array $data): array
    {
        $prompt = AnalystAgentPrompt::get($data);

        $response = $this->aiManager->chat([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'temperature' => 0.2, // Very low temperature for analytical accuracy
            'max_tokens' => 4096, // Adequate for detailed metrics analysis
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
            return $this->getDefaultAnalysis();
        }

        // Validate and normalize the structure
        return $this->normalizeAnalysis($json);
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
     * Normalize the analysis structure.
     */
    private function normalizeAnalysis(array $analysis): array
    {
        $default = $this->getDefaultAnalysis();

        return [
            'metrics' => array_merge($default['metrics'], $analysis['metrics'] ?? []),
            'anomalies' => $analysis['anomalies'] ?? [],
            'identified_patterns' => $analysis['identified_patterns'] ?? [],
            'overall_health' => array_merge(
                $default['overall_health'],
                $analysis['overall_health'] ?? []
            ),
        ];
    }

    /**
     * Get default analysis structure.
     */
    private function getDefaultAnalysis(): array
    {
        return [
            'metrics' => [
                'sales' => [
                    'total' => 0,
                    'daily_average' => 0,
                    'trend' => 'stable',
                    'previous_period_variation' => 0,
                ],
                'average_order_value' => [
                    'value' => 0,
                    'benchmark' => 0,
                    'percentage_difference' => 0,
                ],
                'conversion' => [
                    'rate' => 0,
                    'benchmark' => 0,
                ],
                'cancellation' => [
                    'rate' => 0,
                    'main_reasons' => [],
                ],
                'inventory' => [
                    'out_of_stock_products' => 0,
                    'critical_stock_products' => 0,
                    'stagnant_inventory_value' => 0,
                ],
                'coupons' => [
                    'usage_rate' => 0,
                    'ticket_impact' => 0,
                ],
            ],
            'anomalies' => [],
            'identified_patterns' => [],
            'overall_health' => [
                'score' => 50,
                'classification' => 'attention',
                'main_points' => ['Could not complete full analysis'],
            ],
        ];
    }
}
