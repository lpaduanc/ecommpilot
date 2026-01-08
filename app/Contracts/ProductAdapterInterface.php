<?php

namespace App\Contracts;

/**
 * Interface for adapting external product data to internal SyncedProduct structure.
 *
 * This contract defines the behavior for transforming product data from different
 * e-commerce platforms into a normalized format compatible with the SyncedProduct model.
 */
interface ProductAdapterInterface
{
    /**
     * Transform external product data to SyncedProduct attributes.
     *
     * @param  array  $externalData  Raw product data from the external platform API
     * @return array Normalized product attributes matching SyncedProduct fillable fields
     */
    public function transform(array $externalData): array;

    /**
     * Extract product images from external data.
     *
     * @param  array  $externalData  Raw product data from the external platform API
     * @return array Array of image URLs
     */
    public function extractImages(array $externalData): array;

    /**
     * Extract product categories from external data.
     *
     * @param  array  $externalData  Raw product data from the external platform API
     * @return array Array of category names
     */
    public function extractCategories(array $externalData): array;

    /**
     * Extract product variants from external data.
     *
     * @param  array  $externalData  Raw product data from the external platform API
     * @return array Array of variant data
     */
    public function extractVariants(array $externalData): array;

    /**
     * Get the primary variant (usually the first one) for price and stock data.
     *
     * @param  array  $externalData  Raw product data from the external platform API
     * @return array Primary variant data
     */
    public function getPrimaryVariant(array $externalData): array;

    /**
     * Extract localized string value with fallback.
     *
     * @param  array|string|null  $value  The value to extract (could be localized object or plain string)
     * @param  string  $defaultLocale  Primary locale to use (e.g., 'pt')
     * @param  string  $fallbackLocale  Fallback locale if primary is not available (e.g., 'en')
     * @param  string|null  $default  Default value if no localized value is found
     * @return string|null Extracted value
     */
    public function extractLocalizedValue(
        array|string|null $value,
        string $defaultLocale = 'pt',
        string $fallbackLocale = 'en',
        ?string $default = null
    ): ?string;
}
