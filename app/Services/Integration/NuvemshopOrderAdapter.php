<?php

namespace App\Services\Integration;

use App\Contracts\OrderAdapterInterface;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;

/**
 * Adapter for transforming Nuvemshop order data to SyncedOrder structure.
 *
 * Nuvemshop API returns order data with various edge cases that need handling:
 * - Numeric fields can be strings or non-numeric values (e.g., shipping: "table_default")
 * - Customer data nested in 'customer' object
 * - Products/items in 'products' array with specific structure
 * - Various status values that need mapping to internal enums
 *
 * Example Nuvemshop order structure:
 * {
 *   "id": 1686532383,
 *   "number": 100,
 *   "status": "pending",
 *   "payment_status": "pending",
 *   "shipping_status": "unpacked",
 *   "customer": {
 *     "name": "Guilherme Paduan",
 *     "email": "guilherme.paduan@softio.com.br",
 *     "phone": ""
 *   },
 *   "subtotal": "49.90",
 *   "discount": "0.00",
 *   "shipping": "table_default",  // Can be non-numeric!
 *   "total": "49.90",
 *   "payment_details": {
 *     "method": "custom"
 *   },
 *   "products": [
 *     {
 *       "product_id": 255132557,
 *       "name": "Shampoo Loiro 250ml",
 *       "quantity": 1,
 *       "price": "49.90"
 *     }
 *   ],
 *   "shipping_address": {
 *     "address": "Avenida...",
 *     "city": "Hortolândia",
 *     "province": "São Paulo",
 *     "zipcode": "13183250",
 *     "country": "BR"
 *   },
 *   "created_at": "2025-04-10T18:41:13+0000"
 * }
 */
class NuvemshopOrderAdapter implements OrderAdapterInterface
{
    /**
     * Transform Nuvemshop order data to SyncedOrder attributes.
     *
     * @param  array  $externalData  Raw order data from Nuvemshop API
     * @return array Normalized order attributes
     */
    public function transform(array $externalData): array
    {
        $customerInfo = $this->extractCustomerInfo($externalData);
        $items = $this->extractItems($externalData);
        $shippingAddress = $this->extractShippingAddress($externalData);

        return [
            'external_id' => (string) $externalData['id'],
            'order_number' => $externalData['number'] ?? $externalData['id'],
            'status' => $this->mapOrderStatus($externalData['status'] ?? null),
            'payment_status' => $this->mapPaymentStatus($externalData['payment_status'] ?? null),
            'shipping_status' => $externalData['shipping_status'] ?? null,
            'customer_name' => $customerInfo['name'],
            'customer_email' => $customerInfo['email'],
            'customer_phone' => $customerInfo['phone'],
            'subtotal' => $this->sanitizeNumericValue($externalData['subtotal'] ?? 0),
            'discount' => $this->sanitizeNumericValue($externalData['discount'] ?? 0),
            'shipping' => $this->sanitizeNumericValue($externalData['shipping'] ?? 0),
            'total' => $this->sanitizeNumericValue($externalData['total'] ?? 0),
            'payment_method' => $this->extractPaymentMethod($externalData),
            'items' => $items,
            'shipping_address' => $shippingAddress,
            'external_created_at' => $externalData['created_at'] ?? null,
        ];
    }

    /**
     * Extract customer information from Nuvemshop order data.
     *
     * Nuvemshop nests customer data in a 'customer' object.
     *
     * @param  array  $externalData  Raw order data
     * @return array Customer data with normalized keys
     */
    public function extractCustomerInfo(array $externalData): array
    {
        $customer = $externalData['customer'] ?? [];

        return [
            'name' => ! empty($customer['name']) ? $customer['name'] : 'Desconhecido',
            'email' => ! empty($customer['email']) ? $customer['email'] : null,
            'phone' => ! empty($customer['phone']) ? $customer['phone'] : null,
        ];
    }

