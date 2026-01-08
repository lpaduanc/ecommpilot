<?php

namespace App\Contracts;

/**
 * Interface for adapting external order data to internal SyncedOrder structure.
 *
 * This contract defines the behavior for transforming order data from different
 * e-commerce platforms into a normalized format compatible with the SyncedOrder model.
 */
interface OrderAdapterInterface
{
    /**
     * Transform external order data to SyncedOrder attributes.
     *
     * @param  array  $externalData  Raw order data from the external platform API
     * @return array Normalized order attributes matching SyncedOrder fillable fields
     */
    public function transform(array $externalData): array;

    /**
     * Extract customer information from external order data.
     *
     * @param  array  $externalData  Raw order data from the external platform API
     * @return array Customer data (name, email, phone)
     */
    public function extractCustomerInfo(array $externalData): array;

    /**
     * Extract order items from external data.
     *
     * @param  array  $externalData  Raw order data from the external platform API
     * @return array Array of order items with product details
     */
    public function extractItems(array $externalData): array;

    /**
     * Extract shipping address from external data.
     *
     * @param  array  $externalData  Raw order data from the external platform API
     * @return array|null Shipping address data or null if not available
     */
    public function extractShippingAddress(array $externalData): ?array;

    /**
     * Sanitize numeric field value.
     *
     * Handles cases where API returns non-numeric strings (e.g., "table_default" for shipping).
     *
     * @param  mixed  $value  The value to sanitize
     * @param  float  $default  Default value if not numeric
     * @return float Sanitized numeric value
     */
    public function sanitizeNumericValue(mixed $value, float $default = 0.0): float;

    /**
     * Map external order status to internal OrderStatus enum value.
     *
     * @param  string|null  $externalStatus  External platform status
     * @return string Internal status value matching OrderStatus enum
     */
    public function mapOrderStatus(?string $externalStatus): string;

    /**
     * Map external payment status to internal PaymentStatus enum value.
     *
     * @param  string|null  $externalStatus  External platform payment status
     * @return string Internal payment status value matching PaymentStatus enum
     */
    public function mapPaymentStatus(?string $externalStatus): string;
}
