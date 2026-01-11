<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmbeddingService
{
    private string $apiKey;

    private string $model = 'text-embedding-3-small';

    private string $baseUrl = 'https://api.openai.com/v1/embeddings';

    public function __construct()
    {
        $this->apiKey = config('openai.api_key') ?? '';
    }

    /**
     * Generate embedding for a given text.
     */
    public function generate(string $text): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('OpenAI API key is not configured for embeddings.');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl, [
            'model' => $this->model,
            'input' => $text,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Error generating embedding: '.$response->body());
        }

        return $response->json('data.0.embedding');
    }

    /**
     * Calculate cosine similarity between two embeddings.
     */
    public function calculateSimilarity(array $embedding1, array $embedding2): float
    {
        if (count($embedding1) !== count($embedding2)) {
            throw new RuntimeException('Embeddings must have the same dimension.');
        }

        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;

        for ($i = 0; $i < count($embedding1); $i++) {
            $dotProduct += $embedding1[$i] * $embedding2[$i];
            $norm1 += $embedding1[$i] ** 2;
            $norm2 += $embedding2[$i] ** 2;
        }

        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }

        return $dotProduct / (sqrt($norm1) * sqrt($norm2));
    }

    /**
     * Search for similar items in a table using pgvector.
     */
    public function searchSimilar(array $embedding, string $table, int $limit = 5, ?string $whereClause = null): array
    {
        $embeddingStr = '['.implode(',', $embedding).']';

        $sql = "
            SELECT *, embedding <=> '{$embeddingStr}'::vector as distance
            FROM {$table}
        ";

        if ($whereClause) {
            $sql .= " WHERE {$whereClause}";
        }

        $sql .= " ORDER BY distance LIMIT {$limit}";

        return DB::select($sql);
    }

    /**
     * Search for similar knowledge embeddings by category and niche.
     */
    public function searchKnowledge(array $embedding, string $category, ?string $niche = null, int $limit = 5): array
    {
        $embeddingStr = '['.implode(',', $embedding).']';

        $sql = "
            SELECT
                id, title, content, category, niche, metadata,
                embedding <=> '{$embeddingStr}'::vector as distance
            FROM knowledge_embeddings
            WHERE category = ?
        ";

        $params = [$category];

        if ($niche) {
            $sql .= ' AND (niche = ? OR niche = ?)';
            $params[] = $niche;
            $params[] = 'general';
        }

        $sql .= ' ORDER BY distance LIMIT ?';
        $params[] = $limit;

        return DB::select($sql, $params);
    }

    /**
     * Search for similar suggestions for a store.
     */
    public function searchSimilarSuggestions(array $embedding, int $storeId, int $limit = 10): array
    {
        $embeddingStr = '['.implode(',', $embedding).']';

        return DB::select("
            SELECT
                id, title, description, category, status,
                embedding <=> '{$embeddingStr}'::vector as distance
            FROM suggestions
            WHERE store_id = ? AND embedding IS NOT NULL
            ORDER BY distance
            LIMIT ?
        ", [$storeId, $limit]);
    }

    /**
     * Check if a suggestion is too similar to existing ones.
     * Returns true if similarity is above threshold.
     */
    public function isTooSimilar(array $embedding, int $storeId, float $threshold = 0.85): bool
    {
        $results = $this->searchSimilarSuggestions($embedding, $storeId, 1);

        if (empty($results)) {
            return false;
        }

        // pgvector returns distance (1 - similarity for cosine)
        // So lower distance = higher similarity
        $distance = $results[0]->distance;
        $similarity = 1 - $distance;

        return $similarity > $threshold;
    }

    /**
     * Format embedding array for pgvector storage.
     */
    public function formatForStorage(array $embedding): string
    {
        return '['.implode(',', $embedding).']';
    }

    /**
     * Parse embedding from pgvector format.
     */
    public function parseFromStorage(string $embeddingStr): array
    {
        $embeddingStr = trim($embeddingStr, '[]');

        return array_map('floatval', explode(',', $embeddingStr));
    }

    /**
     * Check if the embedding service is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }
}
