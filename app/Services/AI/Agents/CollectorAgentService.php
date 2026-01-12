<?php

namespace App\Services\AI\Agents;

use App\Services\AI\AIManager;
use App\Services\AI\JsonExtractor;
use App\Services\AI\Prompts\CollectorAgentPrompt;
use Illuminate\Support\Facades\Log;

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
        Log::debug('Collector raw response (first 2000 chars): '.substr($response, 0, 2000));

        // Try to extract JSON from the response
        $json = $this->extractJson($response);

        if ($json === null) {
            Log::warning('Collector: Could not extract JSON from response');

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

        Log::info('Collector: Successfully parsed context with '.count($json).' keys');

        return $json;
    }

    /**
     * Extract JSON from response text.
     */
    private function extractJson(string $content): ?array
    {
        return JsonExtractor::extract($content, 'Collector');
    }
}
