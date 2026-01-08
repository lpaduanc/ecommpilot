<?php

namespace App\Services\Integration;

use App\Contracts\ProductAdapterInterface;

/**
 * Adapter for transforming Nuvemshop product data to SyncedProduct structure.
 *
 * Nuvemshop API returns product data with localized fields (e.g., name.pt, description.pt)
 * and a complex variant structure. This adapter normalizes that data into a flat structure
 * compatible with the SyncedProduct model.
 *
 * Example Nuvemshop product structure:
 * {
 *   "id": 255132555,
 *   "name": { "pt": "Shampoo Detox 250ml" },
 *   "description": { "pt": "<html description>" },
 *   "published": true,
 *   "created_at": "2025-02-12T12:54:26+0000",
 *   "updated_at": "2025-02-12T12:54:46+0000",
 *   "variants": [
 *     {
 *       "id": 1128608380,
 *       "price": "130.00",
 *       "compare_at_price": "130.00",
 *       "promotional_price": "59.90",
 *       "stock": null,
 *       "sku": "DN-SH250/23",
 *       "barcode": "7898546694127"
 *     }
 *   ],
 *   "images": [{"id": 820702142, "src": "https://...", "position": 2}],
 *   "categories": [{"id": 29500164, "name": {"pt": "Produtos"}}]
 * }
 */
class NuvemshopProductAdapter implements ProductAdapterInterface
{
    /**
     * Default locale for extracting localized fields.
     */
    private const DEFAULT_LOCALE = 'pt';

    /**
     * Fallback locale when default is not available.
     */
    private const FALLBACK_LOCALE = 'en';

    /**
     * Transform Nuvemshop product data to SyncedProduct attributes.
     *
     * @param  array  $externalData  Raw product data from Nuvemshop API
     * @return array Normalized product attributes
     */
    public function transform(array $externalData): array
    {
        $primaryVariant = $this->getPrimaryVariant($externalData);

        return [
            'external_id' => (string) $externalData['id'],
            'name' => $this->extractLocalizedValue(
                $externalData['name'] ?? null,
                self::DEFAULT_LOCALE,
                self::FALLBACK_LOCALE,
                'Sem nome'
            ),
            'description' => $this->extractLocalizedValue(
                $externalData['description'] ?? null,
                self::DEFAULT_LOCALE,
                self::FALLBACK_LOCALE
            ),
            'price' => $this->extractPrice($primaryVariant),
            'compare_at_price' => $this->extractCompareAtPrice($primaryVariant),
            'stock_quantity' => $this->extractStockQuantity($primaryVariant),
            'sku' => $primaryVariant['sku'] ?? null,
            'images' => $this->extractImages($externalData),
            'categories' => $this->extractCategories($externalData),
            'variants' => $this->extractVariants($externalData),
            'is_active' => (bool) ($externalData['published'] ?? true),
            'external_created_at' => $externalData['created_at'] ?? null,
            'external_updated_at' => $externalData['updated_at'] ?? null,
        ];
    }

    /**
     * Extract product images from Nuvemshop data.
     *
     * Nuvemshop images structure: [{"id": 123, "src": "https://...", "position": 1}]
     * We extract only the 'src' field and sort by position.
     *
     * @param  array  $externalData  Raw product data
     * @return array Array of image URLs
     */
    public function extractImages(array $externalData): array
    {
        $images = $externalData['images'] ?? [];

        if (empty($images) || ! is_array($images)) {
            return [];
        }

        // Sort by position and extract src
        $sortedImages = collect($images)
            ->sortBy('position')
            ->pluck('src')
            ->filter() // Remove null/empty values
            ->values()
            ->toArray();

        return $sortedImages;
    }

    /**
     * Extract product categories from Nuvemshop data.
     *
     * Nuvemshop categories structure: [{"id": 123, "name": {"pt": "Categoria"}}]
     * We extract the localized name.
     *
     * @param  array  $externalData  Raw product data
     * @return array Array of category names
     */
    public function extractCategories(array $externalData): array
    {
        $categories = $externalData['categories'] ?? [];

        if (empty($categories) || ! is_array($categories)) {
            return [];
        }

        return collect($categories)
            ->map(fn ($category) => $this->extractLocalizedValue(
                $category['name'] ?? null,
                self::DEFAULT_LOCALE,
                self::FALLBACK_LOCALE
            ))
            ->filter() // Remove null/empty values
            ->values()
            ->toArray();
    }

    /**
     * Extract product variants from Nuvemshop data.
     *
     * We store the complete variant data for future reference and analytics.
     *
     * @param  array  $externalData  Raw product data
     * @return array Array of variant data
     */
    public function extractVariants(array $externalData): array
    {
        $variants = $externalData['variants'] ?? [];

        if (empty($variants) || ! is_array($variants)) {
            return [];
        }

        // Normalize variant data to ensure consistent structure
        return collect($variants)->map(function ($variant) {
            return [
                'id' => $variant['id'] ?? null,
                'product_id' => $variant['product_id'] ?? null,
                'position' => $variant['position'] ?? null,
                'price' => $variant['price'] ?? '0.00',
                'compare_at_price' => $variant['compare_at_price'] ?? null,
                'promotional_price' => $variant['promotional_price'] ?? null,
                'stock_management' => $variant['stock_management'] ?? false,
                'stock' => $variant['stock'] ?? null,
                'weight' => $variant['weight'] ?? null,
                'width' => $variant['width'] ?? null,
                'height' => $variant['height'] ?? null,
                'depth' => $variant['depth'] ?? null,
                'sku' => $variant['sku'] ?? null,
                'barcode' => $variant['barcode'] ?? null,
                'values' => $variant['values'] ?? [],
                'visible' => $variant['visible'] ?? true,
            ];
        })->toArray();
    }

