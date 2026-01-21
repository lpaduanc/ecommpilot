<?php

namespace App\Services\ExternalData;

use Illuminate\Support\Facades\Log;

/**
 * Service to parse raw content from Decodo into structured data.
 * Transforms HTML/Markdown into JSON with prices, products, reviews, etc.
 */
class DecodoParserService
{
    private string $logChannel = 'analysis';

    /**
     * Parse raw content into structured data.
     */
    public function parse(string $content): array
    {
        $startTime = microtime(true);

        $result = [
            'precos' => $this->extractPrices($content),
            'produtos' => $this->extractProducts($content),
            'avaliacoes' => $this->extractReviews($content),
            'categorias' => $this->extractCategories($content),
            'promocoes' => $this->extractPromotions($content),
            'diferenciais' => $this->extractFeatures($content),
            'produtos_estimados' => $this->estimateProductCount($content),
        ];

        // Calculate price statistics
        $result['estatisticas_preco'] = $this->calculatePriceStatistics($result['precos']);

        $parseTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::channel($this->logChannel)->debug('DecodoParserService: Parsed content', [
            'precos_encontrados' => count($result['precos']),
            'produtos_encontrados' => count($result['produtos']),
            'avaliacoes_encontradas' => ! empty($result['avaliacoes']['nota_media']),
            'categorias_encontradas' => count($result['categorias']),
            'promocoes_encontradas' => count($result['promocoes']),
            'diferenciais_encontrados' => count($result['diferenciais']),
            'parse_time_ms' => $parseTime,
        ]);

        return $result;
    }

