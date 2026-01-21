<?php

namespace App\Services\ExternalData;

use App\Enums\Platform;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MarketDataService
{
    private string $logChannel = 'analysis';

    /**
     * Check if the service is enabled.
     * Reads directly from database to avoid cache issues with queue workers.
     */
    private function isEnabled(): bool
    {
        $mainEnabled = SystemSetting::where('key', 'external_data.enabled')->first();
        $marketEnabled = SystemSetting::where('key', 'external_data.market.enabled')->first();

        return ($mainEnabled ? (bool) $mainEnabled->getCastedValue() : false)
            && ($marketEnabled ? (bool) $marketEnabled->getCastedValue() : false);
    }

    /**
     * Get the API key.
     */
    private function getApiKey(): ?string
    {
        return SystemSetting::get('external_data.serpapi_key');
    }

    /**
     * Check if the service is configured and enabled.
     */
    public function isConfigured(): bool
    {
        return $this->isEnabled() && ! empty($this->getApiKey());
    }

    /**
     * Get market data based on platform.
     *
     * @param  Platform  $platform  Client's platform
     * @param  string  $niche  Store niche
     * @param  string  $subcategory  Store subcategory
     * @param  array  $topProducts  Top products names for search
     * @return array Market data
     */
    public function getMarketData(Platform $platform, string $niche, string $subcategory, array $topProducts = []): array
    {
        if (! $this->isConfigured()) {
            return $this->emptyResponse('Serviço não configurado');
        }

        $source = $this->determineSource($platform);

        try {
            $result = match ($source) {
                'google_shopping' => $this->fetchGoogleShoppingData($niche, $subcategory, $topProducts),
                default => $this->emptyResponse('Fonte de dados não suportada'),
            };

            Log::channel($this->logChannel)->info('MarketDataService: Fetched market data', [
                'source' => $source,
                'niche' => $niche,
                'subcategory' => $subcategory,
                'preco_medio' => $result['faixa_preco']['media'] ?? 0,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::channel($this->logChannel)->error('MarketDataService: Error fetching market data', [
                'source' => $source,
                'niche' => $niche,
                'error' => $e->getMessage(),
            ]);

            return $this->emptyResponse('Erro ao buscar dados: '.$e->getMessage());
        }
    }

    /**
     * Determine data source based on platform.
     */
    private function determineSource(Platform $platform): string
    {
        // Currently all platforms use Google Shopping
        // In the future, marketplaces could use their own APIs
        return match ($platform) {
            // Future: Platform::MercadoLivre => 'mercado_livre',
            // Future: Platform::Amazon => 'amazon',
            default => 'google_shopping',
        };
    }

    /**
     * Fetch data from Google Shopping via SerpAPI.
     */
    private function fetchGoogleShoppingData(string $niche, string $subcategory, array $topProducts = []): array
    {
        // Build search query
        $query = $this->buildSearchQuery($niche, $subcategory, $topProducts);
        $apiKey = $this->getApiKey();

        // Log masked API key for debugging
        $maskedKey = $apiKey ? substr($apiKey, 0, 8).'...'.substr($apiKey, -4) : 'NOT_SET';
        Log::channel($this->logChannel)->debug('MarketDataService: API Request', [
            'api_key_masked' => $maskedKey,
            'api_key_length' => strlen($apiKey ?? ''),
            'query' => $query,
        ]);

        $response = Http::timeout(30)->get('https://serpapi.com/search', [
            'api_key' => $apiKey,
            'engine' => 'google_shopping',
            'q' => $query,
            'location' => 'Brazil',
            'hl' => 'pt',
            'gl' => 'br',
            'num' => 20,
        ]);

        if (! $response->successful()) {
            // Log full error response for debugging
            Log::channel($this->logChannel)->error('MarketDataService: API Error Response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers(),
            ]);
            throw new \Exception('API retornou status '.$response->status().': '.$response->body());
        }

        $data = $response->json();

        // Parse shopping results
        $shoppingResults = $data['shopping_results'] ?? [];

        if (empty($shoppingResults)) {
            return $this->emptyResponse('Nenhum resultado encontrado');
        }

        // Extract prices
        $prices = [];
        $productsReference = [];

        foreach ($shoppingResults as $result) {
            $price = $this->extractPrice($result);
            if ($price > 0) {
                $prices[] = $price;

                // Keep top 10 products as reference
                if (count($productsReference) < 10) {
                    $productsReference[] = [
                        'nome' => $result['title'] ?? '',
                        'preco' => $price,
                        'avaliacao' => $result['rating'] ?? null,
                        'loja' => $result['source'] ?? '',
                    ];
                }
            }
        }

        if (empty($prices)) {
            return $this->emptyResponse('Nenhum preço válido encontrado');
        }

        // Remove outliers (prices outside 1.5 IQR)
        $prices = $this->removeOutliers($prices);

        // Calculate statistics
        sort($prices);
        $min = min($prices);
        $max = max($prices);
        $media = array_sum($prices) / count($prices);
        $mediana = $this->calculateMedian($prices);

        return [
            'fonte' => 'google_shopping',
            'faixa_preco' => [
                'min' => round($min, 2),
                'max' => round($max, 2),
                'media' => round($media, 2),
                'mediana' => round($mediana, 2),
            ],
            'produtos_referencia' => $productsReference,
            'total_produtos_analisados' => count($prices),
            'sucesso' => true,
            'motivo_falha' => null,
            'data_coleta' => now()->toISOString(),
        ];
    }

    /**
     * Build search query for Google Shopping.
     */
    private function buildSearchQuery(string $niche, string $subcategory, array $topProducts = []): string
    {
        // If we have top products, use them
        if (! empty($topProducts)) {
            // Use first 3 products
            $products = array_slice($topProducts, 0, 3);

            return implode(' ', $products);
        }

        // Otherwise use niche + subcategory
        $nicheLabel = $this->getNicheLabel($niche);
        $subcategoryLabel = $this->getSubcategoryLabel($subcategory);

        return "{$nicheLabel} {$subcategoryLabel}";
    }

    /**
     * Extract price from shopping result.
     */
    private function extractPrice(array $result): float
    {
        // Try extracted_price first (already parsed)
        if (isset($result['extracted_price'])) {
            return (float) $result['extracted_price'];
        }

        // Try price string
        if (isset($result['price'])) {
            $price = preg_replace('/[^\d,.]/', '', $result['price']);
            $price = str_replace(',', '.', $price);

            return (float) $price;
        }

        return 0;
    }

    /**
     * Remove outliers using IQR method.
     */
    private function removeOutliers(array $values): array
    {
        if (count($values) < 4) {
            return $values;
        }

        sort($values);
        $count = count($values);

        $q1Index = (int) floor($count * 0.25);
        $q3Index = (int) floor($count * 0.75);

        $q1 = $values[$q1Index];
        $q3 = $values[$q3Index];
        $iqr = $q3 - $q1;

        $lowerBound = $q1 - (1.5 * $iqr);
        $upperBound = $q3 + (1.5 * $iqr);

        return array_values(array_filter($values, fn ($v) => $v >= $lowerBound && $v <= $upperBound));
    }

    /**
     * Calculate median of values.
     */
    private function calculateMedian(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0;
        }

        sort($values);
        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * Get human-readable niche label.
     */
    private function getNicheLabel(string $niche): string
    {
        $labels = [
            'beauty' => 'beleza cosméticos',
            'moda' => 'moda roupas',
            'eletronicos' => 'eletrônicos',
            'casa_decoracao' => 'casa decoração',
            'alimentos' => 'alimentos',
            'pet' => 'pet shop',
            'saude' => 'saúde bem-estar',
            'esportes' => 'esportes fitness',
            'infantil' => 'infantil bebê',
            'joias_relogios' => 'joias relógios',
        ];

        return $labels[$niche] ?? $niche;
    }

    /**
     * Get human-readable subcategory label.
     */
    private function getSubcategoryLabel(string $subcategory): string
    {
        $labels = [
            'haircare' => 'cabelos',
            'skincare' => 'pele rosto',
            'maquiagem' => 'maquiagem',
            'perfumaria' => 'perfumes',
            'masculino' => 'masculino',
            'feminino' => 'feminino',
            'premium' => 'premium',
        ];

        return $labels[$subcategory] ?? $subcategory;
    }

    /**
     * Return empty response with error message.
     */
    private function emptyResponse(?string $reason = null): array
    {
        return [
            'fonte' => 'google_shopping',
            'faixa_preco' => [
                'min' => 0,
                'max' => 0,
                'media' => 0,
                'mediana' => 0,
            ],
            'produtos_referencia' => [],
            'total_produtos_analisados' => 0,
            'sucesso' => false,
            'motivo_falha' => $reason,
            'data_coleta' => now()->toISOString(),
        ];
    }
}