    /**
     * Get the primary variant for price and stock data.
     *
     * Nuvemshop products always have at least one variant. We use the first variant
     * for primary product data (price, stock, SKU).
     *
     * @param  array  $externalData  Raw product data
     * @return array Primary variant data
     */
    public function getPrimaryVariant(array $externalData): array
    {
        $variants = $externalData['variants'] ?? [];

        if (empty($variants) || ! is_array($variants)) {
            return [];
        }

        // Return the first variant (position 1 or first in array)
        $primaryVariant = collect($variants)
            ->sortBy('position')
            ->first();

        return $primaryVariant ?? [];
    }

    /**
     * Extract localized string value with fallback.
     *
     * Nuvemshop returns localized fields as objects: {"pt": "valor", "en": "value"}
     * This method extracts the value in the preferred locale with fallback support.
     *
     * @param  array|string|null  $value  The value to extract
     * @param  string  $defaultLocale  Primary locale (e.g., 'pt')
     * @param  string  $fallbackLocale  Fallback locale (e.g., 'en')
     * @param  string|null  $default  Default value if no localized value found
     * @return string|null Extracted value
     */
    public function extractLocalizedValue(
        array|string|null $value,
        string $defaultLocale = 'pt',
        string $fallbackLocale = 'en',
        ?string $default = null
    ): ?string {
        // If null, return default
        if ($value === null) {
            return $default;
        }

        // If already a string, return it
        if (is_string($value)) {
            return $value ?: $default;
        }

        // If array, try to extract localized value
        if (is_array($value)) {
            // Try default locale
            if (isset($value[$defaultLocale]) && is_string($value[$defaultLocale])) {
                return $value[$defaultLocale] ?: $default;
            }

            // Try fallback locale
            if (isset($value[$fallbackLocale]) && is_string($value[$fallbackLocale])) {
                return $value[$fallbackLocale] ?: $default;
            }

            // Try any available locale
            foreach ($value as $localizedValue) {
                if (is_string($localizedValue) && ! empty($localizedValue)) {
                    return $localizedValue;
                }
            }
        }

        return $default;
    }

    /**
     * Extract price from variant, considering promotional price.
     *
     * Nuvemshop has both 'price' and 'promotional_price'. We use promotional_price
     * if available, otherwise fall back to regular price.
     *
     * @param  array  $variant  Variant data
     * @return string Price as decimal string
     */
    private function extractPrice(array $variant): string
    {
        // Prefer promotional_price if available and greater than 0
        if (isset($variant['promotional_price'])) {
            $promotionalPrice = (float) $variant['promotional_price'];
            if ($promotionalPrice > 0) {
                return (string) $variant['promotional_price'];
            }
        }

        // Fall back to regular price
        return (string) ($variant['price'] ?? '0.00');
    }

    /**
     * Extract compare_at_price from variant.
     *
     * If promotional_price is being used, the regular price becomes compare_at_price.
     * Otherwise, use the compare_at_price field from the variant.
     *
     * @param  array  $variant  Variant data
     * @return string|null Compare at price as decimal string
     */
    private function extractCompareAtPrice(array $variant): ?string
    {
        // If using promotional price, the regular price is the compare_at_price
        if (isset($variant['promotional_price'])) {
            $promotionalPrice = (float) $variant['promotional_price'];
            if ($promotionalPrice > 0 && isset($variant['price'])) {
                return (string) $variant['price'];
            }
        }

        // Otherwise use the compare_at_price field if available
        if (isset($variant['compare_at_price'])) {
            $comparePrice = (float) $variant['compare_at_price'];
            if ($comparePrice > 0) {
                return (string) $variant['compare_at_price'];
            }
        }

        return null;
    }

    /**
     * Extract stock quantity from variant.
     *
     * Nuvemshop uses 'stock_management' flag. If false, stock is unlimited (null).
     * We convert null to 0 for consistency with our database schema.
     *
     * @param  array  $variant  Variant data
     * @return int Stock quantity
     */
    private function extractStockQuantity(array $variant): int
    {
        // If stock management is disabled, consider it as unlimited (we'll use a high number)
        if (isset($variant['stock_management']) && $variant['stock_management'] === false) {
            return 9999; // Represent unlimited stock with a high number
        }

        // If stock is null (unlimited), use 9999
        if (! isset($variant['stock']) || $variant['stock'] === null) {
            return 9999;
        }

        // Convert to integer, default to 0 if invalid
        return max(0, (int) $variant['stock']);
    }
}
