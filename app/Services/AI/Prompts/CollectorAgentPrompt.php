<?php

namespace App\Services\AI\Prompts;

class CollectorAgentPrompt
{
    public static function get(array $context): string
    {
        $platform = $context['platform'] ?? 'unknown';
        $niche = $context['niche'] ?? 'general';
        $operationTime = $context['operation_time'] ?? 'unknown';
        $previousAnalyses = json_encode($context['previous_analyses'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $previousSuggestions = json_encode($context['previous_suggestions'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $benchmarks = json_encode($context['benchmarks'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a specialized agent for collecting and organizing context for e-commerce store analysis.

## Your Task
Analyze the provided information and structure an executive summary of the store context.

## Store Data
- **Platform:** {$platform}
- **Identified niche:** {$niche}
- **Operation time:** {$operationTime}

## Previous Analyses History
```json
{$previousAnalyses}
```

## Previous Suggestions and Status
```json
{$previousSuggestions}
```

## Niche Benchmarks (via RAG)
```json
{$benchmarks}
```

## Instructions
1. Summarize the store's historical context in 3-5 main points
2. Identify patterns of suggestions that worked (status = completed with success)
3. List suggestions that were ignored or did not work
4. Highlight the most relevant benchmarks for this specific store
5. Identify gaps between current performance and benchmarks

## Output Format
Return a structured JSON:
```json
{
  "historical_summary": ["point 1", "point 2"],
  "success_patterns": ["pattern 1", "pattern 2"],
  "suggestions_to_avoid": ["type 1", "type 2"],
  "relevant_benchmarks": {
    "conversion_rate": "X%",
    "average_order_value": "R$ X",
    "other": {}
  },
  "identified_gaps": ["gap 1", "gap 2"],
  "special_context": "additional observations"
}
```

IMPORTANT: Return ONLY valid JSON, no additional text.
PROMPT;
    }
}