    /**
     * Extract all prices from content.
     */
    public function extractPrices(string $content): array
    {
        $prices = [];

        // Common price patterns in e-commerce content
        $patterns = [
            // Brazilian Real format: R$ 99,90 or R$ 1.234,56
            '/R\$\s*([\d.,]+)/i',
            // HTML data attributes
            '/data-price=["\']?([\d.,]+)["\']?/i',
            // JSON inline
            '/"price":\s*([\d.,]+)/i',
            '/"preco":\s*([\d.,]+)/i',
            // Schema.org
            '/itemprop=["\']?price["\']?\s*content=["\']?([\d.,]+)["\']?/i',
            // CSS classes with price
            '/class=["\'][^"\']*price[^"\']*["\'][^>]*>R?\$?\s*([\d.,]+)/i',
            // Markdown bold prices
            '/\*\*R\$\s*([\d.,]+)\*\*/i',
            // Price from/to patterns
            '/de\s*R\$\s*([\d.,]+)/i',
            '/por\s*R\$\s*([\d.,]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $match) {
                    $price = $this->parsePrice($match);
                    if ($price > 0 && $price < 100000) { // Sanity check
                        $prices[] = $price;
                    }
                }
            }
        }

        return array_values(array_unique($prices));
    }

    /**
     * Extract products with their details.
     */
    public function extractProducts(string $content): array
    {
        $products = [];

        // Pattern for product name + price combinations
        // Matches: "Product Name - R$ 99,90" or "**Product Name** R$ 99,90"
        $patterns = [
            // Markdown: **Nome do Produto** R$ 99,90
            '/\*\*([^*]+)\*\*[^R]*R\$\s*([\d.,]+)/i',
            // HTML title + price
            '/class=["\'][^"\']*product[^"\']*["\'][^>]*>([^<]+)<[^>]*>.*?R\$\s*([\d.,]+)/is',
            // Link text + price
            '/<a[^>]+>([^<]+)<\/a>[^R]*R\$\s*([\d.,]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $name = trim($match[1]);
                    $price = $this->parsePrice($match[2]);

                    if (strlen($name) > 3 && strlen($name) < 200 && $price > 0) {
                        $products[] = [
                            'nome' => $name,
                            'preco' => $price,
                        ];
                    }
                }
            }
        }

        // Limit to 50 products and remove duplicates by name
        $seen = [];
        $unique = [];
        foreach ($products as $product) {
            $key = strtolower($product['nome']);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $product;
            }
        }

        return array_slice($unique, 0, 50);
    }

    /**
     * Extract review/rating information.
     */
    public function extractReviews(string $content): array
    {
        $reviews = [
            'nota_media' => null,
            'total_avaliacoes' => null,
            'distribuicao' => [],
        ];

        // Rating patterns
        $ratingPatterns = [
            // "4.9/5" or "4,9/5"
            '/([\d,\.]+)\s*\/\s*5/i',
            // "rating: 4.9"
            '/rating[:\s]*([\d,\.]+)/i',
            // "4.9 estrelas"
            '/([\d,\.]+)\s*estrelas?/i',
            // "avaliação: 4.9"
            '/avalia[çc][aã]o[:\s]*([\d,\.]+)/i',
            // Schema.org rating
            '/ratingValue["\']?\s*[:=]\s*["\']?([\d,\.]+)/i',
        ];

        foreach ($ratingPatterns as $pattern) {
            if (preg_match($pattern, $content, $match)) {
                $rating = (float) str_replace(',', '.', $match[1]);
                if ($rating >= 0 && $rating <= 5) {
                    $reviews['nota_media'] = round($rating, 1);
                    break;
                }
            }
        }

        // Review count patterns
        $countPatterns = [
            // "123 avaliações"
            '/([\d.]+)\s*avalia[çc][õo]es?/i',
            // "baseado em 123 reviews"
            '/(\d+)\s*reviews?/i',
            // "reviewCount": 123
            '/reviewCount["\']?\s*[:=]\s*["\']?(\d+)/i',
        ];

        foreach ($countPatterns as $pattern) {
            if (preg_match($pattern, $content, $match)) {
                $reviews['total_avaliacoes'] = (int) str_replace('.', '', $match[1]);
                break;
            }
        }

        return $reviews;
    }

    /**
     * Extract product categories/lines.
     */
    public function extractCategories(string $content): array
    {
        $categories = [];
        $contentLower = strtolower($content);

        // Common e-commerce categories (beauty focus)
        $categoryKeywords = [
            'cabelos' => ['cabelo', 'shampoo', 'condicionador', 'máscara capilar', 'leave-in', 'finalizador'],
            'pele' => ['skincare', 'hidratante', 'sérum', 'protetor solar', 'limpeza facial', 'tônico'],
            'maquiagem' => ['maquiagem', 'batom', 'base', 'rímel', 'sombra', 'blush', 'corretivo'],
            'perfumaria' => ['perfume', 'colônia', 'body splash', 'eau de toilette', 'fragrância'],
            'corpo' => ['corpo', 'hidratante corporal', 'óleo corporal', 'esfoliante', 'sabonete'],
            'unhas' => ['unha', 'esmalte', 'base para unha', 'removedor'],
            'kits' => ['kit', 'combo', 'conjunto'],
            'acessorios' => ['acessório', 'escova', 'pente', 'necessaire', 'espelho'],
        ];

        foreach ($categoryKeywords as $category => $keywords) {
            $count = 0;
            foreach ($keywords as $keyword) {
                $count += substr_count($contentLower, strtolower($keyword));
            }
            if ($count > 0) {
                $categories[] = [
                    'nome' => $category,
                    'mencoes' => $count,
                ];
            }
        }

        // Sort by mentions
        usort($categories, fn ($a, $b) => $b['mencoes'] <=> $a['mencoes']);

        return $categories;
    }

    /**
     * Extract promotions and discounts.
     */
    public function extractPromotions(string $content): array
    {
        $promotions = [];
        $contentLower = strtolower($content);

        // Percentage discounts
        if (preg_match_all('/(\d{1,2})%\s*(?:off|desconto|de desconto)/i', $content, $matches)) {
            foreach ($matches[1] as $discount) {
                $promotions[] = [
                    'tipo' => 'desconto_percentual',
                    'valor' => (int) $discount.'%',
                ];
            }
        }

        // Coupon codes
        if (preg_match_all('/(?:cupom|código|code)[:\s]+([A-Z0-9]{4,20})/i', $content, $matches)) {
            foreach ($matches[1] as $coupon) {
                $promotions[] = [
                    'tipo' => 'cupom',
                    'codigo' => strtoupper($coupon),
                ];
            }
        }

        // Free shipping
        $freeShippingPatterns = ['frete grátis', 'frete gratuito', 'free shipping', 'entrega grátis'];
        foreach ($freeShippingPatterns as $pattern) {
            if (str_contains($contentLower, $pattern)) {
                $promotions[] = [
                    'tipo' => 'frete_gratis',
                    'descricao' => 'Frete Grátis',
                ];
                break;
            }
        }

        // Flash sales
        $flashSalePatterns = ['black friday', 'cyber monday', 'promoção relâmpago', 'só hoje', 'últimas unidades'];
        foreach ($flashSalePatterns as $pattern) {
            if (str_contains($contentLower, $pattern)) {
                $promotions[] = [
                    'tipo' => 'promocao_especial',
                    'descricao' => ucwords($pattern),
                ];
            }
        }

        return array_slice($promotions, 0, 10);
    }

    /**
     * Extract features/differentiators from content.
     */
    public function extractFeatures(string $content): array
    {
        $features = [];
        $contentLower = strtolower($content);

        // Feature patterns to look for
        $featurePatterns = [
            'frete_gratis' => ['frete grátis', 'frete gratuito', 'free shipping', 'entrega grátis', 'frete gratis'],
            'parcelamento' => ['parcele em', 'parcelamento', 'em até 12x', 'em até 10x', 'sem juros', 'parcela', '10x', '12x'],
            'entrega_rapida' => ['entrega rápida', 'entrega expressa', 'same day', 'entrega em 24h', 'receba hoje', 'entrega no mesmo dia'],
            'cashback' => ['cashback', 'dinheiro de volta', 'cash back'],
            'primeira_compra' => ['primeira compra', 'first purchase', 'desconto primeira', 'cupom de boas-vindas', 'boas vindas'],
            'fidelidade' => ['fidelidade', 'pontos', 'rewards', 'clube de vantagens', 'programa de pontos', 'clube'],
            'troca_facil' => ['troca fácil', 'devolução grátis', 'troca grátis', '30 dias para troca', 'trocas e devoluções'],
            'garantia_estendida' => ['garantia estendida', 'extended warranty', '12 meses de garantia', 'garantia'],
            'vegano' => ['vegano', 'vegan', 'ingredientes veganos', '100% vegano'],
            'cruelty_free' => ['cruelty-free', 'cruelty free', 'não testados em animais', 'livre de crueldade'],
            'sustentavel' => ['sustentável', 'sustentavel', 'eco-friendly', 'reciclável', 'eureciclo'],
            'app_exclusivo' => ['baixe o app', 'aplicativo', 'app exclusivo', 'download app'],
            'reviews_positivos' => ['4.9', '5.0', '4.8', 'reviews', 'avaliações', 'rating'],
            'outlet' => ['outlet', 'promoção', 'desconto', 'off', 'black friday', 'sale'],
            'quiz_personalizado' => ['quiz', 'teste personalizado', 'descubra seu'],
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
     * Estimate product count from content.
     */
    public function estimateProductCount(string $content): int
    {
        // Look for explicit product count indicators
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

        // Count product indicators
        $htmlPatterns = [
            'class="product"',
            'class="produto"',
            'data-product-id',
            'class="item-product"',
        ];

        $count = 0;
        foreach ($htmlPatterns as $pattern) {
            $count += substr_count(strtolower($content), strtolower($pattern));
        }

        if ($count > 0) {
            return $count;
        }

        // Count from Markdown patterns
        $markdownPatterns = [
            'adicionar ao carrinho',
            '/products/',
            'preço promocional',
        ];

        foreach ($markdownPatterns as $pattern) {
            $count += substr_count(strtolower($content), strtolower($pattern));
        }

        // Divide by expected occurrences per product
        if ($count > 0) {
            $estimatedProducts = (int) ceil($count / 2);

            return min($estimatedProducts, 100);
        }

        return 0;
    }

    /**
     * Calculate price statistics from array of prices.
     */
    private function calculatePriceStatistics(array $prices): array
    {
        if (empty($prices)) {
            return [
                'min' => 0,
                'max' => 0,
                'media' => 0,
                'mediana' => 0,
            ];
        }

        // Remove outliers using IQR
        $cleanPrices = $this->removeOutliers($prices);

        if (empty($cleanPrices)) {
            $cleanPrices = $prices;
        }

        sort($cleanPrices);

        return [
            'min' => round(min($cleanPrices), 2),
            'max' => round(max($cleanPrices), 2),
            'media' => round(array_sum($cleanPrices) / count($cleanPrices), 2),
            'mediana' => round($this->calculateMedian($cleanPrices), 2),
        ];
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
     * Parse price string to float (Brazilian format support).
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
}
