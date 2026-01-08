<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Services\Integration\NuvemshopOrderAdapter;
use PHPUnit\Framework\TestCase;

class NuvemshopOrderAdapterTest extends TestCase
{
    private NuvemshopOrderAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new NuvemshopOrderAdapter;
    }

    /** @test */
    public function it_transforms_complete_nuvemshop_order_data(): void
    {
        $externalData = [
            'id' => 1686532383,
            'number' => 100,
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_status' => 'unpacked',
            'customer' => [
                'name' => 'Guilherme Paduan',
                'email' => 'guilherme.paduan@softio.com.br',
                'phone' => '+5519999999999',
            ],
            'subtotal' => '49.90',
            'discount' => '5.00',
            'shipping' => '10.00',
            'total' => '54.90',
            'payment_details' => [
                'method' => 'credit_card',
            ],
            'products' => [
                [
                    'product_id' => 255132557,
                    'variant_id' => 1128608380,
                    'name' => 'Shampoo Loiro 250ml',
                    'quantity' => 1,
                    'price' => '49.90',
                ],
            ],
            'shipping_address' => [
                'address' => 'Avenida Principal',
                'number' => '123',
                'city' => 'Hortolândia',
                'province' => 'São Paulo',
                'zipcode' => '13183250',
                'country' => 'BR',
            ],
            'created_at' => '2025-04-10T18:41:13+0000',
        ];

        $result = $this->adapter->transform($externalData);

        $this->assertEquals('1686532383', $result['external_id']);
        $this->assertEquals(100, $result['order_number']);
        $this->assertEquals(OrderStatus::Pending->value, $result['status']);
        $this->assertEquals(PaymentStatus::Pending->value, $result['payment_status']);
        $this->assertEquals('unpacked', $result['shipping_status']);
        $this->assertEquals('Guilherme Paduan', $result['customer_name']);
        $this->assertEquals('guilherme.paduan@softio.com.br', $result['customer_email']);
        $this->assertEquals('+5519999999999', $result['customer_phone']);
        $this->assertEquals(49.90, $result['subtotal']);
        $this->assertEquals(5.00, $result['discount']);
        $this->assertEquals(10.00, $result['shipping']);
        $this->assertEquals(54.90, $result['total']);
        $this->assertEquals('credit_card', $result['payment_method']);
        $this->assertIsArray($result['items']);
        $this->assertCount(1, $result['items']);
        $this->assertIsArray($result['shipping_address']);
        $this->assertEquals('2025-04-10T18:41:13+0000', $result['external_created_at']);
    }

    /** @test */
    public function it_handles_order_with_minimal_data(): void
    {
        $externalData = [
            'id' => 123,
            'number' => 1,
        ];

        $result = $this->adapter->transform($externalData);

        $this->assertEquals('123', $result['external_id']);
        $this->assertEquals(1, $result['order_number']);
        $this->assertEquals(OrderStatus::Pending->value, $result['status']);
        $this->assertEquals(PaymentStatus::Pending->value, $result['payment_status']);
        $this->assertEquals('Desconhecido', $result['customer_name']);
        $this->assertNull($result['customer_email']);
        $this->assertNull($result['customer_phone']);
        $this->assertEquals(0.0, $result['subtotal']);
        $this->assertEquals(0.0, $result['discount']);
        $this->assertEquals(0.0, $result['shipping']);
        $this->assertEquals(0.0, $result['total']);
        $this->assertNull($result['payment_method']);
        $this->assertIsArray($result['items']);
        $this->assertEmpty($result['items']);
        $this->assertNull($result['shipping_address']);
    }

    /** @test */
    public function it_sanitizes_non_numeric_shipping_value(): void
    {
        $externalData = [
            'id' => 123,
            'number' => 1,
            'shipping' => 'table_default', // Non-numeric value
            'subtotal' => '100.00',
            'total' => '100.00',
        ];

        $result = $this->adapter->transform($externalData);

        $this->assertEquals(0.0, $result['shipping']);
        $this->assertEquals(100.0, $result['subtotal']);
        $this->assertEquals(100.0, $result['total']);
    }

    /** @test */
    public function it_sanitizes_all_numeric_fields_correctly(): void
    {
        $this->assertEquals(49.90, $this->adapter->sanitizeNumericValue('49.90'));
        $this->assertEquals(49.90, $this->adapter->sanitizeNumericValue(49.90));
        $this->assertEquals(0.0, $this->adapter->sanitizeNumericValue('table_default'));
        $this->assertEquals(0.0, $this->adapter->sanitizeNumericValue(''));
        $this->assertEquals(0.0, $this->adapter->sanitizeNumericValue(null));
        $this->assertEquals(100.0, $this->adapter->sanitizeNumericValue('invalid', 100.0));
    }

    /** @test */
    public function it_extracts_customer_info_correctly(): void
    {
        $externalData = [
            'customer' => [
                'name' => 'João Silva',
                'email' => 'joao@example.com',
                'phone' => '+5511988887777',
            ],
        ];

        $result = $this->adapter->extractCustomerInfo($externalData);

        $this->assertEquals('João Silva', $result['name']);
        $this->assertEquals('joao@example.com', $result['email']);
        $this->assertEquals('+5511988887777', $result['phone']);
    }

    /** @test */
    public function it_handles_missing_customer_data(): void
    {
        $result = $this->adapter->extractCustomerInfo([]);

        $this->assertEquals('Desconhecido', $result['name']);
        $this->assertNull($result['email']);
        $this->assertNull($result['phone']);
    }

    /** @test */
    public function it_handles_empty_customer_fields(): void
    {
        $externalData = [
            'customer' => [
                'name' => '',
                'email' => '',
                'phone' => '',
            ],
        ];

        $result = $this->adapter->extractCustomerInfo($externalData);

        $this->assertEquals('Desconhecido', $result['name']);
        $this->assertNull($result['email']);
        $this->assertNull($result['phone']);
    }

    /** @test */
    public function it_extracts_order_items_correctly(): void
    {
        $externalData = [
            'products' => [
                [
                    'product_id' => 255132557,
                    'variant_id' => 1128608380,
                    'name' => 'Shampoo Loiro 250ml',
                    'quantity' => 2,
                    'price' => '49.90',
                ],
                [
                    'product_id' => 255132558,
                    'name' => 'Condicionador',
                    'quantity' => 1,
                    'price' => '39.90',
                ],
            ],
        ];

        $result = $this->adapter->extractItems($externalData);

        $this->assertCount(2, $result);
        $this->assertEquals(255132557, $result[0]['product_id']);
        $this->assertEquals(1128608380, $result[0]['variant_id']);
        $this->assertEquals('Shampoo Loiro 250ml', $result[0]['name']);
        $this->assertEquals(2, $result[0]['quantity']);
        $this->assertEquals(49.90, $result[0]['price']);

        $this->assertEquals(255132558, $result[1]['product_id']);
        $this->assertNull($result[1]['variant_id']);
        $this->assertEquals('Condicionador', $result[1]['name']);
        $this->assertEquals(1, $result[1]['quantity']);
        $this->assertEquals(39.90, $result[1]['price']);
    }

    /** @test */
    public function it_handles_empty_products_array(): void
    {
        $result = $this->adapter->extractItems([]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_handles_items_with_missing_fields(): void
    {
        $externalData = [
            'products' => [
                [
                    'product_id' => 123,
                    // Missing name, quantity, price
                ],
            ],
        ];

        $result = $this->adapter->extractItems($externalData);

        $this->assertCount(1, $result);
        $this->assertEquals(123, $result[0]['product_id']);
        $this->assertEquals('Produto sem nome', $result[0]['name']);
        $this->assertEquals(1, $result[0]['quantity']); // Default quantity
        $this->assertEquals(0.0, $result[0]['price']);
    }

    /** @test */
    public function it_ensures_minimum_quantity_of_one(): void
    {
        $externalData = [
            'products' => [
                ['product_id' => 123, 'quantity' => 0],
                ['product_id' => 456, 'quantity' => -5],
                ['product_id' => 789, 'quantity' => 3],
            ],
        ];

        $result = $this->adapter->extractItems($externalData);

        $this->assertEquals(1, $result[0]['quantity']); // 0 becomes 1
        $this->assertEquals(1, $result[1]['quantity']); // -5 becomes 1
        $this->assertEquals(3, $result[2]['quantity']); // 3 stays 3
    }

    /** @test */
    public function it_extracts_shipping_address_correctly(): void
    {
        $externalData = [
            'shipping_address' => [
                'address' => 'Avenida Principal',
                'number' => '123',
                'floor' => '2A',
                'locality' => 'Centro',
                'city' => 'Hortolândia',
                'province' => 'São Paulo',
                'zipcode' => '13183250',
                'country' => 'BR',
            ],
        ];

        $result = $this->adapter->extractShippingAddress($externalData);

        $this->assertIsArray($result);
        $this->assertEquals('Avenida Principal', $result['address']);
        $this->assertEquals('123', $result['number']);
        $this->assertEquals('2A', $result['floor']);
        $this->assertEquals('Centro', $result['locality']);
        $this->assertEquals('Hortolândia', $result['city']);
        $this->assertEquals('São Paulo', $result['province']);
        $this->assertEquals('13183250', $result['zipcode']);
        $this->assertEquals('BR', $result['country']);
    }

    /** @test */
    public function it_filters_empty_address_fields(): void
    {
        $externalData = [
            'shipping_address' => [
                'address' => 'Rua A',
                'number' => '',
                'city' => 'São Paulo',
                'province' => null,
                'zipcode' => '12345678',
            ],
        ];

        $result = $this->adapter->extractShippingAddress($externalData);

        $this->assertArrayHasKey('address', $result);
        $this->assertArrayHasKey('city', $result);
        $this->assertArrayHasKey('zipcode', $result);
        $this->assertArrayNotHasKey('number', $result); // Empty string filtered out
        $this->assertArrayNotHasKey('province', $result); // Null filtered out
    }

    /** @test */
    public function it_returns_null_for_missing_shipping_address(): void
    {
        $result = $this->adapter->extractShippingAddress([]);

        $this->assertNull($result);
    }

    /** @test */
    public function it_maps_order_statuses_correctly(): void
    {
        $this->assertEquals(OrderStatus::Pending->value, $this->adapter->mapOrderStatus('open'));
        $this->assertEquals(OrderStatus::Pending->value, $this->adapter->mapOrderStatus('pending'));
        $this->assertEquals(OrderStatus::Paid->value, $this->adapter->mapOrderStatus('closed'));
        $this->assertEquals(OrderStatus::Paid->value, $this->adapter->mapOrderStatus('paid'));
        $this->assertEquals(OrderStatus::Shipped->value, $this->adapter->mapOrderStatus('shipped'));
        $this->assertEquals(OrderStatus::Delivered->value, $this->adapter->mapOrderStatus('delivered'));
        $this->assertEquals(OrderStatus::Cancelled->value, $this->adapter->mapOrderStatus('cancelled'));
        $this->assertEquals(OrderStatus::Pending->value, $this->adapter->mapOrderStatus('unknown_status'));
        $this->assertEquals(OrderStatus::Pending->value, $this->adapter->mapOrderStatus(null));
    }

    /** @test */
    public function it_maps_payment_statuses_correctly(): void
    {
        $this->assertEquals(PaymentStatus::Pending->value, $this->adapter->mapPaymentStatus('pending'));
        $this->assertEquals(PaymentStatus::Pending->value, $this->adapter->mapPaymentStatus('authorized'));
        $this->assertEquals(PaymentStatus::Paid->value, $this->adapter->mapPaymentStatus('paid'));
        $this->assertEquals(PaymentStatus::Refunded->value, $this->adapter->mapPaymentStatus('refunded'));
        $this->assertEquals(PaymentStatus::Refunded->value, $this->adapter->mapPaymentStatus('voided'));
        $this->assertEquals(PaymentStatus::Failed->value, $this->adapter->mapPaymentStatus('abandoned'));
        $this->assertEquals(PaymentStatus::Pending->value, $this->adapter->mapPaymentStatus('unknown_status'));
        $this->assertEquals(PaymentStatus::Pending->value, $this->adapter->mapPaymentStatus(null));
    }

    /** @test */
    public function it_uses_order_id_as_fallback_for_order_number(): void
    {
        $externalData = [
            'id' => 999,
            // Missing 'number' field
        ];

        $result = $this->adapter->transform($externalData);

        $this->assertEquals(999, $result['order_number']);
    }

    /** @test */
    public function it_handles_multiple_order_items_with_price_sanitization(): void
    {
        $externalData = [
            'products' => [
                ['product_id' => 1, 'price' => '100.50'],
                ['product_id' => 2, 'price' => 50],
                ['product_id' => 3, 'price' => 'invalid'],
                ['product_id' => 4, 'price' => null],
            ],
        ];

        $result = $this->adapter->extractItems($externalData);

        $this->assertEquals(100.50, $result[0]['price']);
        $this->assertEquals(50.0, $result[1]['price']);
        $this->assertEquals(0.0, $result[2]['price']);
        $this->assertEquals(0.0, $result[3]['price']);
    }

    /** @test */
    public function it_handles_order_with_no_payment_method(): void
    {
        $externalData = [
            'id' => 123,
            'number' => 1,
            // No payment_details
        ];

        $result = $this->adapter->transform($externalData);

        $this->assertNull($result['payment_method']);
    }

    /** @test */
    public function it_handles_order_with_empty_payment_details(): void
    {
        $externalData = [
            'id' => 123,
            'number' => 1,
            'payment_details' => [],
        ];

        $result = $this->adapter->transform($externalData);

        $this->assertNull($result['payment_method']);
    }

    /** @test */
    public function it_transforms_real_world_nuvemshop_order_example(): void
    {
        // Real example from the requirements
        $externalData = [
            'id' => 1686532383,
            'number' => 100,
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_status' => 'unpacked',
            'customer' => [
                'name' => 'Guilherme Paduan',
                'email' => 'guilherme.paduan@softio.com.br',
                'phone' => '',
            ],
            'subtotal' => '49.90',
            'discount' => '0.00',
            'shipping' => 'table_default', // Non-numeric!
            'total' => '49.90',
            'payment_details' => [
                'method' => 'custom',
            ],
            'products' => [
                [
                    'product_id' => 255132557,
                    'name' => 'Shampoo Loiro 250ml',
                    'quantity' => 1,
                    'price' => '49.90',
                ],
            ],
            'shipping_address' => [
                'address' => 'Avenida...',
                'city' => 'Hortolândia',
                'province' => 'São Paulo',
                'zipcode' => '13183250',
                'country' => 'BR',
            ],
            'created_at' => '2025-04-10T18:41:13+0000',
        ];

        $result = $this->adapter->transform($externalData);

        // Verify all critical transformations
        $this->assertEquals('1686532383', $result['external_id']);
        $this->assertEquals(100, $result['order_number']);
        $this->assertEquals(OrderStatus::Pending->value, $result['status']);
        $this->assertEquals(PaymentStatus::Pending->value, $result['payment_status']);
        $this->assertEquals('Guilherme Paduan', $result['customer_name']);
        $this->assertEquals('guilherme.paduan@softio.com.br', $result['customer_email']);
        $this->assertNull($result['customer_phone']); // Empty string becomes null
        $this->assertEquals(49.90, $result['subtotal']);
        $this->assertEquals(0.00, $result['discount']);
        $this->assertEquals(0.00, $result['shipping']); // 'table_default' sanitized to 0
        $this->assertEquals(49.90, $result['total']);
        $this->assertEquals('custom', $result['payment_method']);
        $this->assertCount(1, $result['items']);
        $this->assertEquals(255132557, $result['items'][0]['product_id']);
        $this->assertEquals('Shampoo Loiro 250ml', $result['items'][0]['name']);
        $this->assertIsArray($result['shipping_address']);
        $this->assertEquals('Hortolândia', $result['shipping_address']['city']);
    }
}
