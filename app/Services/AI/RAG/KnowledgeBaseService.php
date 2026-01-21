<?php

namespace App\Services\AI\RAG;

use App\Models\KnowledgeEmbedding;
use App\Services\AI\EmbeddingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KnowledgeBaseService
{
    private string $logChannel = 'embeddings';

    public function __construct(
        private EmbeddingService $embeddingService
    ) {}

    /**
     * Search for benchmarks relevant to a niche and subcategory.
     */
    public function searchBenchmarks(string $niche, ?string $subcategory = null): array
    {
        $subcategoryContext = $subcategory ? " {$subcategory}" : '';
        $query = "benchmarks e-commerce {$niche}{$subcategoryContext} Brazil metrics conversion";

        return $this->search($query, KnowledgeEmbedding::CATEGORY_BENCHMARK, $niche, $subcategory);
    }

    /**
     * Search for strategies relevant to a niche and subcategory.
     */
    public function searchStrategies(string $niche, ?string $subcategory = null): array
    {
        $subcategoryContext = $subcategory ? " {$subcategory}" : '';
        $query = "strategies increase sales {$niche}{$subcategoryContext} e-commerce";

        return $this->search($query, KnowledgeEmbedding::CATEGORY_STRATEGY, $niche, $subcategory);
    }

    /**
     * Search for success cases in a niche and subcategory.
     */
    public function searchCases(string $niche, ?string $subcategory = null): array
    {
        $subcategoryContext = $subcategory ? " {$subcategory}" : '';
        $query = "success cases e-commerce {$niche}{$subcategoryContext}";

        return $this->search($query, KnowledgeEmbedding::CATEGORY_CASE, $niche, $subcategory);
    }

    /**
     * Search for seasonality information.
     */
    public function searchSeasonality(?string $niche = null, ?string $subcategory = null): array
    {
        $context = '';
        if ($niche) {
            $context .= " {$niche}";
        }
        if ($subcategory) {
            $context .= " {$subcategory}";
        }
        $query = "e-commerce calendar dates promotions Brazil seasonality{$context}";

        return $this->search($query, KnowledgeEmbedding::CATEGORY_SEASONALITY, $niche, $subcategory);
    }

    /**
     * Generic search in knowledge base.
     */
    public function search(string $query, string $category, ?string $niche = null, ?string $subcategory = null, int $limit = 5): array
    {
        if (! $this->embeddingService->isConfigured()) {
            // Fallback to text search if embeddings not configured
            return $this->textSearch($category, $niche, $subcategory, $limit);
        }

        // Check if there are any embeddings in the database
        if (! $this->hasEmbeddings($category)) {
            return $this->textSearch($category, $niche, $subcategory, $limit);
        }

        // Use generateForQuery for search queries (optimized for retrieval)
        $embedding = $this->embeddingService->generateForQuery($query);

        $results = $this->embeddingService->searchKnowledge($embedding, $category, $niche, $subcategory, $limit);

        return array_map(function ($item) {
            return [
                'title' => $item->title,
                'content' => $item->content,
                'category' => $item->category,
                'niche' => $item->niche,
                'subcategory' => $item->subcategory ?? null,
                'relevance' => 1 - $item->distance,
                'metadata' => json_decode($item->metadata ?? '{}', true),
            ];
        }, $results);
    }

    /**
     * Check if there are embeddings in the database for a category.
     */
    private function hasEmbeddings(string $category): bool
    {
        if (config('database.default') !== 'pgsql') {
            return false;
        }

        return KnowledgeEmbedding::where('category', $category)
            ->whereNotNull('embedding')
            ->exists();
    }

    /**
     * Fallback text search when embeddings not available.
     */
    private function textSearch(string $category, ?string $niche = null, ?string $subcategory = null, int $limit = 5): array
    {
        $query = KnowledgeEmbedding::where('category', $category);

        if ($niche) {
            $query->where(function ($q) use ($niche) {
                $q->where('niche', $niche)
                    ->orWhere('niche', 'general');
            });
        }

        if ($subcategory) {
            $query->where(function ($q) use ($subcategory) {
                $q->where('subcategory', $subcategory)
                    ->orWhereNull('subcategory');
            });
            // Prioritize exact subcategory matches
            $query->orderByRaw('CASE WHEN subcategory = ? THEN 0 ELSE 1 END', [$subcategory]);
        }

        return $query->limit($limit)->get()->map(function ($item) {
            return [
                'title' => $item->title,
                'content' => $item->content,
                'category' => $item->category,
                'niche' => $item->niche,
                'subcategory' => $item->subcategory ?? null,
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
        $startTime = microtime(true);

        Log::channel($this->logChannel)->info('=== INICIANDO ADICAO DE CONHECIMENTO ===', [
            'title' => $data['title'],
            'category' => $data['category'],
            'niche' => $data['niche'] ?? 'general',
        ]);

        if ($this->embeddingService->isConfigured()) {
            $textToEmbed = $data['title'].' '.$data['content'];
            $textLength = strlen($textToEmbed);

            Log::channel($this->logChannel)->info('Gerando embedding via Gemini', [
                'provider' => $this->embeddingService->getProvider(),
                'model' => $this->embeddingService->getModel(),
                'dimensions' => $this->embeddingService->getDimensions(),
                'text_length' => $textLength,
                'text_preview' => substr($textToEmbed, 0, 100).'...',
            ]);

            $embeddingStart = microtime(true);
            $embedding = $this->embeddingService->generate($textToEmbed);
            $embeddingTime = round((microtime(true) - $embeddingStart) * 1000, 2);

            Log::channel($this->logChannel)->info('Embedding gerado com sucesso', [
                'provider' => $this->embeddingService->getProvider(),
                'dimensions' => count($embedding),
                'time_ms' => $embeddingTime,
                'embedding_preview' => array_slice($embedding, 0, 5),
                'embedding_min' => min($embedding),
                'embedding_max' => max($embedding),
            ]);
        } else {
            Log::channel($this->logChannel)->warning('EmbeddingService nao configurado - salvando sem embedding', [
                'title' => $data['title'],
            ]);
        }

        $knowledge = new KnowledgeEmbedding([
            'category' => $data['category'],
            'niche' => $data['niche'] ?? 'general',
            'subcategory' => $data['subcategory'] ?? null,
            'title' => $data['title'],
            'content' => $data['content'],
            'metadata' => $data['metadata'] ?? null,
        ]);

        $knowledge->save();

        Log::channel($this->logChannel)->info('Registro salvo no banco de dados', [
            'id' => $knowledge->id,
            'category' => $knowledge->category,
            'niche' => $knowledge->niche,
        ]);

        // Update embedding using raw query for pgvector
        if ($embedding && config('database.default') === 'pgsql') {
            $embeddingStr = $this->embeddingService->formatForStorage($embedding);
            DB::statement("UPDATE knowledge_embeddings SET embedding = '{$embeddingStr}'::vector WHERE id = ?", [$knowledge->id]);

            Log::channel($this->logChannel)->info('Embedding armazenado no pgvector', [
                'id' => $knowledge->id,
                'vector_dimensions' => count($embedding),
                'storage_format' => 'vector('.count($embedding).')',
            ]);
        }

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::channel($this->logChannel)->info('=== CONHECIMENTO ADICIONADO COM SUCESSO ===', [
            'id' => $knowledge->id,
            'title' => $knowledge->title,
            'total_time_ms' => $totalTime,
            'has_embedding' => $embedding !== null,
        ]);

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

    /**
     * Get structured benchmarks for a niche with subcategory data.
     *
     * @param  string  $niche  The main niche (e.g., 'beauty', 'fashion')
     * @param  string|null  $subcategory  The subcategory (e.g., 'haircare', 'skincare')
     * @return array Structured benchmark data
     */
    public function getStructuredBenchmarks(string $niche, ?string $subcategory = null): array
    {
        // Get benchmark for the niche
        $benchmark = KnowledgeEmbedding::benchmarks()
            ->where('niche', $niche)
            ->first();

        if (! $benchmark || ! $benchmark->metadata) {
            // Try general benchmark
            $benchmark = KnowledgeEmbedding::benchmarks()
                ->where('niche', 'general')
                ->first();
        }

        if (! $benchmark || ! $benchmark->metadata) {
            return $this->getDefaultBenchmarks();
        }

        $metadata = $benchmark->metadata;
        $metrics = $metadata['metrics'] ?? [];

        // Build structured benchmarks
        $result = [
            'niche' => $niche,
            'subcategory' => $subcategory,
            'sources' => $metadata['sources'] ?? [],
            'year' => $metadata['year'] ?? 2024,
            'verified' => $metadata['verified'] ?? false,

            // Ticket medio
            'ticket_medio' => $this->extractTicketMedio($metrics, $subcategory),

            // Conversion rates
            'taxa_conversao' => [
                'desktop' => $metrics['conversion_rate_desktop']['value'] ?? $metrics['conversion_rate']['average'] ?? 1.5,
                'mobile' => $metrics['conversion_rate_mobile']['value'] ?? ($metrics['conversion_rate']['average'] ?? 1.5) * 0.6,
                'geral' => $metrics['conversion_rate']['average'] ?? 1.5,
            ],

            // Cart abandonment
            'abandono_carrinho' => $metrics['cart_abandonment']['value'] ?? 82,

            // Mobile traffic
            'trafego_mobile' => $metrics['mobile_traffic']['value'] ?? $metrics['mobile_traffic']['min'] ?? 65,

            // Growth metrics
            'crescimento_setor' => $metrics['growth']['value'] ?? $metrics['market_growth']['value'] ?? null,

            // Market share
            'market_share' => $metrics['market_share']['value'] ?? null,
        ];

        // Add subcategory-specific benchmarks if available
        if ($subcategory && isset($metadata['subcategories'][$subcategory])) {
            $subData = $metadata['subcategories'][$subcategory];
            $result['subcategory_data'] = $subData;

            if (isset($subData['ticket_medio'])) {
                $result['ticket_medio'] = $subData['ticket_medio'];
            }
        }

        // Fallback: Use config benchmarks if subcategory has no data from database
        if ($subcategory && $subcategory !== 'geral' && ! isset($result['subcategory_data']['ticket_medio'])) {
            $configBenchmarks = $this->getSubcategoryBenchmarksFromConfig($niche, $subcategory);
            if ($configBenchmarks) {
                $result['ticket_medio'] = $configBenchmarks['ticket_medio'];
                $result['subcategory_data'] = $configBenchmarks;
                $result['benchmark_source'] = 'config';

                // Also use config conversion rates if available
                if (isset($configBenchmarks['taxa_conversao'])) {
                    $result['taxa_conversao'] = $configBenchmarks['taxa_conversao'];
                }
            }
        }

        // Final fallback: Use niche default from config if no subcategory data
        if (! isset($result['benchmark_source']) && ! isset($result['subcategory_data'])) {
            $nicheDefaults = config("benchmarks.{$niche}.default");
            if ($nicheDefaults && isset($nicheDefaults['ticket_medio'])) {
                $result['ticket_medio'] = $nicheDefaults['ticket_medio'];
                $result['benchmark_source'] = 'config_niche_default';

                if (isset($nicheDefaults['taxa_conversao'])) {
                    $result['taxa_conversao'] = $nicheDefaults['taxa_conversao'];
                }
            }
        }

        return $result;
    }

    /**
     * Extract ticket medio from metrics, considering min/max/average.
     */
    private function extractTicketMedio(array $metrics, ?string $subcategory = null): array
    {
        $ticketData = $metrics['average_ticket'] ?? null;

        if (! $ticketData) {
            return $this->getDefaultTicketMedio();
        }

        // Handle different formats
        if (is_array($ticketData)) {
            return [
                'min' => $ticketData['min'] ?? $ticketData['value'] ?? 200,
                'max' => $ticketData['max'] ?? $ticketData['value'] ?? 500,
                'media' => $ticketData['average'] ?? $ticketData['value'] ?? 350,
            ];
        }

        return [
            'min' => $ticketData * 0.7,
            'max' => $ticketData * 1.3,
            'media' => $ticketData,
        ];
    }

    /**
     * Get default benchmarks when no specific data is available.
     */
    private function getDefaultBenchmarks(): array
    {
        return [
            'niche' => 'general',
            'subcategory' => null,
            'sources' => ['ABComm', 'Neotrust'],
            'year' => 2024,
            'verified' => false,
            'ticket_medio' => $this->getDefaultTicketMedio(),
            'taxa_conversao' => [
                'desktop' => 1.65,
                'mobile' => 1.0,
                'geral' => 1.5,
            ],
            'abandono_carrinho' => 82,
            'trafego_mobile' => 65,
            'crescimento_setor' => null,
            'market_share' => null,
        ];
    }

    /**
     * Get default ticket medio values.
     */
    private function getDefaultTicketMedio(): array
    {
        return [
            'min' => 200,
            'max' => 600,
            'media' => 350,
        ];
    }

    /**
     * Get subcategory benchmarks from config file.
     *
     * @param  string  $niche  The niche (e.g., 'beauty')
     * @param  string  $subcategory  The subcategory (e.g., 'haircare')
     * @return array|null Benchmark data or null if not found
     */
    private function getSubcategoryBenchmarksFromConfig(string $niche, string $subcategory): ?array
    {
        return config("benchmarks.{$niche}.subcategories.{$subcategory}");
    }

    /**
     * Identify the store niche using semantic search with embeddings.
     *
     * @param  string  $storeName  The store name
     * @param  array  $categories  Product categories
     * @param  array  $productTitles  Top product titles
     * @return string The identified niche
     */
    public function identifyNiche(string $storeName, array $categories = [], array $productTitles = []): string
    {
        $result = $this->identifyNicheAndSubcategory($storeName, $categories, $productTitles);

        return $result['niche'];
    }

    /**
     * Identify the store niche AND subcategory using semantic search with embeddings.
     *
     * @param  string  $storeName  The store name
     * @param  array  $categories  Product categories
     * @param  array  $productTitles  Top product titles
     * @return array ['niche' => string, 'subcategory' => string]
     */
    public function identifyNicheAndSubcategory(string $storeName, array $categories = [], array $productTitles = []): array
    {
        $defaultResult = ['niche' => 'general', 'subcategory' => 'geral'];

        // Build context text for embedding
        $contextParts = [
            "Loja: {$storeName}",
        ];

        if (! empty($categories)) {
            $categoriesText = implode(', ', array_slice($categories, 0, 10));
            $contextParts[] = "Categorias: {$categoriesText}";
        }

        if (! empty($productTitles)) {
            $titlesText = implode(', ', array_slice($productTitles, 0, 10));
            $contextParts[] = "Produtos: {$titlesText}";
        }

        $contextText = implode('. ', $contextParts);

        Log::channel($this->logChannel)->info('Identificando nicho e subcategoria via RAG', [
            'store_name' => $storeName,
            'categories_count' => count($categories),
            'products_count' => count($productTitles),
            'context_length' => strlen($contextText),
        ]);

        // Check if embeddings are configured
        if (! $this->embeddingService->isConfigured()) {
            Log::channel($this->logChannel)->warning('Embeddings não configurados, retornando general');

            return $defaultResult;
        }

        // Check if we have embeddings in database
        if (config('database.default') !== 'pgsql') {
            Log::channel($this->logChannel)->warning('Database não é PostgreSQL, retornando general');

            return $defaultResult;
        }

        try {
            // Generate embedding for store context
            $embedding = $this->embeddingService->generateForQuery($contextText);
            $embeddingStr = $this->embeddingService->formatForStorage($embedding);

            // Search for similar knowledge entries (across all categories)
            $results = DB::select("
                SELECT niche, embedding <=> '{$embeddingStr}'::vector as distance
                FROM knowledge_embeddings
                WHERE niche != 'general' AND embedding IS NOT NULL
                ORDER BY distance
                LIMIT 10
            ");

            if (empty($results)) {
                Log::channel($this->logChannel)->info('Nenhum resultado encontrado, retornando general');

                return $defaultResult;
            }

            // Count niches by weighted score (closer = higher weight)
            $nicheScores = [];
            foreach ($results as $index => $result) {
                $niche = $result->niche;
                // Weight: first results have higher weight
                $weight = 10 - $index;
                $nicheScores[$niche] = ($nicheScores[$niche] ?? 0) + $weight;
            }

            // Sort by score and get the best niche
            arsort($nicheScores);
            $bestNiche = array_key_first($nicheScores);

            Log::channel($this->logChannel)->info('Nicho identificado via RAG', [
                'identified_niche' => $bestNiche,
                'niche_scores' => $nicheScores,
                'top_result_distance' => $results[0]->distance ?? null,
            ]);

            // Now identify subcategory based on the niche
            $subcategory = $this->identifySubcategory($bestNiche, $contextText, $embedding);

            Log::channel($this->logChannel)->info('Nicho e subcategoria identificados', [
                'niche' => $bestNiche,
                'subcategory' => $subcategory,
            ]);

            return [
                'niche' => $bestNiche,
                'subcategory' => $subcategory,
            ];

        } catch (\Exception $e) {
            Log::channel($this->logChannel)->error('Erro ao identificar nicho via RAG', [
                'error' => $e->getMessage(),
            ]);

            return $defaultResult;
        }
    }

    /**
     * Identify subcategory within a niche using keyword matching and semantic analysis.
     *
     * @param  string  $niche  The identified niche
     * @param  string  $contextText  The store context text
     * @param  array  $embedding  The pre-generated embedding
     * @return string The identified subcategory
     */
    private function identifySubcategory(string $niche, string $contextText, array $embedding): string
    {
        // Get available subcategories for this niche from config
        $nicheConfig = config("niches.niches.{$niche}");

        if (! $nicheConfig || empty($nicheConfig['subcategories'])) {
            return 'geral';
        }

        $subcategories = $nicheConfig['subcategories'];
        $contextLower = strtolower($contextText);

        // Define keyword mappings for each niche's subcategories
        $subcategoryKeywords = $this->getSubcategoryKeywords($niche);

        // Score each subcategory based on keyword matches
        $scores = [];
        foreach ($subcategories as $subKey => $subLabel) {
            if ($subKey === 'geral') {
                continue; // Skip 'geral', it's the fallback
            }

            $scores[$subKey] = 0;

            // Check keywords for this subcategory
            $keywords = $subcategoryKeywords[$subKey] ?? [];
            foreach ($keywords as $keyword) {
                if (str_contains($contextLower, strtolower($keyword))) {
                    $scores[$subKey] += 10;
                }
            }

            // Also check the subcategory label itself
            if (str_contains($contextLower, strtolower($subLabel))) {
                $scores[$subKey] += 15;
            }

            // Check the subcategory key
            if (str_contains($contextLower, strtolower($subKey))) {
                $scores[$subKey] += 15;
            }
        }

        // Get best matching subcategory
        arsort($scores);
        $bestSubcategory = array_key_first($scores);

        // Only return if we have a meaningful match (score > 0)
        if ($bestSubcategory && $scores[$bestSubcategory] > 0) {
            Log::channel($this->logChannel)->info('Subcategoria identificada via keywords', [
                'niche' => $niche,
                'subcategory' => $bestSubcategory,
                'score' => $scores[$bestSubcategory],
                'all_scores' => array_filter($scores),
            ]);

            return $bestSubcategory;
        }

        // Fallback to 'geral' if no match found
        return 'geral';
    }

    /**
     * Get keyword mappings for subcategory identification.
     */
    private function getSubcategoryKeywords(string $niche): array
    {
        $keywords = [
            'beauty' => [
                'haircare' => ['cabelo', 'cabelos', 'capilar', 'shampoo', 'condicionador', 'hidratação', 'tratamento capilar', 'lisos', 'cacheados', 'loiros', 'coloração', 'tintura', 'progressiva', 'botox capilar', 'leave-in', 'máscara capilar', 'finalizador', 'óleo capilar', 'queda de cabelo', 'crescimento capilar'],
                'skincare' => ['pele', 'skincare', 'rosto', 'facial', 'hidratante', 'sérum', 'vitamina c', 'retinol', 'ácido', 'anti-idade', 'antirrugas', 'protetor solar', 'fps', 'limpeza de pele', 'esfoliante', 'tônico', 'acne', 'oleosidade', 'manchas'],
                'maquiagem' => ['maquiagem', 'make', 'makeup', 'batom', 'base', 'pó', 'blush', 'sombra', 'rímel', 'máscara', 'delineador', 'corretivo', 'primer', 'iluminador', 'contorno', 'paleta', 'gloss', 'lápis'],
                'perfumaria' => ['perfume', 'perfumaria', 'fragrância', 'eau de', 'colônia', 'body splash', 'desodorante', 'essência'],
                'corpo_banho' => ['corpo', 'banho', 'sabonete', 'hidratante corporal', 'loção', 'creme corporal', 'esfoliante corporal', 'óleo corporal', 'desodorante', 'depilação'],
            ],
            'moda' => [
                'feminino' => ['feminino', 'feminina', 'mulher', 'mulheres', 'vestido', 'saia', 'blusa feminina', 'calça feminina', 'moda feminina'],
                'masculino' => ['masculino', 'masculina', 'homem', 'homens', 'camisa masculina', 'calça masculina', 'moda masculina', 'bermuda'],
                'infantil' => ['infantil', 'criança', 'crianças', 'kids', 'bebê', 'menino', 'menina', 'roupa infantil'],
                'calcados' => ['calçado', 'calçados', 'sapato', 'tênis', 'sandália', 'bota', 'sapatilha', 'chinelo', 'salto'],
                'acessorios' => ['acessório', 'acessórios', 'bolsa', 'cinto', 'carteira', 'óculos', 'chapéu', 'boné', 'lenço', 'cachecol', 'mochila'],
                'intima' => ['íntima', 'lingerie', 'calcinha', 'sutiã', 'cueca', 'pijama', 'camisola', 'robe'],
                'praia' => ['praia', 'biquíni', 'maiô', 'sunga', 'saída de praia', 'cangas', 'verão'],
            ],
            'eletronicos' => [
                'smartphones' => ['smartphone', 'celular', 'iphone', 'samsung', 'xiaomi', 'motorola', 'tablet', 'ipad'],
                'informatica' => ['computador', 'notebook', 'laptop', 'pc', 'monitor', 'teclado', 'mouse', 'impressora', 'hd', 'ssd', 'memória', 'processador'],
                'games' => ['game', 'games', 'videogame', 'playstation', 'xbox', 'nintendo', 'console', 'joystick', 'controle'],
                'audio_video' => ['áudio', 'vídeo', 'fone', 'headset', 'caixa de som', 'speaker', 'tv', 'televisão', 'home theater', 'soundbar'],
                'acessorios' => ['acessório', 'carregador', 'cabo', 'case', 'capa', 'película', 'suporte', 'power bank'],
            ],
            'alimentos' => [
                'gourmet' => ['gourmet', 'importado', 'premium', 'artesanal', 'especial', 'delicatessen'],
                'saudaveis' => ['saudável', 'saudáveis', 'orgânico', 'natural', 'fit', 'diet', 'light', 'integral', 'sem glúten', 'vegano', 'vegetariano', 'zero açúcar'],
                'bebidas' => ['bebida', 'café', 'chá', 'vinho', 'cerveja', 'suco', 'água', 'refrigerante', 'energético'],
                'doces' => ['doce', 'chocolate', 'bombom', 'biscoito', 'cookie', 'bolo', 'confeitaria', 'sobremesa'],
            ],
            'pet' => [
                'racao' => ['ração', 'alimento', 'petisco', 'snack', 'sachê', 'comida'],
                'acessorios' => ['acessório', 'coleira', 'guia', 'cama', 'casinha', 'brinquedo', 'comedouro', 'bebedouro', 'transporte', 'caixa de transporte'],
                'higiene' => ['higiene', 'banho', 'shampoo', 'condicionador', 'perfume', 'escova', 'tosa', 'limpeza'],
                'medicamentos' => ['medicamento', 'remédio', 'vermífugo', 'antipulgas', 'vacina', 'suplemento', 'vitamina'],
            ],
            'saude' => [
                'suplementos' => ['suplemento', 'whey', 'proteína', 'creatina', 'bcaa', 'pré-treino', 'termogênico', 'vitamina', 'mineral', 'ômega'],
                'fitness' => ['fitness', 'academia', 'treino', 'musculação', 'exercício', 'malhar'],
                'natural' => ['natural', 'fitoterápico', 'homeopático', 'ervas', 'chá', 'óleo essencial', 'aromaterapia'],
                'farmacia' => ['farmácia', 'medicamento', 'remédio', 'genérico', 'drogaria'],
                'ortopedicos' => ['ortopédico', 'postura', 'coluna', 'joelheira', 'munhequeira', 'cinta', 'palmilha'],
            ],
            'esportes' => [
                'fitness' => ['fitness', 'academia', 'musculação', 'crossfit', 'funcional', 'yoga', 'pilates'],
                'outdoor' => ['outdoor', 'camping', 'trilha', 'escalada', 'aventura', 'montanhismo', 'trekking'],
                'aquaticos' => ['natação', 'mergulho', 'surf', 'stand up', 'sup', 'piscina', 'aquático'],
                'ciclismo' => ['ciclismo', 'bicicleta', 'bike', 'pedal', 'mtb', 'speed', 'capacete', 'luva ciclismo'],
                'futebol' => ['futebol', 'chuteira', 'bola', 'camisa time', 'goleiro', 'caneleira'],
                'vestuario' => ['vestuário esportivo', 'roupa esportiva', 'legging', 'top', 'shorts', 'regata', 'camiseta dry fit'],
            ],
            'infantil' => [
                'roupas' => ['roupa', 'vestido', 'macacão', 'body', 'calça', 'camiseta', 'pijama infantil', 'conjunto'],
                'brinquedos' => ['brinquedo', 'boneca', 'carrinho', 'lego', 'jogo', 'puzzle', 'pelúcia', 'educativo'],
                'higiene' => ['higiene', 'fralda', 'lenço umedecido', 'shampoo infantil', 'creme', 'talco', 'chupeta', 'mamadeira'],
                'alimentacao' => ['alimentação', 'papinha', 'fórmula', 'leite', 'cadeirinha', 'babador', 'pratinho', 'copo'],
                'moveis' => ['móvel', 'berço', 'cômoda', 'poltrona', 'tapete', 'decoração quarto'],
            ],
            'casa_decoracao' => [
                'moveis' => ['móvel', 'móveis', 'sofá', 'mesa', 'cadeira', 'estante', 'rack', 'guarda-roupa', 'cama', 'armário'],
                'decoracao' => ['decoração', 'quadro', 'vaso', 'espelho', 'relógio', 'tapete', 'cortina', 'almofada', 'objeto decorativo'],
                'cama_mesa_banho' => ['cama mesa banho', 'lençol', 'edredom', 'cobertor', 'toalha', 'travesseiro', 'fronha', 'jogo de cama'],
                'utilidades' => ['utilidade', 'cozinha', 'panela', 'forma', 'pote', 'organizador', 'lixeira', 'cesto'],
                'jardim' => ['jardim', 'jardinagem', 'vaso', 'planta', 'horta', 'churrasqueira', 'piscina', 'área externa'],
                'iluminacao' => ['iluminação', 'luminária', 'abajur', 'lustre', 'pendente', 'spot', 'led', 'lâmpada'],
            ],
            'joias_relogios' => [
                'joias' => ['joia', 'ouro', 'prata', 'anel', 'aliança', 'colar', 'brinco', 'pulseira', 'pingente', 'diamante'],
                'semi_joias' => ['semi-joia', 'folheado', 'banhado', 'aço inoxidável'],
                'relogios' => ['relógio', 'smartwatch', 'cronômetro', 'pulseira inteligente'],
                'bijuterias' => ['bijuteria', 'fantasia', 'acessório'],
            ],
            'papelaria' => [
                'escolar' => ['escolar', 'escola', 'caderno', 'lápis', 'caneta', 'borracha', 'apontador', 'mochila escolar', 'estojo'],
                'escritorio' => ['escritório', 'office', 'agenda', 'planner', 'pasta', 'arquivo', 'grampeador', 'calculadora'],
                'artesanato' => ['artesanato', 'diy', 'scrapbook', 'eva', 'feltro', 'tecido', 'linha', 'agulha', 'crochê', 'tricô'],
                'presentes' => ['presente', 'embalagem', 'papel de presente', 'sacola', 'caixa', 'laço', 'fita'],
            ],
            'automotivo' => [
                'acessorios' => ['acessório', 'tapete', 'capa banco', 'volante', 'porta-treco', 'organizador'],
                'pecas' => ['peça', 'filtro', 'óleo', 'pneu', 'bateria', 'pastilha', 'lâmpada', 'correia'],
                'som' => ['som automotivo', 'alto-falante', 'subwoofer', 'módulo', 'rádio', 'multimídia', 'central'],
                'cuidados' => ['limpeza', 'cera', 'polish', 'silicone', 'pretinho', 'flanela', 'lavagem'],
            ],
        ];

        return $keywords[$niche] ?? [];
    }
}
