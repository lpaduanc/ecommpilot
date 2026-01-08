<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\SyncedProduct;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $stores = Store::all();

        foreach ($stores as $store) {
            $products = $this->getProductsForStore($store->name);

            foreach ($products as $index => $product) {
                SyncedProduct::create([
                    'store_id' => $store->id,
                    'external_id' => 'PRD'.$store->id.str_pad($index + 1, 5, '0', STR_PAD_LEFT),
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'compare_at_price' => $product['compare_at_price'],
                    'stock_quantity' => $product['stock'],
                    'sku' => $product['sku'],
                    'images' => $product['images'],
                    'categories' => $product['categories'],
                    'variants' => $product['variants'] ?? [],
                    'is_active' => $product['is_active'] ?? true,
                    'external_created_at' => now()->subDays(rand(30, 365)),
                    'external_updated_at' => now()->subDays(rand(1, 30)),
                ]);
            }
        }
    }

    private function getProductsForStore(string $storeName): array
    {
        return match (true) {
            str_contains($storeName, 'Maria') => $this->getClothingProducts(),
            str_contains($storeName, 'Tech') => $this->getTechProducts(),
            str_contains($storeName, 'Moda') => $this->getFashionProducts(),
            str_contains($storeName, 'Esporte') => $this->getSportsProducts(),
            default => $this->getGenericProducts(),
        };
    }

    private function getClothingProducts(): array
    {
        return [
            [
                'name' => 'Vestido Floral Verão',
                'description' => 'Vestido leve e confortável com estampa floral, perfeito para o verão brasileiro. Tecido em viscose de alta qualidade.',
                'price' => 149.90,
                'compare_at_price' => 199.90,
                'stock' => 45,
                'sku' => 'VF-001',
                'images' => ['https://placehold.co/600x800/e91e63/white?text=Vestido+Floral'],
                'categories' => ['Vestidos', 'Verão', 'Promoção'],
                'variants' => [
                    ['size' => 'P', 'stock' => 15],
                    ['size' => 'M', 'stock' => 20],
                    ['size' => 'G', 'stock' => 10],
                ],
            ],
            [
                'name' => 'Blusa Básica Algodão',
                'description' => 'Blusa básica em 100% algodão, disponível em várias cores. Confortável e versátil para o dia a dia.',
                'price' => 59.90,
                'compare_at_price' => null,
                'stock' => 120,
                'sku' => 'BB-001',
                'images' => ['https://placehold.co/600x800/2196f3/white?text=Blusa+Basica'],
                'categories' => ['Blusas', 'Básicos'],
            ],
            [
                'name' => 'Calça Jeans Skinny',
                'description' => 'Calça jeans skinny com elastano para maior conforto. Lavagem moderna e acabamento premium.',
                'price' => 189.90,
                'compare_at_price' => 249.90,
                'stock' => 8, // Low stock
                'sku' => 'CJ-001',
                'images' => ['https://placehold.co/600x800/1e3a5f/white?text=Calca+Jeans'],
                'categories' => ['Calças', 'Jeans', 'Promoção'],
            ],
            [
                'name' => 'Conjunto Moletom Confort',
                'description' => 'Conjunto de moletom super confortável, ideal para dias frios ou loungewear. Blusa e calça combinando.',
                'price' => 229.90,
                'compare_at_price' => null,
                'stock' => 35,
                'sku' => 'CM-001',
                'images' => ['https://placehold.co/600x800/607d8b/white?text=Moletom'],
                'categories' => ['Conjuntos', 'Moletom', 'Inverno'],
            ],
            [
                'name' => 'Saia Midi Plissada',
                'description' => 'Saia midi plissada elegante, perfeita para looks sofisticados. Tecido leve com caimento impecável.',
                'price' => 139.90,
                'compare_at_price' => 169.90,
                'stock' => 0, // Out of stock
                'sku' => 'SM-001',
                'images' => ['https://placehold.co/600x800/9c27b0/white?text=Saia+Midi'],
                'categories' => ['Saias', 'Elegante'],
                'is_active' => false,
            ],
            [
                'name' => 'Blazer Feminino Clássico',
                'description' => 'Blazer feminino clássico em tecido estruturado. Perfeito para ambientes profissionais.',
                'price' => 299.90,
                'compare_at_price' => null,
                'stock' => 22,
                'sku' => 'BF-001',
                'images' => ['https://placehold.co/600x800/424242/white?text=Blazer'],
                'categories' => ['Blazers', 'Trabalho', 'Elegante'],
            ],
            [
                'name' => 'Shorts Jeans Destroyed',
                'description' => 'Shorts jeans com detalhes destroyed, estilo descolado para o verão.',
                'price' => 99.90,
                'compare_at_price' => 129.90,
                'stock' => 67,
                'sku' => 'SJ-001',
                'images' => ['https://placehold.co/600x800/4caf50/white?text=Shorts'],
                'categories' => ['Shorts', 'Jeans', 'Verão'],
            ],
            [
                'name' => 'Camiseta Oversized',
                'description' => 'Camiseta oversized moderna em algodão premium. Corte amplo e confortável.',
                'price' => 79.90,
                'compare_at_price' => null,
                'stock' => 89,
                'sku' => 'CO-001',
                'images' => ['https://placehold.co/600x800/ff9800/white?text=Camiseta'],
                'categories' => ['Camisetas', 'Casual', 'Tendência'],
            ],
        ];
    }

    private function getTechProducts(): array
    {
        return [
            [
                'name' => 'Smartphone Galaxy Ultra 256GB',
                'description' => 'Smartphone de última geração com câmera de 200MP, tela AMOLED 6.8", processador octa-core e 5G.',
                'price' => 5499.90,
                'compare_at_price' => 6299.90,
                'stock' => 15,
                'sku' => 'SMGU-256',
                'images' => ['https://placehold.co/600x600/1a237e/white?text=Smartphone'],
                'categories' => ['Smartphones', 'Samsung', 'Promoção'],
            ],
            [
                'name' => 'Notebook Pro 15" i7 16GB',
                'description' => 'Notebook profissional com processador Intel i7 de 13ª geração, 16GB RAM, SSD 512GB, tela Full HD.',
                'price' => 4299.90,
                'compare_at_price' => null,
                'stock' => 8,
                'sku' => 'NB-PRO15',
                'images' => ['https://placehold.co/600x600/37474f/white?text=Notebook'],
                'categories' => ['Notebooks', 'Trabalho', 'Intel'],
            ],
            [
                'name' => 'Fone Bluetooth Premium ANC',
                'description' => 'Fone de ouvido bluetooth com cancelamento ativo de ruído, bateria de 40h e som Hi-Fi.',
                'price' => 899.90,
                'compare_at_price' => 1199.90,
                'stock' => 45,
                'sku' => 'FB-ANC01',
                'images' => ['https://placehold.co/600x600/263238/white?text=Fone+ANC'],
                'categories' => ['Fones', 'Bluetooth', 'Premium'],
            ],
            [
                'name' => 'Smart TV 55" 4K QLED',
                'description' => 'Smart TV 55 polegadas com tecnologia QLED, resolução 4K, HDR10+ e sistema operacional Tizen.',
                'price' => 3299.90,
                'compare_at_price' => 3999.90,
                'stock' => 5, // Low stock
                'sku' => 'TV-55QLED',
                'images' => ['https://placehold.co/600x600/0d47a1/white?text=Smart+TV'],
                'categories' => ['TVs', 'QLED', '4K'],
            ],
            [
                'name' => 'Tablet 11" 128GB WiFi',
                'description' => 'Tablet com tela de 11", 128GB de armazenamento, 8GB RAM, ideal para produtividade e entretenimento.',
                'price' => 2199.90,
                'compare_at_price' => null,
                'stock' => 23,
                'sku' => 'TB-11128',
                'images' => ['https://placehold.co/600x600/311b92/white?text=Tablet'],
                'categories' => ['Tablets', 'Produtividade'],
            ],
            [
                'name' => 'Smartwatch Fitness Pro',
                'description' => 'Smartwatch com GPS integrado, monitor cardíaco, SpO2, 100+ modos esportivos e bateria de 14 dias.',
                'price' => 1299.90,
                'compare_at_price' => 1599.90,
                'stock' => 38,
                'sku' => 'SW-FIT01',
                'images' => ['https://placehold.co/600x600/004d40/white?text=Smartwatch'],
                'categories' => ['Smartwatches', 'Fitness', 'Wearables'],
            ],
            [
                'name' => 'Câmera de Segurança WiFi',
                'description' => 'Câmera de segurança com visão noturna, detecção de movimento, áudio bidirecional e armazenamento em nuvem.',
                'price' => 249.90,
                'compare_at_price' => null,
                'stock' => 112,
                'sku' => 'CAM-WIFI01',
                'images' => ['https://placehold.co/600x600/455a64/white?text=Camera'],
                'categories' => ['Segurança', 'Smart Home', 'Câmeras'],
            ],
            [
                'name' => 'Power Bank 20000mAh',
                'description' => 'Carregador portátil de 20000mAh com carregamento rápido 65W, 3 portas USB e display LED.',
                'price' => 199.90,
                'compare_at_price' => 249.90,
                'stock' => 78,
                'sku' => 'PB-20K',
                'images' => ['https://placehold.co/600x600/bf360c/white?text=Power+Bank'],
                'categories' => ['Acessórios', 'Carregadores', 'Portátil'],
            ],
            [
                'name' => 'Teclado Mecânico Gamer RGB',
                'description' => 'Teclado mecânico com switches blue, iluminação RGB personalizada, anti-ghosting e apoio para pulso.',
                'price' => 349.90,
                'compare_at_price' => null,
                'stock' => 0, // Out of stock
                'sku' => 'TC-GMR01',
                'images' => ['https://placehold.co/600x600/880e4f/white?text=Teclado+Gamer'],
                'categories' => ['Periféricos', 'Gamer', 'Teclados'],
                'is_active' => false,
            ],
            [
                'name' => 'Mouse Gamer 16000 DPI',
                'description' => 'Mouse gamer com sensor óptico de 16000 DPI, 8 botões programáveis e iluminação RGB.',
                'price' => 199.90,
                'compare_at_price' => 279.90,
                'stock' => 56,
                'sku' => 'MS-GMR01',
                'images' => ['https://placehold.co/600x600/1b5e20/white?text=Mouse+Gamer'],
                'categories' => ['Periféricos', 'Gamer', 'Mouses'],
            ],
        ];
    }

    private function getFashionProducts(): array
    {
        return [
            [
                'name' => 'Vestido Longo Estampado',
                'description' => 'Vestido longo com estampa exclusiva, decote V e manga bufante. Perfeito para ocasiões especiais.',
                'price' => 289.90,
                'compare_at_price' => 359.90,
                'stock' => 28,
                'sku' => 'VLE-001',
                'images' => ['https://placehold.co/600x800/ad1457/white?text=Vestido+Longo'],
                'categories' => ['Vestidos', 'Festa', 'Estampados'],
            ],
            [
                'name' => 'Bolsa Tote Couro Sintético',
                'description' => 'Bolsa tote espaçosa em couro sintético premium, com alças reforçadas e bolsos internos.',
                'price' => 179.90,
                'compare_at_price' => null,
                'stock' => 42,
                'sku' => 'BT-001',
                'images' => ['https://placehold.co/600x600/5d4037/white?text=Bolsa+Tote'],
                'categories' => ['Bolsas', 'Acessórios'],
            ],
            [
                'name' => 'Sandália Salto Bloco',
                'description' => 'Sandália com salto bloco de 7cm, tiras delicadas e palmilha macia. Conforto e elegância.',
                'price' => 159.90,
                'compare_at_price' => 199.90,
                'stock' => 3, // Low stock
                'sku' => 'SS-001',
                'images' => ['https://placehold.co/600x600/c2185b/white?text=Sandalia'],
                'categories' => ['Calçados', 'Sandálias', 'Salto'],
            ],
            [
                'name' => 'Conjunto Cropped + Saia',
                'description' => 'Conjunto moderno com cropped e saia combinando, tecido canelado. Look completo e estiloso.',
                'price' => 199.90,
                'compare_at_price' => null,
                'stock' => 55,
                'sku' => 'CC-001',
                'images' => ['https://placehold.co/600x800/7b1fa2/white?text=Conjunto'],
                'categories' => ['Conjuntos', 'Tendência', 'Verão'],
            ],
            [
                'name' => 'Óculos de Sol Aviador',
                'description' => 'Óculos de sol modelo aviador com lentes polarizadas e proteção UV400. Armação em metal dourado.',
                'price' => 129.90,
                'compare_at_price' => 169.90,
                'stock' => 87,
                'sku' => 'OC-AV01',
                'images' => ['https://placehold.co/600x600/ff6f00/white?text=Oculos'],
                'categories' => ['Acessórios', 'Óculos', 'Verão'],
            ],
            [
                'name' => 'Lenço Estampado Seda',
                'description' => 'Lenço em seda com estampa floral, versátil para usar no cabelo, pescoço ou bolsa.',
                'price' => 89.90,
                'compare_at_price' => null,
                'stock' => 64,
                'sku' => 'LN-SD01',
                'images' => ['https://placehold.co/600x600/e91e63/white?text=Lenco'],
                'categories' => ['Acessórios', 'Lenços', 'Seda'],
            ],
            [
                'name' => 'Body Renda Premium',
                'description' => 'Body em renda com forro, decote coração e alças ajustáveis. Peça versátil e sensual.',
                'price' => 119.90,
                'compare_at_price' => 149.90,
                'stock' => 33,
                'sku' => 'BD-RN01',
                'images' => ['https://placehold.co/600x800/d81b60/white?text=Body+Renda'],
                'categories' => ['Bodies', 'Renda', 'Elegante'],
            ],
            [
                'name' => 'Macacão Pantacourt',
                'description' => 'Macacão pantacourt com amarração na cintura, tecido fluido e caimento impecável.',
                'price' => 219.90,
                'compare_at_price' => null,
                'stock' => 19,
                'sku' => 'MC-PT01',
                'images' => ['https://placehold.co/600x800/00695c/white?text=Macacao'],
                'categories' => ['Macacões', 'Casual', 'Tendência'],
            ],
        ];
    }

    private function getSportsProducts(): array
    {
        return [
            [
                'name' => 'Tênis Running Performance',
                'description' => 'Tênis de corrida com tecnologia de amortecimento, solado em borracha e cabedal respirável.',
                'price' => 399.90,
                'compare_at_price' => 499.90,
                'stock' => 34,
                'sku' => 'TR-001',
                'images' => ['https://placehold.co/600x600/1565c0/white?text=Tenis+Running'],
                'categories' => ['Calçados', 'Corrida', 'Performance'],
            ],
            [
                'name' => 'Legging Compressão Feminina',
                'description' => 'Legging de alta compressão com tecnologia dry-fit, cintura alta e bolso lateral para celular.',
                'price' => 139.90,
                'compare_at_price' => null,
                'stock' => 78,
                'sku' => 'LG-CF01',
                'images' => ['https://placehold.co/600x800/37474f/white?text=Legging'],
                'categories' => ['Feminino', 'Leggings', 'Fitness'],
            ],
            [
                'name' => 'Top Esportivo Treino',
                'description' => 'Top esportivo com suporte médio, alças ajustáveis e tecido que absorve suor.',
                'price' => 79.90,
                'compare_at_price' => 99.90,
                'stock' => 92,
                'sku' => 'TP-TR01',
                'images' => ['https://placehold.co/600x600/e91e63/white?text=Top+Esportivo'],
                'categories' => ['Feminino', 'Tops', 'Treino'],
            ],
            [
                'name' => 'Bermuda Masculina Dry-Fit',
                'description' => 'Bermuda esportiva masculina com tecnologia dry-fit, bolsos laterais e cós elástico.',
                'price' => 99.90,
                'compare_at_price' => null,
                'stock' => 65,
                'sku' => 'BM-DF01',
                'images' => ['https://placehold.co/600x600/263238/white?text=Bermuda'],
                'categories' => ['Masculino', 'Bermudas', 'Treino'],
            ],
            [
                'name' => 'Kit Halteres 2-10kg',
                'description' => 'Kit com 5 pares de halteres emborrachados (2, 4, 6, 8 e 10kg) com suporte organizador.',
                'price' => 599.90,
                'compare_at_price' => 749.90,
                'stock' => 12,
                'sku' => 'KH-210',
                'images' => ['https://placehold.co/600x600/455a64/white?text=Halteres'],
                'categories' => ['Equipamentos', 'Musculação', 'Halteres'],
            ],
            [
                'name' => 'Corda de Pular Speed',
                'description' => 'Corda de pular profissional com rolamentos, cabo de aço revestido e pegadores ergonômicos.',
                'price' => 69.90,
                'compare_at_price' => null,
                'stock' => 156,
                'sku' => 'CP-SP01',
                'images' => ['https://placehold.co/600x600/ff5722/white?text=Corda'],
                'categories' => ['Equipamentos', 'Cardio', 'Acessórios'],
            ],
            [
                'name' => 'Bola de Futebol Campo',
                'description' => 'Bola de futebol de campo oficial, costurada à mão, aprovada pela FIFA.',
                'price' => 189.90,
                'compare_at_price' => 229.90,
                'stock' => 47,
                'sku' => 'BF-CP01',
                'images' => ['https://placehold.co/600x600/4caf50/white?text=Bola+Futebol'],
                'categories' => ['Futebol', 'Bolas', 'Campo'],
            ],
            [
                'name' => 'Luvas de Treino Musculação',
                'description' => 'Luvas para musculação com palma em couro, dorso em tecido respirável e ajuste no pulso.',
                'price' => 59.90,
                'compare_at_price' => null,
                'stock' => 83,
                'sku' => 'LV-MU01',
                'images' => ['https://placehold.co/600x600/212121/white?text=Luvas'],
                'categories' => ['Acessórios', 'Musculação', 'Proteção'],
            ],
            [
                'name' => 'Garrafa Térmica 1L',
                'description' => 'Garrafa térmica de 1 litro em aço inox, mantém bebida gelada por 24h ou quente por 12h.',
                'price' => 119.90,
                'compare_at_price' => 149.90,
                'stock' => 7, // Low stock
                'sku' => 'GT-1L01',
                'images' => ['https://placehold.co/600x600/0097a7/white?text=Garrafa'],
                'categories' => ['Acessórios', 'Hidratação', 'Inox'],
            ],
            [
                'name' => 'Tapete Yoga Premium',
                'description' => 'Tapete de yoga antiderrapante, 6mm de espessura, com alça para transporte.',
                'price' => 149.90,
                'compare_at_price' => null,
                'stock' => 41,
                'sku' => 'TY-PM01',
                'images' => ['https://placehold.co/600x600/7b1fa2/white?text=Tapete+Yoga'],
                'categories' => ['Yoga', 'Tapetes', 'Relaxamento'],
            ],
        ];
    }

    private function getGenericProducts(): array
    {
        return [
            [
                'name' => 'Produto Demonstração 1',
                'description' => 'Produto de demonstração para testes.',
                'price' => 99.90,
                'compare_at_price' => null,
                'stock' => 50,
                'sku' => 'DEMO-001',
                'images' => ['https://placehold.co/600x600/9e9e9e/white?text=Produto+1'],
                'categories' => ['Geral'],
            ],
            [
                'name' => 'Produto Demonstração 2',
                'description' => 'Produto de demonstração para testes.',
                'price' => 149.90,
                'compare_at_price' => 199.90,
                'stock' => 30,
                'sku' => 'DEMO-002',
                'images' => ['https://placehold.co/600x600/9e9e9e/white?text=Produto+2'],
                'categories' => ['Geral', 'Promoção'],
            ],
        ];
    }
}
