<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AIManager;
use App\Services\AI\Prompts\CollectorAgentPrompt;

class CollectorAgentService
{
    public function __construct(
        private AIManager $aiManager
    ) {}

    /**
     * Execute the collector agent.
     */
    public function execute(array $context): array
    {
        $prompt = CollectorAgentPrompt::get($context);

        $response = $this->aiManager->chat([
            ['role' => 'user', 'content' => $prompt],
        ], [
            'temperature' => 0.3, // Lower temperature for more consistent output
            'max_tokens' => 4096, // Adequate for context collection
        ]);

        return $this->parseResponse($response);
    }

    /**
     * Parse the AI response into structured data.
     */
    private function parseResponse(string $response): array
    {
        // Try to extract JSON from the response
        $json = $this->extractJson($response);

        if ($json === null) {
            // Return default structure if parsing fails
            return [
                'historical_summary' => [],
                'success_patterns' => [],
                'suggestions_to_avoid' => [],
                'relevant_benchmarks' => [],
                'identified_gaps' => [],
                'special_context' => 'Could not parse collector response',
            ];
        }

        return $json;
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
}
