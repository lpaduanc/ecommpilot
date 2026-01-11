<?php

namespace App\Services\AI\RAG;

use App\Models\KnowledgeEmbedding;
use App\Services\AI\EmbeddingService;
use Illuminate\Support\Facades\DB;

class KnowledgeBaseService
{
    public function __construct(
        private EmbeddingService $embeddingService
    ) {}

    /**
     * Search for benchmarks relevant to a niche.
     */
    public function searchBenchmarks(string $niche): array
    {
        $query = "benchmarks e-commerce {$niche} Brazil metrics conversion";

        return $this->search($query, KnowledgeEmbedding::CATEGORY_BENCHMARK, $niche);
    }

    /**
     * Search for strategies relevant to a niche.
     */
    public function searchStrategies(string $niche): array
    {
        $query = "strategies increase sales {$niche} e-commerce";

        return $this->search($query, KnowledgeEmbedding::CATEGORY_STRATEGY, $niche);
    }

    /**
     * Search for success cases in a niche.
     */
    public function searchCases(string $niche): array
    {
        $query = "success cases e-commerce {$niche}";

        return $this->search($query, KnowledgeEmbedding::CATEGORY_CASE, $niche);
    }

    /**
     * Search for seasonality information.
     */
    public function searchSeasonality(): array
    {
        $query = 'e-commerce calendar dates promotions Brazil seasonality';

        return $this->search($query, KnowledgeEmbedding::CATEGORY_SEASONALITY, null);
    }

    /**
     * Generic search in knowledge base.
     */
    public function search(string $query, string $category, ?string $niche = null, int $limit = 5): array
    {
        if (! $this->embeddingService->isConfigured()) {
            // Fallback to text search if embeddings not configured
            return $this->textSearch($category, $niche, $limit);
        }

        $embedding = $this->embeddingService->generate($query);

        $results = $this->embeddingService->searchKnowledge($embedding, $category, $niche, $limit);

        return array_map(function ($item) {
            return [
                'title' => $item->title,
                'content' => $item->content,
                'category' => $item->category,
                'niche' => $item->niche,
                'relevance' => 1 - $item->distance,
                'metadata' => json_decode($item->metadata ?? '{}', true),
            ];
        }, $results);
    }

    /**
     * Fallback text search when embeddings not available.
     */
    private function textSearch(string $category, ?string $niche = null, int $limit = 5): array
    {
        $query = KnowledgeEmbedding::where('category', $category);

        if ($niche) {
            $query->where(function ($q) use ($niche) {
                $q->where('niche', $niche)
                    ->orWhere('niche', 'general');
            });
        }

        return $query->limit($limit)->get()->map(function ($item) {
            return [
                'title' => $item->title,
                'content' => $item->content,
                'category' => $item->category,
                'niche' => $item->niche,
                'relevance' => 1.0,
                'metadata' => $item->metadata ?? [],
            ];
        })->toArray();
    }

    /**
     * Add new knowledge to the base.
     */
    public function add(array $data): KnowledgeEmbedding
    {
        $embedding = null;

        if ($this->embeddingService->isConfigured()) {
            $textToEmbed = $data['title'].' '.$data['content'];
            $embedding = $this->embeddingService->generate($textToEmbed);
        }

        $knowledge = new KnowledgeEmbedding([
            'category' => $data['category'],
            'niche' => $data['niche'] ?? 'general',
            'title' => $data['title'],
            'content' => $data['content'],
            'metadata' => $data['metadata'] ?? null,
        ]);

        $knowledge->save();

        // Update embedding using raw query for pgvector
        if ($embedding && config('database.default') === 'pgsql') {
            $embeddingStr = $this->embeddingService->formatForStorage($embedding);
            DB::statement("UPDATE knowledge_embeddings SET embedding = '{$embeddingStr}'::vector WHERE id = ?", [$knowledge->id]);
        }

        return $knowledge;
    }

    /**
     * Get all benchmarks for a niche (without embedding search).
     */
    public function getBenchmarks(?string $niche = null): array
    {
        $query = KnowledgeEmbedding::benchmarks();

        if ($niche) {
            $query->where(function ($q) use ($niche) {
                $q->where('niche', $niche)
                    ->orWhere('niche', 'general');
            });
        }

        return $query->get()->map(function ($item) {
            return [
                'title' => $item->title,
                'content' => $item->content,
                'niche' => $item->niche,
                'metadata' => $item->metadata ?? [],
            ];
        })->toArray();
    }

    /**
     * Format knowledge results for including in a prompt.
     */
    public function formatForPrompt(array $results): string
    {
        if (empty($results)) {
            return 'No specific knowledge available for this context.';
        }

        $formatted = [];

        foreach ($results as $item) {
            $formatted[] = "### {$item['title']}\n{$item['content']}";
        }

        return implode("\n\n", $formatted);
    }

    /**
     * Get relevant strategies based on store data.
     */
    public function getRelevantStrategies(array $storeData, string $niche = 'general'): array
    {
        $strategies = [];

        // Check for retention issues
        if (isset($storeData['customer_insights']['repeat_purchase_rate']) &&
            $storeData['customer_insights']['repeat_purchase_rate'] < 15) {
            $retentionStrategies = $this->searchStrategies('retention customer loyalty');
            $strategies = array_merge($strategies, array_slice($retentionStrategies, 0, 2));
        }

        // Check for inventory issues
        if (isset($storeData['inventory_alerts'])) {
            $outOfStock = $storeData['inventory_alerts']['out_of_stock'] ?? 0;
            $lowStock = $storeData['inventory_alerts']['low_stock'] ?? 0;

            if ($outOfStock > 3 || $lowStock > 5) {
                $inventoryStrategies = $this->searchStrategies('inventory management stock');
                $strategies = array_merge($strategies, array_slice($inventoryStrategies, 0, 2));
            }
        }

        // Check for revenue trends
        if (isset($storeData['trends']['revenue_trend'])) {
            $trend = $storeData['trends']['revenue_trend'];
            if (in_array($trend, ['decline', 'strong_decline'])) {
                $revenueStrategies = $this->searchStrategies('increase sales revenue growth');
                $strategies = array_merge($strategies, array_slice($revenueStrategies, 0, 2));
            }
        }

        // Add niche-specific strategies
        if ($niche !== 'general') {
            $nicheStrategies = $this->searchStrategies($niche);
            $strategies = array_merge($strategies, array_slice($nicheStrategies, 0, 2));
        }

        // Limit to top 5 strategies
        return array_slice($strategies, 0, 5);
    }
}
