<?php

namespace App\Services\Integration;

use App\Contracts\CouponAdapterInterface;

/**
 * Adapter for transforming Nuvemshop coupon data to SyncedCoupon structure.
 *
 * Nuvemshop API returns coupon data with the following structure:
 * {
 *   "id": 123456,
 *   "code": "DESCONTO10",
 *   "type": "percentage",  // percentage, absolute, shipping
 *   "value": "10.00",
 *   "valid": true,
 *   "used": 5,
 *   "max_uses": 100,
 *   "start_date": "2025-01-01T00:00:00+0000",
 *   "end_date": "2025-12-31T23:59:59+0000",
 *   "min_price": "50.00",
 *   "categories": [123, 456]
 * }
 *
 * API Documentation: https://tiendanube.github.io/api-documentation/resources/coupon
 */
class NuvemshopCouponAdapter implements CouponAdapterInterface
{
    /**
     * Transform Nuvemshop coupon data to SyncedCoupon attributes.
     *
     * @param  array  $externalData  Raw coupon data from Nuvemshop API
     * @return array Normalized coupon attributes
     */
    public function transform(array $externalData): array
    {
        return [
            'external_id' => (string) $externalData['id'],
            'code' => $externalData['code'] ?? '',
            'type' => $this->mapCouponType($externalData['type'] ?? null),
            'value' => $this->sanitizeNumericValue($externalData['value'] ?? 0),
            'valid' => (bool) ($externalData['valid'] ?? true),
            'used' => (int) ($externalData['used'] ?? 0),
            'max_uses' => isset($externalData['max_uses']) ? (int) $externalData['max_uses'] : null,
            'start_date' => $externalData['start_date'] ?? null,
            'end_date' => $externalData['end_date'] ?? null,
            'min_price' => isset($externalData['min_price'])
                ? $this->sanitizeNumericValue($externalData['min_price'])
                : null,
            'categories' => $this->extractCategories($externalData),
        ];
    }

    /**
     * Map Nuvemshop coupon type to internal type.
     *
     * Nuvemshop types: percentage, absolute, shipping
     *
     * @param  string|null  $externalType  Nuvemshop coupon type
     * @return string Internal type value
     */
    public function mapCouponType(?string $externalType): string
    {
        return match ($externalType) {
            'percentage' => 'percentage',
            'absolute' => 'absolute',
            'shipping', 'free_shipping' => 'shipping',
            default => 'percentage',
        };
    }

    /**
     * Extract category IDs from coupon data.
     *
     * Nuvemshop may return categories as an array of IDs or category objects.
     *
     * @param  array  $externalData  Raw coupon data
     * @return array Array of category IDs
     */
    private function extractCategories(array $externalData): array
    {
        $categories = $externalData['categories'] ?? [];

        if (empty($categories) || ! is_array($categories)) {
            return [];
        }

        // If categories are objects with 'id', extract IDs
        // Otherwise, assume they are already IDs
        return collect($categories)
            ->map(function ($category) {
                if (is_array($category) && isset($category['id'])) {
                    return (int) $category['id'];
                }

                return (int) $category;
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Sanitize numeric field value.
     *
     * @param  mixed  $value  The value to sanitize
     * @param  float  $default  Default value if not numeric
     * @return float Sanitized numeric value
     */
    private function sanitizeNumericValue(mixed $value, float $default = 0.0): float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }
}