    /**
     * Extract order items from Nuvemshop data.
     *
     * Nuvemshop products structure: [{"product_id": 123, "name": "...", "quantity": 1, "price": "49.90"}]
     *
     * @param  array  $externalData  Raw order data
     * @return array Array of normalized order items
     */
    public function extractItems(array $externalData): array
    {
        $products = $externalData['products'] ?? [];

        if (empty($products) || ! is_array($products)) {
            return [];
        }

        return collect($products)->map(function ($item) {
            return [
                'product_id' => $item['product_id'] ?? null,
                'variant_id' => $item['variant_id'] ?? null,
                'name' => $item['name'] ?? 'Produto sem nome',
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'price' => $this->sanitizeNumericValue($item['price'] ?? 0),
            ];
        })->toArray();
    }

    /**
     * Extract shipping address from Nuvemshop data.
     *
     * @param  array  $externalData  Raw order data
     * @return array|null Shipping address or null if not available
     */
    public function extractShippingAddress(array $externalData): ?array
    {
        $address = $externalData['shipping_address'] ?? null;

        if (empty($address) || ! is_array($address)) {
            return null;
        }

        // Return the address data as-is, as it's already in a usable format
        // Filter out empty values for cleaner storage
        return array_filter([
            'address' => $address['address'] ?? null,
            'number' => $address['number'] ?? null,
            'floor' => $address['floor'] ?? null,
            'locality' => $address['locality'] ?? null,
            'city' => $address['city'] ?? null,
            'province' => $address['province'] ?? null,
            'zipcode' => $address['zipcode'] ?? null,
            'country' => $address['country'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Sanitize numeric field value.
     *
     * Nuvemshop sometimes returns non-numeric strings for numeric fields.
     * For example, shipping can be "table_default" instead of a number.
     *
     * @param  mixed  $value  The value to sanitize
     * @param  float  $default  Default value if not numeric
     * @return float Sanitized numeric value
     */
    public function sanitizeNumericValue(mixed $value, float $default = 0.0): float
    {
        // If null or empty string, return default
        if ($value === null || $value === '') {
            return $default;
        }

        // If already numeric, convert to float
        if (is_numeric($value)) {
            return (float) $value;
        }

        // If not numeric (e.g., "table_default"), return default
        return $default;
    }

    /**
     * Map Nuvemshop order status to internal OrderStatus enum value.
     *
     * Nuvemshop statuses: open, pending, closed, paid, shipped, delivered, cancelled
     *
     * @param  string|null  $externalStatus  Nuvemshop order status
     * @return string Internal status value
     */
    public function mapOrderStatus(?string $externalStatus): string
    {
        return match ($externalStatus) {
            'open', 'pending' => OrderStatus::Pending->value,
            'closed', 'paid' => OrderStatus::Paid->value,
            'shipped' => OrderStatus::Shipped->value,
            'delivered' => OrderStatus::Delivered->value,
            'cancelled' => OrderStatus::Cancelled->value,
            default => OrderStatus::Pending->value,
        };
    }

    /**
     * Map Nuvemshop payment status to internal PaymentStatus enum value.
     *
     * Nuvemshop payment statuses: pending, authorized, paid, refunded, voided
     *
     * @param  string|null  $externalStatus  Nuvemshop payment status
     * @return string Internal payment status value
     */
    public function mapPaymentStatus(?string $externalStatus): string
    {
        return match ($externalStatus) {
            'pending', 'authorized' => PaymentStatus::Pending->value,
            'paid' => PaymentStatus::Paid->value,
            'refunded', 'voided' => PaymentStatus::Refunded->value,
            'abandoned' => PaymentStatus::Failed->value,
            default => PaymentStatus::Pending->value,
        };
    }

    /**
     * Extract payment method from payment_details.
     *
     * @param  array  $externalData  Raw order data
     * @return string|null Payment method or null
     */
    private function extractPaymentMethod(array $externalData): ?string
    {
        $paymentDetails = $externalData['payment_details'] ?? [];

        if (empty($paymentDetails) || ! is_array($paymentDetails)) {
            return null;
        }

        return $paymentDetails['method'] ?? null;
    }
}
