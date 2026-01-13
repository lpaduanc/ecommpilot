<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

class JsonExtractor
{
    /**
     * Extract JSON from AI response text with detailed logging.
     */
    public static function extract(string $content, string $agentName = 'Unknown'): ?array
    {
        // Method 1: Try to find JSON in markdown code blocks (```json ... ```)
        if (preg_match('/```(?:json)?\s*\n?([\s\S]*?)\n?```/m', $content, $matches)) {
            $jsonStr = trim($matches[1]);
            $decoded = json_decode($jsonStr, true);
            if ($decoded !== null) {
                Log::debug("{$agentName}: Extracted JSON from markdown code block");

                return $decoded;
            }
            Log::debug("{$agentName}: Found code block but JSON decode failed: ".json_last_error_msg());
        }

        // Method 2: Try direct parse (response is pure JSON)
        $trimmed = trim($content);
        $decoded = json_decode($trimmed, true);
        if ($decoded !== null) {
            Log::debug("{$agentName}: Extracted JSON via direct parse");

            return $decoded;
        }

        // Method 3: Try to find JSON object starting with { and ending with }
        // Use a more careful approach to find balanced braces
        $start = strpos($content, '{');
        if ($start !== false) {
            $jsonCandidate = substr($content, $start);
            $end = self::findMatchingBrace($jsonCandidate);
            if ($end !== false) {
                $jsonStr = substr($jsonCandidate, 0, $end + 1);
                $decoded = json_decode($jsonStr, true);
                if ($decoded !== null) {
                    Log::debug("{$agentName}: Extracted JSON by finding balanced braces");

                    return $decoded;
                }
                Log::debug("{$agentName}: Found braces but JSON decode failed: ".json_last_error_msg());
            }
        }

        // Method 4: Try to clean common issues and parse again
        $cleaned = self::cleanJsonString($content);
        $decoded = json_decode($cleaned, true);
        if ($decoded !== null) {
            Log::debug("{$agentName}: Extracted JSON after cleaning");

            return $decoded;
        }

        // Method 5: Try to repair truncated JSON by adding missing closing braces/brackets
        $repaired = self::repairTruncatedJson($content);
        if ($repaired) {
            $decoded = json_decode($repaired, true);
            if ($decoded !== null) {
                Log::warning("{$agentName}: Extracted JSON after repairing truncation (data may be incomplete)");

                return $decoded;
            }
            Log::debug("{$agentName}: Repair attempted but JSON still invalid: ".json_last_error_msg());
        }

        // Detect if this looks like truncated JSON
        if (self::isTruncatedJson($content)) {
            Log::error("{$agentName}: Response appears to be truncated JSON. The AI response was cut off before completion.");
        }

        Log::warning("{$agentName}: All JSON extraction methods failed. Last error: ".json_last_error_msg());
        Log::debug("{$agentName}: Content snippet: ".substr($content, 0, 500));

        return null;
    }

    /**
     * Check if content appears to be truncated JSON.
     */
    private static function isTruncatedJson(string $content): bool
    {
        // Count opening and closing braces/brackets
        $openBraces = substr_count($content, '{');
        $closeBraces = substr_count($content, '}');
        $openBrackets = substr_count($content, '[');
        $closeBrackets = substr_count($content, ']');

        // If we have more opens than closes, likely truncated
        return ($openBraces > $closeBraces) || ($openBrackets > $closeBrackets);
    }

    /**
     * Attempt to repair truncated JSON by adding missing closing characters.
     */
    private static function repairTruncatedJson(string $content): ?string
    {
        // Find JSON start
        $start = strpos($content, '{');
        if ($start === false) {
            return null;
        }

        $json = substr($content, $start);

        // Track what needs to be closed
        $stack = [];
        $inString = false;
        $escape = false;
        $len = strlen($json);

        for ($i = 0; $i < $len; $i++) {
            $char = $json[$i];

            if ($escape) {
                $escape = false;

                continue;
            }

            if ($char === '\\' && $inString) {
                $escape = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;

                continue;
            }

            if (! $inString) {
                if ($char === '{') {
                    $stack[] = '}';
                } elseif ($char === '[') {
                    $stack[] = ']';
                } elseif ($char === '}' || $char === ']') {
                    if (! empty($stack) && end($stack) === $char) {
                        array_pop($stack);
                    }
                }
            }
        }

        // If we're still in a string, close it
        if ($inString) {
            $json .= '"';
        }

        // Remove trailing incomplete elements (like partial strings or dangling commas)
        $json = preg_replace('/,\s*$/', '', $json);
        $json = preg_replace('/:\s*$/', ': null', $json);
        $json = preg_replace('/"\s*$/', '"', $json);

        // Add missing closing characters
        if (! empty($stack)) {
            // Reverse the stack to close in correct order
            $closers = array_reverse($stack);
            $json .= implode('', $closers);

            Log::debug('JsonExtractor: Added '.count($closers).' closing characters to repair truncated JSON');
        }

        // Only return if we actually added something
        return ! empty($stack) || $inString ? $json : null;
    }

    /**
     * Find the position of the matching closing brace.
     */
    private static function findMatchingBrace(string $str): int|false
    {
        $depth = 0;
        $inString = false;
        $escape = false;
        $len = strlen($str);

        for ($i = 0; $i < $len; $i++) {
            $char = $str[$i];

            if ($escape) {
                $escape = false;

                continue;
            }

            if ($char === '\\' && $inString) {
                $escape = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;

                continue;
            }

            if (! $inString) {
                if ($char === '{') {
                    $depth++;
                } elseif ($char === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return $i;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Clean common issues in JSON strings.
     */
    private static function cleanJsonString(string $content): string
    {
        // Find JSON start
        $start = strpos($content, '{');
        if ($start === false) {
            return $content;
        }

        // Find JSON end (last closing brace)
        $end = strrpos($content, '}');
        if ($end === false) {
            return $content;
        }

        $json = substr($content, $start, $end - $start + 1);

        // Remove trailing commas before ] or }
        $json = preg_replace('/,\s*([\]}])/m', '$1', $json);

        // Fix common escape issues
        $json = str_replace(["\r\n", "\r"], "\n", $json);

        return $json;
    }
}
