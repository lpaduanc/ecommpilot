<?php

namespace Tests\Unit;

use App\Services\Integration\NuvemshopProductAdapter;
use PHPUnit\Framework\TestCase;

class NuvemshopProductAdapterTest extends TestCase
{
    private NuvemshopProductAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new NuvemshopProductAdapter;
    }

    /**
     * Test that the adapter correctly transforms Nuvemshop product data.
     */
    public function test_transform_nuvemshop_product_data(): void
    {
        // Example Nuvemshop product data
        $nuvemshopData = [
            'id' => 255132555,
            'name' => ['pt' => 'Shampoo Detox 250ml'],
            'description' => ['pt' => '<p>Descrição do produto</p>'],
            'handle' => ['pt' => 'shampoo-detox-250ml'],
            'published' => true,
            'free_shipping' => false,
            'requires_shipping' => true,
            'created_at' => '2025-02-12T12:54:26+0000',
            'updated_at' => '2025-02-12T12:54:46+0000',
            'variants' => [
                [
                    'id' => 1128608380,
                    'product_id' => 255132555,
                    'position' => 1,
                    'price' => '130.00',
                    'compare_at_price' => '130.00',
                    'promotional_price' => '59.90',
                    'stock_management' => true,
                    'stock' => 50,
                    'weight' => '0.330',
                    'width' => '4.80',
                    'height' => '21.50',
                    'depth' => '4.80',
                    'sku' => 'DN-SH250/23',
                    'values' => [['pt' => 'Branco'], ['pt' => 'P']],
                    'barcode' => '7898546694127',
                    'visible' => true,
                ],
            ],
            'tags' => 'detox, shampoo',
            'images' => [
                [
                    'id' => 820702142,
                    'product_id' => 255132555,
                    'src' => 'https://example.com/image1.jpg',
                    'position' => 2,
                    'height' => 1024,
                    'width' => 1024,
                ],
                [
                    'id' => 820702143,
                    'product_id' => 255132555,
                    'src' => 'https://example.com/image2.jpg',
                    'position' => 1,
                    'height' => 1024,
                    'width' => 1024,
                ],
            ],
            'categories' => [
                [
                    'id' => 29500164,
                    'name' => ['pt' => 'Produtos'],
                    'handle' => ['pt' => 'produtos1'],
                    'parent' => null,
                ],
            ],
        ];

        // Transform the data
        $result = $this->adapter->transform($nuvemshopData);

        // Assertions
        $this->assertEquals('255132555', $result['external_id']);
        $this->assertEquals('Shampoo Detox 250ml', $result['name']);
        $this->assertEquals('<p>Descrição do produto</p>', $result['description']);
        $this->assertEquals('59.90', $result['price']); // Should use promotional price
        $this->assertEquals('130.00', $result['compare_at_price']); // Regular price becomes compare_at_price
        $this->assertEquals(50, $result['stock_quantity']);
        $this->assertEquals('DN-SH250/23', $result['sku']);
        $this->assertTrue($result['is_active']);
        $this->assertEquals('2025-02-12T12:54:26+0000', $result['external_created_at']);
        $this->assertEquals('2025-02-12T12:54:46+0000', $result['external_updated_at']);

        // Check images (should be sorted by position)
        $this->assertCount(2, $result['images']);
        $this->assertEquals('https://example.com/image2.jpg', $result['images'][0]); // Position 1
        $this->assertEquals('https://example.com/image1.jpg', $result['images'][1]); // Position 2

        // Check categories
        $this->assertCount(1, $result['categories']);
        $this->assertEquals('Produtos', $result['categories'][0]);

        // Check variants
        $this->assertCount(1, $result['variants']);
        $this->assertEquals(1128608380, $result['variants'][0]['id']);
        $this->assertEquals('DN-SH250/23', $result['variants'][0]['sku']);
    }

    /**
     * Test extracting localized values with fallback.
     */
    public function test_extract_localized_value(): void
    {
        // Test with Portuguese value
        $result = $this->adapter->extractLocalizedValue(['pt' => 'Valor em português', 'en' => 'Value in english']);
        $this->assertEquals('Valor em português', $result);

        // Test with only English value (fallback)
        $result = $this->adapter->extractLocalizedValue(['en' => 'Value in english']);
        $this->assertEquals('Value in english', $result);

        // Test with plain string
        $result = $this->adapter->extractLocalizedValue('Plain string');
        $this->assertEquals('Plain string', $result);

        // Test with null and default value
        $result = $this->adapter->extractLocalizedValue(null, 'pt', 'en', 'Default value');
        $this->assertEquals('Default value', $result);

        // Test with empty string and default
        $result = $this->adapter->extractLocalizedValue('', 'pt', 'en', 'Default value');
        $this->assertEquals('Default value', $result);
    }

    /**
     * Test extracting images with sorting.
     */
    public function test_extract_images(): void
    {
        $data = [
            'images' => [
                ['id' => 1, 'src' => 'https://example.com/img3.jpg', 'position' => 3],
                ['id' => 2, 'src' => 'https://example.com/img1.jpg', 'position' => 1],
                ['id' => 3, 'src' => 'https://example.com/img2.jpg', 'position' => 2],
            ],
        ];

        $result = $this->adapter->extractImages($data);

        $this->assertCount(3, $result);
        $this->assertEquals('https://example.com/img1.jpg', $result[0]);
        $this->assertEquals('https://example.com/img2.jpg', $result[1]);
        $this->assertEquals('https://example.com/img3.jpg', $result[2]);
    }

    /**
     * Test extracting categories with localized names.
     */
    public function test_extract_categories(): void
    {
        $data = [
            'categories' => [
                ['id' => 1, 'name' => ['pt' => 'Categoria 1']],
                ['id' => 2, 'name' => ['pt' => 'Categoria 2']],
                ['id' => 3, 'name' => ['en' => 'Category 3']], // Fallback to English
            ],
        ];

        $result = $this->adapter->extractCategories($data);

        $this->assertCount(3, $result);
        $this->assertEquals('Categoria 1', $result[0]);
        $this->assertEquals('Categoria 2', $result[1]);
        $this->assertEquals('Category 3', $result[2]);
    }

    /**
     * Test getting primary variant.
     */
    public function test_get_primary_variant(): void
    {
        $data = [
            'variants' => [
                ['id' => 2, 'position' => 2, 'sku' => 'SKU-002'],
                ['id' => 1, 'position' => 1, 'sku' => 'SKU-001'],
                ['id' => 3, 'position' => 3, 'sku' => 'SKU-003'],
            ],
        ];

        $result = $this->adapter->getPrimaryVariant($data);

        // Should return the variant with position 1
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('SKU-001', $result['sku']);
    }

    /**
     * Test handling unlimited stock (stock_management = false).
     */
    public function test_transform_with_unlimited_stock(): void
    {
        $data = [
            'id' => 123,
            'name' => ['pt' => 'Produto com estoque ilimitado'],
            'published' => true,
            'created_at' => '2025-01-01T00:00:00+0000',
            'updated_at' => '2025-01-01T00:00:00+0000',
            'variants' => [
                [
                    'id' => 1,
                    'position' => 1,
                    'price' => '100.00',
                    'stock_management' => false,
                    'stock' => null,
                    'sku' => 'SKU-UNLIMITED',
                ],
            ],
            'images' => [],
            'categories' => [],
        ];

        $result = $this->adapter->transform($data);

        // Should use 9999 to represent unlimited stock
        $this->assertEquals(9999, $result['stock_quantity']);
    }

    /**
     * Test handling product without promotional price.
     */
    public function test_transform_without_promotional_price(): void
    {
        $data = [
            'id' => 456,
            'name' => ['pt' => 'Produto sem promoção'],
            'published' => true,
            'created_at' => '2025-01-01T00:00:00+0000',
            'updated_at' => '2025-01-01T00:00:00+0000',
            'variants' => [
                [
                    'id' => 1,
                    'position' => 1,
                    'price' => '100.00',
                    'compare_at_price' => '150.00',
                    'stock_management' => true,
                    'stock' => 10,
                    'sku' => 'SKU-NO-PROMO',
                ],
            ],
            'images' => [],
            'categories' => [],
        ];

        $result = $this->adapter->transform($data);

        // Should use regular price
        $this->assertEquals('100.00', $result['price']);
        // Should use compare_at_price from variant
        $this->assertEquals('150.00', $result['compare_at_price']);
    }
}
