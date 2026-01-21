<?php

namespace App\Services\ExternalData;

use App\Models\Store;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CompetitorAnalysisService
{
    private string $logChannel = 'analysis';

    private ?DecodoProxyService $decodoProxy = null;

    private DecodoParserService $parser;

    public function __construct(?DecodoProxyService $decodoProxy = null, ?DecodoParserService $parser = null)
    {
        $this->decodoProxy = $decodoProxy;
        $this->parser = $parser ?? new DecodoParserService;
    }

    /**
     * Check if Decodo proxy should be used.
     */
    private function shouldUseDecodo(): bool
    {
        return $this->decodoProxy !== null && $this->decodoProxy->isEnabled();
    }

    /**
     * Check if the service is enabled.
     * Reads directly from database to avoid cache issues with queue workers.
     */
    public function isEnabled(): bool
    {
        $mainEnabled = SystemSetting::where('key', 'external_data.enabled')->first();
        $competitorsEnabled = SystemSetting::where('key', 'external_data.competitors.enabled')->first();

        return ($mainEnabled ? (bool) $mainEnabled->getCastedValue() : false)
            && ($competitorsEnabled ? (bool) $competitorsEnabled->getCastedValue() : false);
    }

    /**
     * Get max competitors per store setting.
     */
    private function getMaxPerStore(): int
    {
        return (int) SystemSetting::get('external_data.competitors.max_per_store', 5);
    }

    /**
     * Get scrape timeout setting.
     */
    private function getScrapeTimeout(): int
    {
        return (int) SystemSetting::get('external_data.competitors.scrape_timeout', 15);
    }

    /**
     * Analyze competitors for a store.
     *
     * @param  Store  $store  Store to analyze competitors for
     * @return array Competitor analysis data
     */
    public function analyze(Store $store): array
    {
        // Debug: Log raw competitors data
        Log::channel($this->logChannel)->debug('CompetitorAnalysisService: Debug store data', [
            'store_id' => $store->id,
            'store_name' => $store->name,
            'competitors_raw' => $store->getAttributes()['competitors'] ?? 'NOT_IN_ATTRIBUTES',
            'competitors_accessor' => $store->competitors,
            'competitors_type' => gettype($store->competitors),
        ]);

        $competitors = $store->competitors ?? [];

        if (empty($competitors)) {
            Log::channel($this->logChannel)->info('CompetitorAnalysisService: No competitors found', [
                'store_id' => $store->id,
            ]);

            return [
                'tem_concorrentes' => false,
                'concorrentes' => [],
                'concorrentes_informados' => 0,
                'concorrentes_analisados' => 0,
                'resumo' => 'Nenhum concorrente informado pelo cliente.',
            ];
        }

        if (! $this->isEnabled()) {
            return [
                'tem_concorrentes' => true,
                'concorrentes' => [],
                'concorrentes_informados' => count($competitors),
                'concorrentes_analisados' => 0,
                'resumo' => 'Análise de concorrentes desabilitada.',
            ];
        }

        // Limit competitors
        $competitors = array_slice($competitors, 0, $this->getMaxPerStore());

        $analyzedCompetitors = [];
        $successCount = 0;

        foreach ($competitors as $competitor) {
            $url = $competitor['url'] ?? '';
            $name = $competitor['name'] ?? $this->extractDomain($url);

            if (empty($url)) {
                Log::channel($this->logChannel)->debug('CompetitorAnalysisService: URL vazia, pulando', [
                    'name' => $name,
                ]);

                continue;
            }

            // Scrape competitor
            Log::channel($this->logChannel)->info('CompetitorAnalysisService: Iniciando scraping', [
                'name' => $name,
                'url' => $url,
            ]);

            $result = $this->scrapeCompetitor($url, $name);
            $analyzedCompetitors[] = $result;

            Log::channel($this->logChannel)->info('CompetitorAnalysisService: Scraping concluído', [
                'name' => $name,
                'url' => $url,
                'sucesso' => $result['sucesso'],
                'produtos_estimados' => $result['produtos_estimados'] ?? 0,
                'faixa_preco' => $result['faixa_preco'] ?? [],
                'diferenciais' => $result['diferenciais'] ?? [],
                'motivo_falha' => $result['motivo_falha'] ?? null,
            ]);

            if ($result['sucesso']) {
                $successCount++;
            }
        }

        $informados = count($competitors);
        $resumo = $successCount === $informados
            ? "Todos os {$informados} concorrentes analisados com sucesso."
            : "{$successCount} de {$informados} concorrentes analisados.";

        Log::channel($this->logChannel)->info('CompetitorAnalysisService: Analysis complete', [
            'store_id' => $store->id,
            'informados' => $informados,
            'analisados' => $successCount,
        ]);

        return [
            'tem_concorrentes' => true,
            'concorrentes' => $analyzedCompetitors,
            'concorrentes_informados' => $informados,
            'concorrentes_analisados' => $successCount,
            'resumo' => $resumo,
        ];
    }

    /**
     * Scrape a competitor website.
     */
    private function scrapeCompetitor(string $url, string $name): array
    {
        try {
            $useDecodo = $this->shouldUseDecodo();

            Log::channel($this->logChannel)->debug('CompetitorAnalysisService: Starting scrape', [
                'url' => $url,
                'name' => $name,
                'using_decodo' => $useDecodo,
            ]);

            if ($useDecodo) {
                // Use Decodo API for better success rate
                $result = $this->decodoProxy->get($url);

                if (! $result['success']) {
                    // Fallback to direct request if Decodo fails
                    Log::channel($this->logChannel)->info('CompetitorAnalysisService: Decodo failed, trying direct', [
                        'url' => $url,
                        'error' => $result['error'],
                    ]);

                    return $this->scrapeDirectly($url, $name);
                }

                $content = $result['body'];

                // Log full markdown content for debugging
                Log::channel($this->logChannel)->debug('CompetitorAnalysisService: Decodo markdown response', [
                    'url' => $url,
                    'name' => $name,
                    'content_length' => strlen($content),
                    'content_preview' => substr($content, 0, 500).'...',
                ]);

                // Log full content in a separate entry for detailed analysis
                Log::channel($this->logChannel)->debug('CompetitorAnalysisService: Full Decodo content', [
                    'url' => $url,
                    'full_markdown' => $content,
                ]);
            } else {
                // Direct request without proxy
                return $this->scrapeDirectly($url, $name);
            }

            // Use parser to extract structured data
            $parsedData = $this->parser->parse($content);

            return [
                'nome' => $name,
                'url' => $url,
                'produtos_estimados' => $parsedData['produtos_estimados'],
                'faixa_preco' => $parsedData['estatisticas_preco'],
                'diferenciais' => $parsedData['diferenciais'],
                'sucesso' => true,
                'motivo_falha' => null,
                'data_coleta' => now()->toISOString(),
                'proxy_usado' => $useDecodo ? 'decodo' : 'direto',
                // Rich data from parser
                'dados_ricos' => [
                    'produtos' => $parsedData['produtos'],
                    'avaliacoes' => $parsedData['avaliacoes'],
                    'categorias' => $parsedData['categorias'],
                    'promocoes' => $parsedData['promocoes'],
                    'precos_detalhados' => $parsedData['precos'],
                ],
            ];

        } catch (\Exception $e) {
            Log::channel($this->logChannel)->warning('CompetitorAnalysisService: Scrape failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $this->emptyCompetitorResponse($name, $url, $e->getMessage());
        }
    }

    /**
     * Scrape directly without proxy (original method).
     */
    private function scrapeDirectly(string $url, string $name): array
    {
        try {
            $response = Http::timeout($this->getScrapeTimeout())
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                ])
                ->get($url);

            if (! $response->successful()) {
                return $this->emptyCompetitorResponse($name, $url, 'HTTP '.$response->status());
            }

            $html = $response->body();

            // Use parser to extract structured data
            $parsedData = $this->parser->parse($html);

            return [
                'nome' => $name,
                'url' => $url,
                'produtos_estimados' => $parsedData['produtos_estimados'],
                'faixa_preco' => $parsedData['estatisticas_preco'],
                'diferenciais' => $parsedData['diferenciais'],
                'sucesso' => true,
                'motivo_falha' => null,
                'data_coleta' => now()->toISOString(),
                'proxy_usado' => 'direto',
                // Rich data from parser
                'dados_ricos' => [
                    'produtos' => $parsedData['produtos'],
                    'avaliacoes' => $parsedData['avaliacoes'],
                    'categorias' => $parsedData['categorias'],
                    'promocoes' => $parsedData['promocoes'],
                    'precos_detalhados' => $parsedData['precos'],
                ],
            ];

        } catch (\Exception $e) {
            Log::channel($this->logChannel)->warning('CompetitorAnalysisService: Direct scrape failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $this->emptyCompetitorResponse($name, $url, $e->getMessage());
        }
    }

    /**
     * Extract prices from HTML.
     */
    private function extractPrices(string $html): array
    {
        $prices = [];

        // Common price patterns in e-commerce HTML
        $patterns = [
            '/R\$\s*([\d.,]+)/i',
            '/data-price=["\']?([\d.,]+)["\']?/i',
            '/"price":\s*([\d.,]+)/i',
            '/itemprop=["\']?price["\']?\s*content=["\']?([\d.,]+)["\']?/i',
            '/class=["\'][^"\']*price[^"\']*["\'][^>]*>R?\$?\s*([\d.,]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $match) {
                    $price = $this->parsePrice($match);
                    if ($price > 0 && $price < 100000) { // Sanity check
                        $prices[] = $price;
                    }
                }
            }
        }

        return array_unique($prices);
    }

    /**
     * Extract features/differentiators from content (HTML or Markdown).
     */
    private function extractFeatures(string $content): array
    {
        $features = [];
        $contentLower = strtolower($content);

        // Feature patterns to look for (expanded for better detection)
        $featurePatterns = [
            'frete gratis' => ['frete grátis', 'frete gratuito', 'free shipping', 'entrega grátis', 'frete gratis'],
            'parcelamento' => ['parcele em', 'parcelamento', 'em até 12x', 'em até 10x', 'sem juros', 'parcela', '10x', '12x'],
            'entrega rapida' => ['entrega rápida', 'entrega expressa', 'same day', 'entrega em 24h', 'receba hoje', 'entrega no mesmo dia'],
            'cashback' => ['cashback', 'dinheiro de volta', 'cash back'],
            'primeira compra' => ['primeira compra', 'first purchase', 'desconto primeira', 'cupom de boas-vindas', 'boas vindas'],
            'fidelidade' => ['fidelidade', 'pontos', 'rewards', 'clube de vantagens', 'programa de pontos', 'clube'],
            'troca facil' => ['troca fácil', 'devolução grátis', 'troca grátis', '30 dias para troca', 'trocas e devoluções'],
            'garantia estendida' => ['garantia estendida', 'extended warranty', '12 meses de garantia', 'garantia'],
            'vegano' => ['vegano', 'vegan', 'ingredientes veganos', '100% vegano'],
            'cruelty free' => ['cruelty-free', 'cruelty free', 'não testados em animais', 'livre de crueldade'],
            'sustentavel' => ['sustentável', 'sustentavel', 'eco-friendly', 'reciclável', 'eureciclo'],
            'app exclusivo' => ['baixe o app', 'aplicativo', 'app exclusivo', 'download app'],
            'reviews positivos' => ['4.9', '5.0', '4.8', 'reviews', 'avaliações', 'rating'],
            'outlet' => ['outlet', 'promoção', 'desconto', 'off', 'black friday', 'sale'],
            'quiz personalizado' => ['quiz', 'teste personalizado', 'descubra seu'],
            'whatsapp' => ['whatsapp', 'fale conosco', 'atendimento whatsapp'],
        ];

        foreach ($featurePatterns as $feature => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($contentLower, strtolower($keyword))) {
                    $features[] = $feature;
                    break;
                }
            }
        }

        return array_values(array_unique($features));
    }

    /**
     * Estimate product count from content (HTML or Markdown).
     */
    private function estimateProductCount(string $content): int
    {
        // Look for total products indicators
        $patterns = [
            '/(\d+)\s*produtos?\s*encontrado/i',
            '/total[:\s]*(\d+)\s*produto/i',
            '/mostrando\s*\d+\s*de\s*(\d+)/i',
            '/(\d+)\s*resultado/i',
            '/(\d+)\s*itens/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return (int) $matches[1];
            }
        }

        // Count product cards from HTML
        $htmlProductPatterns = [
            'class="product"',
            'class="produto"',
            'data-product-id',
            'class="item-product"',
        ];

        $count = 0;
        foreach ($htmlProductPatterns as $pattern) {
            $count += substr_count(strtolower($content), strtolower($pattern));
        }

        if ($count > 0) {
            return $count;
        }

        // Count from Markdown patterns (e.g., product links, "Adicionar ao Carrinho" buttons)
        $markdownProductPatterns = [
            'adicionar ao carrinho',
            '/products/',
            'preço promocional',
            'price',
        ];

        foreach ($markdownProductPatterns as $pattern) {
            $count += substr_count(strtolower($content), strtolower($pattern));
        }

        // Divide by expected occurrences per product (rough estimate)
        if ($count > 0) {
            // "Adicionar ao Carrinho" + "Preço" typically appear once per product
            $estimatedProducts = (int) ceil($count / 2);

            return min($estimatedProducts, 100); // Cap at 100
        }

        return 0;
    }

    /**
     * Parse price string to float.
     */
    private function parsePrice(string $price): float
    {
        // Remove non-numeric except comma and dot
        $price = preg_replace('/[^\d,.]/', '', $price);

        // Handle Brazilian format (1.234,56)
        if (preg_match('/^\d{1,3}(\.\d{3})*,\d{2}$/', $price)) {
            $price = str_replace('.', '', $price);
            $price = str_replace(',', '.', $price);
        }
        // Handle format with comma as decimal (123,45)
        elseif (preg_match('/^\d+,\d{2}$/', $price)) {
            $price = str_replace(',', '.', $price);
        }

        return (float) $price;
    }

    /**
     * Calculate price range from array of prices.
     */
    private function calculatePriceRange(array $prices): array
    {
        if (empty($prices)) {
            return [
                'min' => 0,
                'max' => 0,
                'media' => 0,
            ];
        }

        // Remove outliers
        sort($prices);
        if (count($prices) > 4) {
            // Remove top and bottom 10%
            $cutoff = (int) ceil(count($prices) * 0.1);
            $prices = array_slice($prices, $cutoff, count($prices) - (2 * $cutoff));
        }

        if (empty($prices)) {
            return [
                'min' => 0,
                'max' => 0,
                'media' => 0,
            ];
        }

        return [
            'min' => round(min($prices), 2),
            'max' => round(max($prices), 2),
            'media' => round(array_sum($prices) / count($prices), 2),
        ];
    }

    /**
     * Extract domain from URL.
     */
    private function extractDomain(string $url): string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? $url;

        // Remove www.
        return preg_replace('/^www\./', '', $host);
    }

    /**
     * Return empty competitor response.
     */
    private function emptyCompetitorResponse(string $name, string $url, string $reason): array
    {
        return [
            'nome' => $name,
            'url' => $url,
            'produtos_estimados' => 0,
            'faixa_preco' => [
                'min' => 0,
                'max' => 0,
                'media' => 0,
                'mediana' => 0,
            ],
            'diferenciais' => [],
            'sucesso' => false,
            'motivo_falha' => $reason,
            'data_coleta' => now()->toISOString(),
            'dados_ricos' => [
                'produtos' => [],
                'avaliacoes' => ['nota_media' => null, 'total_avaliacoes' => null, 'distribuicao' => []],
                'categorias' => [],
                'promocoes' => [],
                'precos_detalhados' => [],
            ],
        ];
    }
}
