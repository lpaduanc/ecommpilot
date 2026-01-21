<?php

namespace App\Services\AI;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EmbeddingService
{
    private string $provider;

    private string $apiKey;

    private string $model;

    private int $dimensions;

    public function __construct()
    {
        // Busca provider do banco primeiro, depois config/env
        $this->provider = SystemSetting::get(
            'ai.embeddings.provider',
            config('services.ai.embeddings.provider', 'gemini')
        );
        $this->initializeProvider();
    }

    /**
     * Initialize the embedding provider configuration.
     * Busca API keys do banco de dados (SystemSetting) primeiro,
     * com fallback para config/env.
     */
    private function initializeProvider(): void
    {
        switch ($this->provider) {
            case 'gemini':
                // Busca key do banco primeiro (mesma key usada pelo GeminiProvider)
                $this->apiKey = SystemSetting::get(
                    'ai.gemini.api_key',
                    config('services.ai.gemini.api_key')
                ) ?? '';
                $this->model = SystemSetting::get(
                    'ai.embeddings.gemini.model',
                    config('services.ai.embeddings.gemini.model', 'text-embedding-004')
                );
                $this->dimensions = (int) SystemSetting::get(
                    'ai.embeddings.gemini.dimensions',
                    config('services.ai.embeddings.gemini.dimensions', 768)
                );
                break;

            case 'openai':
                // Busca key do banco primeiro
                $this->apiKey = SystemSetting::get(
                    'ai.openai.api_key',
                    config('openai.api_key')
                ) ?? '';
                $this->model = SystemSetting::get(
                    'ai.embeddings.openai.model',
                    config('services.ai.embeddings.openai.model', 'text-embedding-3-small')
                );
                $this->dimensions = (int) SystemSetting::get(
                    'ai.embeddings.openai.dimensions',
                    config('services.ai.embeddings.openai.dimensions', 1536)
                );
                break;

            default:
                throw new RuntimeException("Unsupported embedding provider: {$this->provider}");
        }

        Log::channel('embeddings')->debug('EmbeddingService inicializado', [
            'provider' => $this->provider,
            'model' => $this->model,
            'dimensions' => $this->dimensions,
            'api_key_configured' => ! empty($this->apiKey),
            'api_key_source' => ! empty($this->apiKey) ? 'SystemSetting/config' : 'none',
        ]);
    }

    /**
     * Generate embedding for a given text.
     */
    public function generate(string $text): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException("API key is not configured for {$this->provider} embeddings.");
        }

        return match ($this->provider) {
            'gemini' => $this->generateWithGemini($text),
            'openai' => $this->generateWithOpenAI($text),
            default => throw new RuntimeException("Unsupported provider: {$this->provider}"),
        };
    }

    /**
     * Generate embedding using Google Gemini API.
     */
    private function generateWithGemini(string $text): array
    {
        $url = "https://generativelanguage.googleapis.com/v1/models/{$this->model}:embedContent";

        Log::channel('embeddings')->debug('Gemini API Request - Iniciando chamada', [
            'url' => $url,
            'model' => $this->model,
            'task_type' => 'RETRIEVAL_DOCUMENT',
            'text_length' => strlen($text),
            'text_words' => str_word_count($text),
        ]);

        $startTime = microtime(true);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url.'?key='.$this->apiKey, [
            'model' => "models/{$this->model}",
            'content' => [
                'parts' => [
                    ['text' => $text],
                ],
            ],
            'taskType' => 'RETRIEVAL_DOCUMENT',
        ]);

        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        if ($response->failed()) {
            Log::channel('embeddings')->error('Gemini API Error - Falha na requisicao', [
                'status' => $response->status(),
                'body' => $response->body(),
                'response_time_ms' => $responseTime,
            ]);
            Log::error('Gemini embedding error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Error generating Gemini embedding: '.$response->body());
        }

        $embedding = $response->json('embedding.values');

        if (empty($embedding)) {
            Log::channel('embeddings')->error('Gemini API Error - Embedding vazio', [
                'response' => $response->json(),
            ]);
            throw new RuntimeException('Empty embedding returned from Gemini API');
        }

        Log::channel('embeddings')->info('Gemini API Response - Sucesso', [
            'status' => $response->status(),
            'response_time_ms' => $responseTime,
            'embedding_dimensions' => count($embedding),
            'embedding_sample' => [
                'first_5' => array_slice($embedding, 0, 5),
                'last_5' => array_slice($embedding, -5),
            ],
            'embedding_stats' => [
                'min' => round(min($embedding), 6),
                'max' => round(max($embedding), 6),
                'avg' => round(array_sum($embedding) / count($embedding), 6),
            ],
        ]);

        return $embedding;
    }

    /**
     * Generate embedding using OpenAI API.
     */
    private function generateWithOpenAI(string $text): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/embeddings', [
            'model' => $this->model,
            'input' => $text,
        ]);

        if ($response->failed()) {
            Log::error('OpenAI embedding error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Error generating OpenAI embedding: '.$response->body());
        }

        return $response->json('data.0.embedding');
    }

    /**
     * Generate embedding for search queries (optimized for retrieval).
     */
    public function generateForQuery(string $text): array
    {
        if ($this->provider === 'gemini') {
            return $this->generateWithGeminiQuery($text);
        }

        return $this->generate($text);
    }

    /**
     * Generate embedding for queries using Gemini (different task type).
     */
    private function generateWithGeminiQuery(string $text): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('Gemini API key is not configured for embeddings.');
        }

        $url = "https://generativelanguage.googleapis.com/v1/models/{$this->model}:embedContent";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url.'?key='.$this->apiKey, [
            'model' => "models/{$this->model}",
            'content' => [
                'parts' => [
                    ['text' => $text],
                ],
            ],
            'taskType' => 'RETRIEVAL_QUERY',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Error generating Gemini query embedding: '.$response->body());
        }

        $embedding = $response->json('embedding.values');

        if (empty($embedding)) {
            throw new RuntimeException('Empty embedding returned from Gemini API');
        }

        return $embedding;
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
     * Search for similar knowledge embeddings by category, niche, and subcategory.
     */
    public function searchKnowledge(array $embedding, string $category, ?string $niche = null, ?string $subcategory = null, int $limit = 5): array
    {
        $embeddingStr = '['.implode(',', $embedding).']';

        $sql = "
            SELECT
                id, title, content, category, niche, subcategory, metadata,
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

        if ($subcategory) {
            // Match exact subcategory OR records without subcategory (general for the niche)
            $sql .= ' AND (subcategory = ? OR subcategory IS NULL)';
            $params[] = $subcategory;
        }

        // Order by: 1) exact subcategory match first (if filtering), 2) distance
        if ($subcategory) {
            $sql .= ' ORDER BY (CASE WHEN subcategory = ? THEN 0 ELSE 1 END), distance LIMIT ?';
            $params[] = $subcategory;
        } else {
            $sql .= ' ORDER BY distance LIMIT ?';
        }
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

    /**
     * Get the current provider name.
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get the current model name.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get the embedding dimensions for the current provider.
     */
    public function getDimensions(): int
    {
        return $this->dimensions;
    }
}
