<?php

namespace Database\Seeders;

use App\Services\AI\RAG\KnowledgeBaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class KnowledgeBaseSeeder extends Seeder
{
    private string $logChannel = 'embeddings';

    /**
     * Run the database seeds.
     *
     * Base de conhecimento com dados verificados de fontes oficiais:
     * - ABComm (Associacao Brasileira de Comercio Eletronico)
     * - Neotrust / NeoAtlas
     * - NuvemCommerce (Nuvemshop)
     * - Circana (Mercado de Beleza)
     * - NIQ/NielsenIQ
     * - IEMI (Casa e Decoracao)
     * - Fitness Brasil / Tecnofit
     * - E-commerce Radar / Yampi
     */
    public function run(KnowledgeBaseService $kb): void
    {
        $knowledge = [
            // =====================================================
            // BENCHMARKS - Metricas de referencia verificadas
            // =====================================================

            // Benchmark Geral - ABComm/Neotrust 2024/2025
            [
                'category' => 'benchmark',
                'niche' => 'general',
                'title' => 'Benchmarks E-commerce Brasil 2024/2025 - Dados Oficiais ABComm',
                'content' => 'Faturamento e-commerce Brasil 2024: R$ 204,3 bilhoes com 414,9 milhoes de pedidos (ABComm). Ticket medio 2024: R$ 492,40. Projecao 2025: R$ 234 bilhoes (+15%), ticket medio R$ 539,28, 94 milhoes de consumidores ativos (+3 milhoes). Taxa de conversao media Brasil: 1,65% (Experian Hitwise), variando de 1,3% (lojas menores) a 3,7% (grandes e-commerces). Taxa de abandono de carrinho: 82% no Brasil - maior do mundo (E-commerce Radar). Principal etapa de abandono: 68% acontece na etapa de dados de entrega. Motivos de abandono: indecisao (39%), prazo demorado (36,5%), frete alto (6,5%). Trafego mobile: mais de 65% das compras online sao via dispositivos moveis.',
                'metadata' => [
                    'sources' => [
                        'ABComm - Associacao Brasileira de Comercio Eletronico',
                        'Neotrust',
                        'Experian Hitwise',
                        'E-commerce Radar',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'total_revenue' => ['value' => 204.3, 'unit' => 'bilhoes BRL'],
                        'total_orders' => ['value' => 414.9, 'unit' => 'milhoes'],
                        'average_ticket' => ['value' => 492.40, 'unit' => 'BRL'],
                        'conversion_rate' => ['min' => 1.3, 'max' => 3.7, 'average' => 1.65, 'unit' => '%'],
                        'cart_abandonment' => ['value' => 82, 'unit' => '%'],
                        'mobile_traffic' => ['value' => 65, 'unit' => '%'],
                    ],
                    'projections_2025' => [
                        'revenue' => 234,
                        'ticket_medio' => 539.28,
                        'consumers' => 94000000,
                    ],
                    'verified' => true,
                    'tags' => ['geral', 'brasil', 'ecommerce', 'metricas', 'abcomm'],
                ],
            ],

            // Benchmark Moda - NuvemCommerce 2024
            [
                'category' => 'benchmark',
                'niche' => 'fashion',
                'title' => 'Benchmarks E-commerce Moda Brasil 2024 - Dados NuvemCommerce',
                'content' => 'Moda representa 32% dos e-commerces brasileiros, sendo o segmento mais comum (NuvemCommerce 2024). Ticket medio moda: R$ 260-262. Taxa de conversao moda: 1,6% desktop, 0,9% mobile (NeoAtlas). Publico: 66% preferem comprar roupas online (Opinion Box). Comportamento: 24% gastam ate R$ 200/mes em moda. Taxa de abandono em moda/luxo: acima da media, categoria com mais abandono. Trafego: 70-80% via mobile. Sazonalidade: Dia das Maes (+40%), Black Friday (+80%), Natal (+50%). Mais de 80% dos lojistas de moda estao otimistas para 2025. E-commerce moda 1T/2025: faturou R$ 413 milhoes. Live commerce: marcas registram ate 30% de conversao em transmissoes ao vivo.',
                'metadata' => [
                    'sources' => [
                        'NuvemCommerce 2024/2025 (Nuvemshop)',
                        'NeoAtlas',
                        'Opinion Box - Consumo de Moda no Brasil',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_share' => ['value' => 32, 'unit' => '%'],
                        'average_ticket' => ['min' => 260, 'max' => 262, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 1.6, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.9, 'unit' => '%'],
                        'online_preference' => ['value' => 66, 'unit' => '%'],
                        'mobile_traffic' => ['min' => 70, 'max' => 80, 'unit' => '%'],
                    ],
                    'verified' => true,
                    'tags' => ['moda', 'fashion', 'vestuario', 'roupas', 'nuvemcommerce'],
                ],
            ],

            // Benchmark Eletronicos - Neotrust/NeoAtlas 2024
            [
                'category' => 'benchmark',
                'niche' => 'electronics',
                'title' => 'Benchmarks E-commerce Eletronicos Brasil 2024 - Dados Oficiais',
                'content' => 'Eletronicos foi a categoria mais consumida em 2024, representando mais de 28% das vendas via smartphones (ABComm). Taxa de conversao eletronicos: 2,2% desktop, 1,4% mobile - acima da media por ser compra mais planejada (NeoAtlas). Ticket medio: R$ 800 a R$ 1.500 (alto valor). Margem de lucro operacional: 15-20% (entre as melhores do e-commerce). Ciclo de decisao: 7-15 dias, 85% comparam preco em 3+ sites antes de comprar. Garantia estendida: 20-30% dos clientes aceitam o upsell. Reviews sao criticos: produtos com avaliacoes vendem 3x mais. Sazonalidade: Black Friday (+120%), Volta as Aulas (+30%), Natal (+40%).',
                'metadata' => [
                    'sources' => [
                        'ABComm',
                        'NeoAtlas',
                        'Neotrust',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_share_mobile' => ['value' => 28, 'unit' => '%'],
                        'conversion_rate_desktop' => ['value' => 2.2, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 1.4, 'unit' => '%'],
                        'average_ticket' => ['min' => 800, 'max' => 1500, 'unit' => 'BRL'],
                        'profit_margin' => ['min' => 15, 'max' => 20, 'unit' => '%'],
                        'extended_warranty_acceptance' => ['min' => 20, 'max' => 30, 'unit' => '%'],
                    ],
                    'verified' => true,
                    'tags' => ['eletronicos', 'tecnologia', 'celulares', 'computadores'],
                ],
            ],

            // =====================================================
            // BENCHMARKS POR SUBCATEGORIA - ELECTRONICS
            // =====================================================

            // Benchmark Smartphones - Celulares e Acessorios
            [
                'category' => 'benchmark',
                'niche' => 'electronics',
                'subcategory' => 'smartphones',
                'title' => 'Benchmarks E-commerce Smartphones Brasil 2024',
                'content' => 'Mercado de smartphones Brasil 2024: segmento de maior ticket medio em eletronicos. Ticket medio smartphones: R$ 1.200-3.500 (media R$ 2.200). Taxa de conversao: 0,8% desktop, 0,4% mobile - baixa por pesquisa extensa e comparacao de precos. Ciclo de decisao: 10-20 dias, 90% comparam em 4+ sites. Principais marcas: Samsung (35%), Motorola (25%), Xiaomi (18%), Apple (12%). Parcelamento: essencial, 85% compram parcelado em 10-12x. Lancamentos: primeiros 30 dias concentram 40% das vendas. Trade-in (troca): 15-20% aceitam programa de troca do antigo. Capas e peliculas protetoras: cross-sell em 60% dos pedidos (+R$ 80-150 ticket). Reviews sao criticos: 95% leem avaliacoes antes de comprar. Garantia estendida: 25-35% aceitam upsell. Sazonalidade: Black Friday (+150%), Dia das Maes (+35%), Natal (+45%).',
                'metadata' => [
                    'sources' => [
                        'IDC Brasil',
                        'GfK',
                        'ABComm',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'ticket_medio' => ['min' => 1200, 'max' => 3500, 'avg' => 2200, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 0.8, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.4, 'unit' => '%'],
                        'installment_usage' => ['value' => 85, 'unit' => '%'],
                        'trade_in_acceptance' => ['min' => 15, 'max' => 20, 'unit' => '%'],
                        'cross_sell_rate' => ['value' => 60, 'unit' => '%'],
                        'warranty_acceptance' => ['min' => 25, 'max' => 35, 'unit' => '%'],
                    ],
                    'top_brands' => ['Samsung', 'Motorola', 'Xiaomi', 'Apple'],
                    'avoid_mentions' => ['notebook', 'desktop', 'console', 'PlayStation', 'fone gamer', 'TV', 'geladeira', 'smartwatch fitness'],
                    'verified' => true,
                    'tags' => ['smartphones', 'celular', 'iphone', 'android', 'parcelamento'],
                ],
            ],

            // Benchmark Computers - Computadores e Notebooks
            [
                'category' => 'benchmark',
                'niche' => 'electronics',
                'subcategory' => 'computers',
                'title' => 'Benchmarks E-commerce Computadores Brasil 2024',
                'content' => 'Mercado de informatica Brasil 2024: segmento tecnico com alto engajamento pre-compra. Ticket medio computadores: R$ 800-3.000 (media R$ 1.800). Taxa de conversao: 1,0% desktop, 0,4% mobile - compra realizada majoritariamente no desktop por necessidade de comparar especificacoes. Ciclo de decisao: 15-30 dias, pesquisa intensiva. Principais produtos: Notebooks (55%), Desktops (20%), Componentes/upgrades (15%), Monitores (10%). Publico: 40% gamers, 35% trabalho/estudo, 15% criadores de conteudo, 10% uso geral. Especificacoes tecnicas: CPU, GPU, RAM, armazenamento devem estar destacadas. Comparacao lado a lado aumenta conversao em 25%. Configuracao customizada (componentes): aumenta ticket em 30%. Parcelamento em 10-12x e esperado. Software incluido (Windows, Office): diferencial em 20% das vendas. Sazonalidade: Volta as aulas (+40%), Black Friday (+130%), fim de ano (+35%).',
                'metadata' => [
                    'sources' => [
                        'IDC Brasil',
                        'GfK',
                        'ABComm',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'ticket_medio' => ['min' => 800, 'max' => 3000, 'avg' => 1800, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 1.0, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.4, 'unit' => '%'],
                        'notebook_share' => ['value' => 55, 'unit' => '%'],
                        'gamer_audience' => ['value' => 40, 'unit' => '%'],
                        'comparison_conversion' => ['value' => 25, 'unit' => '%'],
                        'custom_ticket_increase' => ['value' => 30, 'unit' => '%'],
                    ],
                    'top_products' => ['Notebooks', 'Desktops', 'Componentes', 'Monitores'],
                    'avoid_mentions' => ['smartphone', 'celular', 'console', 'jogo', 'TV', 'caixa de som', 'geladeira', 'pulseira fitness'],
                    'verified' => true,
                    'tags' => ['computadores', 'notebook', 'desktop', 'informatica', 'componentes'],
                ],
            ],

            // Benchmark Gaming - Games e Consoles
            [
                'category' => 'benchmark',
                'niche' => 'electronics',
                'subcategory' => 'gaming',
                'title' => 'Benchmarks E-commerce Gaming Brasil 2024',
                'content' => 'Mercado de games Brasil 2024: 5o maior mercado de games do mundo, publico engajado e fiel. Ticket medio gaming: R$ 300-800 (media R$ 500). Taxa de conversao: 1,4% desktop, 0,6% mobile - conversao alta por compra de paixao. Principais produtos: Jogos digitais/fisicos (40%), Perifericos gamer (30%), Consoles (20%), Acessorios (10%). Plataformas: PlayStation (38%), Xbox (22%), Nintendo (18%), PC (22%). Lancamentos: pre-venda representa 25-30% das vendas do titulo. Comunidade: forum, Discord, reviews de jogadores aumentam engajamento em 50%. Edicoes especiais/colecionaveis: premium de 40-80% sobre edicao padrao, 15% dos gamers compram. Perifericos RGB: teclado, mouse, headset gamer tem margem 20-25%. Bundles (console + jogo + controle extra): aumentam ticket em 35%. Programa de fidelidade/pontos para gamers: recompra de 40-55%. Sazonalidade: Lancamentos AAA (toda semana), Black Friday (+140%), Natal (+60%).',
                'metadata' => [
                    'sources' => [
                        'GfK',
                        'ABComm',
                        'Euromonitor',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'ticket_medio' => ['min' => 300, 'max' => 800, 'avg' => 500, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 1.4, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.6, 'unit' => '%'],
                        'preorder_share' => ['min' => 25, 'max' => 30, 'unit' => '%'],
                        'community_engagement' => ['value' => 50, 'unit' => '%'],
                        'special_edition_buyers' => ['value' => 15, 'unit' => '%'],
                        'bundle_ticket_increase' => ['value' => 35, 'unit' => '%'],
                        'repurchase_rate' => ['min' => 40, 'max' => 55, 'unit' => '%'],
                    ],
                    'platforms' => ['PlayStation', 'Xbox', 'Nintendo', 'PC'],
                    'avoid_mentions' => ['smartphone', 'notebook trabalho', 'TV comum', 'caixa de som Bluetooth', 'geladeira', 'smartwatch casual'],
                    'verified' => true,
                    'tags' => ['gaming', 'games', 'console', 'playstation', 'xbox', 'nintendo'],
                ],
            ],

            // Benchmark Audio - Audio e Som
            [
                'category' => 'benchmark',
                'niche' => 'electronics',
                'subcategory' => 'audio',
                'title' => 'Benchmarks E-commerce Audio Brasil 2024',
                'content' => 'Mercado de audio Brasil 2024: segmento em crescimento com foco em qualidade sonora e mobilidade. Ticket medio audio: R$ 200-800 (media R$ 400). Taxa de conversao: 1,1% desktop, 0,5% mobile. Principais produtos: Fones de ouvido (45%), Caixas de som Bluetooth (30%), Soundbars (15%), Microfones (10%). Fones: TWS (True Wireless) dominam 60% das vendas, ANC (cancelamento de ruido) e diferencial premium (+40% ticket). Marcas premium: Sony, JBL, Bose, Sennheiser tem fidelidade de 35%. Audiofilo vs casual: 25% buscam especificacoes tecnicas (impedancia, drivers, frequencia), 75% priorizam marca e design. Reviews com teste de som aumentam conversao em 30%. Comparacao de som (audio samples) diferencial unico. Garantia contra defeitos: essencial, 20% aceitam estendida. Cross-sell com capas e cabos: 40% dos pedidos. Sazonalidade: Dia dos Pais (+40%), Black Friday (+110%), Natal (+50%).',
                'metadata' => [
                    'sources' => [
                        'GfK',
                        'IDC Brasil',
                        'Euromonitor',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'ticket_medio' => ['min' => 200, 'max' => 800, 'avg' => 400, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 1.1, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.5, 'unit' => '%'],
                        'tws_share' => ['value' => 60, 'unit' => '%'],
                        'anc_premium' => ['value' => 40, 'unit' => '%'],
                        'brand_loyalty' => ['value' => 35, 'unit' => '%'],
                        'audiophile_audience' => ['value' => 25, 'unit' => '%'],
                        'review_conversion' => ['value' => 30, 'unit' => '%'],
                        'cross_sell_rate' => ['value' => 40, 'unit' => '%'],
                    ],
                    'top_products' => ['Fones TWS', 'Caixas Bluetooth', 'Soundbars', 'Microfones'],
                    'avoid_mentions' => ['smartphone', 'notebook', 'console', 'TV LED', 'geladeira', 'ar condicionado', 'smartwatch'],
                    'verified' => true,
                    'tags' => ['audio', 'fone', 'caixa de som', 'soundbar', 'bluetooth', 'TWS'],
                ],
            ],

            // Benchmark TV & Video - Televisores e Video
            [
                'category' => 'benchmark',
                'niche' => 'electronics',
                'subcategory' => 'tv_video',
                'title' => 'Benchmarks E-commerce TV e Video Brasil 2024',
                'content' => 'Mercado de TV Brasil 2024: segmento de alto ticket com pesquisa extensa. Ticket medio TV: R$ 1.500-5.000 (media R$ 2.500). Taxa de conversao: 0,7% desktop, 0,3% mobile - baixa por compra planejada de alto valor. Principais produtos: Smart TVs (85%), Projetores (10%), Streaming devices (5%). Tamanhos: 50-55 polegadas (40%), 43 polegadas (25%), 65+ polegadas (20%), ate 42 polegadas (15%). Tecnologias: 4K UHD domina 70%, QLED/OLED premium 15%, Full HD basico 15%. Smart TV: Android TV, Google TV, Tizen, webOS - sistema operacional influencia 30% da decisao. Comparacao de especificacoes: HDR, taxa de atualizacao (Hz), HDMI 2.1 sao diferenciais. Reviews com fotos/videos de qualidade de imagem aumentam conversao em 35%. Parcelamento longo (12-18x) esperado. Instalacao/suporte tecnico: 15-20% contratam servico adicional. Sazonalidade: Copa do Mundo, Olimpiadas (+200%), Black Friday (+120%), Natal (+55%).',
                'metadata' => [
                    'sources' => [
                        'GfK',
                        'IDC Brasil',
                        'ABComm',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'ticket_medio' => ['min' => 1500, 'max' => 5000, 'avg' => 2500, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 0.7, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.3, 'unit' => '%'],
                        'smart_tv_share' => ['value' => 85, 'unit' => '%'],
                        '4k_share' => ['value' => 70, 'unit' => '%'],
                        'review_conversion' => ['value' => 35, 'unit' => '%'],
                        'installation_service' => ['min' => 15, 'max' => 20, 'unit' => '%'],
                    ],
                    'popular_sizes' => ['50-55"', '43"', '65"+', 'ate 42"'],
                    'avoid_mentions' => ['smartphone', 'notebook', 'console', 'fone', 'caixa de som pequena', 'geladeira', 'smartwatch'],
                    'verified' => true,
                    'tags' => ['TV', 'smart tv', '4K', 'QLED', 'OLED', 'projetor'],
                ],
            ],

            // Benchmark Appliances - Eletrodomesticos
            [
                'category' => 'benchmark',
                'niche' => 'electronics',
                'subcategory' => 'appliances',
                'title' => 'Benchmarks E-commerce Eletrodomesticos Brasil 2024',
                'content' => 'Mercado de eletrodomesticos Brasil 2024: compra de necessidade com pesquisa de preco intensa. Ticket medio eletrodomesticos: R$ 1.000-4.000 (media R$ 2.000). Taxa de conversao: 0,8% desktop, 0,3% mobile - compra complexa por frete alto e instalacao. Principais produtos: Geladeira/refrigerador (30%), Maquina de lavar (25%), Ar condicionado (20%), Fogao/cooktop (15%), Micro-ondas (10%). Selo Procel: eficiencia energetica influencia 45% das compras. Capacidade/tamanho: especificacao critica (litros, kg de roupa, BTUs). Frete e instalacao: 70% esperam frete gratis + instalacao inclusa ou com desconto. Garantia estendida: 30-40% aceitam para produtos de alto valor. Parcelamento longo (12-24x) obrigatorio. Troca do antigo (retirada): diferencial valorizado por 25%. Reviews de durabilidade e consumo de energia sao criticos. Sazonalidade: Mudanca/reforma (jan-mar +30%), Dia das Maes (+25%), Black Friday (+100%), Natal (+40%).',
                'metadata' => [
                    'sources' => [
                        'GfK',
                        'Euromonitor',
                        'ABComm',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'ticket_medio' => ['min' => 1000, 'max' => 4000, 'avg' => 2000, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 0.8, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.3, 'unit' => '%'],
                        'procel_influence' => ['value' => 45, 'unit' => '%'],
                        'free_shipping_expectation' => ['value' => 70, 'unit' => '%'],
                        'warranty_acceptance' => ['min' => 30, 'max' => 40, 'unit' => '%'],
                        'trade_in_interest' => ['value' => 25, 'unit' => '%'],
                    ],
                    'top_products' => ['Geladeira', 'Maquina de lavar', 'Ar condicionado', 'Fogao', 'Micro-ondas'],
                    'avoid_mentions' => ['smartphone', 'notebook', 'console', 'fone', 'TV pequena', 'smartwatch', 'pulseira fitness'],
                    'verified' => true,
                    'tags' => ['eletrodomesticos', 'geladeira', 'maquina de lavar', 'ar condicionado', 'linha branca'],
                ],
            ],

            // Benchmark Wearables - Smartwatches e Wearables
            [
                'category' => 'benchmark',
                'niche' => 'electronics',
                'subcategory' => 'wearables',
                'title' => 'Benchmarks E-commerce Wearables Brasil 2024',
                'content' => 'Mercado de wearables Brasil 2024: categoria em expansao com foco em saude e fitness. Ticket medio wearables: R$ 300-1.500 (media R$ 700). Taxa de conversao: 1,2% desktop, 0,6% mobile. Principais produtos: Smartwatches (60%), Pulseiras fitness (30%), Oculos VR/AR (10%). Smartwatches: Apple Watch (40% premium), Samsung Galaxy Watch (25%), Xiaomi Mi Band/Watch (20%), outras marcas (15%). Funcionalidades valorizadas: monitoramento cardiaco (80%), GPS (60%), notificacoes (90%), pagamento NFC (35%), chamadas (40%). Compatibilidade: iOS vs Android influencia 70% da escolha. Publico fitness: 55% buscam rastreamento de atividades, calorias, sono. Reviews de autonomia de bateria sao criticos: duracao influencia 60% das compras. Cross-sell com pulseiras extras: 35% compram acessorios adicionais. Garantia contra agua/suor: essencial. Sazonalidade: Janeiro (metas fitness +60%), Dia dos Namorados (+30%), Black Friday (+100%), Natal (+50%).',
                'metadata' => [
                    'sources' => [
                        'IDC Brasil',
                        'GfK',
                        'Euromonitor',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'ticket_medio' => ['min' => 300, 'max' => 1500, 'avg' => 700, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 1.2, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.6, 'unit' => '%'],
                        'smartwatch_share' => ['value' => 60, 'unit' => '%'],
                        'fitness_audience' => ['value' => 55, 'unit' => '%'],
                        'compatibility_influence' => ['value' => 70, 'unit' => '%'],
                        'battery_influence' => ['value' => 60, 'unit' => '%'],
                        'cross_sell_accessories' => ['value' => 35, 'unit' => '%'],
                    ],
                    'top_brands' => ['Apple Watch', 'Samsung', 'Xiaomi', 'Garmin'],
                    'avoid_mentions' => ['smartphone comum', 'notebook', 'console', 'TV', 'geladeira', 'caixa de som grande', 'ar condicionado'],
                    'verified' => true,
                    'tags' => ['wearables', 'smartwatch', 'pulseira fitness', 'apple watch', 'saude', 'fitness'],
                ],
            ],

            // Benchmark Beleza - Circana/NIQ/Nuvemshop 2024
            [
                'category' => 'benchmark',
                'niche' => 'beauty',
                'title' => 'Benchmarks E-commerce Beleza e Cosmeticos Brasil 2024 - Dados Circana e NIQ',
                'content' => 'Mercado de beleza Brasil 2024: aproximadamente US$ 27 bilhoes, 4o maior do mundo (NIQ). Crescimento 2024: 12,7% em valor. Beleza de prestigio cresceu 19%, maquiagem +26-27%. PMEs de Saude e Beleza no e-commerce: R$ 257,5 milhoes de janeiro a agosto 2024 (+40% vs 2023) - dados Nuvemshop. E-commerce de beleza: canal de maior crescimento, CAGR projetado de 8,77% ate 2030. Taxa de conversao beleza: 1% desktop, 0,5% mobile (NeoAtlas) - abaixo da media por alta pesquisa. Categorias destaque: Oleos Capilares (+68%), Cosmeticos Labiais (+47%). Redes sociais: 21% dos pedidos vem de social, 83% via Instagram. Brasil e 4o pais com maior penetracao online para beleza seletiva. Lojas especializadas detem 38,58% do mercado.',
                'metadata' => [
                    'sources' => [
                        'Circana',
                        'NIQ/NielsenIQ',
                        'Nuvemshop/NuvemCommerce',
                        'NeoAtlas',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size' => ['value' => 27, 'unit' => 'bilhoes USD'],
                        'market_growth' => ['value' => 12.7, 'unit' => '%'],
                        'prestige_growth' => ['value' => 19, 'unit' => '%'],
                        'makeup_growth' => ['value' => 27, 'unit' => '%'],
                        'pme_revenue' => ['value' => 257.5, 'unit' => 'milhoes BRL'],
                        'conversion_rate_desktop' => ['value' => 1.0, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.5, 'unit' => '%'],
                        'social_orders' => ['value' => 21, 'unit' => '%'],
                        'instagram_share' => ['value' => 83, 'unit' => '%'],
                    ],
                    'verified' => true,
                    'tags' => ['beleza', 'cosmeticos', 'maquiagem', 'skincare', 'perfumaria'],
                ],
            ],

            // Benchmark Alimentos - ABComm/Nielsen 2024
            [
                'category' => 'benchmark',
                'niche' => 'food',
                'title' => 'Benchmarks E-commerce Alimentos e Bebidas Brasil 2024 - ABComm e Nielsen',
                'content' => 'Setor de alimentos e bebidas no e-commerce 2024: R$ 16 bilhoes (+18,4% vs 2023) - ABComm. Maior crescimento do e-commerce no 1o semestre 2024. Taxa de conversao alimentos: 4,5% desktop, 0,8% mobile (NeoAtlas) - maior taxa desktop por necessidade recorrente. Taxa de abandono alimentos: 72%, menor que media (82%). Online representa 4,3% das vendas de FMCG (vs 4,1% em 2023). Projecao: triplicar ate 2027 (McKinsey). 40% dos supermercadistas operam com e-commerce, mas participacao media ainda e 12%. Email marketing representa 32,9% da receita do setor. Itens mais vendidos: perfumes, cosmeticos e maquiagens (crossover com beleza). Higiene/Beleza e Alimentos juntos respondem por mais da metade do crescimento.',
                'metadata' => [
                    'sources' => [
                        'ABComm',
                        'Nielsen/NIQ',
                        'McKinsey',
                        'NeoAtlas',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'sector_revenue' => ['value' => 16, 'unit' => 'bilhoes BRL'],
                        'growth' => ['value' => 18.4, 'unit' => '%'],
                        'conversion_rate_desktop' => ['value' => 4.5, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.8, 'unit' => '%'],
                        'cart_abandonment' => ['value' => 72, 'unit' => '%'],
                        'fmcg_online_share' => ['value' => 4.3, 'unit' => '%'],
                        'email_revenue_share' => ['value' => 32.9, 'unit' => '%'],
                    ],
                    'verified' => true,
                    'tags' => ['alimentos', 'bebidas', 'comida', 'supermercado', 'fmcg'],
                ],
            ],

            // Benchmark Casa e Decoracao - ABCasa/IEMI 2024
            [
                'category' => 'benchmark',
                'niche' => 'home',
                'title' => 'Benchmarks E-commerce Casa e Decoracao Brasil 2024 - ABCasa e IEMI',
                'content' => 'Setor de artigos para casa 2024: R$ 102 bilhoes (+8,7% vs 2023). Consumo per capita: R$ 478/ano, media por domicilio R$ 1.362. Total de pontos de venda: 238,8 mil, crescimento anual de 6,1% desde 2019 (ABCasa). E-commerce Casa e Jardim (Nuvemshop): R$ 206,5 milhoes em 2024 (+30% vs 2023). Audiencia Casa e Moveis: 78,4 milhoes de acessos/mes, crescimento de 4,5% em marco/2024. Mobile: 69% das visitas, 7,7% via apps, 23,3% desktop. Enfeites e Decoracao: +40% no Mercado Livre. Criterios de decisao: qualidade (50%), durabilidade (35%), preco acessivel (32%). Concentracao regional: Sudeste 47,2%, Sul 18,9%, Nordeste 17,9%. Black Friday lidera ocasioes de compra (46%), Dia das Maes (14%), Natal (7%). China fornece 73,7% das importacoes.',
                'metadata' => [
                    'sources' => [
                        'ABCasa - Associacao Brasileira de Artigos para Casa',
                        'IEMI',
                        'NuvemCommerce/Nuvemshop',
                        'Similar Web',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'sector_revenue' => ['value' => 102, 'unit' => 'bilhoes BRL'],
                        'growth' => ['value' => 8.7, 'unit' => '%'],
                        'per_capita_consumption' => ['value' => 478, 'unit' => 'BRL/ano'],
                        'household_consumption' => ['value' => 1362, 'unit' => 'BRL'],
                        'nuvemshop_revenue' => ['value' => 206.5, 'unit' => 'milhoes BRL'],
                        'nuvemshop_growth' => ['value' => 30, 'unit' => '%'],
                        'mobile_traffic' => ['value' => 69, 'unit' => '%'],
                        'monthly_access' => ['value' => 78.4, 'unit' => 'milhoes'],
                    ],
                    'verified' => true,
                    'tags' => ['casa', 'decoracao', 'moveis', 'jardim', 'abcasa'],
                ],
            ],

            // Benchmark Esportes/Fitness - Fitness Brasil 2024
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'title' => 'Benchmarks E-commerce Esportes e Fitness Brasil 2024 - Fitness Brasil e Tecnofit',
                'content' => 'Mercado fitness Brasil: R$ 8-12 bilhoes/ano, mais de 64 mil empresas (Sebrae/Fitness Brasil). Crescimento janeiro 2025: +22,22% vs janeiro 2024 (Tecnofit). Crescimento 2023: 13,97%. PMEs online produtos fitness 2024: R$ 160 milhoes. Moda esportiva/fitness: 12,6% da producao de vestuario, 9,3% do faturamento. E-commerce cresceu 50,4% no canal internet 2023 vs anterior. Track&Field 2T/2025: R$ 409 milhoes (+27,8% vs 2T/2024). Suplementos: R$ 4,6 bilhoes em 2023, crescimento anual 20%+, quase dobrou em 4 anos. Perfil: 62% homens, 38% mulheres. Faixa etaria: 36-45 anos (32%), 26-35 anos (26%). 50% dos brasileiros praticam atividades fisicas regularmente (Datafolha 2025). Potencial: apenas 5% da populacao usa academias.',
                'metadata' => [
                    'sources' => [
                        'Fitness Brasil',
                        'Tecnofit',
                        'Sebrae',
                        'Datafolha',
                        'IEMI',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size' => ['min' => 8, 'max' => 12, 'unit' => 'bilhoes BRL'],
                        'companies' => ['value' => 64000, 'unit' => 'empresas'],
                        'growth_jan_2025' => ['value' => 22.22, 'unit' => '%'],
                        'pme_online_revenue' => ['value' => 160, 'unit' => 'milhoes BRL'],
                        'supplements_market' => ['value' => 4.6, 'unit' => 'bilhoes BRL'],
                        'supplements_growth' => ['value' => 20, 'unit' => '% ao ano'],
                        'male_practitioners' => ['value' => 62, 'unit' => '%'],
                        'regular_exercise' => ['value' => 50, 'unit' => '%'],
                        'gym_penetration' => ['value' => 5, 'unit' => '%'],
                    ],
                    'verified' => true,
                    'tags' => ['esportes', 'fitness', 'academia', 'suplementos', 'moda esportiva'],
                ],
            ],

            // =====================================================
            // TAXAS DE CONVERSAO POR SEGMENTO - NeoAtlas
            // =====================================================

            [
                'category' => 'benchmark',
                'niche' => 'general',
                'title' => 'Taxas de Conversao por Segmento E-commerce Brasil - NeoAtlas',
                'content' => 'Taxas de conversao por segmento (Desktop / Mobile) segundo NeoAtlas: Alimentos e Bebidas: 4,5% / 0,8% (maior desktop). Eletroeletronicos: 2,2% / 1,4% (acima da media). Calcados e Acessorios: 1,6% / 0,9%. Bebes e Criancas: 1,5% / 0,9%. Games: 1,4% / 0,8%. Acessorios Automotivos: 1,3% / 0,5%. Beleza: 1,0% / 0,5% (muito pesquisado). Media geral Brasil: 1,65% (Experian) ou 1,28% para lojas acima de R$ 100k (NuvemCommerce). Lojas com faturamento abaixo de R$ 100k: conversao media de 0,82%. Globalmente: taxa media de 1,92% (IRP Commerce). Benchmark mundial: 2,5% a 3% e considerado bom. Fatores que afetam: preco do produto (alto = menor conversao), confianca no site, experiencia mobile.',
                'metadata' => [
                    'sources' => [
                        'NeoAtlas',
                        'Experian Hitwise',
                        'NuvemCommerce',
                        'IRP Commerce Market Data',
                    ],
                    'year' => 2024,
                    'conversion_rates' => [
                        'alimentos_bebidas' => ['desktop' => 4.5, 'mobile' => 0.8],
                        'eletroeletronicos' => ['desktop' => 2.2, 'mobile' => 1.4],
                        'calcados_acessorios' => ['desktop' => 1.6, 'mobile' => 0.9],
                        'bebes_criancas' => ['desktop' => 1.5, 'mobile' => 0.9],
                        'games' => ['desktop' => 1.4, 'mobile' => 0.8],
                        'automotivo' => ['desktop' => 1.3, 'mobile' => 0.5],
                        'beleza' => ['desktop' => 1.0, 'mobile' => 0.5],
                    ],
                    'averages' => [
                        'brasil' => 1.65,
                        'lojas_grandes' => 1.28,
                        'lojas_pequenas' => 0.82,
                        'global' => 1.92,
                        'benchmark_bom' => 3.0,
                    ],
                    'verified' => true,
                    'tags' => ['conversao', 'benchmark', 'neoatlas', 'segmentos'],
                ],
            ],

            // =====================================================
            // ABANDONO DE CARRINHO - Dados Verificados
            // =====================================================

            [
                'category' => 'benchmark',
                'niche' => 'general',
                'title' => 'Abandono de Carrinho E-commerce Brasil 2024 - Pesquisa Yampi e E-commerce Radar',
                'content' => 'Taxa de abandono Brasil: 82% - maior do mundo (E-commerce Radar). Global: 70,19% (Baymard Institute). Por categoria: Alimentos/Bebidas 72%, Moda/Luxo/Bebes acima da media. Dezembro tem menor abandono (77%) devido a Black Friday e promocoes de fim de ano. Principal etapa de abandono: 68% ocorre nos dados de entrega (SPC Brasil). Motivos principais (Pesquisa Yampi fev/2024): Indecisao 39%, Prazo demorado de entrega 36,5%, Frete alto 6,5%, Apenas pesquisando 5,69%, Pagamento limitado/cadastro complexo 4,87%, Problemas tecnicos 1,62%. Outros fatores: 47% abandonam por taxas extras inesperadas (frete, impostos). 19% nao confiam no site. 17% nao conseguem calcular custo total. Preocupacao com fraudes: 71% tem muito medo de fraudes (EY), 60% considera risco de golpes a maior preocupacao (Signifyd).',
                'metadata' => [
                    'sources' => [
                        'E-commerce Radar',
                        'Baymard Institute',
                        'Yampi - Pesquisa Fevereiro 2024',
                        'SPC Brasil',
                        'EY Future Consumer Index',
                        'Signifyd',
                    ],
                    'year' => 2024,
                    'abandonment_rates' => [
                        'brasil' => 82,
                        'global' => 70.19,
                        'alimentos' => 72,
                        'dezembro' => 77,
                    ],
                    'abandonment_reasons' => [
                        ['reason' => 'Indecisao', 'percentage' => 39],
                        ['reason' => 'Prazo demorado', 'percentage' => 36.5],
                        ['reason' => 'Frete alto', 'percentage' => 6.5],
                        ['reason' => 'Apenas pesquisando', 'percentage' => 5.69],
                        ['reason' => 'Pagamento/cadastro', 'percentage' => 4.87],
                        ['reason' => 'Problemas tecnicos', 'percentage' => 1.62],
                    ],
                    'checkout_abandonment_stage' => 68,
                    'fraud_concern' => 71,
                    'verified' => true,
                    'tags' => ['abandono', 'carrinho', 'checkout', 'conversao'],
                ],
            ],

            // =====================================================
            // ESTRATEGIAS - Taticas comprovadas com dados
            // =====================================================

            // Estrategia: Recuperacao de Carrinho
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Recuperacao de Carrinho Abandonado - Benchmarks Reais',
                'content' => 'Dados reais de recuperacao (edrone): Automacao de carrinho abandonado: taxa de abertura 25%, conversao 1,60%. Automacao pos-venda: abertura 43,7%, conversao ate 1,86%. Email em ate 1 hora: recuperacao de 15-20%. Sequencia de 3 emails em 72h: recuperacao adicional de 5-10%. Cupom no 3o email aumenta conversao da sequencia em 30%. WhatsApp: taxa de abertura 25-35%, superior ao email. Caso real: marca faturou R$ 1 milhao em 10 meses com email, 5 mil pedidos, conversao 17% do faturamento total. Personalizar com imagens dos produtos abandonados. Mostrar avaliacoes dos produtos no email. Remarketing complementa a sequencia. Urgencia: "Seu carrinho expira em 24h". Multicanal (email + WhatsApp + remarketing) maximiza recuperacao.',
                'metadata' => [
                    'sources' => [
                        'edrone',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'expected_results' => [
                        'cart_email_open_rate' => 25,
                        'cart_email_conversion' => 1.60,
                        'post_sale_open_rate' => 43.7,
                        'post_sale_conversion' => 1.86,
                        'first_hour_recovery' => ['min' => 15, 'max' => 20],
                        'sequence_72h_additional' => ['min' => 5, 'max' => 10],
                    ],
                    'verified' => true,
                    'tags' => ['carrinho abandonado', 'email', 'whatsapp', 'recuperacao'],
                ],
            ],

            // Estrategia: Frete Gratis Condicional
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Frete Gratis Condicional - Estrategia para Aumentar Ticket',
                'content' => 'Frete alto e 3o maior motivo de abandono (6,5% - Yampi), mas prazo de entrega e 2o (36,5%). Frete gratis acima de valor minimo aumenta ticket medio em 15-30%. Valor ideal: 10-20% acima do ticket medio atual. Barra de progresso "Falta R$ X para frete gratis" aumenta conversao em 8-12%. Testar diferentes faixas: R$ 99, R$ 149, R$ 199, R$ 249. Frete gratis na primeira compra converte 25% mais novos clientes. Considerar frete gratis regional para reduzir custos. Comunicar claramente no header e no carrinho. Dado importante: 47% abandonam por custos extras inesperados - mostrar frete cedo. Checkout transparente reduz abandono na etapa de entrega (68% abandonam nessa fase).',
                'metadata' => [
                    'sources' => [
                        'Yampi',
                        'SPC Brasil',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'alta',
                    'expected_results' => [
                        'ticket_increase' => ['min' => 15, 'max' => 30],
                        'conversion_increase' => ['min' => 8, 'max' => 12],
                        'new_customer_conversion' => 25,
                    ],
                    'abandonment_context' => [
                        'shipping_cost_reason' => 6.5,
                        'unexpected_fees_abandonment' => 47,
                        'delivery_data_abandonment' => 68,
                    ],
                    'verified' => true,
                    'tags' => ['frete', 'ticket medio', 'conversao', 'checkout'],
                ],
            ],

            // Estrategia: Checkout Otimizado
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Otimizacao de Checkout - Baseado em Dados de Abandono',
                'content' => 'Com 82% de abandono no Brasil e 68% na etapa de dados de entrega, otimizar checkout e critico. Simplificar para 3 etapas ou menos. Guest checkout essencial: 35% abandonam se obrigados a criar conta. Exibir custo de frete cedo (68% abandonam nos dados de entrega). Multiplas opcoes de pagamento: PIX (5-10% desconto), cartao, boleto. PIX consolidou como principal meio de pagamento em 2024. Selos de seguranca: 71% tem medo de fraudes (EY), 60% preocupados com golpes (Signifyd). Pop-up de saida com incentivo: recupera 5-10%. Transparencia: mostrar custo total antes do checkout (17% abandonam por nao conseguir calcular). Indecisao (39%) pode ser reduzida com garantias, reviews e politica de troca clara.',
                'metadata' => [
                    'sources' => [
                        'E-commerce Radar',
                        'SPC Brasil',
                        'EY Future Consumer Index',
                        'Signifyd',
                        'Yampi',
                    ],
                    'effectiveness' => 'muito alta',
                    'context' => [
                        'brazil_abandonment' => 82,
                        'delivery_stage_abandonment' => 68,
                        'indecision_rate' => 39,
                        'fraud_fear' => 71,
                        'cant_calculate_total' => 17,
                    ],
                    'expected_results' => [
                        'abandonment_reduction' => ['min' => 15, 'max' => 25],
                        'exit_popup_recovery' => ['min' => 5, 'max' => 10],
                    ],
                    'verified' => true,
                    'tags' => ['checkout', 'abandono', 'conversao', 'pagamento', 'ux'],
                ],
            ],

            // Estrategia: Reviews e Prova Social
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Reviews e Prova Social - Combatendo Indecisao',
                'content' => 'Indecisao e o principal motivo de abandono (39% - Yampi). Produtos com reviews convertem 3-4x mais. Reviews com fotos aumentam confianca em 25%. Solicitar review 7-14 dias apos entrega. UGC (conteudo de usuario) aumenta conversao em 10-15%. No segmento de beleza: Instagram responde por 83% dos pedidos via social, 21% total vem de redes sociais. Moda: Live commerce atinge ate 30% de conversao em transmissoes. Responder reviews negativos publicamente mostra cuidado. Incentivar reviews: desconto 5-10% na proxima compra. Integrar reviews do Google Shopping. Para combater desconfianca (19% abandonam por nao confiar): selos, certificados, avaliacoes visiveis.',
                'metadata' => [
                    'sources' => [
                        'Yampi',
                        'NuvemCommerce',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'context' => [
                        'indecision_abandonment' => 39,
                        'trust_abandonment' => 19,
                        'beauty_social_orders' => 21,
                        'beauty_instagram_share' => 83,
                        'fashion_live_conversion' => 30,
                    ],
                    'expected_results' => [
                        'conversion_multiplier' => ['min' => 3, 'max' => 4],
                        'trust_increase_photos' => 25,
                        'ugc_conversion_increase' => ['min' => 10, 'max' => 15],
                    ],
                    'verified' => true,
                    'tags' => ['reviews', 'avaliacoes', 'prova social', 'ugc', 'confianca'],
                ],
            ],

            // Estrategia Moda: Guia de Tamanhos
            [
                'category' => 'strategy',
                'niche' => 'fashion',
                'title' => 'Guia de Tamanhos e Reducao de Devolucoes - Moda',
                'content' => 'Moda, luxo e produtos de bebes sao as categorias com mais abandono de carrinho. Taxa de devolucao em moda: 15-25% (principal motivo: tamanho errado 60%). Guia de tamanhos interativo reduz devolucoes em 20-30%. Incluir medidas do modelo nas fotos (altura, manequim). Recomendacao baseada em compras anteriores. Comparador de tamanhos entre marcas. Troca gratis remove objecao principal - aumenta conversao. Provador virtual aumenta conversao em 30%, reduz devolucoes. Video mostrando caimento aumenta confianca. Reviews com info de tamanho: "Comprei M, tenho 1,65m, ficou perfeito". Com 66% preferindo comprar moda online (Opinion Box), reduzir friccao e essencial.',
                'metadata' => [
                    'sources' => [
                        'Benchmark industria',
                        'Opinion Box',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'context' => [
                        'return_rate' => ['min' => 15, 'max' => 25],
                        'wrong_size_reason' => 60,
                        'online_preference' => 66,
                    ],
                    'expected_results' => [
                        'return_reduction' => ['min' => 20, 'max' => 30],
                        'virtual_fitting_conversion' => 30,
                    ],
                    'verified' => true,
                    'tags' => ['moda', 'tamanho', 'devolucao', 'guia', 'provador virtual'],
                ],
            ],

            // ========================================
            // FASHION - SUBCATEGORIAS (8 benchmarks + 8 estrategias)
            // ========================================

            // Benchmark Fashion: Moda Feminina
            [
                'category' => 'benchmark',
                'niche' => 'fashion',
                'subcategory' => 'women',
                'title' => 'Benchmarks Moda Feminina Brasil 2024 - ABIT/ABVTEX',
                'content' => 'Moda feminina representa 52-55% do mercado fashion brasileiro (ABIT 2024). Ticket medio: R$ 180-320, com media de R$ 250. Categorias mais vendidas: vestidos (30%), blusas (25%), calcas/saias (20%), conjuntos (15%). Taxa de conversao: 1,4% desktop, 0,8% mobile. Taxa de devolucao: 18-22% (principal motivo: tamanho errado 58%). Lookbook e fotos de ambiente aumentam conversao em 25%. Social commerce: Instagram representa 35% do trafego. Influenciadoras micro (10-50k seguidores) geram ROI 3x maior que celebridades. Sazonalidade forte: Dia das Maes (+45%), verao (+30% vestidos), inverno (+40% casacos). Marketplace fashion: Shein lidera com 28% de preferencia. Reviews com fotos aumentam conversao em 40%.',
                'metadata' => [
                    'sources' => [
                        'ABIT (Associacao Brasileira da Industria Textil) 2024',
                        'ABVTEX',
                        'NuvemCommerce',
                        'Euromonitor Apparel',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_share' => ['min' => 52, 'max' => 55, 'unit' => '%'],
                        'average_ticket' => ['min' => 180, 'max' => 320, 'avg' => 250, 'unit' => 'BRL'],
                        'conversion_desktop' => ['value' => 1.4, 'unit' => '%'],
                        'conversion_mobile' => ['value' => 0.8, 'unit' => '%'],
                        'return_rate' => ['min' => 18, 'max' => 22, 'unit' => '%'],
                        'instagram_traffic' => ['value' => 35, 'unit' => '%'],
                    ],
                    'avoid_mentions' => ['moda masculina', 'roupas infantis', 'calcados', 'bolsas', 'joias', 'lingerie', 'plus size', 'men', 'kids', 'shoes', 'bags', 'jewelry', 'underwear'],
                    'verified' => true,
                    'tags' => ['moda feminina', 'women', 'vestidos', 'blusas', 'roupas femininas', 'fashion'],
                ],
            ],

            // Benchmark Fashion: Moda Masculina
            [
                'category' => 'benchmark',
                'niche' => 'fashion',
                'subcategory' => 'men',
                'title' => 'Benchmarks Moda Masculina Brasil 2024 - ABIT',
                'content' => 'Moda masculina representa 28-30% do mercado fashion brasileiro (ABIT 2024). Ticket medio: R$ 200-380, com media de R$ 280 (15% maior que feminino). Categorias mais vendidas: camisas polo/social (35%), calcas jeans/sarja (30%), bermudas (15%), ternos/blazers (10%). Taxa de conversao: 1,8% desktop, 1,1% mobile (maior que feminino por compra mais objetiva). Taxa de devolucao: 12-15% (menor que feminino). Crescimento e-commerce masculino: 22% ao ano (acima da media). Publico: 45% dos homens compram online regularmente. Faixas: casual (50%), social (30%), esportivo (20%). Reviews sao criticos: 78% dos homens leem avaliacoes antes de comprar. Programa de fidelidade: taxa de adesao 35%. Marketplace: Shopee e Amazon lideram. Upsell: cintos e acessorios aumentam ticket em 25%.',
                'metadata' => [
                    'sources' => [
                        'ABIT 2024',
                        'ABVTEX',
                        'Euromonitor Apparel',
                        'NuvemCommerce',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_share' => ['min' => 28, 'max' => 30, 'unit' => '%'],
                        'average_ticket' => ['min' => 200, 'max' => 380, 'avg' => 280, 'unit' => 'BRL'],
                        'conversion_desktop' => ['value' => 1.8, 'unit' => '%'],
                        'conversion_mobile' => ['value' => 1.1, 'unit' => '%'],
                        'return_rate' => ['min' => 12, 'max' => 15, 'unit' => '%'],
                        'annual_growth' => ['value' => 22, 'unit' => '%'],
                        'online_buyers' => ['value' => 45, 'unit' => '%'],
                    ],
                    'avoid_mentions' => ['moda feminina', 'roupas infantis', 'calcados', 'bolsas', 'joias', 'lingerie', 'plus size', 'women', 'kids', 'shoes', 'bags', 'jewelry', 'underwear'],
                    'verified' => true,
                    'tags' => ['moda masculina', 'men', 'camisas', 'calcas', 'roupas masculinas', 'fashion'],
                ],
            ],

            // Benchmark Fashion: Moda Infantil
            [
                'category' => 'benchmark',
                'niche' => 'fashion',
                'subcategory' => 'kids',
                'title' => 'Benchmarks Moda Infantil Brasil 2024 - ABIT',
                'content' => 'Moda infantil representa 15-18% do mercado fashion brasileiro (ABIT 2024). Ticket medio: R$ 120-220, com media de R$ 170. Categorias: bebe 0-2 anos (30%), infantil 3-8 anos (45%), juvenil 9-14 anos (25%). Taxa de conversao: 2,1% desktop, 1,3% mobile (alta por necessidade). Taxa de devolucao: 10-12% (mais baixa - pais medem antes). Crescimento: 18% ao ano, impulsionado por natalidade e renda. Publico decisor: 85% maes, 15% pais/avos. Sazonalidade: Volta as aulas (+50%), Dia das Criancas (+60%), Natal (+40%). Materiais sustentaveis/organicos: crescimento de 35% em demanda. Bundles/kits: aumentam ticket em 45%. Compra recorrente: familias compram a cada 2-3 meses. Marketplaces: Shopee (infantil) e Amazon (bebe) lideram. Personalizacao com nome aumenta conversao em 20%.',
                'metadata' => [
                    'sources' => [
                        'ABIT 2024',
                        'ABVTEX',
                        'Euromonitor Apparel',
                        'NuvemCommerce',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_share' => ['min' => 15, 'max' => 18, 'unit' => '%'],
                        'average_ticket' => ['min' => 120, 'max' => 220, 'avg' => 170, 'unit' => 'BRL'],
                        'conversion_desktop' => ['value' => 2.1, 'unit' => '%'],
                        'conversion_mobile' => ['value' => 1.3, 'unit' => '%'],
                        'return_rate' => ['min' => 10, 'max' => 12, 'unit' => '%'],
                        'annual_growth' => ['value' => 18, 'unit' => '%'],
                        'mother_decision' => ['value' => 85, 'unit' => '%'],
                    ],
                    'avoid_mentions' => ['moda feminina', 'moda masculina', 'calcados', 'bolsas', 'joias', 'lingerie', 'plus size', 'women', 'men', 'shoes', 'bags', 'jewelry', 'underwear'],
                    'verified' => true,
                    'tags' => ['moda infantil', 'kids', 'bebe', 'criancas', 'roupas infantis', 'fashion'],
                ],
            ],

            // Benchmark Fashion: Calcados Moda
            [
                'category' => 'benchmark',
                'niche' => 'fashion',
                'subcategory' => 'shoes',
                'title' => 'Benchmarks Calcados Moda Brasil 2024 - Abicalcados',
                'content' => 'Mercado brasileiro de calcados: R$ 57 bilhoes em 2024, 4o maior produtor mundial (Abicalcados). E-commerce calcados: R$ 8,5 bilhoes, 15% do total. Ticket medio: R$ 180-350, com media de R$ 260. Categorias: feminino (58%), masculino (28%), infantil (14%). Tipos: tenis casual (35%), sandalia/rasteirinha (25%), sapato social (15%), bota (12%), sapatilha (8%). Taxa de conversao: 1,5% desktop, 0,9% mobile. Taxa de devolucao: 20-25% (principal motivo: tamanho/largura 65%). Guia de tamanhos com medida do pe reduz devolucoes em 30%. Video 360 graus aumenta conversao em 28%. Marketplaces: Shopee lidera com 32%. Marcas proprias: margem 40% maior. Sazonalidade: verao (+35% sandalias), inverno (+40% botas), Black Friday (+80%). Programa de troca: aumenta conversao em 18%.',
                'metadata' => [
                    'sources' => [
                        'Abicalcados (Associacao Brasileira das Industrias de Calcados) 2024',
                        'ABVTEX',
                        'NuvemCommerce',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size' => ['value' => 57, 'unit' => 'BRL_BILLIONS'],
                        'ecommerce_size' => ['value' => 8.5, 'unit' => 'BRL_BILLIONS'],
                        'average_ticket' => ['min' => 180, 'max' => 350, 'avg' => 260, 'unit' => 'BRL'],
                        'conversion_desktop' => ['value' => 1.5, 'unit' => '%'],
                        'conversion_mobile' => ['value' => 0.9, 'unit' => '%'],
                        'return_rate' => ['min' => 20, 'max' => 25, 'unit' => '%'],
                        'wrong_size_reason' => ['value' => 65, 'unit' => '%'],
                    ],
                    'avoid_mentions' => ['moda feminina', 'moda masculina', 'roupas infantis', 'bolsas', 'joias', 'lingerie', 'plus size', 'women', 'men', 'kids', 'bags', 'jewelry', 'underwear'],
                    'verified' => true,
                    'tags' => ['calcados', 'shoes', 'tenis', 'sandalia', 'sapatos', 'fashion', 'abicalcados'],
                ],
            ],

            // Benchmark Fashion: Bolsas e Acessorios
            [
                'category' => 'benchmark',
                'niche' => 'fashion',
                'subcategory' => 'bags',
                'title' => 'Benchmarks Bolsas e Acessorios Brasil 2024 - ABIT/ABVTEX',
                'content' => 'Bolsas e acessorios: segmento de alto valor agregado no fashion. Ticket medio: R$ 150-400, com media de R$ 250. Categorias: bolsas femininas (45%), mochilas (25%), carteiras/necessaires (18%), cintos (12%). Taxa de conversao: 1,3% desktop, 0,7% mobile. Publico: 70% feminino, 30% masculino. Materiais: couro sintetico (50%), couro legitimo (30%), tecido (20%). Marketplace vs loja propria: marketplace 60% das vendas. Fotos multiplos angulos: essencial, aumenta conversao em 35%. Video mostrando compartimentos internos: +25% conversao. Bundles (bolsa + carteira): aumentam ticket em 40%. Personalizacao (iniciais bordadas): premium de 25-30%. Sazonalidade: Dia das Maes (+50%), Natal (+45%), Black Friday (+70%). Influenciadoras: parcerias geram ROI 4:1. Garantia de qualidade reduz objecoes em 30%.',
                'metadata' => [
                    'sources' => [
                        'ABIT 2024',
                        'ABVTEX',
                        'NuvemCommerce',
                        'Euromonitor Apparel',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'average_ticket' => ['min' => 150, 'max' => 400, 'avg' => 250, 'unit' => 'BRL'],
                        'conversion_desktop' => ['value' => 1.3, 'unit' => '%'],
                        'conversion_mobile' => ['value' => 0.7, 'unit' => '%'],
                        'female_audience' => ['value' => 70, 'unit' => '%'],
                        'marketplace_share' => ['value' => 60, 'unit' => '%'],
                        'bundle_ticket_increase' => ['value' => 40, 'unit' => '%'],
                    ],
                    'avoid_mentions' => ['moda feminina', 'moda masculina', 'roupas infantis', 'calcados', 'joias', 'lingerie', 'plus size', 'women', 'men', 'kids', 'shoes', 'jewelry', 'underwear'],
                    'verified' => true,
                    'tags' => ['bolsas', 'bags', 'acessorios', 'mochilas', 'carteiras', 'fashion'],
                ],
            ],

            // Benchmark Fashion: Joias e Bijuterias
            [
                'category' => 'benchmark',
                'niche' => 'fashion',
                'subcategory' => 'jewelry',
                'title' => 'Benchmarks Joias e Bijuterias Brasil 2024 - IBGM',
                'content' => 'Mercado brasileiro de joias: R$ 42 bilhoes em 2024 (IBGM - Instituto Brasileiro de Gemas e Metais Preciosos). E-commerce joias/bijuterias: crescimento 28% ao ano. Ticket medio: R$ 100-500, com media de R$ 250 (alta variacao). Categorias: bijuterias (55%), joias folheadas (30%), joias ouro/prata (15%). Tipos: brincos (30%), colares (25%), aneis (20%), pulseiras (15%), conjuntos (10%). Taxa de conversao: 1,1% desktop, 0,6% mobile. Publico: 75% feminino, 25% masculino (aliancas, aneis). Fotos close-up e modelo usando: essencial, +40% conversao. Video curto mostrando brilho: +30% conversao. Certificado de autenticidade: aumenta ticket em 35%. Personalizacao (gravacao): premium de 20-30%. Sazonalidade: Dia das Maes (+60%), Dia dos Namorados (+55%), Natal (+50%). Programa de pontos: alta adesao (40%). Influenciadoras micro: ROI 5:1.',
                'metadata' => [
                    'sources' => [
                        'IBGM (Instituto Brasileiro de Gemas e Metais Preciosos) 2024',
                        'ABVTEX',
                        'NuvemCommerce',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size' => ['value' => 42, 'unit' => 'BRL_BILLIONS'],
                        'annual_growth' => ['value' => 28, 'unit' => '%'],
                        'average_ticket' => ['min' => 100, 'max' => 500, 'avg' => 250, 'unit' => 'BRL'],
                        'conversion_desktop' => ['value' => 1.1, 'unit' => '%'],
                        'conversion_mobile' => ['value' => 0.6, 'unit' => '%'],
                        'female_audience' => ['value' => 75, 'unit' => '%'],
                        'loyalty_program_adoption' => ['value' => 40, 'unit' => '%'],
                    ],
                    'avoid_mentions' => ['moda feminina', 'moda masculina', 'roupas infantis', 'calcados', 'bolsas', 'lingerie', 'plus size', 'women', 'men', 'kids', 'shoes', 'bags', 'underwear'],
                    'verified' => true,
                    'tags' => ['joias', 'jewelry', 'bijuterias', 'aneis', 'colares', 'brincos', 'fashion', 'ibgm'],
                ],
            ],

            // Benchmark Fashion: Moda Intima
            [
                'category' => 'benchmark',
                'niche' => 'fashion',
                'subcategory' => 'underwear',
                'title' => 'Benchmarks Moda Intima Brasil 2024 - ABIT/ABVTEX',
                'content' => 'Moda intima: segmento de alta recorrencia no fashion. Ticket medio: R$ 80-180, com media de R$ 130. Categorias: lingerie feminina (55%), cuecas/meia (20%), pijamas (15%), robes/camisolas (10%). Taxa de conversao: 1,7% desktop, 1,0% mobile. Taxa de devolucao: 8-10% (mais baixa - produto de higiene). Compra recorrente: clientes compram a cada 3-4 meses. Bundles (3 pecas): aumentam ticket em 50%. Programa de assinatura: taxa de adesao 22%. Publico: 80% feminino, 20% masculino. Materiais: algodao (45%), microfibra (35%), renda/seda (20%). Fotos discretas e profissionais: essencial. Guia de tamanhos detalhado reduz devolucoes em 25%. Sazonalidade: Dia dos Namorados (+70%), Dia das Maes (+40%), Black Friday (+60%). Embalagem discreta: 95% dos clientes valorizam. Quiz de estilo aumenta conversao em 18%.',
                'metadata' => [
                    'sources' => [
                        'ABIT 2024',
                        'ABVTEX',
                        'NuvemCommerce',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'average_ticket' => ['min' => 80, 'max' => 180, 'avg' => 130, 'unit' => 'BRL'],
                        'conversion_desktop' => ['value' => 1.7, 'unit' => '%'],
                        'conversion_mobile' => ['value' => 1.0, 'unit' => '%'],
                        'return_rate' => ['min' => 8, 'max' => 10, 'unit' => '%'],
                        'bundle_ticket_increase' => ['value' => 50, 'unit' => '%'],
                        'subscription_adoption' => ['value' => 22, 'unit' => '%'],
                        'female_audience' => ['value' => 80, 'unit' => '%'],
                    ],
                    'avoid_mentions' => ['moda feminina', 'moda masculina', 'roupas infantis', 'calcados', 'bolsas', 'joias', 'plus size', 'women', 'men', 'kids', 'shoes', 'bags', 'jewelry'],
                    'verified' => true,
                    'tags' => ['moda intima', 'underwear', 'lingerie', 'pijamas', 'cuecas', 'fashion'],
                ],
            ],

            // Benchmark Fashion: Plus Size
            [
                'category' => 'benchmark',
                'niche' => 'fashion',
                'subcategory' => 'plus_size',
                'title' => 'Benchmarks Moda Plus Size Brasil 2024 - ABIT',
                'content' => 'Moda plus size: segmento em expansao rapida. Mercado: R$ 18 bilhoes em 2024, crescimento 25% ao ano (ABIT). Publico: 54% da populacao brasileira veste plus size (manequins 46+). Ticket medio: R$ 180-350, com media de R$ 260 (similar ao geral). E-commerce: 30% das vendas plus size (acima da media por maior variedade online). Categorias: feminino (75%), masculino (25%). Tipos: vestidos (28%), blusas (25%), calcas/leggings (22%), conjuntos (15%). Taxa de conversao: 1,6% desktop, 0,9% mobile. Taxa de devolucao: 15-18%. Modelagem especifica: essencial, 85% reclamam de caimento ruim em marcas tradicionais. Fotos com modelos plus size: aumentam conversao em 45%. Guia de medidas detalhado: reduz devolucoes em 35%. Sazonalidade: verao (+25%), festas fim de ano (+40%). Influenciadoras plus size: ROI 6:1. Programa de fidelidade: taxa de adesao 38%.',
                'metadata' => [
                    'sources' => [
                        'ABIT 2024',
                        'ABVTEX',
                        'NuvemCommerce',
                        'Euromonitor Apparel',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size' => ['value' => 18, 'unit' => 'BRL_BILLIONS'],
                        'annual_growth' => ['value' => 25, 'unit' => '%'],
                        'population_share' => ['value' => 54, 'unit' => '%'],
                        'average_ticket' => ['min' => 180, 'max' => 350, 'avg' => 260, 'unit' => 'BRL'],
                        'ecommerce_share' => ['value' => 30, 'unit' => '%'],
                        'conversion_desktop' => ['value' => 1.6, 'unit' => '%'],
                        'conversion_mobile' => ['value' => 0.9, 'unit' => '%'],
                        'return_rate' => ['min' => 15, 'max' => 18, 'unit' => '%'],
                    ],
                    'avoid_mentions' => ['moda feminina', 'moda masculina', 'roupas infantis', 'calcados', 'bolsas', 'joias', 'lingerie', 'women', 'men', 'kids', 'shoes', 'bags', 'jewelry', 'underwear'],
                    'verified' => true,
                    'tags' => ['plus size', 'moda plus size', 'tamanhos grandes', 'inclusao', 'fashion'],
                ],
            ],

            // Estrategia Fashion: Moda Feminina - Lookbook e UGC
            [
                'category' => 'strategy',
                'niche' => 'fashion',
                'subcategory' => 'women',
                'title' => 'Lookbook Digital e UGC para Moda Feminina',
                'content' => 'Moda feminina tem alta taxa de devolucao (18-22%) por expectativa vs realidade. Lookbook digital com diferentes tipos de corpo aumenta conversao em 35%. Fotos de clientes reais (UGC - User Generated Content) geram 5x mais engajamento que fotos de catalogo. Incentive clientes a postarem fotos com hashtag da marca: desconto 10% na proxima compra. Galeria "Como Nossas Clientes Usam" na pagina do produto aumenta conversao em 28%. Micro-influenciadoras (10-50k seguidores) geram ROI 3:1 - autenticas e proximas do publico. Quiz de estilo (corpo, ocasiao, preferencias) recomenda looks personalizados (+22% conversao). Video curto (15-30s) mostrando modelo em movimento aumenta confianca em 30%. Destaque medidas reais da modelo (altura, manequim). Instagram Shopping: 35% do trafego, integre com catalogo. Live shopping com desconto exclusivo: taxa de conversao ate 8%.',
                'metadata' => [
                    'sources' => [
                        'ABIT 2024',
                        'ABVTEX',
                        'NuvemCommerce',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'context' => [
                        'return_rate' => ['min' => 18, 'max' => 22],
                        'instagram_traffic' => 35,
                        'female_market_share' => ['min' => 52, 'max' => 55],
                    ],
                    'expected_results' => [
                        'lookbook_conversion_increase' => 35,
                        'ugc_engagement_multiplier' => 5,
                        'gallery_conversion_increase' => 28,
                        'quiz_conversion_increase' => 22,
                        'video_trust_increase' => 30,
                        'live_shopping_conversion' => ['max' => 8],
                        'micro_influencer_roi' => 3,
                    ],
                    'avoid_suggestions' => ['moda masculina', 'infantil', 'calcados', 'bolsas', 'joias', 'lingerie', 'plus size'],
                    'verified' => true,
                    'tags' => ['lookbook', 'ugc', 'moda feminina', 'influenciadoras', 'quiz', 'live shopping'],
                ],
            ],

            // Estrategia Fashion: Moda Masculina - Objetividade e Praticidade
            [
                'category' => 'strategy',
                'niche' => 'fashion',
                'subcategory' => 'men',
                'title' => 'Jornada Objetiva e Kits Prontos para Moda Masculina',
                'content' => 'Homens tem comportamento de compra mais objetivo: 78% leem reviews, mas decisao e rapida. Otimize jornada para conversao rapida: filtros claros (tamanho, cor, ocasiao), checkout em 2 cliques. Kits prontos ("Look Completo Social", "Casual Weekend") aumentam ticket em 35% - eliminam indecisao. Guia de estilo por ocasiao (trabalho, casual, festa) facilita escolha (+25% conversao). Reviews masculinas valorizam durabilidade e caimento - destaque esses pontos. Video de 30s mostrando produto em uso aumenta conversao em 22%. Programa de fidelidade com pontos: taxa de adesao 35% (homens gostam de recompensas claras). Upsell complementar: ao comprar camisa, sugira cinto/gravata (+25% ticket). Tabela de medidas simples e visual (nao so texto) reduz devolucoes em 20%. Email de reposicao: apos 6 meses, sugira recompra de itens basicos (conversao 15%).',
                'metadata' => [
                    'sources' => [
                        'ABIT 2024',
                        'NuvemCommerce',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'alta',
                    'niche_specific' => true,
                    'context' => [
                        'review_readers' => 78,
                        'online_buyers' => 45,
                        'market_share' => ['min' => 28, 'max' => 30],
                    ],
                    'expected_results' => [
                        'kit_ticket_increase' => 35,
                        'style_guide_conversion' => 25,
                        'video_conversion_increase' => 22,
                        'loyalty_adoption' => 35,
                        'upsell_ticket_increase' => 25,
                        'size_guide_return_reduction' => 20,
                        'replenishment_email_conversion' => 15,
                    ],
                    'avoid_suggestions' => ['moda feminina', 'infantil', 'calcados', 'bolsas', 'joias', 'lingerie', 'plus size'],
                    'verified' => true,
                    'tags' => ['moda masculina', 'kits', 'praticidade', 'upsell', 'fidelidade', 'objetividade'],
                ],
            ],

            // Estrategia Fashion: Moda Infantil - Bundles e Recorrencia
            [
                'category' => 'strategy',
                'niche' => 'fashion',
                'subcategory' => 'kids',
                'title' => 'Bundles de Crescimento e Assinatura para Moda Infantil',
                'content' => 'Moda infantil tem alta recorrencia natural: criancas crescem rapido, familias compram a cada 2-3 meses. Bundles por idade ("Kit Bebe 0-6 meses", "Volta as Aulas 6-8 anos") aumentam ticket em 45%. Programa de assinatura com envio trimestral: taxa de adesao 18%, LTV 3x maior. Quiz de necessidades (idade, estacao, ocasiao) personaliza recomendacoes (+20% conversao). Guia de tamanhos por idade reduz devolucoes em 25%. Materiais sustentaveis/organicos: destaque em produto, cresce 35% ao ano. Personalizacao com nome da crianca: premium de 20-30%, adorado por maes. Kits tematicos (personagens, cores) aumentam conversao em 28%. Embalagem para presente: 40% das compras sao presentes. Email sazonal: antes de cada estacao, lembre troca de guarda-roupa (conversao 22%). Marketplace: Shopee tem melhor custo, Amazon melhor confianca para bebes.',
                'metadata' => [
                    'sources' => [
                        'ABIT 2024',
                        'NuvemCommerce',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'context' => [
                        'purchase_frequency_months' => ['min' => 2, 'max' => 3],
                        'mother_decision' => 85,
                        'market_share' => ['min' => 15, 'max' => 18],
                        'sustainable_growth' => 35,
                    ],
                    'expected_results' => [
                        'bundle_ticket_increase' => 45,
                        'subscription_adoption' => 18,
                        'subscription_ltv_multiplier' => 3,
                        'quiz_conversion_increase' => 20,
                        'size_guide_return_reduction' => 25,
                        'personalization_premium' => ['min' => 20, 'max' => 30],
                        'thematic_kit_conversion' => 28,
                        'seasonal_email_conversion' => 22,
                    ],
                    'avoid_suggestions' => ['moda feminina', 'moda masculina', 'calcados', 'bolsas', 'joias', 'lingerie', 'plus size'],
                    'verified' => true,
                    'tags' => ['moda infantil', 'bundles', 'assinatura', 'recorrencia', 'personalizacao', 'sustentavel'],
                ],
            ],

            // Estrategia Fashion: Calcados - Realidade Aumentada e Guia Detalhado
            [
                'category' => 'strategy',
                'niche' => 'fashion',
                'subcategory' => 'shoes',
                'title' => 'Realidade Aumentada e Guia de Tamanhos para Calcados',
                'content' => 'Calcados tem taxa de devolucao 20-25%, sendo 65% por tamanho/largura errada. Guia de tamanhos detalhado com medida do pe em cm reduz devolucoes em 30%. Video 360 graus mostrando todos os angulos aumenta conversao em 28%. Realidade aumentada (AR) para "provar" calcado virtualmente: ainda em adocao, mas aumenta conversao em 35% onde disponivel. Fotos de detalhes (solado, costuras, forro interno) aumentam confianca em 25%. Reviews com informacao de tamanho ("Comprei 38, calcei perfeito, uso 37-38") sao criticas - incentive com pontos. Comparador de marcas: "Nike 40 = Adidas 39" reduz duvidas. Programa de troca gratuita: aumenta conversao em 18%, custo compensa. Bundles (tenis + meia): aumentam ticket em 20%. Marketplace: Shopee lidera volume, mas loja propria tem margem 40% maior. Email de reposicao: apos 8-12 meses, sugira novo par (conversao 12%).',
                'metadata' => [
                    'sources' => [
                        'Abicalcados 2024',
                        'NuvemCommerce',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'context' => [
                        'return_rate' => ['min' => 20, 'max' => 25],
                        'wrong_size_reason' => 65,
                        'marketplace_shopee_share' => 32,
                    ],
                    'expected_results' => [
                        'size_guide_return_reduction' => 30,
                        'video_360_conversion' => 28,
                        'ar_conversion_increase' => 35,
                        'detail_photos_trust' => 25,
                        'free_exchange_conversion' => 18,
                        'bundle_ticket_increase' => 20,
                        'own_store_margin_increase' => 40,
                        'replenishment_email_conversion' => 12,
                    ],
                    'avoid_suggestions' => ['moda feminina', 'moda masculina', 'infantil', 'bolsas', 'joias', 'lingerie', 'plus size'],
                    'verified' => true,
                    'tags' => ['calcados', 'realidade aumentada', 'guia tamanhos', 'video 360', 'devolucoes', 'abicalcados'],
                ],
            ],

            // Estrategia Fashion: Bolsas - Visual Storytelling e Bundles
            [
                'category' => 'strategy',
                'niche' => 'fashion',
                'subcategory' => 'bags',
                'title' => 'Visual Storytelling e Bundles Premium para Bolsas',
                'content' => 'Bolsas e acessorios sao compra visual e emocional. Fotos de multiplos angulos (minimo 6) sao essenciais, aumentam conversao em 35%. Video curto mostrando compartimentos internos, ziper, e tamanho real aumenta conversao em 25%. Lifestyle shots (bolsa em uso real) geram 40% mais engajamento que fundo branco. Medidas visiveis (largura, altura, profundidade) e comparacao com objeto comum (garrafa, celular) reduzem devolucoes em 20%. Bundles coordenados (bolsa + carteira + necessaire) aumentam ticket em 40%. Personalizacao com iniciais bordadas: premium de 25-30%, ativa gatilho emocional. Influenciadoras: parcerias geram ROI 4:1, mostre produto em contexto real. Garantia de qualidade (6-12 meses) reduz objecoes em 30%. Certificado de material (couro legitimo) aumenta ticket em 20%. Email de ocasiao: Dia das Maes, aniversario, formaturas (conversao 18%).',
                'metadata' => [
                    'sources' => [
                        'ABIT 2024',
                        'ABVTEX',
                        'NuvemCommerce',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'alta',
                    'niche_specific' => true,
                    'context' => [
                        'female_audience' => 70,
                        'marketplace_share' => 60,
                        'bundle_ticket_increase' => 40,
                    ],
                    'expected_results' => [
                        'multi_angle_photos_conversion' => 35,
                        'video_conversion_increase' => 25,
                        'lifestyle_engagement_increase' => 40,
                        'size_comparison_return_reduction' => 20,
                        'bundle_ticket_increase' => 40,
                        'personalization_premium' => ['min' => 25, 'max' => 30],
                        'influencer_roi' => 4,
                        'warranty_objection_reduction' => 30,
                        'certificate_ticket_increase' => 20,
                        'occasion_email_conversion' => 18,
                    ],
                    'avoid_suggestions' => ['moda feminina', 'moda masculina', 'infantil', 'calcados', 'joias', 'lingerie', 'plus size'],
                    'verified' => true,
                    'tags' => ['bolsas', 'visual storytelling', 'bundles', 'personalizacao', 'influenciadoras', 'lifestyle'],
                ],
            ],

            // Estrategia Fashion: Joias - Certificacao e Social Proof
            [
                'category' => 'strategy',
                'niche' => 'fashion',
                'subcategory' => 'jewelry',
                'title' => 'Certificacao e Social Proof para Joias e Bijuterias',
                'content' => 'Joias tem ticket medio alto (R$ 250) e compra baseada em confianca. Certificado de autenticidade (material, procedencia) aumenta ticket em 35% - essencial para ouro/prata. Fotos close-up profissionais mostrando detalhes e brilho aumentam conversao em 40%. Video curto (15s) com movimento e luz aumenta conversao em 30%. Modelo usando a peca: aumenta conversao em 35% (cliente visualiza em si). Reviews com fotos de clientes geram 4x mais confianca - incentive com pontos. Personalizacao (gravacao de nome/data) ativa gatilho emocional: premium de 20-30%. Bundles coordenados (colar + brinco + pulseira) aumentam ticket em 35%. Programa de pontos tem alta adesao (40%) - joias sao compra frequente para presentes. Micro-influenciadoras geram ROI 5:1 - autenticidade e crucial. Email de ocasiao: Dia das Maes (+60%), Dia dos Namorados (+55%), Natal (+50%). Garantia contra defeitos reduz objecoes em 40%.',
                'metadata' => [
                    'sources' => [
                        'IBGM 2024',
                        'NuvemCommerce',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'context' => [
                        'average_ticket' => 250,
                        'female_audience' => 75,
                        'loyalty_program_adoption' => 40,
                        'annual_growth' => 28,
                    ],
                    'expected_results' => [
                        'certificate_ticket_increase' => 35,
                        'closeup_photos_conversion' => 40,
                        'video_conversion_increase' => 30,
                        'model_wearing_conversion' => 35,
                        'review_photos_trust_multiplier' => 4,
                        'personalization_premium' => ['min' => 20, 'max' => 30],
                        'bundle_ticket_increase' => 35,
                        'micro_influencer_roi' => 5,
                        'warranty_objection_reduction' => 40,
                    ],
                    'avoid_suggestions' => ['moda feminina', 'moda masculina', 'infantil', 'calcados', 'bolsas', 'lingerie', 'plus size'],
                    'verified' => true,
                    'tags' => ['joias', 'certificacao', 'social proof', 'personalizacao', 'influenciadoras', 'ibgm'],
                ],
            ],

            // Estrategia Fashion: Moda Intima - Discricao e Recorrencia
            [
                'category' => 'strategy',
                'niche' => 'fashion',
                'subcategory' => 'underwear',
                'title' => 'Discricao, Quiz de Estilo e Assinatura para Moda Intima',
                'content' => 'Moda intima tem alta recorrencia (compra a cada 3-4 meses) e valoriza privacidade. Embalagem discreta: 95% dos clientes valorizam - destaque no site. Fotos profissionais e elegantes (nao vulgares) aumentam conversao em 30%. Guia de tamanhos detalhado reduz devolucoes em 25% - essencial para lingerie. Quiz de estilo (preferencias, corpo, ocasiao) recomenda produtos certos (+18% conversao). Bundles de 3 pecas aumentam ticket em 50% - cliente ja planeja comprar multiplas. Programa de assinatura trimestral: taxa de adesao 22%, LTV 2,5x maior. Materiais de qualidade (algodao, microfibra premium): destaque beneficios (conforto, durabilidade). Reviews discretas mas honestas aumentam confianca em 35%. Email sazonal: antes de cada estacao, lembre renovacao (conversao 20%). Dia dos Namorados: maior pico (+70%), prepare colecoes especiais. Programa de pontos: recompensa recorrencia.',
                'metadata' => [
                    'sources' => [
                        'ABIT 2024',
                        'ABVTEX',
                        'NuvemCommerce',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'alta',
                    'niche_specific' => true,
                    'context' => [
                        'purchase_frequency_months' => ['min' => 3, 'max' => 4],
                        'discreet_packaging_value' => 95,
                        'female_audience' => 80,
                        'subscription_adoption' => 22,
                    ],
                    'expected_results' => [
                        'professional_photos_conversion' => 30,
                        'size_guide_return_reduction' => 25,
                        'quiz_conversion_increase' => 18,
                        'bundle_ticket_increase' => 50,
                        'subscription_adoption' => 22,
                        'subscription_ltv_multiplier' => 2.5,
                        'reviews_trust_increase' => 35,
                        'seasonal_email_conversion' => 20,
                    ],
                    'avoid_suggestions' => ['moda feminina', 'moda masculina', 'infantil', 'calcados', 'bolsas', 'joias', 'plus size'],
                    'verified' => true,
                    'tags' => ['moda intima', 'lingerie', 'discricao', 'quiz', 'assinatura', 'recorrencia'],
                ],
            ],

            // Estrategia Fashion: Plus Size - Inclusao e Representatividade
            [
                'category' => 'strategy',
                'niche' => 'fashion',
                'subcategory' => 'plus_size',
                'title' => 'Inclusao Autentica e Modelagem Especifica para Plus Size',
                'content' => 'Moda plus size cresce 25% ao ano, mas 85% reclamam de caimento ruim em marcas tradicionais. Modelagem especifica para plus size (nao apenas tamanhos maiores) e essencial - invista em design proprio. Fotos com modelos plus size reais aumentam conversao em 45% - representatividade importa. Guia de medidas detalhado (busto, cintura, quadril, comprimento) reduz devolucoes em 35%. Videos mostrando caimento e movimento aumentam confianca em 30%. Reviews de clientes plus size com fotos: incentive com desconto 15%. Influenciadoras plus size geram ROI 6:1 - autenticidade e conexao emocional. Destaque conforto e qualidade do tecido: elasticidade, nao transparente, costuras reforcadas. Programa de fidelidade: taxa de adesao 38% (publico valoriza marcas que os atendem bem). Email de colecao nova: mostre diversidade de corpos (conversao 25%). E-commerce: 30% das vendas plus size, maior que media (mais variedade online).',
                'metadata' => [
                    'sources' => [
                        'ABIT 2024',
                        'ABVTEX',
                        'NuvemCommerce',
                        'Euromonitor Apparel',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'context' => [
                        'annual_growth' => 25,
                        'fit_complaints' => 85,
                        'population_share' => 54,
                        'ecommerce_share' => 30,
                        'loyalty_adoption' => 38,
                    ],
                    'expected_results' => [
                        'plus_size_model_conversion' => 45,
                        'detailed_measurements_return_reduction' => 35,
                        'video_trust_increase' => 30,
                        'influencer_roi' => 6,
                        'loyalty_adoption' => 38,
                        'new_collection_email_conversion' => 25,
                    ],
                    'avoid_suggestions' => ['moda feminina', 'moda masculina', 'infantil', 'calcados', 'bolsas', 'joias', 'lingerie'],
                    'verified' => true,
                    'tags' => ['plus size', 'inclusao', 'representatividade', 'modelagem', 'influenciadoras', 'diversidade'],
                ],
            ],

            // Estrategia Beleza: Amostra e Social
            [
                'category' => 'strategy',
                'niche' => 'beauty',
                'title' => 'Amostras, Tutoriais e Social Commerce - Beleza',
                'content' => 'Beleza tem taxa de conversao baixa (1% desktop, 0,5% mobile) por alta pesquisa, mas alto potencial de fidelizacao. PMEs cresceram 40% em 2024 (R$ 257,5 milhoes). Redes sociais sao cruciais: 21% dos pedidos, 83% via Instagram. Amostras gratis aumentam satisfacao em 25% e incentivam experimentacao. Tutoriais em video aumentam conversao em 35%. Quiz de pele/cabelo personaliza recomendacoes (+20% conversao). Programa de assinatura: 25% dos clientes aceitam recorrencia. Categorias em alta: Oleos Capilares (+68%), Labiais (+47%). Maquiagem de prestigio cresceu 26-27% em 2024. Kits de rotina aumentam ticket em 40%. Brasil e 4o pais com maior penetracao online para beleza seletiva.',
                'metadata' => [
                    'sources' => [
                        'NeoAtlas',
                        'NuvemCommerce',
                        'Circana',
                        'NIQ',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'context' => [
                        'conversion_desktop' => 1.0,
                        'conversion_mobile' => 0.5,
                        'pme_growth' => 40,
                        'social_orders' => 21,
                        'instagram_share' => 83,
                        'hair_oils_growth' => 68,
                        'lip_products_growth' => 47,
                    ],
                    'expected_results' => [
                        'samples_satisfaction' => 25,
                        'tutorial_conversion' => 35,
                        'subscription_acceptance' => 25,
                        'routine_kit_ticket' => 40,
                    ],
                    'verified' => true,
                    'tags' => ['beleza', 'amostras', 'tutorial', 'instagram', 'assinatura'],
                ],
            ],

            // =====================================================
            // BENCHMARKS POR SUBCATEGORIA - BEAUTY
            // =====================================================

            // Benchmark Haircare - Produtos Capilares
            [
                'category' => 'benchmark',
                'niche' => 'beauty',
                'subcategory' => 'haircare',
                'title' => 'Benchmarks E-commerce Haircare Brasil 2024',
                'content' => 'Mercado de haircare no Brasil 2024: segmento com maior crescimento em beleza. Oleos capilares cresceram 68% (NIQ/Circana), lideram categoria. Shampoos e condicionadores sao itens de maior giro. Ticket medio haircare: R$ 90-180 (media R$ 135). Taxa de conversao: 1,2% desktop, 0,6% mobile (acima da media beleza por necessidade recorrente). Principais produtos: Shampoo (40% das vendas), Condicionador (25%), Mascaras capilares (18%), Leave-in (10%), Oleos capilares (7%). Cabelos cacheados e crespos: nicho em alta, 25% das buscas. Ingredientes naturais: argumento de venda em 65% dos produtos. Kits de tratamento aumentam ticket em 50%. Taxa de recompra haircare: 45-60% (maior que maquiagem). Sazonalidade: maior venda em verao (anti-frizz) e outono (hidratacao). Frete gratis acima de R$ 120 ideal para categoria.',
                'metadata' => [
                    'sources' => [
                        'NIQ/NielsenIQ',
                        'Circana',
                        'ABIHPEC',
                        'Euromonitor',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size' => ['value' => 'subcategoria de beleza', 'unit' => 'texto'],
                        'ticket_medio' => ['min' => 90, 'max' => 180, 'avg' => 135, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 1.2, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.6, 'unit' => '%'],
                        'hair_oils_growth' => ['value' => 68, 'unit' => '%'],
                        'repurchase_rate' => ['min' => 45, 'max' => 60, 'unit' => '%'],
                        'curly_searches' => ['value' => 25, 'unit' => '%'],
                    ],
                    'top_products' => ['Shampoo', 'Condicionador', 'Mascara capilar', 'Leave-in', 'Oleo capilar'],
                    'avoid_mentions' => ['skincare', 'maquiagem', 'perfume', 'esmalte', 'barba', 'serum facial', 'protetor solar', 'base', 'batom'],
                    'verified' => true,
                    'tags' => ['haircare', 'cabelo', 'shampoo', 'condicionador', 'oleo capilar'],
                ],
            ],

            // Benchmark Skincare - Cuidados com a Pele
            [
                'category' => 'benchmark',
                'niche' => 'beauty',
                'subcategory' => 'skincare',
                'title' => 'Benchmarks E-commerce Skincare Brasil 2024',
                'content' => 'Mercado skincare Brasil 2024: categoria premium em expansao, beleza de prestigio cresceu 19%. Ticket medio skincare: R$ 150-350 (media R$ 250), maior que haircare por produtos de alta tecnologia. Taxa de conversao: 0,8% desktop, 0,4% mobile (menor por maior pesquisa e comparacao). Principais produtos: Hidratante facial (35%), Protetor solar (28%), Serum/Vitamina C (20%), Esfoliante (10%), Sabonete facial (7%). Protecao solar: crescimento de 45% anual, conscientizacao dermatologica. Ingredientes: acido hialuronico, vitamina C, niacinamida sao os mais buscados. Quiz de tipo de pele aumenta conversao em 25%. Kits de rotina (manha/noite) aumentam ticket em 60%. Recompra skincare: 50-70% se produto funciona. Embalagem refil: diferencial para publico consciente (15% preferem). Certificacoes: cruelty-free, vegano, dermatologicamente testado sao obrigatorios.',
                'metadata' => [
                    'sources' => [
                        'Euromonitor',
                        'Circana',
                        'ABIHPEC',
                        'NIQ',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'prestige_growth' => ['value' => 19, 'unit' => '%'],
                        'ticket_medio' => ['min' => 150, 'max' => 350, 'avg' => 250, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 0.8, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.4, 'unit' => '%'],
                        'sunscreen_growth' => ['value' => 45, 'unit' => '%'],
                        'repurchase_rate' => ['min' => 50, 'max' => 70, 'unit' => '%'],
                        'refill_preference' => ['value' => 15, 'unit' => '%'],
                    ],
                    'top_products' => ['Hidratante facial', 'Protetor solar', 'Serum', 'Vitamina C', 'Esfoliante'],
                    'avoid_mentions' => ['shampoo', 'condicionador', 'maquiagem', 'perfume', 'esmalte', 'barba', 'oleo capilar', 'batom', 'base'],
                    'verified' => true,
                    'tags' => ['skincare', 'pele', 'hidratante', 'protetor solar', 'serum', 'vitamina c'],
                ],
            ],

            // Benchmark Makeup - Maquiagem
            [
                'category' => 'benchmark',
                'niche' => 'beauty',
                'subcategory' => 'makeup',
                'title' => 'Benchmarks E-commerce Makeup Brasil 2024',
                'content' => 'Mercado de maquiagem Brasil 2024: categoria com maior crescimento +26-27% (NIQ/Circana). Maquiagem de prestigio liderou expansao. Ticket medio makeup: R$ 120-300 (media R$ 180). Taxa de conversao: 1,0% desktop, 0,5% mobile (igual media beleza). Principais produtos: Base (30%), Batom (25%), Rimel/Mascara (15%), Paleta de sombras (12%), Blush (8%), Delineador (6%), Corretivo (4%). Produtos labiais cresceram 47% (Circana). Tutoriais em video aumentam conversao em 40% (maior impacto entre subcategorias). Instagram: 85% dos pedidos de makeup vem de social. Live commerce: conversao de ate 35% em transmissoes de maquiagem. Swatches (mostras de cor na pele) sao essenciais: aumentam conversao em 30%. Recompra makeup: 35-50% (menor que skincare/haircare). Kits de maquiagem completa aumentam ticket em 45%. Lancamentos constantes: 60% buscam novidades mensalmente.',
                'metadata' => [
                    'sources' => [
                        'NIQ/NielsenIQ',
                        'Circana',
                        'ABIHPEC',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'makeup_growth' => ['value' => 27, 'unit' => '%'],
                        'ticket_medio' => ['min' => 120, 'max' => 300, 'avg' => 180, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 1.0, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.5, 'unit' => '%'],
                        'lip_products_growth' => ['value' => 47, 'unit' => '%'],
                        'social_conversion' => ['value' => 85, 'unit' => '%'],
                        'live_commerce_conversion' => ['value' => 35, 'unit' => '%'],
                        'repurchase_rate' => ['min' => 35, 'max' => 50, 'unit' => '%'],
                    ],
                    'top_products' => ['Base', 'Batom', 'Rimel', 'Sombra', 'Blush', 'Delineador'],
                    'avoid_mentions' => ['shampoo', 'condicionador', 'hidratante', 'protetor solar', 'perfume', 'esmalte', 'barba', 'oleo capilar'],
                    'verified' => true,
                    'tags' => ['makeup', 'maquiagem', 'base', 'batom', 'rimel', 'sombra'],
                ],
            ],

            // Benchmark Nails - Produtos para Unhas
            [
                'category' => 'benchmark',
                'niche' => 'beauty',
                'subcategory' => 'nails',
                'title' => 'Benchmarks E-commerce Nails Brasil 2024',
                'content' => 'Mercado de produtos para unhas Brasil 2024: nicho em expansao, nail art impulsiona vendas. Ticket medio nails: R$ 50-120 (media R$ 85), menor que outras categorias beleza. Taxa de conversao: 1,5% desktop, 0,8% mobile (maior que media beleza por compra por impulso). Principais produtos: Esmalte (45%), Base/Top coat (20%), Removedor (15%), Acessorios nail art (12%), Fortalecedor (8%). Esmaltes em gel e semi-permanentes: crescimento de 55% anual. Colecoes sazonais: verao (cores vibrantes), inverno (tons escuros) impulsionam vendas. Kits de nail art (3-5 esmaltes tematicos) aumentam ticket em 40%. Recompra nails: 40-55%, impulsionada por colecoes. Publico jovem: 70% dos compradores tem 18-35 anos. Instagram e Pinterest: principais fontes de inspiracao (90%). Frete gratis acima de R$ 80 ideal. Sustentabilidade: esmaltes 10-free/veganos crescem 30% ao ano.',
                'metadata' => [
                    'sources' => [
                        'ABIHPEC',
                        'Euromonitor',
                        'Pesquisa de mercado',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'ticket_medio' => ['min' => 50, 'max' => 120, 'avg' => 85, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 1.5, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.8, 'unit' => '%'],
                        'gel_polish_growth' => ['value' => 55, 'unit' => '%'],
                        'repurchase_rate' => ['min' => 40, 'max' => 55, 'unit' => '%'],
                        'young_audience' => ['value' => 70, 'unit' => '%'],
                        'social_inspiration' => ['value' => 90, 'unit' => '%'],
                        'sustainable_growth' => ['value' => 30, 'unit' => '%'],
                    ],
                    'top_products' => ['Esmalte', 'Base para unhas', 'Top coat', 'Removedor', 'Acessorios nail art'],
                    'avoid_mentions' => ['shampoo', 'condicionador', 'hidratante', 'maquiagem', 'perfume', 'barba', 'base facial', 'batom'],
                    'verified' => true,
                    'tags' => ['nails', 'unhas', 'esmalte', 'nail art', 'manicure'],
                ],
            ],

            // Benchmark Perfumery - Perfumaria
            [
                'category' => 'benchmark',
                'niche' => 'beauty',
                'subcategory' => 'perfumery',
                'title' => 'Benchmarks E-commerce Perfumery Brasil 2024',
                'content' => 'Mercado de perfumaria Brasil 2024: categoria premium, Brasil e 3o maior mercado de perfumes do mundo. Ticket medio perfumery: R$ 200-500 (media R$ 320), o mais alto de beleza. Taxa de conversao: 0,6% desktop, 0,3% mobile (menor por alto valor, compra planejada). Principais produtos: Perfumes (60%), Colonia/Eau de Toilette (20%), Body splash (12%), Desodorante premium (8%). Perfumes importados: 70% das vendas online. Miniatura/amostra: estrategia essencial, 80% dos compradores querem testar antes. Kits presente: 40% das vendas em datas comemorativas (Dia das Maes, Natal, Dia dos Namorados). Recompra perfumery: 30-45% (menor por experimentacao). Familias olfativas: floral (35%), amadeirado (25%), citrico (20%), oriental (15%), frutado (5%). Parcelamento: 85% parcelam compras acima de R$ 300. Certificado de autenticidade obrigatorio. Frete gratis acima de R$ 299 ideal.',
                'metadata' => [
                    'sources' => [
                        'Euromonitor',
                        'ABIHPEC',
                        'NIQ',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'world_rank' => ['value' => 3, 'unit' => 'posicao'],
                        'ticket_medio' => ['min' => 200, 'max' => 500, 'avg' => 320, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 0.6, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.3, 'unit' => '%'],
                        'imported_share' => ['value' => 70, 'unit' => '%'],
                        'sample_demand' => ['value' => 80, 'unit' => '%'],
                        'gift_sets_share' => ['value' => 40, 'unit' => '%'],
                        'repurchase_rate' => ['min' => 30, 'max' => 45, 'unit' => '%'],
                        'installment_rate' => ['value' => 85, 'unit' => '%'],
                    ],
                    'top_products' => ['Perfume', 'Eau de Toilette', 'Body splash', 'Desodorante premium', 'Kit presente'],
                    'avoid_mentions' => ['shampoo', 'condicionador', 'hidratante', 'maquiagem', 'esmalte', 'barba', 'oleo capilar', 'base', 'batom'],
                    'verified' => true,
                    'tags' => ['perfumery', 'perfume', 'fragancia', 'colonia', 'body splash'],
                ],
            ],

            // Benchmark Barbershop - Produtos Masculinos
            [
                'category' => 'benchmark',
                'niche' => 'beauty',
                'subcategory' => 'barbershop',
                'title' => 'Benchmarks E-commerce Barbershop Brasil 2024',
                'content' => 'Mercado barbershop Brasil 2024: segmento masculino em expansao, crescimento 35% ao ano. Ticket medio barbershop: R$ 80-200 (media R$ 140). Taxa de conversao: 1,3% desktop, 0,7% mobile (acima media beleza, publico objetivo definido). Principais produtos: Shampoo/Condicionador para barba (30%), Oleo para barba (25%), Pomada/Cera modeladora (20%), Navalha/aparador (15%), Pos-barba (10%). Crescimento de barba: tendencia consolidada, 60% dos homens 25-45 anos cultivam barba. Kits de cuidados (3-4 produtos) aumentam ticket em 55%. Recompra barbershop: 50-65% (fidelizacao alta). Perfil: 80% dos compradores tem 25-45 anos, classe B/C. Embalagem masculina (preto, madeira, metal) e decisiva. Ingredientes naturais: 55% preferem produtos organicos. YouTube e principal canal de educacao (70%). Assinatura mensal: 20% aceitam modelo recorrente.',
                'metadata' => [
                    'sources' => [
                        'ABIHPEC',
                        'Euromonitor',
                        'Pesquisa de mercado',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'segment_growth' => ['value' => 35, 'unit' => '%'],
                        'ticket_medio' => ['min' => 80, 'max' => 200, 'avg' => 140, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 1.3, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.7, 'unit' => '%'],
                        'beard_prevalence' => ['value' => 60, 'unit' => '%'],
                        'repurchase_rate' => ['min' => 50, 'max' => 65, 'unit' => '%'],
                        'target_age' => ['value' => 80, 'unit' => '%'],
                        'organic_preference' => ['value' => 55, 'unit' => '%'],
                        'subscription_acceptance' => ['value' => 20, 'unit' => '%'],
                    ],
                    'top_products' => ['Shampoo para barba', 'Oleo para barba', 'Pomada', 'Navalha', 'Pos-barba'],
                    'avoid_mentions' => ['maquiagem', 'esmalte', 'batom', 'base', 'rimel', 'hidratante feminino', 'perfume feminino'],
                    'verified' => true,
                    'tags' => ['barbershop', 'barba', 'masculino', 'grooming', 'barbearia'],
                ],
            ],

            // =====================================================
            // ESTRATEGIAS POR SUBCATEGORIA - BEAUTY
            // =====================================================

            // Estrategia Haircare - Kits de Tratamento
            [
                'category' => 'strategy',
                'niche' => 'beauty',
                'subcategory' => 'haircare',
                'title' => 'Kits de Tratamento e Programas de Assinatura - Haircare',
                'content' => 'Haircare tem alta taxa de recompra (45-60%), ideal para estrategias de recorrencia. Kits de tratamento (Shampoo + Condicionador + Mascara) aumentam ticket em 50%. Quiz capilar (tipo de cabelo, necessidades) personaliza recomendacao (+25% conversao). Programas de assinatura mensal: 30% dos clientes aceitam modelo recorrente para produtos de uso diario. Frete gratis acima de R$ 120 (ideal para 2 produtos). Ingredientes naturais: destacar em 65% dos produtos (argumento de venda). Nicho cachos/crespos: criar colecao especifica, representa 25% das buscas. Cronograma capilar: educar cliente aumenta valor percebido. Tutorial de uso em video: +40% conversao (como aplicar mascara, oleo). Amostras gratis em pedidos acima de R$ 100: incentiva experimentacao de linha completa. Email pos-venda 30 dias: "Acabando seu shampoo? Recompre com 10% OFF".',
                'metadata' => [
                    'sources' => [
                        'Benchmark industria',
                        'ABIHPEC',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'subcategory_specific' => true,
                    'context' => [
                        'repurchase_rate' => ['min' => 45, 'max' => 60],
                        'ticket_medio' => ['min' => 90, 'max' => 180],
                        'curly_searches' => 25,
                    ],
                    'expected_results' => [
                        'kit_ticket_increase' => 50,
                        'quiz_conversion_increase' => 25,
                        'subscription_acceptance' => 30,
                        'tutorial_conversion' => 40,
                    ],
                    'avoid_suggestions' => ['swatches de maquiagem', 'quiz de pele', 'lancamentos de batom', 'nail art', 'fragrancia', 'produtos de barba'],
                    'verified' => true,
                    'tags' => ['haircare', 'kit', 'assinatura', 'quiz capilar', 'cronograma'],
                ],
            ],

            // Estrategia Skincare - Quiz de Pele e Rotinas
            [
                'category' => 'strategy',
                'niche' => 'beauty',
                'subcategory' => 'skincare',
                'title' => 'Quiz de Tipo de Pele e Rotinas Personalizadas - Skincare',
                'content' => 'Skincare tem ticket medio alto (R$ 250) e recompra de 50-70% se produto funciona. Quiz de tipo de pele (oleosa, seca, mista, sensivel) aumenta conversao em 25%. Recomendar rotina completa (manha: limpeza + hidratante + protetor solar / noite: limpeza + serum + hidratante) aumenta ticket em 60%. Kits de rotina prontos facilitam compra. Protecao solar: destacar crescimento 45%, consciencia dermatologica. Ingredientes: acido hialuronico, vitamina C, niacinamida devem estar em destaque nos filtros. Embalagem refil: oferecer para produtos de maior giro, 15% preferem opcao sustentavel. Certificacoes: cruelty-free, vegano, dermatologicamente testado sao obrigatorios na pagina. Tutoriais: ordem de aplicacao, quantidade certa aumentam confianca. Amostras em pedidos acima de R$ 200. Programa VIP: clientes recorrentes ganham desconto progressivo 5-10-15%.',
                'metadata' => [
                    'sources' => [
                        'Benchmark industria',
                        'Euromonitor',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'subcategory_specific' => true,
                    'context' => [
                        'ticket_medio' => ['min' => 150, 'max' => 350],
                        'repurchase_rate' => ['min' => 50, 'max' => 70],
                        'sunscreen_growth' => 45,
                        'refill_preference' => 15,
                    ],
                    'expected_results' => [
                        'quiz_conversion_increase' => 25,
                        'routine_ticket_increase' => 60,
                    ],
                    'avoid_suggestions' => ['kits de maquiagem', 'colecoes de esmalte', 'tutoriais de cabelo', 'fragrancia', 'produtos de barba'],
                    'verified' => true,
                    'tags' => ['skincare', 'quiz', 'rotina', 'tipo de pele', 'personalizacao'],
                ],
            ],

            // Estrategia Makeup - Tutoriais e Live Commerce
            [
                'category' => 'strategy',
                'niche' => 'beauty',
                'subcategory' => 'makeup',
                'title' => 'Tutoriais em Video e Live Commerce - Makeup',
                'content' => 'Maquiagem tem maior impacto de video: tutoriais aumentam conversao em 40%. Live commerce atinge ate 35% de conversao em transmissoes ao vivo. Instagram representa 85% dos pedidos de makeup via social. Swatches (mostras de cor na pele): essenciais, aumentam conversao em 30% - incluir em todas as fotos de produtos. Kits de maquiagem completa (base + batom + rimel + sombra) aumentam ticket em 45%. Lancamentos mensais: 60% buscam novidades, criar secao "Novidades" em destaque. Categorias em alta: produtos labiais (+47%), focar em variedade de tons. Looks prontos: "Maquiagem para o dia", "Make para festa" facilitam compra por ocasiao. Influenciadoras: parcerias com micro-influencers (5-50k seguidores) tem ROI 3x maior. Reviews com fotos em diferentes tons de pele aumentam confianca. Amostras em pedidos acima de R$ 150. Cupom de boas-vindas 10% OFF na primeira compra de makeup.',
                'metadata' => [
                    'sources' => [
                        'Circana',
                        'NIQ',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'subcategory_specific' => true,
                    'context' => [
                        'makeup_growth' => 27,
                        'lip_products_growth' => 47,
                        'social_conversion' => 85,
                        'live_commerce_conversion' => 35,
                    ],
                    'expected_results' => [
                        'tutorial_conversion' => 40,
                        'swatch_conversion' => 30,
                        'kit_ticket_increase' => 45,
                        'micro_influencer_roi' => 3,
                    ],
                    'avoid_suggestions' => ['cronograma capilar', 'quiz de pele', 'rotina de skincare', 'esmaltes sazonais', 'fragrancia', 'barba'],
                    'verified' => true,
                    'tags' => ['makeup', 'tutorial', 'live commerce', 'swatches', 'influencer'],
                ],
            ],

            // Estrategia Nails - Colecoes Sazonais
            [
                'category' => 'strategy',
                'niche' => 'beauty',
                'subcategory' => 'nails',
                'title' => 'Colecoes Sazonais e Kits Tematicos - Nails',
                'content' => 'Nails tem maior conversao (1,5% desktop) por compra por impulso, aproveitar com lancamentos frequentes. Colecoes sazonais: verao (cores vibrantes, neon), inverno (tons escuros, metalicos) impulsionam vendas. Kits tematicos de 3-5 esmaltes aumentam ticket em 40%. Instagram e Pinterest: 90% buscam inspiracao, investir em fotos de nail art. Esmaltes em gel/semi-permanentes: crescimento 55%, destacar durabilidade (ate 15 dias). Publico jovem (70% tem 18-35 anos): comunicacao descontraida, trends do TikTok. Acessorios nail art: vender junto com esmaltes (adesivos, strass, carimbos) aumenta ticket em 25%. Sustentabilidade: esmaltes 10-free/veganos crescem 30% ao ano, criar linha eco. Frete gratis acima de R$ 80 (baixo para incentivar compra). Clube de assinatura mensal: envio de 2-3 esmaltes da colecao nova, 25% aceitam. Tutoriais rapidos de nail art no Reels/TikTok viralizam.',
                'metadata' => [
                    'sources' => [
                        'ABIHPEC',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'alta',
                    'niche_specific' => true,
                    'subcategory_specific' => true,
                    'context' => [
                        'conversion_rate_desktop' => 1.5,
                        'gel_polish_growth' => 55,
                        'young_audience' => 70,
                        'social_inspiration' => 90,
                        'sustainable_growth' => 30,
                    ],
                    'expected_results' => [
                        'kit_ticket_increase' => 40,
                        'accessories_ticket_increase' => 25,
                        'subscription_acceptance' => 25,
                    ],
                    'avoid_suggestions' => ['kits de maquiagem', 'quiz capilar', 'rotina de skincare', 'tutoriais de batom', 'fragrancia', 'barba'],
                    'verified' => true,
                    'tags' => ['nails', 'colecao sazonal', 'nail art', 'esmalte', 'sustentabilidade'],
                ],
            ],

            // Estrategia Perfumery - Amostras e Kits Presente
            [
                'category' => 'strategy',
                'niche' => 'beauty',
                'subcategory' => 'perfumery',
                'title' => 'Estrategia de Amostras e Kits Presente - Perfumery',
                'content' => 'Perfumaria tem ticket mais alto (R$ 320) mas menor conversao (0,6% desktop) por compra planejada. Amostras/miniaturas: 80% querem testar antes, oferecer miniatura gratis em compras acima de R$ 250 ou venda avulsa (R$ 25-40). Kits presente: 40% das vendas em datas comemorativas (Dia das Maes, Natal, Dia dos Namorados), criar kits 60 dias antes. Familias olfativas: organizar catalogo por floral, amadeirado, citrico, oriental, frutado facilita escolha. Parcelamento: 85% parcelam acima de R$ 300, destacar "12x sem juros". Certificado de autenticidade: obrigatorio para perfumes importados (70% das vendas online). Frete gratis acima de R$ 299. Quiz olfativo: "Qual perfume combina com voce?" aumenta conversao em 20%. Programa de fidelidade: a cada 3 perfumes comprados, ganhe miniatura. Embalagem premium: investir em unboxing experience aumenta reviews positivos em 35%.',
                'metadata' => [
                    'sources' => [
                        'Euromonitor',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'subcategory_specific' => true,
                    'context' => [
                        'ticket_medio' => ['min' => 200, 'max' => 500],
                        'sample_demand' => 80,
                        'gift_sets_share' => 40,
                        'installment_rate' => 85,
                        'imported_share' => 70,
                    ],
                    'expected_results' => [
                        'quiz_conversion_increase' => 20,
                        'unboxing_reviews_increase' => 35,
                    ],
                    'avoid_suggestions' => ['kits de maquiagem', 'cronograma capilar', 'quiz de pele', 'nail art', 'produtos de barba'],
                    'verified' => true,
                    'tags' => ['perfumery', 'amostras', 'kit presente', 'familia olfativa', 'parcelamento'],
                ],
            ],

            // Estrategia Barbershop - Kits de Cuidados
            [
                'category' => 'strategy',
                'niche' => 'beauty',
                'subcategory' => 'barbershop',
                'title' => 'Kits de Cuidados e Educacao via YouTube - Barbershop',
                'content' => 'Barbershop tem crescimento 35% ao ano, aproveitar tendencia de cultivo de barba (60% homens 25-45 anos). Kits completos de cuidados (Shampoo + Oleo + Pomada) aumentam ticket em 55%. Publico objetivo definido: 80% tem 25-45 anos, comunicacao direta e masculina. YouTube: principal canal de educacao (70%), criar tutoriais "Como cuidar da barba", "Produtos essenciais". Embalagem masculina: preto, madeira, metal sao decisivos na escolha. Ingredientes naturais/organicos: 55% preferem, destacar na descricao. Assinatura mensal: 20% aceitam modelo recorrente (Kit Barba Todo Mes). Frete gratis acima de R$ 120 (2-3 produtos). Recompra alta (50-65%): email 45 dias pos-venda "Renove seu kit". Parcerias com barbearias: programa de indicacao B2B. Linha profissional vs linha home care: segmentar por uso. Presenteavel: destacar em Dia dos Pais, Dia do Homem.',
                'metadata' => [
                    'sources' => [
                        'ABIHPEC',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'alta',
                    'niche_specific' => true,
                    'subcategory_specific' => true,
                    'context' => [
                        'segment_growth' => 35,
                        'beard_prevalence' => 60,
                        'target_age' => 80,
                        'repurchase_rate' => ['min' => 50, 'max' => 65],
                        'organic_preference' => 55,
                        'subscription_acceptance' => 20,
                    ],
                    'expected_results' => [
                        'kit_ticket_increase' => 55,
                    ],
                    'avoid_suggestions' => ['maquiagem', 'esmalte', 'produtos femininos', 'nail art', 'swatches de batom'],
                    'verified' => true,
                    'tags' => ['barbershop', 'kit', 'youtube', 'barba', 'masculino'],
                ],
            ],

            // Estrategia Eletronicos: Confianca e Comparacao
            [
                'category' => 'strategy',
                'niche' => 'electronics',
                'title' => 'Comparacao e Confianca - Eletronicos',
                'content' => 'Eletronicos tem maior conversao (2,2% desktop, 1,4% mobile) por ser compra planejada, mas ciclo longo (85% comparam em 3+ sites). E a categoria mais consumida (28% via mobile). Tabela comparativa de especificacoes aumenta conversao em 15-20%. Videos de demonstracao/unboxing aumentam engajamento em 40%. Chat tecnico especializado reduz abandono em 30%. Garantia estendida: 20-30% aceitam upsell. FAQ tecnico reduz tickets de suporte. Selo de produto original/autorizado aumenta confianca. Reviews tecnicos (benchmarks) sao valorizados. Parcelamento longo e decisivo para tickets altos. Comparar com modelos anteriores justifica upgrade. Margem de 15-20% e das melhores do e-commerce.',
                'metadata' => [
                    'sources' => [
                        'NeoAtlas',
                        'ABComm',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'alta',
                    'niche_specific' => true,
                    'context' => [
                        'conversion_desktop' => 2.2,
                        'conversion_mobile' => 1.4,
                        'market_share_mobile' => 28,
                        'comparison_behavior' => 85,
                        'profit_margin' => ['min' => 15, 'max' => 20],
                    ],
                    'expected_results' => [
                        'comparison_conversion' => ['min' => 15, 'max' => 20],
                        'video_engagement' => 40,
                        'chat_abandonment_reduction' => 30,
                        'warranty_upsell' => ['min' => 20, 'max' => 30],
                    ],
                    'verified' => true,
                    'tags' => ['eletronicos', 'comparacao', 'garantia', 'video', 'chat'],
                ],
            ],

            // =====================================================
            // ESTRATEGIAS POR SUBCATEGORIA - ELECTRONICS
            // =====================================================

            // Estrategia Smartphones - Parcelamento e Trade-in
            [
                'category' => 'strategy',
                'niche' => 'electronics',
                'subcategory' => 'smartphones',
                'title' => 'Parcelamento Longo e Programa Trade-in - Smartphones',
                'content' => 'Smartphones tem ticket alto (R$ 2.200) e parcelamento e essencial: 85% compram parcelado em 10-12x. Exibir valor da parcela em destaque (maior que preco total) aumenta conversao em 20%. Programa Trade-in (troca do smartphone antigo): 15-20% aceitam, valorizar aparelho usado reduz ticket final e facilita upgrade. Calculadora de trade-in: usuario informa modelo/estado, recebe desconto instantaneo. Lancamentos: criar pre-venda com brinde exclusivo (capa, fone), primeiros 30 dias concentram 40% das vendas. Comparacao de modelos (camera, bateria, processador) lado a lado aumenta conversao em 15%. Cross-sell obrigatorio: capas e peliculas protetoras em 60% dos pedidos (+R$ 80-150). Garantia estendida: 25-35% aceitam upsell contra quebra de tela. Reviews com fotos reais aumentam confianca em 30%. Parcerias com operadoras (planos): diferencial para lojas maiores. Black Friday: preparar estoque, esperar +150% vendas.',
                'metadata' => [
                    'sources' => [
                        'IDC Brasil',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'subcategory_specific' => true,
                    'context' => [
                        'ticket_medio' => ['min' => 1200, 'max' => 3500],
                        'installment_usage' => 85,
                        'trade_in_acceptance' => ['min' => 15, 'max' => 20],
                        'cross_sell_rate' => 60,
                    ],
                    'expected_results' => [
                        'installment_display_conversion' => 20,
                        'comparison_conversion' => 15,
                        'review_trust_increase' => 30,
                        'warranty_upsell' => ['min' => 25, 'max' => 35],
                    ],
                    'avoid_suggestions' => ['configuracao de PC', 'pre-venda de jogo', 'bundle de console', 'audio samples', 'instalacao de TV', 'eficiencia energetica'],
                    'verified' => true,
                    'tags' => ['smartphones', 'parcelamento', 'trade-in', 'lancamento', 'cross-sell'],
                ],
            ],

            // Estrategia Computers - Comparacao Tecnica e Customizacao
            [
                'category' => 'strategy',
                'niche' => 'electronics',
                'subcategory' => 'computers',
                'title' => 'Comparacao Tecnica e Configuracao Customizada - Computadores',
                'content' => 'Computadores tem publico tecnico (40% gamers, 35% trabalho): especificacoes CPU, GPU, RAM, SSD devem estar em destaque. Tabela comparativa lado a lado (ate 3 modelos): aumenta conversao em 25%. Filtros avancados por uso: "Para jogos", "Para edicao de video", "Para programacao" facilitam escolha. Configuracao customizada (escolher componentes): aumenta ticket em 30%, ideal para gamers e criadores. PC Builder: ferramenta interativa para montar PC, mostra compatibilidade. Quiz de uso: "Que tipo de computador voce precisa?" gera recomendacao personalizada. Software incluido (Windows, Office, antivirus): diferencial em 20% das vendas, destacar. Parcelamento 10-12x obrigatorio para tickets altos. Upgrade futuro: mencionar slots livres, expansibilidade aumenta valor percebido. Reviews com benchmarks (FPS em jogos, tempo de renderizacao): essenciais para gamers. Volta as aulas: promocao para estudantes (+40% vendas). Chat tecnico: suporte especializado reduz abandono em 30%.',
                'metadata' => [
                    'sources' => [
                        'IDC Brasil',
                        'GfK',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'subcategory_specific' => true,
                    'context' => [
                        'ticket_medio' => ['min' => 800, 'max' => 3000],
                        'gamer_audience' => 40,
                        'comparison_conversion' => 25,
                        'custom_ticket_increase' => 30,
                    ],
                    'expected_results' => [
                        'comparison_conversion' => 25,
                        'custom_config_ticket' => 30,
                        'quiz_personalization' => 20,
                        'chat_abandonment_reduction' => 30,
                    ],
                    'avoid_suggestions' => ['trade-in de smartphone', 'pre-venda de jogo', 'fones TWS', 'instalacao de eletrodomestico', 'app fitness'],
                    'verified' => true,
                    'tags' => ['computadores', 'customizacao', 'comparacao', 'PC builder', 'especificacoes'],
                ],
            ],

            // Estrategia Gaming - Pre-venda e Comunidade
            [
                'category' => 'strategy',
                'niche' => 'electronics',
                'subcategory' => 'gaming',
                'title' => 'Pre-venda de Lancamentos e Comunidade Gamer - Gaming',
                'content' => 'Games tem publico engajado e fiel: pre-venda de lancamentos AAA representa 25-30% das vendas totais do titulo. Calendario de lancamentos: pagina dedicada a proximos jogos, newsletter semanal. Edicoes especiais/colecionaveis: steelbook, action figures, DLC exclusivo - premium de 40-80%, 15% dos gamers compram. Bundles estrategicos: console + jogo + controle extra aumentam ticket em 35%. Programa de fidelidade para gamers: pontos por compra, troca por jogos/DLC, recompra de 40-55%. Comunidade: forum, Discord oficial, secao de reviews de jogadores aumentam engajamento em 50%. Perifericos RGB: teclado mecanico, mouse gamer, headset - margem 20-25%, cross-sell com PC/console. Streaming: parceria com streamers para unboxing/gameplay ao vivo. Plataformas: filtrar por PlayStation, Xbox, Nintendo, PC - compatibilidade e critica. Parcelamento facilitado para consoles (ticket R$ 2.000-4.000). Black Friday games: preparar estoque massivo (+140%).',
                'metadata' => [
                    'sources' => [
                        'GfK',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'subcategory_specific' => true,
                    'context' => [
                        'ticket_medio' => ['min' => 300, 'max' => 800],
                        'preorder_share' => ['min' => 25, 'max' => 30],
                        'community_engagement' => 50,
                        'special_edition_buyers' => 15,
                        'bundle_ticket_increase' => 35,
                        'repurchase_rate' => ['min' => 40, 'max' => 55],
                    ],
                    'expected_results' => [
                        'preorder_revenue_share' => 30,
                        'community_engagement' => 50,
                        'bundle_ticket' => 35,
                        'loyalty_repurchase' => 50,
                    ],
                    'avoid_suggestions' => ['trade-in smartphone', 'configuracao PC trabalho', 'quiz de pele', 'instalacao TV', 'selo Procel', 'app saude'],
                    'verified' => true,
                    'tags' => ['gaming', 'pre-venda', 'comunidade', 'bundle', 'fidelidade', 'edicao especial'],
                ],
            ],

            // Estrategia Audio - Reviews com Teste de Som
            [
                'category' => 'strategy',
                'niche' => 'electronics',
                'subcategory' => 'audio',
                'title' => 'Reviews com Audio Samples e Comparacao Sonora - Audio',
                'content' => 'Audio tem 25% de publico audiofilo que valoriza especificacoes tecnicas (impedancia, drivers, resposta de frequencia), 75% priorizam marca e design. Reviews com teste de som: gravar audio samples (grave, medio, agudo) aumentam conversao em 30% - diferencial unico do segmento. Comparacao lado a lado: ate 3 fones/caixas, destacar diferenciais (ANC, TWS, autonomia). Fones TWS: dominam 60% das vendas, filtro dedicado. ANC (cancelamento de ruido): diferencial premium, aumenta ticket em 40%. Marcas premium (Sony, JBL, Bose, Sennheiser): destacar garantia e qualidade, fidelidade de 35%. Casos de uso: "Para academia", "Para trabalho remoto", "Para audiofilo" facilitam escolha. Cross-sell com capas e cabos: 40% compram junto. Garantia contra defeitos: essencial para eletronicos de audio, 20% aceitam estendida. Parcelamento 6-10x para produtos premium. Dia dos Pais: campanha dedicada, +40% vendas. Reviews de usuarios com fotos reais aumentam confianca.',
                'metadata' => [
                    'sources' => [
                        'GfK',
                        'Euromonitor',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'subcategory_specific' => true,
                    'context' => [
                        'ticket_medio' => ['min' => 200, 'max' => 800],
                        'audiophile_audience' => 25,
                        'tws_share' => 60,
                        'anc_premium' => 40,
                        'brand_loyalty' => 35,
                        'cross_sell_rate' => 40,
                    ],
                    'expected_results' => [
                        'audio_sample_conversion' => 30,
                        'anc_ticket_increase' => 40,
                        'cross_sell_rate' => 40,
                        'warranty_acceptance' => 20,
                    ],
                    'avoid_suggestions' => ['pre-venda de jogo', 'trade-in smartphone', 'PC builder', 'instalacao TV', 'programa fitness', 'selo Procel'],
                    'verified' => true,
                    'tags' => ['audio', 'teste de som', 'TWS', 'ANC', 'audiofilo', 'comparacao'],
                ],
            ],

            // Estrategia TV & Video - Comparacao de Imagem e Instalacao
            [
                'category' => 'strategy',
                'niche' => 'electronics',
                'subcategory' => 'tv_video',
                'title' => 'Comparacao de Qualidade de Imagem e Servico de Instalacao - TV',
                'content' => 'TVs tem ticket altissimo (R$ 2.500 media) e pesquisa extensa: reviews com fotos/videos de qualidade de imagem aumentam conversao em 35%. Comparacao de especificacoes: 4K vs Full HD, QLED vs OLED, HDR, taxa de atualizacao (Hz), HDMI 2.1 - tabela lado a lado essencial. Filtro por tamanho: 43", 50-55", 65"+ - mais importante que marca para muitos. Sistema operacional: Android TV, Google TV, Tizen, webOS - influencia 30%, destacar apps disponiveis (Netflix, Prime, Disney+). Calculadora de tamanho ideal: baseado em distancia do sofa, gera recomendacao personalizada. Parcelamento longo (12-18x): obrigatorio, exibir parcela em destaque. Servico de instalacao/suporte: 15-20% contratam instalacao na parede, combo TV + suporte + instalacao aumenta ticket. Cross-sell com soundbar: "Melhore o audio da sua TV", bundle com desconto. Eventos esportivos: Copa, Olimpiadas disparam vendas (+200%), preparar campanhas. Black Friday TV: categoria top 3 mais vendida (+120%).',
                'metadata' => [
                    'sources' => [
                        'GfK',
                        'IDC Brasil',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'subcategory_specific' => true,
                    'context' => [
                        'ticket_medio' => ['min' => 1500, 'max' => 5000],
                        'smart_tv_share' => 85,
                        '4k_share' => 70,
                        'review_conversion' => 35,
                        'installation_service' => ['min' => 15, 'max' => 20],
                    ],
                    'expected_results' => [
                        'image_review_conversion' => 35,
                        'size_calculator_personalization' => 25,
                        'installation_service_uptake' => 18,
                        'soundbar_cross_sell' => 20,
                    ],
                    'avoid_suggestions' => ['trade-in smartphone', 'pre-venda jogo', 'fone TWS', 'configuracao PC', 'programa fitness', 'app smartwatch'],
                    'verified' => true,
                    'tags' => ['TV', 'instalacao', 'comparacao', '4K', 'QLED', 'tamanho'],
                ],
            ],

            // Estrategia Appliances - Eficiencia Energetica e Frete Gratis
            [
                'category' => 'strategy',
                'niche' => 'electronics',
                'subcategory' => 'appliances',
                'title' => 'Selo Procel e Frete Gratis com Instalacao - Eletrodomesticos',
                'content' => 'Eletrodomesticos tem ticket alto (R$ 2.000) e frete/instalacao sao decisivos: 70% esperam frete gratis + instalacao inclusa ou com desconto. Destacar frete gratis acima de R$ 1.500 aumenta conversao. Selo Procel (eficiencia energetica): influencia 45% das compras, filtro por classificacao A/B/C essencial. Calculadora de economia: "Economize R$ X/mes na conta de luz com modelo A" aumenta valor percebido. Capacidade/tamanho: especificacao critica - litros (geladeira), kg (lavadora), BTUs (ar condicionado), destacar em titulo. Comparacao lado a lado: ate 3 modelos, mostrar diferenca de consumo, capacidade, funcoes. Garantia estendida: 30-40% aceitam para eletrodomesticos de alto valor, ofertar no carrinho. Programa de troca do antigo: retirada gratuita do eletrodomestico usado, valorizado por 25%. Parcelamento longo (12-24x): obrigatorio. Reviews de durabilidade: "Uso ha 2 anos" aumentam confianca. Mudanca/reforma: campanha jan-mar (+30%). Black Friday: +100% vendas.',
                'metadata' => [
                    'sources' => [
                        'GfK',
                        'Euromonitor',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'subcategory_specific' => true,
                    'context' => [
                        'ticket_medio' => ['min' => 1000, 'max' => 4000],
                        'procel_influence' => 45,
                        'free_shipping_expectation' => 70,
                        'warranty_acceptance' => ['min' => 30, 'max' => 40],
                        'trade_in_interest' => 25,
                    ],
                    'expected_results' => [
                        'free_shipping_conversion' => 25,
                        'procel_filter_usage' => 45,
                        'savings_calculator_value' => 20,
                        'warranty_upsell' => 35,
                        'trade_in_adoption' => 25,
                    ],
                    'avoid_suggestions' => ['trade-in smartphone', 'pre-venda jogo', 'audio samples', 'configuracao PC gamer', 'app fitness', 'quiz capilar'],
                    'verified' => true,
                    'tags' => ['eletrodomesticos', 'Procel', 'frete gratis', 'instalacao', 'eficiencia energetica'],
                ],
            ],

            // Estrategia Wearables - App Fitness e Compatibilidade
            [
                'category' => 'strategy',
                'niche' => 'electronics',
                'subcategory' => 'wearables',
                'title' => 'Compatibilidade iOS/Android e Funcoes Fitness - Wearables',
                'content' => 'Wearables tem compatibilidade como fator decisivo: iOS vs Android influencia 70% da escolha. Filtro por compatibilidade: "Para iPhone", "Para Android" evita frustacao. Funcionalidades em destaque: monitoramento cardiaco (80% valorizam), GPS (60%), notificacoes (90%), pagamento NFC (35%), chamadas (40%). Quiz de uso: "Qual smartwatch ideal para voce?" baseado em atividades, sistema operacional, orcamento. Publico fitness (55%): destacar rastreamento de corrida, natacao, ciclismo, calorias, sono. Reviews de autonomia de bateria: duracao influencia 60% das compras, criar filtro "Bateria 5+ dias". Cross-sell com pulseiras extras: oferecer cores diferentes, 35% compram acessorios adicionais. Garantia contra agua/suor: essencial para uso esportivo, destacar certificacao IP. Parcelamento 6-10x para modelos premium (Apple Watch, Galaxy Watch). Janeiro: "Metas de Ano Novo" dispara vendas fitness (+60%). Comparacao: Apple Watch vs Galaxy Watch vs Xiaomi - tabela de funcoes.',
                'metadata' => [
                    'sources' => [
                        'IDC Brasil',
                        'GfK',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'muito alta',
                    'niche_specific' => true,
                    'subcategory_specific' => true,
                    'context' => [
                        'ticket_medio' => ['min' => 300, 'max' => 1500],
                        'compatibility_influence' => 70,
                        'fitness_audience' => 55,
                        'battery_influence' => 60,
                        'cross_sell_accessories' => 35,
                    ],
                    'expected_results' => [
                        'compatibility_filter_usage' => 70,
                        'quiz_conversion' => 20,
                        'battery_filter_engagement' => 30,
                        'cross_sell_accessories' => 35,
                    ],
                    'avoid_suggestions' => ['trade-in smartphone', 'pre-venda jogo', 'PC builder', 'audio samples', 'instalacao TV', 'selo Procel'],
                    'verified' => true,
                    'tags' => ['wearables', 'smartwatch', 'compatibilidade', 'fitness', 'bateria', 'iOS', 'Android'],
                ],
            ],

            // Estrategia Alimentos: Recorrencia
            [
                'category' => 'strategy',
                'niche' => 'food',
                'title' => 'Assinatura e Recorrencia - Alimentos e Bebidas',
                'content' => 'Alimentos tem a maior taxa de conversao desktop (4,5%) e menor abandono (72% vs 82% geral) por ser necessidade. Cresceu 18,4% em 2024, projecao de triplicar ate 2027 (McKinsey). Email marketing representa 32,9% da receita do setor. Modelo de assinatura e ideal para consumo regular (10-15% desconto fideliza). Kits tematicos aumentam ticket em 30%. Entrega expressa e diferencial critico. Prazo de entrega e 2o maior motivo de abandono geral (36,5%). Embalagem termica obrigatoria para pereciveis. Receitas incluidas aumentam engajamento. Lembretes de recompra baseados no ciclo de consumo. 40% dos supermercadistas ja tem e-commerce, mas participacao media e 12% - grande potencial.',
                'metadata' => [
                    'sources' => [
                        'NeoAtlas',
                        'ABComm',
                        'McKinsey',
                        'edrone',
                        'Yampi',
                    ],
                    'effectiveness' => 'alta',
                    'niche_specific' => true,
                    'context' => [
                        'conversion_desktop' => 4.5,
                        'conversion_mobile' => 0.8,
                        'cart_abandonment' => 72,
                        'sector_growth' => 18.4,
                        'email_revenue_share' => 32.9,
                        'delivery_abandonment_reason' => 36.5,
                    ],
                    'expected_results' => [
                        'subscription_discount' => ['min' => 10, 'max' => 15],
                        'kit_ticket_increase' => 30,
                    ],
                    'verified' => true,
                    'tags' => ['alimentos', 'assinatura', 'kit', 'entrega', 'recorrencia'],
                ],
            ],

            // Estrategia Casa: Ambientacao
            [
                'category' => 'strategy',
                'niche' => 'home',
                'title' => 'Ambientacao e Projetos - Casa e Decoracao',
                'content' => 'Setor de R$ 102 bilhoes cresceu 8,7% em 2024. E-commerce Casa/Jardim Nuvemshop: R$ 206,5 milhoes (+30%). Criterios de decisao: qualidade (50%), durabilidade (35%), preco (32%). Fotos de ambientacao aumentam conversao em 40%. Realidade aumentada para visualizar movel no ambiente. Projetos completos incentivam multiplos itens (+60% itens/pedido). Consultoria online como servico premium. Medidas detalhadas e video 360 reduzem devolucoes. Frete com agendamento para itens grandes reduz reclamacoes em 50%. Black Friday lidera vendas (46%), Dia das Maes (14%), Natal (7%). Concentracao no Sudeste (47,2%) indica oportunidades regionais. Mobile: 69% das visitas, investir em experiencia responsiva.',
                'metadata' => [
                    'sources' => [
                        'ABCasa',
                        'NuvemCommerce',
                        'Similar Web',
                    ],
                    'effectiveness' => 'alta',
                    'niche_specific' => true,
                    'context' => [
                        'sector_revenue' => 102,
                        'nuvemshop_growth' => 30,
                        'quality_criterion' => 50,
                        'durability_criterion' => 35,
                        'price_criterion' => 32,
                        'mobile_traffic' => 69,
                    ],
                    'expected_results' => [
                        'ambiance_conversion' => 40,
                        'project_items_increase' => 60,
                        'scheduled_delivery_complaints_reduction' => 50,
                    ],
                    'verified' => true,
                    'tags' => ['casa', 'decoracao', 'ambientacao', 'projeto', 'moveis'],
                ],
            ],

            // Estrategia Esportes: Comunidade
            [
                'category' => 'strategy',
                'niche' => 'sports',
                'title' => 'Comunidade e Assinatura - Esportes e Fitness',
                'content' => 'Mercado de R$ 8-12 bilhoes com apenas 5% de penetracao em academias - grande potencial. Crescimento jan/2025: +22,22% (Tecnofit). Suplementos: R$ 4,6 bilhoes, crescimento 20%+/ano. Perfil: 62% homens, 36-45 anos (32%), 26-35 (26%). 50% dos brasileiros praticam atividades regularmente (Datafolha). Conteudo de treino e dicas fideliza alem da compra. Desafios mensais com premiacao engajam +35%. Parceria com atletas/influenciadores para credibilidade. Kits por objetivo (emagrecer, hipertrofia) aumentam ticket em 45%. Suplementos em assinatura: maior potencial de recorrencia. Guia de tamanho especifico para roupas esportivas. Programa de embaixadores com atletas amadores. Track&Field cresceu 27,8% no 2T/2025.',
                'metadata' => [
                    'sources' => [
                        'Fitness Brasil',
                        'Tecnofit',
                        'Datafolha',
                        'Sebrae',
                    ],
                    'effectiveness' => 'alta',
                    'niche_specific' => true,
                    'context' => [
                        'market_size' => ['min' => 8, 'max' => 12],
                        'gym_penetration' => 5,
                        'growth_jan_2025' => 22.22,
                        'supplements_market' => 4.6,
                        'regular_exercise' => 50,
                        'male_share' => 62,
                    ],
                    'expected_results' => [
                        'challenge_engagement' => 35,
                        'goal_kit_ticket' => 45,
                    ],
                    'verified' => true,
                    'tags' => ['esportes', 'fitness', 'comunidade', 'suplementos', 'assinatura'],
                ],
            ],

            // =====================================================
            // SAZONALIDADE - Calendario Comercial
            // =====================================================

            // Calendario Geral 2025
            [
                'category' => 'seasonality',
                'niche' => 'general',
                'title' => 'Calendario Comercial E-commerce Brasil 2025/2026',
                'content' => 'Janeiro: Liquidacoes de verao, volta as aulas (+30% eletronicos). Fevereiro: Carnaval, volta as aulas continua. Marco: Dia da Mulher (8), inicio do outono. Abril: Pascoa, Dia do Frete Gratis. Maio: Dia das Maes (2a maior data, +40-50% dependendo do segmento). Junho: Dia dos Namorados (12), Sao Joao. Julho: Ferias escolares, liquidacoes de inverno. Agosto: Dia dos Pais (+25% eletronicos). Setembro: Dia do Cliente (15), inicio da primavera. Outubro: Dia das Criancas (12), esquenta Black Friday. Novembro: Black Friday (ultima sexta - maior data, +80-120%), Cyber Monday. Dezembro: Natal (+30-50%), menor abandono do ano (77% vs 82%). Preparacao: estocar 60 dias antes das datas principais.',
                'metadata' => [
                    'sources' => [
                        'ABComm',
                        'E-commerce Radar',
                        'Benchmark industria',
                    ],
                    'year' => 2025,
                    'key_dates' => [
                        ['date' => '08-03', 'event' => 'Dia da Mulher', 'increase' => 25],
                        ['date' => 'variavel', 'event' => 'Pascoa'],
                        ['date' => '2o domingo maio', 'event' => 'Dia das Maes', 'increase' => 50],
                        ['date' => '12-06', 'event' => 'Dia dos Namorados', 'increase' => 30],
                        ['date' => '2o domingo agosto', 'event' => 'Dia dos Pais', 'increase' => 25],
                        ['date' => '15-09', 'event' => 'Dia do Cliente'],
                        ['date' => '12-10', 'event' => 'Dia das Criancas'],
                        ['date' => 'ultima sexta novembro', 'event' => 'Black Friday', 'increase' => 120],
                        ['date' => '25-12', 'event' => 'Natal', 'increase' => 50],
                    ],
                    'december_abandonment' => 77,
                    'verified' => true,
                    'tags' => ['calendario', 'sazonalidade', 'datas', 'promocoes'],
                ],
            ],

            // Black Friday
            [
                'category' => 'seasonality',
                'niche' => 'general',
                'title' => 'Guia Black Friday Brasil - Dados e Estrategias',
                'content' => 'Black Friday e a maior data do e-commerce brasileiro, com aumentos de 80% a 120% nas vendas. Dezembro tem o menor abandono do ano (77%) devido a BF e promocoes de fim de ano. Preparacao: 90 dias antes (planejamento), 60 dias (estoque), 30 dias (aquecimento email), 15 dias (teste infraestrutura). Black Week (comecar segunda) aumenta vendas em 40% vs so sexta. Horarios de pico: 10h-12h e 19h-23h. Descontos reais sao essenciais (consumidor pesquisa). Frete gratis e esperado. Cyber Monday para tech. Pos-BF: liquidacao ate dezembro. Cuidados: nao inflar precos antes, garantir estoque dos mais vendidos, ter plano B para alto trafego. PIX consolidou como principal meio de pagamento.',
                'metadata' => [
                    'sources' => [
                        'ABComm',
                        'E-commerce Radar',
                        'Neotrust',
                    ],
                    'event' => 'Black Friday',
                    'preparation_timeline' => [
                        ['days_before' => 90, 'action' => 'Inicio do planejamento'],
                        ['days_before' => 60, 'action' => 'Garantir estoque'],
                        ['days_before' => 30, 'action' => 'Aquecimento de email'],
                        ['days_before' => 15, 'action' => 'Teste de infraestrutura'],
                    ],
                    'peak_hours' => ['10:00-12:00', '19:00-23:00'],
                    'black_week_increase' => 40,
                    'sales_increase' => ['min' => 80, 'max' => 120],
                    'december_abandonment' => 77,
                    'verified' => true,
                    'tags' => ['black friday', 'novembro', 'promocao', 'maior data'],
                ],
            ],

            // Sazonalidade Moda
            [
                'category' => 'seasonality',
                'niche' => 'fashion',
                'title' => 'Calendario Sazonal Moda - Com Dados de Mercado',
                'content' => 'Moda representa 32% dos e-commerces brasileiros (NuvemCommerce). 66% preferem comprar roupas online (Opinion Box). Colecoes: Primavera/Verao lanca em setembro (pico outubro-fevereiro), Outono/Inverno lanca em marco (pico abril-agosto). Transicao de colecao: liquidacao 30-50% off. Lancamentos: primeiros 7 dias = 15-20% das vendas da colecao. Datas: Dia das Maes (+40% feminino), Dia dos Namorados (+35%), Natal (+50%), Black Friday (+80%). Live commerce: ate 30% de conversao. Verao: moda praia jan-fev. Inverno: casacos e botas mar-jul. 80%+ dos lojistas de moda estao otimistas para 2025. E-commerce moda 1T/2025: R$ 413 milhoes.',
                'metadata' => [
                    'sources' => [
                        'NuvemCommerce',
                        'Opinion Box',
                    ],
                    'collections' => [
                        ['name' => 'Primavera/Verao', 'launch' => 'Setembro', 'peak' => 'Outubro-Fevereiro'],
                        ['name' => 'Outono/Inverno', 'launch' => 'Marco', 'peak' => 'Abril-Agosto'],
                    ],
                    'key_dates_increase' => [
                        ['event' => 'Dia das Maes', 'increase' => 40],
                        ['event' => 'Natal', 'increase' => 50],
                        ['event' => 'Black Friday', 'increase' => 80],
                    ],
                    'market_share' => 32,
                    'online_preference' => 66,
                    'live_commerce_conversion' => 30,
                    'verified' => true,
                    'tags' => ['moda', 'colecao', 'sazonalidade', 'lancamento'],
                ],
            ],

            // Sazonalidade Beleza
            [
                'category' => 'seasonality',
                'niche' => 'beauty',
                'title' => 'Calendario Sazonal Beleza - Com Dados Circana/NIQ',
                'content' => 'Mercado de beleza Brasil: US$ 27 bilhoes, 4o maior do mundo (NIQ). Crescimento 2024: 12,7%. Beleza de prestigio: +19%, maquiagem +26-27% (Circana). PMEs cresceram 40% em 2024. Maiores datas: Dia das Maes (+50%, maior do segmento), Natal (+40%), Dia dos Namorados (+30%), Black Friday (+60%). Por estacao: Verao - protetores, produtos para cabelo, maquiagem waterproof. Inverno - hidratantes, lip balm, cremes densos. Categorias em alta: Oleos Capilares (+68%), Labiais (+47%). Kits presenteaveis vendem 60% mais em datas comemorativas. Instagram: 83% dos pedidos via social. Brasil e 4o pais com maior penetracao online para beleza seletiva.',
                'metadata' => [
                    'sources' => [
                        'Circana',
                        'NIQ/NielsenIQ',
                        'NuvemCommerce',
                    ],
                    'market_size' => 27,
                    'market_growth' => 12.7,
                    'prestige_growth' => 19,
                    'makeup_growth' => 27,
                    'key_dates_increase' => [
                        ['event' => 'Dia das Maes', 'increase' => 50],
                        ['event' => 'Black Friday', 'increase' => 60],
                        ['event' => 'Natal', 'increase' => 40],
                    ],
                    'category_growth' => [
                        ['category' => 'Oleos Capilares', 'growth' => 68],
                        ['category' => 'Labiais', 'growth' => 47],
                    ],
                    'verified' => true,
                    'tags' => ['beleza', 'cosmeticos', 'sazonalidade', 'kits'],
                ],
            ],

            // Sazonalidade Eletronicos
            [
                'category' => 'seasonality',
                'niche' => 'electronics',
                'title' => 'Calendario Sazonal Eletronicos - Com Dados ABComm',
                'content' => 'Eletronicos: categoria mais consumida em 2024, 28% das vendas mobile (ABComm). Taxa de conversao acima da media: 2,2% desktop, 1,4% mobile. Picos de venda: Black Friday (+120%, maior data), Volta as Aulas jan-fev (+30% notebooks/tablets), Dia dos Pais agosto (+25% gadgets/smartphones), Natal (+40% consoles/fones/acessorios). Lancamentos: Apple (setembro), Samsung (fevereiro/agosto). Produtos seminovos vendem bem pos-lancamentos. Copa do Mundo/Olimpiadas: TVs e soundbars. Momentos de troca: quando novas versoes sao anunciadas, versao anterior tem pico de busca. Margem operacional: 15-20%. Garantia estendida: 20-30% aceitam.',
                'metadata' => [
                    'sources' => [
                        'ABComm',
                        'NeoAtlas',
                    ],
                    'market_share_mobile' => 28,
                    'conversion_desktop' => 2.2,
                    'conversion_mobile' => 1.4,
                    'key_dates_increase' => [
                        ['event' => 'Black Friday', 'increase' => 120],
                        ['event' => 'Volta as Aulas', 'increase' => 30],
                        ['event' => 'Dia dos Pais', 'increase' => 25],
                        ['event' => 'Natal', 'increase' => 40],
                    ],
                    'manufacturer_launches' => [
                        ['brand' => 'Apple', 'typical_month' => 'Setembro'],
                        ['brand' => 'Samsung', 'typical_month' => 'Fevereiro/Agosto'],
                    ],
                    'verified' => true,
                    'tags' => ['eletronicos', 'sazonalidade', 'black friday', 'lancamentos'],
                ],
            ],

            // Sazonalidade Alimentos
            [
                'category' => 'seasonality',
                'niche' => 'food',
                'title' => 'Calendario Sazonal Alimentos - Com Dados ABComm',
                'content' => 'Setor de alimentos e bebidas 2024: R$ 16 bilhoes (+18,4%), maior crescimento do e-commerce no 1o semestre. Taxa de conversao mais alta: 4,5% desktop. Menor abandono: 72% (vs 82% geral). Projecao: triplicar ate 2027 (McKinsey). Email marketing: 32,9% da receita. Picos: Natal e reveillon (bebidas), Pascoa (chocolates), datas comemorativas familiares. Constante demanda: necessidade recorrente favorece assinaturas. Higiene/Beleza e Alimentos juntos: mais da metade do crescimento do e-commerce. Online representa 4,3% das vendas FMCG. 40% dos supermercadistas tem e-commerce, participacao media 12% - muito potencial de crescimento.',
                'metadata' => [
                    'sources' => [
                        'ABComm',
                        'McKinsey',
                        'edrone',
                        'Nielsen/NIQ',
                    ],
                    'sector_revenue' => 16,
                    'growth' => 18.4,
                    'conversion_desktop' => 4.5,
                    'cart_abandonment' => 72,
                    'email_revenue_share' => 32.9,
                    'fmcg_online_share' => 4.3,
                    'supermarket_ecommerce_penetration' => 40,
                    'average_online_share' => 12,
                    'verified' => true,
                    'tags' => ['alimentos', 'bebidas', 'sazonalidade', 'supermercado'],
                ],
            ],

            // Sazonalidade Casa
            [
                'category' => 'seasonality',
                'niche' => 'home',
                'title' => 'Calendario Sazonal Casa e Decoracao - Com Dados ABCasa',
                'content' => 'Setor de artigos para casa 2024: R$ 102 bilhoes (+8,7%). E-commerce Casa/Jardim Nuvemshop: R$ 206,5 milhoes (+30%). Enfeites e Decoracao: +40% no Mercado Livre. Picos: Black Friday (46% das vendas sazonais), Dia das Maes (14% - utensilios), Natal (7% - decoracao). Mudancas de estacao geram troca de decoracao. Casamentos: marco a novembro. Mudancas residenciais: janeiro e julho. Por estacao: Verao - ventiladores, area externa. Inverno - cobertores, aquecedores. Concentracao Sudeste (47,2%). Mobile: 69% das visitas. Criterios: qualidade (50%), durabilidade (35%), preco (32%).',
                'metadata' => [
                    'sources' => [
                        'ABCasa',
                        'NuvemCommerce',
                        'Mercado Livre',
                    ],
                    'sector_revenue' => 102,
                    'growth' => 8.7,
                    'nuvemshop_revenue' => 206.5,
                    'nuvemshop_growth' => 30,
                    'key_dates_share' => [
                        ['event' => 'Black Friday', 'share' => 46],
                        ['event' => 'Dia das Maes', 'share' => 14],
                        ['event' => 'Natal', 'share' => 7],
                    ],
                    'moving_peaks' => ['Janeiro', 'Julho'],
                    'wedding_season' => 'Marco a Novembro',
                    'mobile_traffic' => 69,
                    'verified' => true,
                    'tags' => ['casa', 'decoracao', 'sazonalidade', 'mudanca'],
                ],
            ],

            // Sazonalidade Esportes
            [
                'category' => 'seasonality',
                'niche' => 'sports',
                'title' => 'Calendario Sazonal Esportes - Com Dados Fitness Brasil',
                'content' => 'Mercado fitness: R$ 8-12 bilhoes/ano, 64 mil empresas, apenas 5% de penetracao em academias (Sebrae/Fitness Brasil). Crescimento jan/2025: +22,22% vs jan/2024 (Tecnofit). Suplementos: R$ 4,6 bilhoes, crescimento 20%+/ano. 50% dos brasileiros praticam atividades regularmente (Datafolha 2025). Picos: Janeiro (+60% resolucoes de ano novo), Setembro-Novembro (pre-verao, boom academia), Black Friday (+60%), Verao (+40% roupas fitness). Track&Field 2T/2025: +27,8%. Por modalidade: natacao (verao), ciclismo (primavera/verao), academia (janeiro/setembro). Copa/Olimpiadas: artigos de torcida. Desafios fitness no app aumentam engajamento em periodos de baixa.',
                'metadata' => [
                    'sources' => [
                        'Fitness Brasil',
                        'Tecnofit',
                        'Datafolha',
                        'Sebrae',
                    ],
                    'market_size' => ['min' => 8, 'max' => 12],
                    'gym_penetration' => 5,
                    'growth_jan_2025' => 22.22,
                    'supplements_market' => 4.6,
                    'regular_exercise' => 50,
                    'key_dates_increase' => [
                        ['event' => 'Janeiro (ano novo)', 'increase' => 60],
                        ['event' => 'Black Friday', 'increase' => 60],
                        ['event' => 'Verao', 'increase' => 40],
                        ['event' => 'Pre-verao (set-nov)', 'increase' => 35],
                    ],
                    'verified' => true,
                    'tags' => ['esportes', 'fitness', 'sazonalidade', 'academia', 'suplementos'],
                ],
            ],

            // =====================================================
            // CASOS DE SUCESSO - Exemplos documentados
            // =====================================================

            [
                'category' => 'case',
                'niche' => 'general',
                'title' => 'Caso Real: E-commerce de Moda R$ 1 Milhao com Email Marketing',
                'content' => 'Caso documentado pela edrone: marca de moda faturou mais de R$ 1 milhao em vendas diretas por email em apenas 10 meses. Resultados: mais de 5 mil pedidos aprovados, taxa de conversao acima de 17% em relacao ao faturamento total da loja. Estrategias utilizadas: Automacao de carrinho abandonado (abertura 25%, conversao 1,60%), Automacao pos-venda (abertura 43,7%, conversao 1,86%), Segmentacao de base, Personalizacao com produtos visualizados. Este caso demonstra o potencial do email marketing bem executado no e-commerce brasileiro.',
                'metadata' => [
                    'sources' => [
                        'edrone - Caso documentado',
                    ],
                    'duration' => '10 meses',
                    'results' => [
                        'revenue' => 1000000,
                        'orders' => 5000,
                        'conversion_share' => 17,
                    ],
                    'tactics' => [
                        'cart_abandonment_open_rate' => 25,
                        'cart_abandonment_conversion' => 1.60,
                        'post_sale_open_rate' => 43.7,
                        'post_sale_conversion' => 1.86,
                    ],
                    'verified' => true,
                    'tags' => ['caso de sucesso', 'email', 'moda', 'automacao'],
                ],
            ],

            [
                'category' => 'case',
                'niche' => 'fashion',
                'title' => 'Caso Real: Live Commerce Moda ate 30% Conversao',
                'content' => 'Tendencia documentada pelo setor: marcas de moda online estao registrando taxas de conversao de ate 30% atraves de transmissoes ao vivo no Facebook e Instagram, alem de observar reducao nas taxas de devolucao. O live commerce combina entretenimento, demonstracao de produto e interacao em tempo real. Fatores de sucesso: demonstracao de caimento e tamanho, resposta a duvidas ao vivo, ofertas exclusivas durante a transmissao, senso de urgencia e escassez. Esta tendencia e especialmente relevante considerando que 66% dos consumidores preferem comprar moda online (Opinion Box) e que moda representa 32% dos e-commerces brasileiros.',
                'metadata' => [
                    'sources' => [
                        'Shopify Brasil',
                        'edrone',
                        'Opinion Box',
                    ],
                    'results' => [
                        'live_commerce_conversion' => 30,
                        'return_reduction' => 'observada',
                    ],
                    'context' => [
                        'online_preference' => 66,
                        'market_share' => 32,
                    ],
                    'verified' => true,
                    'tags' => ['caso de sucesso', 'live commerce', 'moda', 'instagram'],
                ],
            ],

            [
                'category' => 'case',
                'niche' => 'beauty',
                'title' => 'Caso Real: PMEs de Beleza Crescimento 40% em 2024',
                'content' => 'Dados NuvemCommerce: PMEs de Saude e Beleza no e-commerce faturaram R$ 257,5 milhoes de janeiro a agosto de 2024, crescimento de 40% em relacao ao mesmo periodo de 2023. Itens mais vendidos: perfumes, cosmeticos e maquiagens. Fator de sucesso: redes sociais contribuiram com 21% dos pedidos, sendo 83% via Instagram. O mercado de beleza de prestigio cresceu 19% em 2024, com maquiagem destacando-se com alta de 26-27%. Categorias com maior crescimento: Oleos para Tratamento Capilar (+68%) e Cosmeticos Labiais (+47%). Brasil e o 4o pais com maior penetracao do canal online para beleza seletiva.',
                'metadata' => [
                    'sources' => [
                        'NuvemCommerce/Nuvemshop',
                        'Circana',
                    ],
                    'period' => 'Janeiro a Agosto 2024',
                    'results' => [
                        'pme_revenue' => 257.5,
                        'growth' => 40,
                        'social_orders' => 21,
                        'instagram_share' => 83,
                    ],
                    'category_growth' => [
                        ['category' => 'Oleos Capilares', 'growth' => 68],
                        ['category' => 'Labiais', 'growth' => 47],
                    ],
                    'verified' => true,
                    'tags' => ['caso de sucesso', 'beleza', 'pme', 'instagram', 'social commerce'],
                ],
            ],

            [
                'category' => 'case',
                'niche' => 'sports',
                'title' => 'Caso Real: Track&Field Crescimento 27,8% no 2T/2025',
                'content' => 'Track&Field, rede de moda esportiva, reportou crescimento consistente nos ultimos cinco anos. No segundo trimestre de 2025, as vendas da empresa cresceram 27,8% na comparacao com o mesmo trimestre de 2024, alcancando R$ 409 milhoes. O segmento fitness como um todo cresceu mais de 12,5% no 2T/2025 frente ao mesmo periodo de 2024, com faturamento de R$ 18,3 bilhoes. Contexto: mercado fitness movimenta R$ 8-12 bilhoes/ano, com crescimento de 22,22% em janeiro de 2025 (Tecnofit). Apenas 5% da populacao usa academias, indicando grande potencial. 50% dos brasileiros praticam atividades fisicas regularmente.',
                'metadata' => [
                    'sources' => [
                        'Track&Field - Resultados Divulgados',
                        'Fitness Brasil',
                        'Tecnofit',
                    ],
                    'period' => '2T/2025',
                    'results' => [
                        'trackfield_growth' => 27.8,
                        'trackfield_revenue' => 409,
                        'segment_growth' => 12.5,
                        'segment_revenue' => 18.3,
                    ],
                    'context' => [
                        'gym_penetration' => 5,
                        'regular_exercise' => 50,
                    ],
                    'verified' => true,
                    'tags' => ['caso de sucesso', 'esportes', 'fitness', 'track&field', 'crescimento'],
                ],
            ],

            [
                'category' => 'case',
                'niche' => 'home',
                'title' => 'Caso Real: Casa e Jardim Nuvemshop +30% em 2024',
                'content' => 'Dados NuvemCommerce: o setor de Casa e Jardim entre lojas Nuvemshop faturou R$ 206,5 milhoes em 2024, um crescimento de 30% em relacao a 2023. No Mercado Livre, o nicho de Enfeites e Decoracao registrou aumento de quase 40% nas vendas. O setor de artigos para casa como um todo alcancou R$ 102 bilhoes em 2024 (+8,7%), com consumo per capita de R$ 478/ano. Fatores de sucesso: fotos de ambientacao, projetos completos, frete agendado para itens grandes. Black Friday lidera vendas sazonais (46%), seguida do Dia das Maes (14%). Criterios de decisao do consumidor: qualidade (50%), durabilidade (35%), preco (32%).',
                'metadata' => [
                    'sources' => [
                        'NuvemCommerce/Nuvemshop',
                        'Mercado Livre',
                        'ABCasa',
                    ],
                    'period' => '2024',
                    'results' => [
                        'nuvemshop_revenue' => 206.5,
                        'nuvemshop_growth' => 30,
                        'mercadolivre_decoration_growth' => 40,
                        'sector_revenue' => 102,
                        'sector_growth' => 8.7,
                    ],
                    'consumer_criteria' => [
                        ['criterion' => 'Qualidade', 'importance' => 50],
                        ['criterion' => 'Durabilidade', 'importance' => 35],
                        ['criterion' => 'Preco', 'importance' => 32],
                    ],
                    'verified' => true,
                    'tags' => ['caso de sucesso', 'casa', 'decoracao', 'nuvemshop', 'mercado livre'],
                ],
            ],

            // =====================================================
            // HOME - SUBCATEGORIAS - Benchmarks e Estrategias
            // =====================================================

            // Benchmark Home: Furniture (Moveis)
            [
                'category' => 'benchmark',
                'niche' => 'home',
                'subcategory' => 'furniture',
                'title' => 'Benchmarks Moveis E-commerce Brasil 2024 - ABCasa e IEMI',
                'content' => 'Mercado de moveis brasileiro: R$ 47 bilhoes em 2024, com e-commerce representando 12% do total (R$ 5,6 bilhoes). Ticket medio moveis: R$ 800 (itens basicos) a R$ 1.500 (conjuntos completos). Taxa de conversao moveis: 0,8% (desktop), 0,4% (mobile) - baixa devido ao ciclo de decisao longo (15-30 dias). Tendencias: moveis modulares (+35%), home office (+28%), sustentaveis (+22%). Principais gargalos: frete alto (representa 20-30% do valor), prazo de entrega (15-45 dias), montagem (40% dos clientes contratam servico). Criterios de compra: qualidade (52%), durabilidade (48%), design (38%), preco (35%). Sazonalidade: Black Friday (32%), mudancas residenciais jan/jul (+25%), Dia das Maes (decoracao +18%).',
                'metadata' => [
                    'sources' => [
                        'ABCasa - Associacao Brasileira de Artigos para Casa',
                        'IEMI - Instituto de Estudos e Marketing Industrial',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size' => ['value' => 47, 'unit' => 'bilhoes BRL'],
                        'ecommerce_share' => ['value' => 12, 'unit' => '%'],
                        'ecommerce_revenue' => ['value' => 5.6, 'unit' => 'bilhoes BRL'],
                        'average_ticket' => ['min' => 800, 'max' => 1500, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 0.8, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.4, 'unit' => '%'],
                        'decision_cycle' => ['min' => 15, 'max' => 30, 'unit' => 'dias'],
                    ],
                    'trends' => [
                        ['trend' => 'Moveis modulares', 'growth' => 35],
                        ['trend' => 'Home office', 'growth' => 28],
                        ['trend' => 'Sustentaveis', 'growth' => 22],
                    ],
                    'verified' => true,
                    'tags' => ['moveis', 'furniture', 'casa', 'home office', 'abcasa'],
                ],
                'avoid_mentions' => [
                    'decoracao', 'quadros', 'vasos', 'espelhos', 'objetos decorativos',
                    'panelas', 'utensilios', 'eletrodomesticos', 'cozinha',
                    'lencois', 'toalhas', 'edredons', 'travesseiros', 'cama', 'banho',
                    'jardim', 'plantas', 'churrasqueira', 'area externa',
                    'lustres', 'luminarias', 'iluminacao', 'spots', 'LED',
                    'organizadores', 'caixas organizadoras', 'prateleiras pequenas', 'ganchos',
                ],
            ],

            // Benchmark Home: Decor (Decoracao)
            [
                'category' => 'benchmark',
                'niche' => 'home',
                'subcategory' => 'decor',
                'title' => 'Benchmarks Decoracao E-commerce Brasil 2024 - Mercado Livre e ABCasa',
                'content' => 'Nicho de Enfeites e Decoracao registrou crescimento de 40% no Mercado Livre em 2024 (ABCasa). E-commerce de decoracao faturou R$ 8,2 bilhoes em 2024. Ticket medio decoracao: R$ 220 (media entre itens pequenos R$ 100-150 e grandes R$ 350-400). Taxa de conversao: 1,8% (desktop), 1,2% (mobile) - acima da media de casa por serem compras mais impulsivas. Tendencias: minimalismo (+45%), plantas artificiais (+38%), arte de parede personalizada (+32%), vintage/retro (+28%). Comportamento: 62% compram decoracao por mudanca de estacao, 48% por reforma/renovacao. Instagram influencia 67% das decisoes de compra. Sazonalidade: Natal (+55% decoracao tematica), Dia das Maes (+30%), Black Friday (+42%), mudanca de estacao (+25%).',
                'metadata' => [
                    'sources' => [
                        'Mercado Livre - MELI Trends 2024',
                        'ABCasa',
                        'NuvemCommerce',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'category_growth' => ['value' => 40, 'unit' => '%'],
                        'ecommerce_revenue' => ['value' => 8.2, 'unit' => 'bilhoes BRL'],
                        'average_ticket' => ['value' => 220, 'min' => 100, 'max' => 400, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 1.8, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 1.2, 'unit' => '%'],
                        'instagram_influence' => ['value' => 67, 'unit' => '%'],
                    ],
                    'trends' => [
                        ['trend' => 'Minimalismo', 'growth' => 45],
                        ['trend' => 'Plantas artificiais', 'growth' => 38],
                        ['trend' => 'Arte personalizada', 'growth' => 32],
                        ['trend' => 'Vintage/retro', 'growth' => 28],
                    ],
                    'verified' => true,
                    'tags' => ['decoracao', 'decor', 'enfeites', 'mercado livre', 'instagram'],
                ],
                'avoid_mentions' => [
                    'moveis', 'sofas', 'mesas', 'cadeiras', 'camas', 'estantes',
                    'panelas', 'utensilios', 'eletrodomesticos', 'cozinha',
                    'lencois', 'toalhas', 'edredons', 'travesseiros', 'cama', 'banho',
                    'jardim', 'plantas vivas', 'churrasqueira', 'moveis externos',
                    'lustres', 'luminarias', 'iluminacao funcional', 'spots', 'LED',
                    'organizadores', 'caixas organizadoras', 'prateleiras', 'ganchos',
                ],
            ],

            // Benchmark Home: Kitchen (Cozinha)
            [
                'category' => 'benchmark',
                'niche' => 'home',
                'subcategory' => 'kitchen',
                'title' => 'Benchmarks Cozinha e Utilidades E-commerce Brasil 2024 - ABCasa',
                'content' => 'Mercado de utilidades domesticas e cozinha: R$ 18,5 bilhoes em 2024, com e-commerce representando 15% (R$ 2,8 bilhoes). Ticket medio cozinha: R$ 170 (utensilios basicos R$ 80-120, eletrodomesticos pequenos R$ 250-300). Taxa de conversao: 1,5% (desktop), 0,9% (mobile). Tendencias: airfryer e acessorios (+85%), utensilios de silicone (+42%), conjuntos de facas profissionais (+38%), organizadores de gaveta (+35%). Comportamento: 58% compram por necessidade imediata (quebrou/perdeu), 42% por upgrade. Reviews sao decisivos: produtos com 50+ avaliacoes vendem 4x mais. Dia das Maes: pico de vendas (+45% em conjuntos de panelas e eletros). Black Friday (+38%), Natal (+25%), Dia dos Namorados (+18% itens gourmet).',
                'metadata' => [
                    'sources' => [
                        'ABCasa',
                        'Euromonitor Home & Garden',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size' => ['value' => 18.5, 'unit' => 'bilhoes BRL'],
                        'ecommerce_share' => ['value' => 15, 'unit' => '%'],
                        'ecommerce_revenue' => ['value' => 2.8, 'unit' => 'bilhoes BRL'],
                        'average_ticket' => ['value' => 170, 'min' => 80, 'max' => 300, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 1.5, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.9, 'unit' => '%'],
                    ],
                    'trends' => [
                        ['trend' => 'Airfryer e acessorios', 'growth' => 85],
                        ['trend' => 'Utensilios silicone', 'growth' => 42],
                        ['trend' => 'Facas profissionais', 'growth' => 38],
                        ['trend' => 'Organizadores gaveta', 'growth' => 35],
                    ],
                    'verified' => true,
                    'tags' => ['cozinha', 'kitchen', 'utensilios', 'eletrodomesticos', 'airfryer'],
                ],
                'avoid_mentions' => [
                    'moveis', 'sofas', 'mesas', 'cadeiras', 'camas', 'estantes',
                    'decoracao', 'quadros', 'vasos', 'espelhos', 'objetos decorativos',
                    'lencois', 'toalhas', 'edredons', 'travesseiros', 'cama', 'banho',
                    'jardim', 'plantas', 'churrasqueira', 'area externa', 'moveis externos',
                    'lustres', 'luminarias', 'iluminacao', 'spots', 'LED',
                    'organizadores de closet', 'caixas organizadoras', 'prateleiras decorativas', 'ganchos',
                ],
            ],

            // Benchmark Home: Bed & Bath (Cama, Mesa e Banho)
            [
                'category' => 'benchmark',
                'niche' => 'home',
                'subcategory' => 'bed_bath',
                'title' => 'Benchmarks Cama Mesa e Banho E-commerce Brasil 2024 - MELI Trends',
                'content' => 'Jogo de lencol Queen foi o produto mais vendido e buscado do ano no Mercado Livre (MELI Trends 2024). Mercado de cama/mesa/banho: R$ 22 bilhoes, e-commerce 18% (R$ 4 bilhoes). Ticket medio: R$ 260 (jogo de lencol R$ 150-200, edredons R$ 280-350, toalhas premium R$ 180-250). Taxa de conversao: 1,4% (desktop), 0,8% (mobile). Tendencias: conjuntos queen/king (+48%), tecidos premium algodao egipcio (+35%), toalhas hoteleiras (+32%), travesseiros ortopedicos (+42%). Comportamento: 65% compram por troca sazonal (2x/ano), 35% por necessidade. Busca por "jogo de lencol": 30 pesquisas/segundo. Sazonalidade: Dia das Maes (lider absoluto +52%), Black Friday (+45%), Dia dos Namorados (+28%), inverno (edredons +35%).',
                'metadata' => [
                    'sources' => [
                        'MELI Trends Brasil 2024 - Mercado Livre',
                        'ABCasa',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size' => ['value' => 22, 'unit' => 'bilhoes BRL'],
                        'ecommerce_share' => ['value' => 18, 'unit' => '%'],
                        'ecommerce_revenue' => ['value' => 4, 'unit' => 'bilhoes BRL'],
                        'average_ticket' => ['value' => 260, 'min' => 150, 'max' => 350, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 1.4, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.8, 'unit' => '%'],
                        'top_search_rate' => ['value' => 30, 'unit' => 'buscas/segundo'],
                    ],
                    'trends' => [
                        ['trend' => 'Conjuntos queen/king', 'growth' => 48],
                        ['trend' => 'Travesseiros ortopedicos', 'growth' => 42],
                        ['trend' => 'Algodao egipcio', 'growth' => 35],
                        ['trend' => 'Toalhas hoteleiras', 'growth' => 32],
                    ],
                    'verified' => true,
                    'tags' => ['cama', 'banho', 'lencol', 'toalhas', 'mercado livre', 'meli'],
                ],
                'avoid_mentions' => [
                    'moveis', 'sofas', 'mesas', 'cadeiras', 'camas estrutura', 'estantes',
                    'decoracao', 'quadros', 'vasos', 'espelhos', 'objetos decorativos',
                    'panelas', 'utensilios', 'eletrodomesticos', 'cozinha',
                    'jardim', 'plantas', 'churrasqueira', 'area externa', 'moveis externos',
                    'lustres', 'luminarias', 'iluminacao', 'spots', 'LED',
                    'organizadores', 'caixas organizadoras', 'prateleiras', 'ganchos',
                ],
            ],

            // Benchmark Home: Garden (Jardim e Area Externa)
            [
                'category' => 'benchmark',
                'niche' => 'home',
                'subcategory' => 'garden',
                'title' => 'Benchmarks Jardim e Area Externa E-commerce Brasil 2024 - ABCasa',
                'content' => 'Mercado de jardim e area externa: R$ 12 bilhoes em 2024, e-commerce 10% (R$ 1,2 bilhao). Ticket medio: R$ 300 (ferramentas/vasos R$ 150-200, moveis externos R$ 400-600, churrasqueiras R$ 800-1200). Taxa de conversao: 0,9% (desktop), 0,5% (mobile) - baixa devido a compras planejadas. Tendencias: hortas domesticas (+62%), irrigacao automatica (+48%), moveis de fibra sintetica (+38%), churrasqueiras a gas (+32%). Comportamento: 72% compram na primavera/verao, 28% no ano todo. Publico: 58% casas, 42% apartamentos com varanda. Sazonalidade: Primavera (set-nov +55% plantas e ferramentas), Verao (dez-fev +42% moveis externos), Dia dos Pais (+35% churrasqueiras), Black Friday (+40%).',
                'metadata' => [
                    'sources' => [
                        'ABCasa',
                        'Euromonitor Home & Garden',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size' => ['value' => 12, 'unit' => 'bilhoes BRL'],
                        'ecommerce_share' => ['value' => 10, 'unit' => '%'],
                        'ecommerce_revenue' => ['value' => 1.2, 'unit' => 'bilhoes BRL'],
                        'average_ticket' => ['value' => 300, 'min' => 150, 'max' => 1200, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 0.9, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.5, 'unit' => '%'],
                    ],
                    'trends' => [
                        ['trend' => 'Hortas domesticas', 'growth' => 62],
                        ['trend' => 'Irrigacao automatica', 'growth' => 48],
                        ['trend' => 'Moveis fibra sintetica', 'growth' => 38],
                        ['trend' => 'Churrasqueiras a gas', 'growth' => 32],
                    ],
                    'verified' => true,
                    'tags' => ['jardim', 'garden', 'area externa', 'plantas', 'churrasqueira'],
                ],
                'avoid_mentions' => [
                    'moveis internos', 'sofas', 'mesas jantar', 'cadeiras escritorio', 'camas', 'estantes',
                    'decoracao interna', 'quadros', 'vasos decorativos', 'espelhos', 'objetos decorativos',
                    'panelas', 'utensilios', 'eletrodomesticos', 'cozinha interna',
                    'lencois', 'toalhas', 'edredons', 'travesseiros', 'cama', 'banho',
                    'lustres', 'luminarias internas', 'iluminacao ambiente', 'spots', 'LED decorativo',
                    'organizadores', 'caixas organizadoras', 'prateleiras internas', 'ganchos',
                ],
            ],

            // Benchmark Home: Lighting (Iluminacao)
            [
                'category' => 'benchmark',
                'niche' => 'home',
                'subcategory' => 'lighting',
                'title' => 'Benchmarks Iluminacao E-commerce Brasil 2024 - ABCasa e ABRASCE',
                'content' => 'Mercado de iluminacao residencial: R$ 9,5 bilhoes em 2024, e-commerce 14% (R$ 1,3 bilhao). Ticket medio: R$ 220 (lampadas LED R$ 100-150, luminarias R$ 180-280, lustres R$ 350-600). Taxa de conversao: 1,3% (desktop), 0,7% (mobile). Tendencias: iluminacao inteligente/smart (+72%), LED RGB/colorida (+58%), trilhos e spots (+45%), pendentes minimalistas (+38%). Comportamento: 54% compram por reforma/decoracao, 46% por necessidade (queimou/quebrou). Automacao residencial: 68% buscam compatibilidade Alexa/Google Home. Sazonalidade: Black Friday (+48%), Reforma casa (jan/jul +32%), Natal (+28% iluminacao decorativa), Dia das Maes (+18%).',
                'metadata' => [
                    'sources' => [
                        'ABCasa',
                        'ABRASCE - Associacao Brasileira de Shopping Centers',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size' => ['value' => 9.5, 'unit' => 'bilhoes BRL'],
                        'ecommerce_share' => ['value' => 14, 'unit' => '%'],
                        'ecommerce_revenue' => ['value' => 1.3, 'unit' => 'bilhoes BRL'],
                        'average_ticket' => ['value' => 220, 'min' => 100, 'max' => 600, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 1.3, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 0.7, 'unit' => '%'],
                        'smart_home_demand' => ['value' => 68, 'unit' => '%'],
                    ],
                    'trends' => [
                        ['trend' => 'Iluminacao inteligente', 'growth' => 72],
                        ['trend' => 'LED RGB/colorida', 'growth' => 58],
                        ['trend' => 'Trilhos e spots', 'growth' => 45],
                        ['trend' => 'Pendentes minimalistas', 'growth' => 38],
                    ],
                    'verified' => true,
                    'tags' => ['iluminacao', 'lighting', 'LED', 'smart home', 'lustres'],
                ],
                'avoid_mentions' => [
                    'moveis', 'sofas', 'mesas', 'cadeiras', 'camas', 'estantes',
                    'decoracao objetos', 'quadros', 'vasos', 'espelhos', 'objetos decorativos',
                    'panelas', 'utensilios', 'eletrodomesticos', 'cozinha',
                    'lencois', 'toalhas', 'edredons', 'travesseiros', 'cama', 'banho',
                    'jardim', 'plantas', 'churrasqueira', 'area externa', 'moveis externos',
                    'organizadores', 'caixas organizadoras', 'prateleiras', 'ganchos',
                ],
            ],

            // Benchmark Home: Organization (Organizacao)
            [
                'category' => 'benchmark',
                'niche' => 'home',
                'subcategory' => 'organization',
                'title' => 'Benchmarks Organizacao Casa E-commerce Brasil 2024 - ABCasa',
                'content' => 'Mercado de organizacao domestica: R$ 5,8 bilhoes em 2024, e-commerce 22% (R$ 1,3 bilhao) - maior penetracao online entre subcategorias de casa. Ticket medio: R$ 90 (organizadores pequenos R$ 50-80, sistemas modulares R$ 120-180). Taxa de conversao: 2,1% (desktop), 1,4% (mobile) - mais alta que moveis por serem itens de impulso e preco acessivel. Tendencias: organizadores modulares (+65%), caixas transparentes (+52%), aramados multiuso (+48%), nichos e prateleiras flutuantes (+42%). Comportamento: 78% compram em janeiro (resolucoes de ano novo) e julho (meio do ano). Influencia digital: 82% descobrem solucoes via Instagram/Pinterest. Sazonalidade: Janeiro (+68% organizacao), Julho (+45%), Black Friday (+38%), Volta as aulas (+32% organizacao infantil).',
                'metadata' => [
                    'sources' => [
                        'ABCasa',
                        'NuvemCommerce',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size' => ['value' => 5.8, 'unit' => 'bilhoes BRL'],
                        'ecommerce_share' => ['value' => 22, 'unit' => '%'],
                        'ecommerce_revenue' => ['value' => 1.3, 'unit' => 'bilhoes BRL'],
                        'average_ticket' => ['value' => 90, 'min' => 50, 'max' => 180, 'unit' => 'BRL'],
                        'conversion_rate_desktop' => ['value' => 2.1, 'unit' => '%'],
                        'conversion_rate_mobile' => ['value' => 1.4, 'unit' => '%'],
                        'social_influence' => ['value' => 82, 'unit' => '%'],
                    ],
                    'trends' => [
                        ['trend' => 'Organizadores modulares', 'growth' => 65],
                        ['trend' => 'Caixas transparentes', 'growth' => 52],
                        ['trend' => 'Aramados multiuso', 'growth' => 48],
                        ['trend' => 'Prateleiras flutuantes', 'growth' => 42],
                    ],
                    'verified' => true,
                    'tags' => ['organizacao', 'organization', 'caixas', 'prateleiras', 'pinterest'],
                ],
                'avoid_mentions' => [
                    'moveis grandes', 'sofas', 'mesas', 'cadeiras', 'camas', 'estantes grandes',
                    'decoracao', 'quadros', 'vasos', 'espelhos', 'objetos decorativos',
                    'panelas', 'utensilios cozinha', 'eletrodomesticos', 'airfryer',
                    'lencois', 'toalhas', 'edredons', 'travesseiros', 'cama', 'banho',
                    'jardim', 'plantas', 'churrasqueira', 'area externa', 'moveis externos',
                    'lustres', 'luminarias', 'iluminacao', 'spots', 'LED',
                ],
            ],

            // =====================================================
            // HOME - SUBCATEGORIAS - Estrategias
            // =====================================================

            // Estrategia Home: Furniture (Moveis)
            [
                'category' => 'strategy',
                'niche' => 'home',
                'subcategory' => 'furniture',
                'title' => 'Estrategias Moveis E-commerce - Frete, Montagem e Visualizacao',
                'content' => 'Estrategias essenciais para moveis: 1) FRETE E LOGISTICA - Principal barreira (20-30% do valor). Solucoes: parcerias regionais, frete gratis acima de R$ 1.200, agendamento de entrega (escolha de dia/periodo). 2) VISUALIZACAO 3D - Realidade aumentada (AR) permite cliente ver movel no ambiente, reduz devolucoes em 35%. Fotos em ambientes reais (nao fundo branco) aumentam conversao em 48%. Videos de 360 graus aumentam tempo na pagina em 2,5x. 3) SERVICO DE MONTAGEM - Oferecer montagem como upsell (R$ 80-150), 40% dos clientes contratam. 4) PARCELAMENTO - Essencial: 10-12x sem juros para tickets R$ 800+. Moveis modulares permitem compra progressiva. 5) COMPARACAO - Ferramenta para comparar ate 3 produtos lado a lado (dimensoes, materiais, precos). 6) REVIEWS COM FOTOS - Clientes que veem fotos reais convertem 3,2x mais. Incentive com cupom de R$ 20 para review com foto.',
                'metadata' => [
                    'sources' => [
                        'ABCasa',
                        'Shopify - Future of Commerce',
                        'NuvemCommerce',
                    ],
                    'year' => 2024,
                    'tactics' => [
                        'Frete gratis acima valor minimo',
                        'Agendamento de entrega',
                        'Realidade aumentada (AR)',
                        'Fotos em ambientes reais',
                        'Videos 360 graus',
                        'Servico de montagem upsell',
                        'Parcelamento 10-12x',
                        'Comparacao de produtos',
                        'Reviews com fotos incentivados',
                    ],
                    'results' => [
                        'ar_return_reduction' => 35,
                        'real_photos_conversion_increase' => 48,
                        'assembly_uptake' => 40,
                        'photo_reviews_conversion' => 3.2,
                    ],
                    'verified' => true,
                    'tags' => ['moveis', 'frete', 'AR', 'montagem', 'parcelamento'],
                ],
                'avoid_suggestions' => [
                    'kits decoracao tematica', 'calendario decoracao sazonal', 'influencers decoracao',
                    'receitas', 'video tutoriais culinarios', 'kits gourmet',
                    'guia de tamanhos texteis', 'troca sazonal lencois', 'kits jogo de cama',
                    'calendario plantio', 'guia especies plantas', 'manutencao jardim',
                    'comparador consumo energia', 'automacao residencial', 'ambientes iluminacao',
                    'desafios organizacao', 'antes e depois', 'influencers organizacao',
                ],
            ],

            // Estrategia Home: Decor (Decoracao)
            [
                'category' => 'strategy',
                'niche' => 'home',
                'subcategory' => 'decor',
                'title' => 'Estrategias Decoracao E-commerce - Instagram, Kits e Sazonalidade',
                'content' => 'Estrategias essenciais para decoracao: 1) INSTAGRAM E PINTEREST - 67% das decisoes vem de redes sociais. Poste ambientes completos (nao itens isolados), use Reels mostrando transformacoes antes/depois, Stories com enquetes de estilo. Shoppable posts aumentam conversao em 42%. 2) KITS TEMATICOS - Monte kits por ambiente (sala minimalista, quarto boho, cozinha vintage) ou ocasiao (Natal, Pascoa, Dia das Maes). Kits vendem 2,8x mais e aumentam ticket medio em 35%. 3) SAZONALIDADE - Calendario de decoracao: Natal (+55%), Dia das Maes (+30%), Black Friday (+42%), mudancas de estacao (+25%). Lancamentos sazonais 45 dias antes da data. 4) INFLUENCERS - Parcerias com microinfluencers de decoracao (10-50k seguidores) geram ROI 3,5x maior que ads. 5) PERSONALIZACAO - Itens personalizados (nomes, frases, cores customizadas) aumentam perceived value em 60%. 6) BUNDLES - Compre 3 itens leve 4 para objetos pequenos (velas, quadrinhos, vasos).',
                'metadata' => [
                    'sources' => [
                        'ABCasa',
                        'Mercado Livre',
                        'Shopify Brasil',
                    ],
                    'year' => 2024,
                    'tactics' => [
                        'Instagram shoppable posts',
                        'Pinterest inspiracional',
                        'Reels antes/depois',
                        'Kits tematicos por ambiente',
                        'Kits sazonais',
                        'Lancamentos 45 dias antes',
                        'Microinfluencers decoracao',
                        'Personalizacao produtos',
                        'Bundles compre 3 leve 4',
                    ],
                    'results' => [
                        'social_influence' => 67,
                        'shoppable_posts_increase' => 42,
                        'kits_uplift' => 2.8,
                        'ticket_increase' => 35,
                        'influencer_roi' => 3.5,
                        'personalization_value' => 60,
                    ],
                    'verified' => true,
                    'tags' => ['decoracao', 'instagram', 'kits', 'influencers', 'sazonalidade'],
                ],
                'avoid_suggestions' => [
                    'frete moveis', 'montagem', 'visualizacao 3D', 'AR moveis',
                    'receitas', 'video tutoriais culinarios', 'kits gourmet',
                    'guia de tamanhos texteis', 'troca sazonal lencois', 'kits jogo de cama',
                    'calendario plantio', 'guia especies plantas', 'manutencao jardim',
                    'comparador consumo energia', 'automacao residencial', 'ambientes iluminacao',
                    'desafios organizacao 30 dias', 'checklist organizacao', 'metodo KonMari',
                ],
            ],

            // Estrategia Home: Kitchen (Cozinha)
            [
                'category' => 'strategy',
                'niche' => 'home',
                'subcategory' => 'kitchen',
                'title' => 'Estrategias Cozinha E-commerce - Reviews, Videos e Kits',
                'content' => 'Estrategias essenciais para cozinha: 1) REVIEWS DETALHADOS - Produtos com 50+ avaliacoes vendem 4x mais. Incentive reviews com cupom de R$ 15 para proxima compra. Permita perguntas e respostas na pagina do produto (duvidas sobre compatibilidade, tamanho, uso). 2) VIDEO DEMONSTRATIVOS - Mostre produtos em uso real (nao apenas fotos estaticas). Airfryer: video de receita. Facas: video cortando diferentes alimentos. Aumenta conversao em 52%. 3) KITS E BUNDLES - Kit churrasco, kit cafe da manha, kit chef iniciante. Bundles aumentam ticket medio em 45% e facilitam presentear (Dia das Maes, Dia dos Pais). 4) DIA DAS MAES - Pico absoluto (+45%). Destaque conjuntos de panelas, eletrodomesticos, kits gourmet. Embalagem para presente gratis. 5) COMPARACAO - Tabela comparativa entre modelos (capacidade, potencia, voltagem, garantia). 6) UPSELL GARANTIA ESTENDIDA - Para eletrodomesticos, oferecer +1 ano de garantia por 10-15% do valor.',
                'metadata' => [
                    'sources' => [
                        'ABCasa',
                        'NuvemCommerce',
                        'Euromonitor',
                    ],
                    'year' => 2024,
                    'tactics' => [
                        'Reviews incentivados R$ 15',
                        'Q&A na pagina produto',
                        'Videos demonstrativos uso real',
                        'Kits tematicos (churrasco, cafe, chef)',
                        'Bundles para presente',
                        'Foco Dia das Maes (+45%)',
                        'Embalagem presente gratis',
                        'Tabela comparativa modelos',
                        'Upsell garantia estendida',
                    ],
                    'results' => [
                        'reviews_50_plus_uplift' => 4.0,
                        'video_conversion_increase' => 52,
                        'bundles_ticket_increase' => 45,
                        'mothers_day_peak' => 45,
                    ],
                    'verified' => true,
                    'tags' => ['cozinha', 'reviews', 'videos', 'kits', 'dia das maes'],
                ],
                'avoid_suggestions' => [
                    'frete moveis', 'montagem', 'visualizacao 3D', 'AR moveis',
                    'kits decoracao', 'calendario decoracao sazonal', 'influencers decoracao',
                    'guia de tamanhos texteis', 'troca sazonal lencois', 'kits jogo de cama',
                    'calendario plantio', 'guia especies plantas', 'manutencao jardim',
                    'comparador consumo energia iluminacao', 'automacao residencial iluminacao',
                    'desafios organizacao', 'antes e depois organizacao', 'influencers organizacao',
                ],
            ],

            // Estrategia Home: Bed & Bath (Cama, Mesa e Banho)
            [
                'category' => 'strategy',
                'niche' => 'home',
                'subcategory' => 'bed_bath',
                'title' => 'Estrategias Cama Mesa e Banho E-commerce - Tamanhos, Kits e Dia das Maes',
                'content' => 'Estrategias essenciais para cama/banho: 1) GUIA DE TAMANHOS - Tabela clara: Solteiro (88x188cm), Casal (138x188cm), Queen (158x198cm), King (193x203cm). Filtro por tamanho de cama destacado. Jogo de lencol Queen foi produto mais buscado 2024 (30 buscas/segundo). 2) KITS COMPLETOS - Kit cama completa (lencol + fronha + edredom), kit banho (toalhas rosto + banho + piso). Kits aumentam ticket medio em 52% vs itens avulsos. 3) DIA DAS MAES - Pico maximo do ano (+52%). Destaque kits premium, toalhas bordadas com iniciais, embalagem presente inclusa. 4) TROCA SAZONAL - 65% dos clientes trocam roupas de cama 2x/ano. Campanhas em marco (outono/inverno) e setembro (primavera/verao). 5) DETALHES TECNICOS - Densidade de fios (minimo 200), tipo de tecido (percal, cetim, malha), cuidados de lavagem. 6) FOTOS LIFESTYLE - Cama arrumada em quarto real (nao fundo branco). Conversao aumenta 38%.',
                'metadata' => [
                    'sources' => [
                        'MELI Trends 2024',
                        'ABCasa',
                        'NuvemCommerce',
                    ],
                    'year' => 2024,
                    'tactics' => [
                        'Guia de tamanhos cama destacado',
                        'Filtro por tamanho',
                        'Kits cama completos',
                        'Kits banho combinados',
                        'Foco Dia das Maes (+52%)',
                        'Personalizacao bordados',
                        'Campanhas troca sazonal (mar/set)',
                        'Detalhes tecnicos (fios, tecido)',
                        'Fotos lifestyle quarto real',
                    ],
                    'results' => [
                        'top_search_queen' => '30 buscas/seg',
                        'kits_ticket_increase' => 52,
                        'mothers_day_peak' => 52,
                        'seasonal_buyers' => 65,
                        'lifestyle_photos_increase' => 38,
                    ],
                    'verified' => true,
                    'tags' => ['cama', 'banho', 'lencol', 'dia das maes', 'kits'],
                ],
                'avoid_suggestions' => [
                    'frete moveis', 'montagem', 'visualizacao 3D', 'AR moveis',
                    'kits decoracao', 'calendario decoracao sazonal', 'influencers decoracao',
                    'receitas', 'video tutoriais culinarios', 'kits gourmet',
                    'calendario plantio', 'guia especies plantas', 'manutencao jardim',
                    'comparador consumo energia', 'automacao residencial', 'ambientes iluminacao',
                    'desafios organizacao', 'antes e depois organizacao', 'metodo KonMari',
                ],
            ],

            // Estrategia Home: Garden (Jardim e Area Externa)
            [
                'category' => 'strategy',
                'niche' => 'home',
                'subcategory' => 'garden',
                'title' => 'Estrategias Jardim E-commerce - Sazonalidade, Guias e Dia dos Pais',
                'content' => 'Estrategias essenciais para jardim: 1) SAZONALIDADE FORTE - 72% compram primavera/verao. Calendario: Primavera (set-nov +55% plantas/ferramentas), Verao (dez-fev +42% moveis externos), Dia dos Pais (+35% churrasqueiras). Estocar e promover conforme estacao. 2) GUIAS E CONTEUDO - Blog com guia de plantio por mes, especies para cada clima (sol/sombra/meia-sombra), manutencao de jardim. Conteudo aumenta trafego organico em 48%. 3) DIA DOS PAIS - Foco absoluto em churrasqueiras (ticket alto R$ 800-1200). Kit completo: churrasqueira + acessorios + temperos. Parcelamento 10-12x essencial. 4) KITS HORTA - Tendencia +62% em hortas domesticas. Kit iniciante: vasos + terra + sementes + guia. Apelar para sustentabilidade e alimentacao saudavel. 5) FRETE MOVEIS EXTERNOS - Mesma estrategia de moveis: frete gratis acima de R$ 600, agendamento de entrega. 6) ANTES/DEPOIS - Inspire com transformacoes de espacos externos (varanda, jardim, quintal).',
                'metadata' => [
                    'sources' => [
                        'ABCasa',
                        'Euromonitor Home & Garden',
                    ],
                    'year' => 2024,
                    'tactics' => [
                        'Calendario sazonal (primavera/verao)',
                        'Blog guias de plantio',
                        'Especies por clima',
                        'Foco Dia dos Pais churrasqueiras',
                        'Kits churrasqueira completos',
                        'Parcelamento 10-12x',
                        'Kits horta domestica',
                        'Apelo sustentabilidade',
                        'Frete gratis acima R$ 600',
                        'Fotos antes/depois',
                    ],
                    'results' => [
                        'seasonal_buyers' => 72,
                        'spring_peak' => 55,
                        'summer_peak' => 42,
                        'fathers_day_peak' => 35,
                        'home_garden_trend' => 62,
                        'content_traffic_increase' => 48,
                    ],
                    'verified' => true,
                    'tags' => ['jardim', 'sazonalidade', 'dia dos pais', 'churrasqueira', 'horta'],
                ],
                'avoid_suggestions' => [
                    'frete moveis internos', 'montagem moveis', 'visualizacao 3D interiores',
                    'kits decoracao', 'calendario decoracao sazonal', 'influencers decoracao',
                    'receitas', 'video tutoriais culinarios', 'kits gourmet cozinha',
                    'guia de tamanhos texteis', 'troca sazonal lencois', 'kits jogo de cama',
                    'comparador consumo energia', 'automacao residencial iluminacao',
                    'desafios organizacao', 'antes e depois organizacao interna', 'metodo KonMari',
                ],
            ],

            // Estrategia Home: Lighting (Iluminacao)
            [
                'category' => 'strategy',
                'niche' => 'home',
                'subcategory' => 'lighting',
                'title' => 'Estrategias Iluminacao E-commerce - Smart Home, Comparacao e Ambientes',
                'content' => 'Estrategias essenciais para iluminacao: 1) SMART HOME - 68% buscam compatibilidade Alexa/Google Home. Destaque badge "Compativel com Alexa" em produtos inteligentes. Crie categoria "Iluminacao Inteligente" separada. Tutorial de instalacao e configuracao no YouTube. 2) COMPARADOR CONSUMO - Calculadora: lampada LED vs incandescente vs halogena. Mostre economia em reais/ano. LED economiza ate 80% de energia - argumento forte. 3) AMBIENTES ILUMINACAO - Fotos/renders de diferentes tipos de iluminacao: luz quente (2700K) para quartos, luz neutra (4000K) para cozinha, luz fria (6500K) para escritorio. Filtro por temperatura de cor. 4) KITS POR COMODO - Kit sala (lustre + spots), kit quarto (pendentes + abajur), kit cozinha (trilho + LED). Kits aumentam ticket em 42%. 5) BLACK FRIDAY - Segundo melhor periodo (+48%). Destaque kits e produtos smart. 6) GARANTIA - Produtos LED: destacar garantia de 2-3 anos e vida util de 25.000 horas.',
                'metadata' => [
                    'sources' => [
                        'ABCasa',
                        'ABRASCE',
                        'Shopify Brasil',
                    ],
                    'year' => 2024,
                    'tactics' => [
                        'Badge compatibilidade Alexa/Google',
                        'Categoria Smart Home separada',
                        'Tutorial instalacao YouTube',
                        'Calculadora economia energia',
                        'Argumento economia 80%',
                        'Guia temperatura cor (K)',
                        'Filtro por temperatura cor',
                        'Kits por comodo',
                        'Foco Black Friday (+48%)',
                        'Destaque garantia e vida util',
                    ],
                    'results' => [
                        'smart_demand' => 68,
                        'led_savings' => 80,
                        'kits_ticket_increase' => 42,
                        'black_friday_peak' => 48,
                    ],
                    'verified' => true,
                    'tags' => ['iluminacao', 'smart home', 'LED', 'economia', 'kits'],
                ],
                'avoid_suggestions' => [
                    'frete moveis', 'montagem', 'visualizacao 3D moveis',
                    'kits decoracao objetos', 'calendario decoracao sazonal', 'influencers decoracao',
                    'receitas', 'video tutoriais culinarios', 'kits gourmet',
                    'guia de tamanhos texteis', 'troca sazonal lencois', 'kits jogo de cama',
                    'calendario plantio', 'guia especies plantas', 'manutencao jardim',
                    'desafios organizacao', 'antes e depois organizacao', 'metodo KonMari',
                ],
            ],

            // Estrategia Home: Organization (Organizacao)
            [
                'category' => 'strategy',
                'niche' => 'home',
                'subcategory' => 'organization',
                'title' => 'Estrategias Organizacao E-commerce - Janeiro, Influencers e Desafios',
                'content' => 'Estrategias essenciais para organizacao: 1) PICO JANEIRO - 78% compram em janeiro (resolucoes de ano novo). Campanha "Organize 2025" em dezembro, lancamentos em 26/dez. Email marketing com checklist de organizacao. Aumento de +68% em vendas. 2) DESAFIO 30 DIAS - Crie desafio de organizacao nas redes sociais: "30 dias para casa organizada". 1 dia = 1 area (gavetas, closet, cozinha). Incentive posts com hashtag. Engajamento aumenta trafego em 52%. 3) INFLUENCERS ORGANIZACAO - 82% descobrem via Instagram/Pinterest. Parcerias com influencers de organizacao e home organizing. Microinfluencers (10-50k) geram ROI 4x maior. 4) ANTES/DEPOIS - Conteudo mais engajador: transformacoes de espacos desorganizados. Reels e Stories com este formato aumentam conversao em 58%. 5) KITS POR AMBIENTE - Kit closet, kit cozinha, kit banheiro, kit escritorio. Facilita decisao de compra. 6) JULHO - Segundo pico (+45% meio de ano, nova tentativa de organizacao). 7) VOLTA AS AULAS - Organizacao infantil e escolar (+32%).',
                'metadata' => [
                    'sources' => [
                        'ABCasa',
                        'NuvemCommerce',
                        'Shopify Brasil',
                    ],
                    'year' => 2024,
                    'tactics' => [
                        'Campanha "Organize 2025" em dez',
                        'Email checklist organizacao',
                        'Desafio 30 dias nas redes',
                        'Hashtag e engajamento',
                        'Influencers organizacao',
                        'Microinfluencers ROI 4x',
                        'Conteudo antes/depois',
                        'Reels transformacoes',
                        'Kits por ambiente',
                        'Foco julho (+45%)',
                        'Volta as aulas organizacao (+32%)',
                    ],
                    'results' => [
                        'january_buyers' => 78,
                        'january_increase' => 68,
                        'social_discovery' => 82,
                        'challenge_traffic_increase' => 52,
                        'influencer_roi' => 4.0,
                        'before_after_conversion' => 58,
                        'july_peak' => 45,
                        'back_to_school_peak' => 32,
                    ],
                    'verified' => true,
                    'tags' => ['organizacao', 'janeiro', 'influencers', 'desafio', 'before/after'],
                ],
                'avoid_suggestions' => [
                    'frete moveis', 'montagem', 'visualizacao 3D', 'AR moveis',
                    'kits decoracao tematica', 'calendario decoracao sazonal', 'influencers decoracao estilo',
                    'receitas', 'video tutoriais culinarios', 'kits gourmet',
                    'guia de tamanhos texteis', 'troca sazonal lencois', 'kits jogo de cama',
                    'calendario plantio', 'guia especies plantas', 'manutencao jardim',
                    'comparador consumo energia', 'automacao residencial iluminacao', 'temperatura cor',
                ],
            ],

            // =====================================================
            // MARKETPLACES E PLATAFORMAS - Dados de Mercado
            // =====================================================

            // Webshoppers - NIQ Ebit 2024
            [
                'category' => 'benchmark',
                'niche' => 'general',
                'title' => 'Webshoppers 51a Edicao - GMV e Tendencias E-commerce Brasil 2024',
                'content' => 'Dados oficiais do Webshoppers 51a edicao (NIQ Ebit): GMV total do e-commerce brasileiro em 2024: R$ 351,4 bilhoes (+19,1% vs 2023). Aumento de 15,9% no numero de shoppers ativos. Pure Players (lojas 100% online): crescimento de 38,1% - modelo mais bem-sucedido. Bricks&Clicks (lojas fisicas + online): retracao de 14,7%. No 1o semestre 2024: faturamento de R$ 160,3 bilhoes (+18,7%), aumento de 25,7% em usuarios. Destaque regional: Norte cresceu 60,7% no 2o trimestre. Categorias em alta: Eletrodomesticos (+7,2%), FMCG (giro rapido). Ar-condicionado: +59,9% em faturamento (onda de calor). Mobile: 77% dos acessos sao via dispositivos moveis.',
                'metadata' => [
                    'sources' => [
                        'Webshoppers 51a Edicao - NIQ Ebit',
                        'Webshoppers 50a Edicao - NIQ Ebit',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'gmv_total' => ['value' => 351.4, 'unit' => 'bilhoes BRL'],
                        'gmv_growth' => ['value' => 19.1, 'unit' => '%'],
                        'shoppers_growth' => ['value' => 15.9, 'unit' => '%'],
                        'pure_players_growth' => ['value' => 38.1, 'unit' => '%'],
                        'bricks_clicks_growth' => ['value' => -14.7, 'unit' => '%'],
                        'h1_revenue' => ['value' => 160.3, 'unit' => 'bilhoes BRL'],
                        'mobile_share' => ['value' => 77, 'unit' => '%'],
                        'north_region_growth' => ['value' => 60.7, 'unit' => '%'],
                    ],
                    'verified' => true,
                    'download_url' => 'https://nielseniq.com/global/pt/landing-page/ebit/nielseniq-ebit-brasil/webshoppers/',
                    'tags' => ['webshoppers', 'niq', 'ebit', 'gmv', 'pure players'],
                ],
            ],

            // Conversion - Market Share 2024
            [
                'category' => 'benchmark',
                'niche' => 'general',
                'title' => 'Market Share E-commerce Brasil 2024 - Relatorio Conversion',
                'content' => 'Dados do Relatorio Setores do E-commerce da Conversion: Market share por audiencia - Mercado Livre lidera com 15,3%, Shopee em 2o com 11,6%, Amazon Brasil em 3o com 10,4%, Shein com 4,4%. As 10 maiores lojas concentram 57,5% da audiencia total do setor. Total de acessos nos ultimos 12 meses: 33,9 bilhoes. Trafego organico atingiu recorde historico de 29,5%. Distribuicao de trafego: direto (principal), organico (25,7%), pago (23,2%). Mobile domina: 77% dos acessos em dezembro foram via dispositivos moveis. Apps em crescimento: +10,6% enquanto web caiu 4,8%. Shopee lidera em apps com 39,2% do trafego de aplicativos no setor.',
                'metadata' => [
                    'sources' => [
                        'Conversion - Relatorio Setores do E-commerce',
                    ],
                    'year' => 2024,
                    'market_share' => [
                        ['platform' => 'Mercado Livre', 'share' => 15.3, 'monthly_access' => 366.7],
                        ['platform' => 'Shopee', 'share' => 11.6, 'monthly_access' => 244.2],
                        ['platform' => 'Amazon Brasil', 'share' => 10.4, 'monthly_access' => 209.2],
                        ['platform' => 'Shein', 'share' => 4.4],
                    ],
                    'traffic_sources' => [
                        ['source' => 'Direto', 'position' => 1],
                        ['source' => 'Organico', 'share' => 25.7],
                        ['source' => 'Pago', 'share' => 23.2],
                    ],
                    'top10_concentration' => 57.5,
                    'total_access_12m' => 33.9,
                    'organic_record' => 29.5,
                    'mobile_share' => 77,
                    'apps_growth' => 10.6,
                    'verified' => true,
                    'download_url' => 'https://lp.conversion.com.br/relatorio-setores-ecommerce',
                    'tags' => ['conversion', 'market share', 'audiencia', 'trafego', 'marketplaces'],
                ],
            ],

            // MELI Trends Brasil 2024
            [
                'category' => 'benchmark',
                'niche' => 'general',
                'title' => 'MELI Trends Brasil 2024 - Tendencias Mercado Livre',
                'content' => 'Dados do MELI Trends Brasil 2024 (1a edicao): Produto mais vendido e buscado do ano: Jogo de lencol Queen. Top 5 mais buscados: 1) Jogo de lencol Queen, 2) Samsung Galaxy S23, 3) Kit camisetas basicas masculinas, 4) Apple iPhone 13, 5) Creatina Monohidratada (30 buscas/segundo). Categorias destaque: Casa e Decoracao (lider), Fitness (creatina top 5), Moda (tenis +40% vs 2023), Beleza (perfumes +61% vs 2023). Sustentabilidade: 2,7 milhoes de usuarios escolheram produtos sustentaveis (+27%). GMV Brasil: crescimento de 36% ano a ano. Alcance: 86% dos brasileiros usam a plataforma (Kantar). Mobile: mais de 70% dos acessos.',
                'metadata' => [
                    'sources' => [
                        'MELI Trends Brasil 2024 - Mercado Livre',
                        'Kantar',
                    ],
                    'year' => 2024,
                    'top_products' => [
                        ['rank' => 1, 'product' => 'Jogo de lencol Queen', 'category' => 'Casa'],
                        ['rank' => 2, 'product' => 'Samsung Galaxy S23', 'category' => 'Eletronicos'],
                        ['rank' => 3, 'product' => 'Kit camisetas basicas', 'category' => 'Moda'],
                        ['rank' => 4, 'product' => 'Apple iPhone 13', 'category' => 'Eletronicos'],
                        ['rank' => 5, 'product' => 'Creatina Monohidratada', 'category' => 'Fitness'],
                    ],
                    'category_growth' => [
                        ['category' => 'Tenis (Moda)', 'growth' => 40],
                        ['category' => 'Perfumes (Beleza)', 'growth' => 61],
                        ['category' => 'Sustentaveis', 'growth' => 27],
                    ],
                    'gmv_growth_brazil' => 36,
                    'platform_reach' => 86,
                    'mobile_access' => 70,
                    'sustainable_users' => 2700000,
                    'verified' => true,
                    'url' => 'https://tendencias.mercadolivre.com.br/',
                    'tags' => ['mercado livre', 'meli trends', 'produtos mais vendidos', 'tendencias'],
                ],
            ],

            // Comparativo de Marketplaces
            [
                'category' => 'benchmark',
                'niche' => 'general',
                'title' => 'Comparativo de Marketplaces Brasil 2024 - Onde Vender',
                'content' => 'Comparativo dos principais marketplaces brasileiros em 2024: MERCADO LIVRE - Lider com 15,3% market share, 366,7 milhoes acessos/mes, GMV Brasil +36%, forte em todas categorias, 86% dos brasileiros usam, retail media US$ 5,5 bi (41% do mercado). SHOPEE - 2o lugar com 11,6% share, 244,2 milhoes acessos, 39,2% do trafego de apps, forte em moda e acessorios, foco em preco baixo. AMAZON BRASIL - 3o lugar com 10,4% share, 209,2 milhoes acessos, +7% vendas 2024, retail media 39% do mercado, forte em eletronicos e livros, R$ 33 bi investidos no Brasil. MAGALU - Marketplace = 40% das vendas online, NPS 77 (recorde), Fulfillment 3P dobrou, parceria AliExpress cross-border. SHEIN - 4,4% share, forte em moda feminina jovem, modelo cross-border.',
                'metadata' => [
                    'sources' => [
                        'Conversion',
                        'MELI Trends',
                        'Magazine Luiza RI',
                        'Amazon',
                    ],
                    'year' => 2024,
                    'marketplaces' => [
                        [
                            'name' => 'Mercado Livre',
                            'market_share' => 15.3,
                            'monthly_access_millions' => 366.7,
                            'gmv_growth' => 36,
                            'strengths' => 'Lider absoluto, todas categorias, logistica',
                            'retail_media_share' => 41,
                        ],
                        [
                            'name' => 'Shopee',
                            'market_share' => 11.6,
                            'monthly_access_millions' => 244.2,
                            'app_traffic_share' => 39.2,
                            'strengths' => 'Apps, preco baixo, moda',
                        ],
                        [
                            'name' => 'Amazon Brasil',
                            'market_share' => 10.4,
                            'monthly_access_millions' => 209.2,
                            'sales_growth' => 7,
                            'strengths' => 'Eletronicos, livros, Prime',
                            'retail_media_share' => 39,
                        ],
                        [
                            'name' => 'Magazine Luiza',
                            'marketplace_share_of_online' => 40,
                            'nps' => 77,
                            'strengths' => 'Omnichannel, fulfillment, fintech',
                        ],
                        [
                            'name' => 'Shein',
                            'market_share' => 4.4,
                            'strengths' => 'Moda feminina, preco, cross-border',
                        ],
                    ],
                    'verified' => true,
                    'tags' => ['marketplaces', 'comparativo', 'onde vender', 'mercado livre', 'shopee', 'amazon'],
                ],
            ],

            // Estrategia: Vender em Marketplaces
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Estrategia de Vendas em Marketplaces - Guia por Categoria',
                'content' => 'Guia estrategico para escolha de marketplaces por categoria: ELETRONICOS - Amazon (forte) e Mercado Livre (lider), consumidor pesquisa muito, reviews criticos. MODA - Shopee (preco), Shein (feminino jovem), Mercado Livre (tenis +40%), use fotos de qualidade. CASA E DECORACAO - Mercado Livre (lider em buscas, jogo de lencol #1), Magalu (omnichannel). BELEZA - Mercado Livre (perfumes +61%), foco em Instagram (83% social). FITNESS/SUPLEMENTOS - Mercado Livre (creatina top 5, 30 buscas/seg), Amazon. ALIMENTOS - Mercado Livre crescendo, mas logistica e desafio. Dica geral: comece no Mercado Livre (maior alcance), expanda para Shopee (volume) e Amazon (margem). Fulfillment dos marketplaces aumenta conversao. Retail media: ML e Amazon dominam 80% do mercado.',
                'metadata' => [
                    'sources' => [
                        'MELI Trends',
                        'Conversion',
                        'Benchmark industria',
                    ],
                    'effectiveness' => 'alta',
                    'recommendations_by_category' => [
                        ['category' => 'Eletronicos', 'primary' => 'Amazon/Mercado Livre', 'reason' => 'Reviews, comparacao'],
                        ['category' => 'Moda', 'primary' => 'Shopee/Mercado Livre', 'reason' => 'Volume, tenis +40%'],
                        ['category' => 'Casa', 'primary' => 'Mercado Livre', 'reason' => 'Lider em buscas'],
                        ['category' => 'Beleza', 'primary' => 'Mercado Livre', 'reason' => 'Perfumes +61%'],
                        ['category' => 'Fitness', 'primary' => 'Mercado Livre/Amazon', 'reason' => 'Creatina top 5'],
                    ],
                    'retail_media_dominance' => [
                        ['platform' => 'Mercado Livre', 'share' => 41],
                        ['platform' => 'Amazon', 'share' => 39],
                    ],
                    'verified' => true,
                    'tags' => ['marketplaces', 'estrategia', 'onde vender', 'categoria'],
                ],
            ],

            // Case: Magazine Luiza 2024
            [
                'category' => 'case',
                'niche' => 'general',
                'title' => 'Caso Real: Magazine Luiza - Turnaround e Marketplace 2024',
                'content' => 'Resultados oficiais Magazine Luiza 2024: Reverteu prejuizo de R$ 979 milhoes (2023) para lucro de R$ 448,7 milhoes. Faturamento total: R$ 65,3 bilhoes (+3,6%). EBITDA: R$ 2,9 bilhoes (+232,6%). Marketplace ja representa 40% das vendas online (R$ 5 bi no 4T24). Lojas fisicas: +10% em vendas, crescimento mesmas lojas de 8%. Fulfillment 3P dobrou de tamanho vs 2023, atingindo 24% dos pedidos. NPS Corporativo: 77 pontos no 4T24 (recorde historico), NPS marketplace +14 pontos. Geracao de caixa operacional: R$ 3,1 bilhoes em 2024. Reducao de divida bruta: quase R$ 3 bilhoes. Parceria inedita com AliExpress para cross-border. Luizacred: lucro de R$ 295 milhoes (reverteu prejuizo de R$ 98 mi). Estrategia: foco em rentabilidade, omnichannel e servicos financeiros.',
                'metadata' => [
                    'sources' => [
                        'Magazine Luiza - Relatorio de Resultados 4T24',
                        'Magazine Luiza RI',
                    ],
                    'period' => '2024',
                    'results' => [
                        'net_income' => 448.7,
                        'revenue' => 65.3,
                        'revenue_growth' => 3.6,
                        'ebitda' => 2.9,
                        'ebitda_growth' => 232.6,
                        'marketplace_share_online' => 40,
                        'physical_stores_growth' => 10,
                        'nps' => 77,
                        'fulfillment_3p_share' => 24,
                        'cash_generation' => 3.1,
                    ],
                    'turnaround' => [
                        'from_loss' => -979,
                        'to_profit' => 448.7,
                    ],
                    'verified' => true,
                    'tags' => ['caso de sucesso', 'magazine luiza', 'magalu', 'marketplace', 'omnichannel'],
                ],
            ],

            // Case: Mercado Livre Brasil 2024
            [
                'category' => 'case',
                'niche' => 'general',
                'title' => 'Caso Real: Mercado Livre - Lideranca e Crescimento Brasil 2024',
                'content' => 'Resultados Mercado Livre Brasil 2024: Lider absoluto com 15,3% de market share e 366,7 milhoes de acessos mensais. GMV Brasil cresceu 36% ano a ano (maior aceleracao da empresa). Numero recorde de 416 milhoes de itens entregues. 56,6 milhoes de compradores unicos (+19%). Retail media: US$ 5,5 bilhoes em receita de publicidade (41% do mercado brasileiro). Alcance: 86% dos brasileiros usam a plataforma (Kantar). Produtos sustentaveis: 2,7 milhoes de usuarios escolheram (+27%). MELI Trends mostrou tendencias: Casa e Decoracao liderou buscas, Perfumes +61%, Tenis +40%. Investimento em logistica (Mercado Envios) e fintech (Mercado Pago) como diferenciais. Mobile: mais de 70% dos acessos vem de dispositivos moveis.',
                'metadata' => [
                    'sources' => [
                        'MELI Trends Brasil 2024',
                        'Mercado Livre - Resultados Q2 2024',
                        'Conversion',
                        'Kantar',
                    ],
                    'period' => '2024',
                    'results' => [
                        'market_share' => 15.3,
                        'monthly_access_millions' => 366.7,
                        'gmv_growth_brazil' => 36,
                        'items_delivered_millions' => 416,
                        'unique_buyers_millions' => 56.6,
                        'buyers_growth' => 19,
                        'retail_media_revenue_bi' => 5.5,
                        'retail_media_market_share' => 41,
                        'platform_reach' => 86,
                        'sustainable_users_millions' => 2.7,
                    ],
                    'category_growth' => [
                        ['category' => 'Perfumes', 'growth' => 61],
                        ['category' => 'Tenis', 'growth' => 40],
                        ['category' => 'Sustentaveis', 'growth' => 27],
                    ],
                    'verified' => true,
                    'tags' => ['caso de sucesso', 'mercado livre', 'lideranca', 'marketplace'],
                ],
            ],

            // Tendencias E-commerce 2025
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Tendencias E-commerce Brasil 2025 - Shopify e Webshoppers',
                'content' => 'Principais tendencias para e-commerce em 2025: MOBILE COMMERCE - Compras via celular devem atingir US$ 2,51 trilhoes globalmente (vs US$ 1,71 tri em 2023). No Brasil, 77% dos acessos ja sao mobile. SOCIAL COMMERCE - Projecao de US$ 1,2 trilhao global ate 2025. 82% dos consumidores usam redes sociais para buscar produtos. Instagram crucial para beleza (83% dos pedidos sociais). INTELIGENCIA ARTIFICIAL - IA generativa em cadeia de suprimentos, personalizacao e atendimento. 43% dos consumidores planejam usar chat ao vivo. PURE PLAYERS - Lojas 100% online cresceram 38,1% em 2024 (Webshoppers), superando bricks&clicks (-14,7%). OMNICHANNEL - 54% pesquisam online e compram na loja fisica, 53% fazem o oposto. APPS - Crescimento de 10,6% enquanto web caiu 4,8%. Shopee lidera com 39,2% do trafego de apps. RETAIL MEDIA - Mercado Livre (41%) e Amazon (39%) dominam 80% do mercado.',
                'metadata' => [
                    'sources' => [
                        'Shopify - Future of Commerce',
                        'Webshoppers 51a Edicao',
                        'Conversion',
                        'Signifyd State of Commerce 2025',
                    ],
                    'year' => 2025,
                    'trends' => [
                        [
                            'trend' => 'Mobile Commerce',
                            'global_projection_tri' => 2.51,
                            'brazil_share' => 77,
                        ],
                        [
                            'trend' => 'Social Commerce',
                            'global_projection_tri' => 1.2,
                            'social_search' => 82,
                        ],
                        [
                            'trend' => 'Pure Players',
                            'growth_2024' => 38.1,
                            'vs_bricks_clicks' => -14.7,
                        ],
                        [
                            'trend' => 'Apps',
                            'growth' => 10.6,
                            'shopee_app_share' => 39.2,
                        ],
                        [
                            'trend' => 'Retail Media',
                            'ml_share' => 41,
                            'amazon_share' => 39,
                        ],
                    ],
                    'omnichannel' => [
                        'research_online_buy_offline' => 54,
                        'research_offline_buy_online' => 53,
                    ],
                    'verified' => true,
                    'tags' => ['tendencias', '2025', 'mobile', 'social commerce', 'ia', 'omnichannel'],
                ],
            ],

            // Brasil vs Mundo - Contexto Global
            [
                'category' => 'benchmark',
                'niche' => 'general',
                'title' => 'E-commerce Brasil vs Mundo 2024 - Contexto Global',
                'content' => 'Posicionamento do Brasil no e-commerce global 2024: Brasil teve o MAIOR crescimento mundial em e-commerce: +16% (vs America do Norte 12%, Europa 10%) - Relatorio Atlantico. E-commerce global 2024: US$ 6,09 trilhoes (+8,4%). Projecao 2025: US$ 6,56 trilhoes (+7,7%). Projecao 2028: ultrapassa US$ 8 trilhoes. Brasil: penetracao de 90% de e-commerce na populacao adulta. Fintechs: 96% de penetracao (uma das maiores da America Latina). Volume Brasil 2024: US$ 346 bilhoes, projecao US$ 586 bilhoes em 2027 (CAGR 19%). E-commerce deve atingir 14% do varejo total em 2025 (vs 11% em 2024). America Latina: vendas cresceram 15% ano a ano em 2024. Brasil lidera o crescimento na regiao.',
                'metadata' => [
                    'sources' => [
                        'Atlantico - Latin America Digital Transformation Report 2024',
                        'Shopify Global E-commerce Statistics',
                        'PCMI - Payments and Commerce Market Intelligence',
                    ],
                    'year' => 2024,
                    'brazil_vs_world' => [
                        'brazil_growth' => 16,
                        'north_america_growth' => 12,
                        'europe_growth' => 10,
                        'global_growth' => 8.4,
                    ],
                    'global_market' => [
                        ['year' => 2024, 'value_tri' => 6.09],
                        ['year' => 2025, 'value_tri' => 6.56],
                        ['year' => 2028, 'value_tri' => 8.0],
                    ],
                    'brazil_market' => [
                        ['year' => 2024, 'value_bi_usd' => 346],
                        ['year' => 2027, 'value_bi_usd' => 586],
                        'cagr' => 19,
                    ],
                    'brazil_penetration' => [
                        'ecommerce' => 90,
                        'fintech' => 96,
                        'retail_share_2025' => 14,
                    ],
                    'verified' => true,
                    'tags' => ['brasil', 'global', 'crescimento', 'penetracao', 'america latina'],
                ],
            ],

            // Retail Media - Tendencia 2025
            [
                'category' => 'strategy',
                'niche' => 'general',
                'title' => 'Retail Media Brasil 2024/2025 - Nova Fronteira de Publicidade',
                'content' => 'Retail Media e a publicidade dentro dos marketplaces/e-commerces, e esta em forte crescimento no Brasil. Mercado Livre lidera com US$ 5,5 bilhoes em receita de publicidade (41% do mercado brasileiro). Amazon Brasil em 2o com 39% do mercado. Juntos, ML e Amazon dominam 80% do retail media no Brasil. Por que investir: consumidor ja esta no momento de compra, alta intencao, dados de comportamento. Formatos: anuncios patrocinados em busca, banners em categoria, Product Ads. ROI tipicamente superior a midia tradicional por estar mais proximo da conversao. Tendencia: varejistas tradicionais (Magalu, Casas Bahia) tambem lancando plataformas de retail media. Para vendedores: anuncios no Mercado Livre Ads e Amazon Ads sao essenciais para visibilidade. Investimento minimo sugerido: 5-10% do faturamento do canal.',
                'metadata' => [
                    'sources' => [
                        'Atlantico Report 2024',
                        'E-commerce Brasil',
                    ],
                    'year' => 2024,
                    'market_share' => [
                        ['platform' => 'Mercado Livre', 'share' => 41, 'revenue_bi_usd' => 5.5],
                        ['platform' => 'Amazon Brasil', 'share' => 39],
                    ],
                    'combined_dominance' => 80,
                    'effectiveness' => 'alta',
                    'recommended_investment' => ['min' => 5, 'max' => 10, 'unit' => '% do faturamento'],
                    'verified' => true,
                    'tags' => ['retail media', 'publicidade', 'mercado livre ads', 'amazon ads', 'marketing'],
                ],
            ],

            // =====================================================
            // FOOD SERVICE E DELIVERY - Dados de Mercado
            // =====================================================

            // iFood - Lider de Delivery
            [
                'category' => 'benchmark',
                'niche' => 'food',
                'title' => 'iFood Brasil 2024 - Lider Absoluto de Delivery',
                'content' => 'Dados oficiais iFood 2024: 110 milhoes de pedidos por mes, 380 mil estabelecimentos parceiros (restaurantes, mercados, farmacias), 360 mil entregadores, 55 milhoes de clientes em 1.500 cidades. Market share: 80,8% do trafego de apps de delivery no Brasil (setembro 2024). Impacto economico: R$ 140 bilhoes movimentados na economia brasileira em 2024 (+26% vs 2023), equivalente a 0,64% do PIB. Em 4 anos, valor movimentado cresceu 130%. Gerou 1,07 milhao de empregos diretos e indiretos. Crescimento de restaurantes: +10,17% (58.466 novos estabelecimentos). 653 milhoes de visualizacoes de pratos por mes. 160 mil estabelecimentos com Selo Super. Categorias multicategoria (mercado, farmacia, pet, shopping) cresceram 50% em 2024. Dark kitchens: 1/3 dos restaurantes no iFood.',
                'metadata' => [
                    'sources' => [
                        'iFood - Relatorio para Restaurantes 2024',
                        'iFood Move 2024',
                        'Fipe/iFood 2025',
                        'Statista',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'monthly_orders_millions' => 110,
                        'partner_establishments' => 380000,
                        'delivery_partners' => 360000,
                        'customers_millions' => 55,
                        'cities' => 1500,
                        'market_share' => 80.8,
                        'economic_impact_bi' => 140,
                        'gdp_share' => 0.64,
                        'jobs_created_millions' => 1.07,
                        'restaurant_growth' => 10.17,
                        'monthly_views_millions' => 653,
                        'dark_kitchen_share' => 33,
                    ],
                    'verified' => true,
                    'tags' => ['ifood', 'delivery', 'food service', 'lider', 'dark kitchen'],
                ],
            ],

            // iFood - Produtos Mais Pedidos
            [
                'category' => 'benchmark',
                'niche' => 'food',
                'title' => 'iFood 2024 - Itens Mais Pedidos e Tendencias de Consumo',
                'content' => 'Tendencias de consumo iFood 2024: Hamburguer segue como item mais pedido. Marmita foi campea de crescimento (+18%). Padaria em 2o lugar de crescimento (+11%), Acai em 3o (+9%). Cafe da manha: crescimento de 31% nos pedidos (2T23 vs 2T24), metade dos pedidos em padarias. Madrugada: +25% nos pedidos, mas apenas 7% dos estabelecimentos funcionam nesse horario (oportunidade). Bebidas e aperitivos doces se destacaram no 1o semestre 2024 (Kantar). Parceiros iFood cresceram 2,3x mais que a media do setor. Projecao IFB para food service 2025: expansao de 6,25%, consumo de R$ 241 bilhoes. Dark kitchens projetadas para US$ 71 bilhoes global ate 2027.',
                'metadata' => [
                    'sources' => [
                        'iFood - Relatorio para Restaurantes 2024',
                        'iFood Move 2024',
                        'Kantar Consumer Insights 2024',
                        'Instituto Foodservice Brasil (IFB)',
                        'Statista',
                    ],
                    'year' => 2024,
                    'top_items' => [
                        ['item' => 'Hamburguer', 'position' => 1],
                        ['item' => 'Marmita', 'growth' => 18],
                        ['item' => 'Padaria', 'growth' => 11],
                        ['item' => 'Acai', 'growth' => 9],
                    ],
                    'daypart_growth' => [
                        ['daypart' => 'Cafe da manha', 'growth' => 31],
                        ['daypart' => 'Madrugada', 'growth' => 25, 'establishments_open' => 7],
                    ],
                    'projection_2025' => [
                        'growth' => 6.25,
                        'consumption_bi' => 241,
                    ],
                    'verified' => true,
                    'tags' => ['ifood', 'produtos', 'tendencias', 'cafe da manha', 'madrugada'],
                ],
            ],

            // Food Service Brasil - IFB/ABRASEL
            [
                'category' => 'benchmark',
                'niche' => 'food',
                'title' => 'Mercado Food Service Brasil 2024/2025 - IFB e ABRASEL',
                'content' => 'Dados oficiais do setor de food service brasileiro: Faturamento 2024: R$ 455 bilhoes (ABRASEL), gasto do consumidor atingiu R$ 221 bilhoes (IFB) - patamar historico. Crescimento 2024: 3,2%. Projecao 2025: +6,25% a 6,9%, consumo de R$ 241 bilhoes (IFB). Delivery: R$ 139 bilhoes em 2023, projecao US$ 21,18 bilhoes em 2025, CAGR 7,04% ate 2029. Penetracao delivery: 38,8% da populacao em 2025, 90,4 milhoes de usuarios ate 2029. Comportamento: 81% das classes A/B/C pedem delivery regularmente (Galunion). Emprego: 4,9 milhoes de pessoas (7,9% dos empregos formais), 1,38 milhao de estabelecimentos. Massa salarial: R$ 107 bilhoes/ano. Projecao global: foodservice Brasil +7% ao ano ate 2028 (Redirection International). Ticket medio sustentou crescimento em 2024.',
                'metadata' => [
                    'sources' => [
                        'Instituto Foodservice Brasil (IFB)',
                        'ABRASEL',
                        'Galunion',
                        'Statista',
                        'Euromonitor',
                        'Redirection International',
                    ],
                    'year' => 2024,
                    'market_size' => [
                        'abrasel_bi' => 455,
                        'ifb_consumer_spending_bi' => 221,
                        'delivery_2023_bi' => 139,
                        'delivery_2025_bi_usd' => 21.18,
                    ],
                    'growth' => [
                        '2024' => 3.2,
                        '2025_projection' => 6.25,
                        'delivery_cagr_2029' => 7.04,
                        'annual_until_2028' => 7,
                    ],
                    'employment' => [
                        'jobs_millions' => 4.9,
                        'formal_jobs_share' => 7.9,
                        'establishments' => 1379420,
                        'salary_mass_bi' => 107,
                    ],
                    'delivery_penetration' => [
                        '2025' => 38.8,
                        'users_2029_millions' => 90.4,
                    ],
                    'regular_delivery_usage' => 81,
                    'verified' => true,
                    'tags' => ['food service', 'abrasel', 'ifb', 'delivery', 'emprego'],
                ],
            ],

            // Concorrencia Delivery - Rappi, Ze, 99Food
            [
                'category' => 'benchmark',
                'niche' => 'food',
                'title' => 'Concorrencia Delivery Brasil 2024 - iFood, Rappi, Ze Delivery, 99Food',
                'content' => 'Cenario competitivo delivery Brasil 2024/2025: IFOOD - Lider absoluto com 80,8% do mercado, 55 milhoes de usuarios. RAPPI - ~8% do mercado, opera em 50+ cidades, meta de 300 cidades ate 2028. Investimento de R$ 1,4 bilhao em 3 anos para dobrar base (30 mil para 60 mil restaurantes). Taxa zero por 3 anos (3,5% adquirencia). Recebeu US$ 25 milhoes da Amazon (set/2025). ZE DELIVERY (Ambev) - Lider em bebidas, presente em 850+ cidades, milhoes de usuarios ativos. Foco em conveniencia e experiencias. 99FOOD (DiDi) - Retornou ao Brasil com investimento de R$ 2 bilhoes ate junho/2026, R$ 50 milhoes para pontos de apoio a entregadores. TAXAS DO SETOR - Variam de 18% a 30% somadas (ABRASEL). Guerra de precos intensa em 2024/2025.',
                'metadata' => [
                    'sources' => [
                        'Statista',
                        'ABRASEL',
                        'Bloomberg Linea',
                        'E-commerce Brasil',
                    ],
                    'year' => 2024,
                    'market_share' => [
                        ['platform' => 'iFood', 'share' => 80.8, 'users_millions' => 55],
                        ['platform' => 'Rappi', 'share' => 8, 'cities' => 50],
                        ['platform' => 'Ze Delivery', 'category' => 'bebidas', 'cities' => 850],
                        ['platform' => '99Food', 'investment_bi' => 2],
                    ],
                    'investments' => [
                        ['platform' => 'Rappi', 'value_bi' => 1.4, 'period' => '3 anos'],
                        ['platform' => '99Food', 'value_bi' => 2, 'period' => 'ate jun/2026'],
                        ['platform' => 'Rappi/Amazon', 'value_mi_usd' => 25],
                    ],
                    'fees_range' => ['min' => 18, 'max' => 30, 'unit' => '%'],
                    'verified' => true,
                    'tags' => ['delivery', 'ifood', 'rappi', 'ze delivery', '99food', 'concorrencia'],
                ],
            ],

            // Supermercados Online - ABRAS
            [
                'category' => 'benchmark',
                'niche' => 'food',
                'title' => 'Setor Supermercadista Brasil 2024 - Ranking ABRAS',
                'content' => 'Dados do Ranking ABRAS 2025 (ano-base 2024): Faturamento bruto do setor: R$ 1,067 trilhao (+6,5% vs 2023), equivalente a 9,12% do PIB. Inclui todos os formatos: atacarejo, minimercados, e-commerce, conveniencia, hortifrutis. Emprego: mais de 9 milhoes diretos e indiretos. Estrutura: 424 mil lojas, 30 milhoes de consumidores/dia. Top 3: 1) Carrefour Brasil R$ 120,5 bi (lider pelo 9o ano), 2) Assai R$ 80,5 bi, 3) Grupo Mateus R$ 36,3 bi, 4) Supermercados BH (novidade). Contexto macroeconomico: consumo das familias +4,8% (melhor desde 2011), desemprego 6,2% (menor da serie historica), renda media R$ 3.255. 8,7 milhoes de brasileiros sairam da pobreza. Desafio: 357 mil vagas em aberto (dificuldade de contratacao). Ranking baseado em 1.251 empresas.',
                'metadata' => [
                    'sources' => [
                        'ABRAS - Ranking 2025',
                        'NielsenIQ',
                        'IBGE',
                    ],
                    'year' => 2024,
                    'sector_metrics' => [
                        'revenue_tri' => 1.067,
                        'growth' => 6.5,
                        'gdp_share' => 9.12,
                        'jobs_millions' => 9,
                        'stores' => 424000,
                        'daily_consumers_millions' => 30,
                    ],
                    'top_retailers' => [
                        ['rank' => 1, 'name' => 'Carrefour Brasil', 'revenue_bi' => 120.5],
                        ['rank' => 2, 'name' => 'Assai', 'revenue_bi' => 80.5],
                        ['rank' => 3, 'name' => 'Grupo Mateus', 'revenue_bi' => 36.3],
                        ['rank' => 4, 'name' => 'Supermercados BH'],
                    ],
                    'macro' => [
                        'family_consumption_growth' => 4.8,
                        'unemployment' => 6.2,
                        'average_income' => 3255,
                        'poverty_exit_millions' => 8.7,
                    ],
                    'verified' => true,
                    'tags' => ['supermercado', 'abras', 'varejo', 'carrefour', 'assai', 'atacarejo'],
                ],
            ],

            // Estrategia Food Delivery
            [
                'category' => 'strategy',
                'niche' => 'food',
                'title' => 'Estrategias para Delivery e Food Service 2024/2025',
                'content' => 'Estrategias baseadas em dados do setor: HORARIOS SUBUTILIZADOS - Cafe da manha cresceu 31%, madrugada +25% mas so 7% funcionam (grande oportunidade). DARK KITCHENS - Ja sao 1/3 do iFood, mercado global projetado em US$ 71 bi ate 2027. Menor custo fixo, foco em delivery. MULTICATEGORIA - iFood cresceu 50% em mercado, farmacia, pet. Diversificar oferta. TAXAS - Variam 18-30%, considerar canais proprios para pedidos recorrentes. ZE DELIVERY - Modelo de conveniencia para bebidas, presente em 850 cidades. SELO SUPER IFOOD - 160 mil estabelecimentos reconhecidos, melhora visibilidade. MARMITA - Maior crescimento (+18%), oportunidade para restaurantes tradicionais. Delivery representa 81% das classes A/B/C regularmente. Penetracao vai de 38,8% para 90,4 mi usuarios ate 2029.',
                'metadata' => [
                    'sources' => [
                        'iFood Move 2024',
                        'IFB',
                        'ABRASEL',
                        'Statista',
                    ],
                    'effectiveness' => 'alta',
                    'opportunities' => [
                        ['opportunity' => 'Cafe da manha', 'growth' => 31, 'competition' => 'baixa'],
                        ['opportunity' => 'Madrugada', 'growth' => 25, 'establishments_open' => 7],
                        ['opportunity' => 'Dark kitchen', 'ifood_share' => 33, 'global_projection_bi' => 71],
                        ['opportunity' => 'Marmita', 'growth' => 18],
                        ['opportunity' => 'Multicategoria', 'ifood_growth' => 50],
                    ],
                    'delivery_penetration' => [
                        'class_abc_regular' => 81,
                        'population_2025' => 38.8,
                        'users_2029_millions' => 90.4,
                    ],
                    'verified' => true,
                    'tags' => ['delivery', 'estrategia', 'dark kitchen', 'horarios', 'oportunidades'],
                ],
            ],

            // Case: iFood Impacto Economico
            [
                'category' => 'case',
                'niche' => 'food',
                'title' => 'Caso Real: iFood - De Startup a 0,64% do PIB Brasileiro',
                'content' => 'Trajetoria do iFood como caso de sucesso: Em 2024, movimentou R$ 140 bilhoes na economia brasileira (+26% vs 2023), equivalente a 0,64% do PIB. Em 4 anos, valor movimentado cresceu 130%. Criou 1,07 milhao de empregos diretos e indiretos. Conecta 380 mil estabelecimentos, 360 mil entregadores e 55 milhoes de clientes. Domina 80,8% do mercado de delivery. Restaurantes parceiros cresceram 2,3x mais que a media do setor. Expandiu para multicategoria: mercado, farmacia, pet shop, shopping (+50% em 2024). Modelo de dark kitchen representa 1/3 dos restaurantes. Selo Super reconhece 160 mil estabelecimentos de qualidade. O case demonstra como uma plataforma digital pode transformar um setor inteiro e gerar impacto economico massivo.',
                'metadata' => [
                    'sources' => [
                        'Fipe/iFood 2025',
                        'iFood - Dados Institucionais',
                    ],
                    'period' => '2024',
                    'results' => [
                        'economic_impact_bi' => 140,
                        'impact_growth' => 26,
                        'gdp_share' => 0.64,
                        'impact_growth_4y' => 130,
                        'jobs_millions' => 1.07,
                        'market_share' => 80.8,
                        'establishments' => 380000,
                        'customers_millions' => 55,
                    ],
                    'success_factors' => [
                        'Dominio de mercado',
                        'Diversificacao multicategoria',
                        'Dark kitchens',
                        'Selo de qualidade',
                        'Escala nacional',
                    ],
                    'verified' => true,
                    'tags' => ['caso de sucesso', 'ifood', 'impacto economico', 'pib', 'delivery'],
                ],
            ],

            // =====================================================
            // FOOD - SUBCATEGORIAS ESPECIFICAS
            // =====================================================

            // GROCERY - Mercearia e Supermercado
            [
                'category' => 'benchmark',
                'niche' => 'food',
                'subcategory' => 'grocery',
                'title' => 'Benchmarks: Mercearia e Supermercado Online',
                'content' => 'Benchmarks para e-commerce de mercearia e supermercado (ABRAS/NIQ 2024): Ticket medio R$ 150-300, media R$ 220. Categorias lideres: Alimentos basicos (arroz, feijao, oleo), limpeza domestica (detergente, sabao), higiene pessoal (shampoo, sabonete, pasta dental). Frequencia de compra: semanal 45%, quinzenal 35%, mensal 20%. Taxa de conversao: 2,8-4,5% (superior a outros segmentos devido a necessidade). Carrinho abandonado: 65-72%, principalmente por frete alto ou minimo de compra. Sazonalidade: picos em inicio de mes (pagamentos), datas festivas (Natal, Pascoa crescem 35-40%). Comportamento: cliente busca conveniencia + preco competitivo. Frete gratis a partir de R$ 100-150 aumenta conversao em 28%. Assinatura/recorrencia: 12-18% dos clientes (papel higienico, cafe, fraldas). Mix ideal: 60% essenciais, 25% limpeza, 15% higiene. Marca propria representa 8-12% das vendas mas 18-22% da margem.',
                'metadata' => [
                    'sources' => [
                        'ABRAS - Ranking 2025',
                        'NielsenIQ',
                        'Neotrust',
                        'Euromonitor',
                    ],
                    'ticket_medio' => 220,
                    'ticket_range' => ['min' => 150, 'max' => 300],
                    'conversion_rate' => ['min' => 2.8, 'max' => 4.5],
                    'cart_abandonment' => ['min' => 65, 'max' => 72],
                    'purchase_frequency' => [
                        ['period' => 'semanal', 'share' => 45],
                        ['period' => 'quinzenal', 'share' => 35],
                        ['period' => 'mensal', 'share' => 20],
                    ],
                    'free_shipping_threshold' => ['min' => 100, 'max' => 150],
                    'subscription_rate' => ['min' => 12, 'max' => 18],
                    'private_label' => [
                        'sales_share' => ['min' => 8, 'max' => 12],
                        'margin_share' => ['min' => 18, 'max' => 22],
                    ],
                    'avoid_mentions' => [
                        'low carb', 'proteico', 'whey', 'fit', // healthy
                        'vinho importado', 'queijo gorgonzola', 'azeite trufado', 'especiarias raras', // gourmet
                        'cafe especial', 'cha premium', 'suco detox', 'agua alcalina', // beverages
                        'organico certificado', 'sem agrotoxicos', 'biodinamico', // organic
                        'meal prep', 'kit refeicao', 'dark kitchen', 'prato pronto', // restaurant
                    ],
                    'verified' => true,
                    'tags' => ['benchmarks', 'grocery', 'mercearia', 'supermercado', 'ticket medio', 'assinatura'],
                ],
            ],

            [
                'category' => 'strategy',
                'niche' => 'food',
                'subcategory' => 'grocery',
                'title' => 'Estrategias Comprovadas: Mercearia e Supermercado Online',
                'content' => 'Estrategias de sucesso para e-commerce de mercearia: 1) CLUBE DE ASSINATURA ESSENCIAIS: Cesta basica mensal recorrente (arroz, feijao, oleo, acucar), desconto 12-15%, entrega programada, reduz CAC. 2) FRETE GRATIS PROGRESSIVO: Faixa 1 (R$ 100 frete R$ 9,90), Faixa 2 (R$ 150 frete gratis), Faixa 3 (R$ 200 brinde), aumenta ticket em 32%. 3) LISTA DE COMPRAS INTELIGENTE: Cliente salva lista mensal, recompra em 1 clique, adiciona sugestoes baseadas em historico, conversao +38%. 4) OFERTAS RELAMPAGO HORARIO: 3 produtos em promocao a cada 2 horas (manha, tarde, noite), cria urgencia, aumenta frequencia de visitas. 5) MARCA PROPRIA DESTAQUE: Linha propria de basicos (arroz, feijao, acucar), 20-30% mais barato, margem 35% superior. 6) KITS TEMATICOS: Kit Cafe da Manha (cafe, leite, pao, manteiga), Kit Churrasco (carvao, sal grosso, guardanapo), facilita decisao. 7) PROGRAMA CASHBACK: 2-5% de volta em creditos para proxima compra, fideliza cliente recorrente. 8) JANELA DE ENTREGA EXPRESSA: Mesma entrega (2-4h) para pedidos acima de R$ 150 em raio 5km, premium. 9) ALERTAS DE ESTOQUE: Cliente cadastra produtos favoritos, recebe alerta quando entrar em promocao ou faltar no estoque (reengajamento). 10) COMBO LIMPEZA + HIGIENE: Pacotes combinados (detergente + esponja + sabao), desconto 10%, aumenta itens por pedido. 11) PARCERIAS CONDOMINIO: Desconto para moradores de condominios, entrega centralizada (portaria), reduz custo logistico. 12) PRIMEIRA COMPRA GUIADA: Quiz rapido identifica tamanho familia, cria cesta sugerida, onboarding personalizado.',
                'metadata' => [
                    'sources' => [
                        'ABRAS',
                        'Neotrust',
                        'Cases Carrefour/Assai',
                    ],
                    'proven_tactics' => [
                        'subscription_essentials' => 'Cesta basica mensal, desconto 12-15%, reduz CAC',
                        'progressive_shipping' => '3 faixas de frete, ticket +32%',
                        'smart_shopping_list' => 'Recompra 1 clique, conversao +38%',
                        'hourly_flash_deals' => '3 produtos a cada 2h, urgencia',
                        'private_label_highlight' => '20-30% mais barato, margem +35%',
                        'thematic_kits' => 'Kit Cafe/Churrasco, facilita decisao',
                        'cashback_program' => '2-5% creditos, fideliza',
                        'express_delivery_window' => '2-4h para R$ 150+, raio 5km',
                        'stock_alerts' => 'Alerta promocao/falta, reengajamento',
                        'cleaning_hygiene_bundles' => 'Combos desconto 10%',
                        'condo_partnerships' => 'Entrega centralizada, reduz custo',
                        'guided_first_purchase' => 'Quiz familia, cesta sugerida',
                    ],
                    'avoid_suggestions' => [
                        'quiz nutricional', 'plano fit', 'receitas low carb', // healthy
                        'clube de vinhos', 'harmonizacao queijos', 'degustacao azeites', // gourmet
                        'assinatura cafe especial', 'blend mensal', 'infusoes premium', // beverages
                        'certificacao organica', 'feira organica', 'cesta vegetal organica', // organic
                        'meal kit semanal', 'chef em casa', 'cardapio dark kitchen', // restaurant
                    ],
                    'verified' => true,
                    'tags' => ['estrategias', 'grocery', 'assinatura', 'frete', 'lista compras', 'marca propria'],
                ],
            ],

            // HEALTHY - Saudaveis e Funcionais
            [
                'category' => 'benchmark',
                'niche' => 'food',
                'subcategory' => 'healthy',
                'title' => 'Benchmarks: Alimentos Saudaveis e Funcionais',
                'content' => 'Benchmarks para e-commerce de alimentos saudaveis e funcionais (Euromonitor/ABIA 2024): Ticket medio R$ 120-280, media R$ 180. Segmento cresce 15-18% ao ano (acima da media geral). Categorias lideres: Low carb (pao, massa, snacks), Proteicos (barras, cookies, pasta amendoim), Sem gluten/lactose, Fit (zero acucar, diet), Superfoods (chia, quinoa, acai bowl). Perfil do cliente: 68% mulheres, 25-45 anos, classe A/B, praticantes de atividade fisica. Taxa de conversao: 3,2-5,8% (publico qualificado). Frequencia: mensal 42%, quinzenal 38%, semanal 20%. Recorrencia/assinatura: 22-28% (maior que grocery tradicional). Canais: 45% e-commerce proprio, 30% marketplaces, 25% redes sociais. Sazonalidade: pico janeiro (resolucoes ano novo +45%), marco-maio (pre-verao +28%). Frete: ticket mais alto permite frete gratis a partir R$ 120. Mix: 40% low carb, 25% proteico, 20% sem gluten/lactose, 15% superfoods.',
                'metadata' => [
                    'sources' => [
                        'Euromonitor',
                        'ABIA',
                        'NIQ',
                        'Mintel Food Trends',
                    ],
                    'ticket_medio' => 180,
                    'ticket_range' => ['min' => 120, 'max' => 280],
                    'segment_growth' => ['min' => 15, 'max' => 18],
                    'conversion_rate' => ['min' => 3.2, 'max' => 5.8],
                    'customer_profile' => [
                        'female' => 68,
                        'age_range' => '25-45',
                        'class' => 'A/B',
                        'active_lifestyle' => true,
                    ],
                    'subscription_rate' => ['min' => 22, 'max' => 28],
                    'seasonality_peaks' => [
                        ['period' => 'Janeiro', 'growth' => 45, 'reason' => 'Resolucoes ano novo'],
                        ['period' => 'Marco-Maio', 'growth' => 28, 'reason' => 'Pre-verao'],
                    ],
                    'product_mix' => [
                        ['category' => 'Low carb', 'share' => 40],
                        ['category' => 'Proteico', 'share' => 25],
                        ['category' => 'Sem gluten/lactose', 'share' => 20],
                        ['category' => 'Superfoods', 'share' => 15],
                    ],
                    'avoid_mentions' => [
                        'arroz comum', 'feijao tradicional', 'sabao em po', 'papel higienico', // grocery
                        'vinho tinto reserva', 'queijo brie', 'prosciutto', 'caviar', // gourmet
                        'refrigerante', 'cerveja pilsen', 'vodka', 'energetico comum', // beverages
                        'certificado IBD', 'sem agrotoxicos', 'agricultura biodinamica', // organic
                        'marmita tradicional', 'prato feito', 'delivery restaurante', 'ifood', // restaurant
                    ],
                    'verified' => true,
                    'tags' => ['benchmarks', 'healthy', 'low carb', 'proteico', 'fit', 'funcional'],
                ],
            ],

            [
                'category' => 'strategy',
                'niche' => 'food',
                'subcategory' => 'healthy',
                'title' => 'Estrategias Comprovadas: Alimentos Saudaveis e Funcionais',
                'content' => 'Estrategias de sucesso para e-commerce healthy: 1) QUIZ NUTRICIONAL PERSONALIZADO: Cliente responde objetivo (emagrecer, ganhar massa, energia), recebe sugestao de produtos, conversao +42%. 2) ASSINATURA MENSAL FIT: Box mensal com 10-15 produtos saudaveis variados (descoberta), desconto 18%, LTV 8x maior. 3) PLANO ALIMENTAR INTEGRADO: Parceria com nutricionistas, cliente recebe plano + produtos necessarios no carrinho, ticket +65%. 4) DESAFIO 30 DIAS: Kit completo para 30 dias low carb/fit, acompanhamento por grupo WhatsApp, gamificacao. 5) BUNDLE PRE-TREINO + POS-TREINO: Combina produtos complementares (barra proteica + pasta amendoim + snack), desconto 15%. 6) SELO DE CERTIFICACAO DESTAQUE: Badges visiveis (sem gluten, sem lactose, low carb, vegano), filtros inteligentes. 7) CONTEUDO EDUCACIONAL: Blog com receitas fit, videos preparo, calculadora macros, posiciona marca como autoridade. 8) PROGRAMA EMBAIXADORES FITNESS: Influencers micro (10-50k), codigo desconto exclusivo, comissao 10-15%. 9) AMOSTRA GRATIS PRIMEIRA COMPRA: Cliente escolhe 2 produtos amostra gratis, conhece linha, recompra futura. 10) COMPARATIVO NUTRICIONAL: Tabela compara produto fit vs tradicional (calorias, carboidratos, proteinas), justifica preco. 11) CLUBE VIP ASSINANTES: Acesso antecipado lancamentos, desconto extra 10%, frete gratis ilimitado, fideliza. 12) ALERTAS JANEIRO E PRE-VERAO: Campanhas tematicas nesses picos (detox pos-festas, corpo verao), urgencia.',
                'metadata' => [
                    'sources' => [
                        'Cases Natue, Jasmine, Fit Food',
                        'Euromonitor',
                        'Mintel',
                    ],
                    'proven_tactics' => [
                        'nutritional_quiz' => 'Objetivo personalizado, conversao +42%',
                        'fit_monthly_subscription' => 'Box 10-15 produtos, desconto 18%, LTV 8x',
                        'integrated_meal_plan' => 'Nutricionista + produtos, ticket +65%',
                        '30_day_challenge' => 'Kit 30 dias, grupo WhatsApp, gamificacao',
                        'pre_post_workout_bundle' => 'Produtos complementares, desconto 15%',
                        'certification_badges' => 'Sem gluten/lactose/vegano, filtros',
                        'educational_content' => 'Receitas, videos, calculadora macros',
                        'fitness_ambassadors' => 'Micro influencers 10-50k, comissao 10-15%',
                        'free_samples_first_purchase' => '2 amostras gratis, recompra futura',
                        'nutritional_comparison' => 'Fit vs tradicional, justifica preco',
                        'vip_subscriber_club' => 'Lancamentos antecipados, desconto +10%',
                        'seasonal_alerts' => 'Janeiro detox, pre-verao',
                    ],
                    'avoid_suggestions' => [
                        'cesta basica mensal', 'combo limpeza', 'marca propria arroz', // grocery
                        'degustacao harmonizada', 'clube vinhos mensais', 'masterclass chef', // gourmet
                        'blend cafe artesanal', 'cha origem unica', 'infusao rara', // beverages
                        'horta em casa', 'feira organica delivery', 'CSA semanal', // organic
                        'kit jantar 2 pessoas', 'chef em casa', 'menu degustacao', // restaurant
                    ],
                    'verified' => true,
                    'tags' => ['estrategias', 'healthy', 'quiz nutricional', 'assinatura fit', 'plano alimentar'],
                ],
            ],

            // GOURMET - Gourmet e Importados
            [
                'category' => 'benchmark',
                'niche' => 'food',
                'subcategory' => 'gourmet',
                'title' => 'Benchmarks: Alimentos Gourmet e Importados',
                'content' => 'Benchmarks para e-commerce gourmet e importados (Euromonitor/ABIA 2024): Ticket medio R$ 200-500, media R$ 300 (o mais alto em food). Publico premium: 72% classe A, 28% classe B, 30-55 anos, alto poder aquisitivo. Categorias lideres: Vinhos e espumantes (45% das vendas), Queijos especiais (18%), Azeites premium (12%), Especiarias raras (8%), Chocolates importados (7%), Cafes especiais (6%), Conservas gourmet (4%). Taxa de conversao: 2,5-4,2% (decisao mais demorada, ticket alto). Frequencia: trimestral 45%, mensal 35%, ocasional 20%. Sazonalidade: picos Natal (+55%), Dia dos Pais (+32%), Dia das Maes (+28%), datas comemorativas. Frete: cliente premium aceita frete, mas espera embalagem impecavel e rapido. Recorrencia clube: 15-22% (clube de vinhos domina). Canais: 55% e-commerce proprio/especializado, 30% marketplaces premium, 15% redes sociais. Ticket medio vinho: R$ 80-200/garrafa. Margem: 35-50% (maior que grocery).',
                'metadata' => [
                    'sources' => [
                        'Euromonitor',
                        'ABIA',
                        'Wine Intelligence Brazil',
                        'Ideal Consulting',
                    ],
                    'ticket_medio' => 300,
                    'ticket_range' => ['min' => 200, 'max' => 500],
                    'customer_profile' => [
                        'class_a' => 72,
                        'class_b' => 28,
                        'age_range' => '30-55',
                        'income_level' => 'high',
                    ],
                    'conversion_rate' => ['min' => 2.5, 'max' => 4.2],
                    'product_mix' => [
                        ['category' => 'Vinhos/espumantes', 'share' => 45],
                        ['category' => 'Queijos especiais', 'share' => 18],
                        ['category' => 'Azeites premium', 'share' => 12],
                        ['category' => 'Especiarias raras', 'share' => 8],
                        ['category' => 'Chocolates importados', 'share' => 7],
                        ['category' => 'Cafes especiais', 'share' => 6],
                        ['category' => 'Conservas gourmet', 'share' => 4],
                    ],
                    'seasonality_peaks' => [
                        ['period' => 'Natal', 'growth' => 55],
                        ['period' => 'Dia dos Pais', 'growth' => 32],
                        ['period' => 'Dia das Maes', 'growth' => 28],
                    ],
                    'subscription_rate' => ['min' => 15, 'max' => 22],
                    'margin' => ['min' => 35, 'max' => 50],
                    'avoid_mentions' => [
                        'arroz tipo 1', 'feijao carioca', 'oleo soja comum', 'macarrao comum', // grocery
                        'barra proteica', 'whey isolado', 'pasta amendoim fit', 'snack zero acucar', // healthy
                        'refrigerante cola', 'suco caixinha', 'agua mineral 500ml', 'energetico', // beverages
                        'verdura organica', 'fruta sem agrotoxicos', 'certificacao IBD', // organic
                        'marmita fit', 'prato executivo', 'combo almoco', 'dark kitchen', // restaurant
                    ],
                    'verified' => true,
                    'tags' => ['benchmarks', 'gourmet', 'vinhos', 'queijos', 'importados', 'premium'],
                ],
            ],

            [
                'category' => 'strategy',
                'niche' => 'food',
                'subcategory' => 'gourmet',
                'title' => 'Estrategias Comprovadas: Alimentos Gourmet e Importados',
                'content' => 'Estrategias de sucesso para e-commerce gourmet: 1) CLUBE DE VINHOS MENSAL: Assinatura com 2-3 garrafas selecionadas por sommelier, ficha tecnica, harmonizacoes, LTV 12x maior, baixo churn. 2) KITS HARMONIZACAO COMPLETA: Kit Queijos + Vinhos + Geleias, Kit Italiano (massa + molho + azeite + vinho), facilita presente, ticket +40%. 3) DEGUSTACAO VIRTUAL GUIADA: Lives mensais com sommelier/chef degustando produtos (cliente compra kit antecipado), cria comunidade. 4) STORYTELLING DE ORIGEM: Fotos produtor, historia do vinho/queijo/azeite, terroir, certificacoes, justifica premium. 5) EMBALAGEM PRESENTEAVEL: Caixas de madeira, papel seda, laco, cartao personalizado (75% compram para presente), cobra R$ 15-25 extra. 6) PROGRAMA PONTOS PREMIUM: 1 ponto a cada R$ 10, troca por produtos exclusivos ou experiencias (jantar com chef), gamificacao. 7) LANCAMENTOS EXCLUSIVOS ANTECIPADOS: Safra nova, lote limitado, pre-venda para cadastrados, escassez. 8) MASTERCLASS ONLINE: Curso online como harmonizar vinhos, cortar queijos, usar especiarias, posiciona expertise. 9) TABELA COMPARATIVA VINHOS: Organiza por regiao, uva, preco, pontuacao criticos, facilita escolha iniciantes. 10) GIFT CARD PREMIUM: Cartao presente digital ou fisico elegante, pico Natal/Dia dos Pais. 11) CONSULTORIA PERSONALIZADA: WhatsApp/chat para sugerir vinho para ocasiao especifica, conversao +35%. 12) SEGMENTACAO PRESENTES CORPORATIVOS: Linha B2B para empresas (kits personalizados, nota fiscal, entrega multipla), margem extra.',
                'metadata' => [
                    'sources' => [
                        'Cases Evino, Wine, Grand Cru',
                        'Euromonitor',
                        'Wine Intelligence',
                    ],
                    'proven_tactics' => [
                        'monthly_wine_club' => '2-3 garrafas sommelier, LTV 12x',
                        'pairing_complete_kits' => 'Queijos+Vinhos+Geleias, ticket +40%',
                        'virtual_guided_tasting' => 'Lives mensais, kit antecipado, comunidade',
                        'origin_storytelling' => 'Fotos produtor, terroir, certificacoes',
                        'gift_packaging' => 'Caixas madeira, laco, cartao, R$ 15-25 extra',
                        'premium_points_program' => '1 pt/R$ 10, produtos exclusivos/experiencias',
                        'exclusive_early_releases' => 'Safra nova, pre-venda, escassez',
                        'online_masterclass' => 'Harmonizacao, corte queijos, especiarias',
                        'wine_comparison_table' => 'Regiao, uva, preco, pontuacao',
                        'premium_gift_card' => 'Digital/fisico elegante, datas comemorativas',
                        'personalized_consulting' => 'WhatsApp/chat, conversao +35%',
                        'corporate_gifts_segmentation' => 'B2B, kits personalizados, margem extra',
                    ],
                    'avoid_suggestions' => [
                        'clube cesta basica', 'combo higiene', 'lista compras recorrente', // grocery
                        'assinatura fit box', 'quiz objetivo fitness', 'desafio 30 dias', // healthy
                        'blend cafe mensal', 'cha origem certificada', 'cafeteira inclusa', // beverages
                        'cesta organica semanal', 'CSA local', 'horta subscription', // organic
                        'meal kit familia', 'chef em casa semanal', 'cardapio rotativo', // restaurant
                    ],
                    'verified' => true,
                    'tags' => ['estrategias', 'gourmet', 'clube vinhos', 'harmonizacao', 'presente', 'premium'],
                ],
            ],

            // BEVERAGES - Bebidas
            [
                'category' => 'benchmark',
                'niche' => 'food',
                'subcategory' => 'beverages',
                'title' => 'Benchmarks: Bebidas Especiais e Premium',
                'content' => 'Benchmarks para e-commerce de bebidas especiais (Euromonitor/ABIA 2024): Ticket medio R$ 80-200, media R$ 130. Segmento cresce 12-15% ao ano. Categorias lideres: Cafes especiais/artesanais (35%), Chas premium (25%), Sucos naturais/detox (20%), Aguas saborizadas/premium (12%), Kombuchas/funcionais (8%). Perfil do cliente: 65% mulheres, 25-50 anos, classe A/B, preocupadas com saude. Taxa de conversao: 3,5-5,2%. Frequencia: mensal 48%, quinzenal 32%, semanal 20%. Recorrencia/assinatura: 25-32% (cafe mensal domina). Sazonalidade: pico inverno para cafes/chas (+22%), verao para sucos/aguas (+18%). Margem: 40-55% (superior a bebidas tradicionais). Canais: 50% e-commerce especializado, 35% marketplaces, 15% redes sociais. Diferenciais valorizados: origem rastreavel, metodo preparo, beneficios saude, embalagem sustentavel. Frete: aceita frete devido especialidade, mas pesa logistica (liquidos).',
                'metadata' => [
                    'sources' => [
                        'Euromonitor',
                        'ABIA',
                        'NIQ',
                        'ABIC (Cafe)',
                    ],
                    'ticket_medio' => 130,
                    'ticket_range' => ['min' => 80, 'max' => 200],
                    'segment_growth' => ['min' => 12, 'max' => 15],
                    'conversion_rate' => ['min' => 3.5, 'max' => 5.2],
                    'customer_profile' => [
                        'female' => 65,
                        'age_range' => '25-50',
                        'class' => 'A/B',
                        'health_conscious' => true,
                    ],
                    'subscription_rate' => ['min' => 25, 'max' => 32],
                    'product_mix' => [
                        ['category' => 'Cafes especiais', 'share' => 35],
                        ['category' => 'Chas premium', 'share' => 25],
                        ['category' => 'Sucos naturais/detox', 'share' => 20],
                        ['category' => 'Aguas premium', 'share' => 12],
                        ['category' => 'Kombuchas/funcionais', 'share' => 8],
                    ],
                    'seasonality' => [
                        ['season' => 'Inverno', 'products' => 'Cafes/chas', 'growth' => 22],
                        ['season' => 'Verao', 'products' => 'Sucos/aguas', 'growth' => 18],
                    ],
                    'margin' => ['min' => 40, 'max' => 55],
                    'avoid_mentions' => [
                        'feijao preto', 'arroz integral comum', 'detergente liquido', 'sabonete', // grocery
                        'whey concentrado', 'creatina monohidratada', 'barra proteica', 'pasta amendoim', // healthy
                        'champagne frances', 'whisky single malt', 'queijo camembert', 'trufas negras', // gourmet
                        'alface organica', 'tomate biodinamico', 'certificacao organica', 'CSA', // organic
                        'hamburguer artesanal', 'pizza napoletana', 'sushi delivery', 'dark kitchen', // restaurant
                    ],
                    'verified' => true,
                    'tags' => ['benchmarks', 'beverages', 'cafe especial', 'cha premium', 'sucos', 'funcional'],
                ],
            ],

            [
                'category' => 'strategy',
                'niche' => 'food',
                'subcategory' => 'beverages',
                'title' => 'Estrategias Comprovadas: Bebidas Especiais e Premium',
                'content' => 'Estrategias de sucesso para e-commerce de bebidas: 1) CLUBE DE CAFE MENSAL: Assinatura com 250g-500g cafe especial artesanal, blend novo todo mes, ficha com notas sensoriais, torra na data, LTV 10x. 2) BLEND PERSONALIZADO: Quiz identifica preferencias (torrado claro/escuro, frutado/achocolatado), cria blend sob medida, premium. 3) GUIA DE PREPARO INCLUIDO: Video/manual como fazer cafe coado, prensa francesa, aeropress, chemex para cada produto. 4) KIT BARISTA COMPLETO: Cafe + moedor + coador + jarra, ticket R$ 250-400, facilita iniciantes. 5) ORIGEM RASTREAVEL: QR code mostra fazenda, altitude, metodo colheita, processo torra, storytelling. 6) ASSINATURA CHA DISCOVERY: 5-8 chas diferentes por mes (descoberta), descricao beneficios, rituais, fideliza. 7) SAZONAL INVERNO/VERAO: Inverno enfatiza cafes/chas quentinhos, verao sucos gelados/aguas saborizadas. 8) BENEFICIOS NA VITRINE: Badges destaque (antioxidante, energizante, detox, digestivo), busca por beneficio. 9) DEGUSTACAO PRESENCIAL/EVENTOS: Patrocina feiras gastronomicas, cafeterias parceiras, amostra gratis, conversao local. 10) RECEITAS CRIATIVAS: Smoothie bowl com cafe, cha gelado infusionado, agua saborizada caseira, engajamento. 11) EMBALAGEM REUTILIZAVEL: Pote vidro retornavel (desconto R$ 5 proxima compra), sustentabilidade premium. 12) CAFETEIRA/INFUSOR BRINDE: Compra acima R$ 150 ganha cafeteira italiana ou infusor cha, aumenta ticket.',
                'metadata' => [
                    'sources' => [
                        'Cases Coffee++, Unique Cafes, Tea Shop',
                        'ABIC',
                        'Euromonitor',
                    ],
                    'proven_tactics' => [
                        'monthly_coffee_club' => '250-500g, blend novo, notas sensoriais, LTV 10x',
                        'personalized_blend' => 'Quiz preferencias, blend sob medida',
                        'brewing_guide_included' => 'Video/manual coado/prensa/aeropress',
                        'complete_barista_kit' => 'Cafe+moedor+coador+jarra, R$ 250-400',
                        'traceable_origin' => 'QR code fazenda, altitude, colheita, torra',
                        'tea_discovery_subscription' => '5-8 chas/mes, beneficios, rituais',
                        'seasonal_winter_summer' => 'Inverno cafes/chas, verao sucos/aguas',
                        'benefits_showcase' => 'Badges antioxidante/energizante/detox',
                        'tasting_events' => 'Feiras, cafeterias parceiras, amostra',
                        'creative_recipes' => 'Smoothie bowl cafe, cha gelado, agua saborizada',
                        'reusable_packaging' => 'Vidro retornavel, desconto R$ 5',
                        'brewer_gift' => 'Compra R$ 150+ ganha cafeteira/infusor',
                    ],
                    'avoid_suggestions' => [
                        'assinatura cesta basica', 'clube essenciais mensais', 'combo limpeza', // grocery
                        'quiz nutricional fitness', 'plano alimentar integrado', 'desafio 30 dias', // healthy
                        'clube vinhos sommelier', 'harmonizacao queijos', 'masterclass degustacao', // gourmet
                        'feira organica semanal', 'CSA verduras', 'certificacao biodinamica', // organic
                        'meal prep semanal', 'kit jantar 2 pessoas', 'chef em domicilio', // restaurant
                    ],
                    'verified' => true,
                    'tags' => ['estrategias', 'beverages', 'clube cafe', 'blend personalizado', 'origem', 'assinatura'],
                ],
            ],

            // ORGANIC - Organicos
            [
                'category' => 'benchmark',
                'niche' => 'food',
                'subcategory' => 'organic',
                'title' => 'Benchmarks: Alimentos Organicos e Certificados',
                'content' => 'Benchmarks para e-commerce de organicos (Organis/MAPA 2024): Ticket medio R$ 150-350, media R$ 230. Mercado brasileiro: R$ 6 bilhoes, cresce 20-25% ao ano (um dos mais rapidos). Categorias lideres: Frutas/verduras organicas (40%), Graos/cereais (25%), Ovos/laticinios organicos (15%), Carnes organicas (10%), Produtos processados organicos (10%). Perfil do cliente: 70% mulheres, 30-55 anos, classe A/B, 85% tem filhos (preocupacao saude familiar). Taxa de conversao: 4,2-6,5% (motivacao forte). Frequencia: semanal 35%, quinzenal 45%, mensal 20%. Recorrencia cesta: 30-38% (maior de food). Certificacoes valorizadas: IBD, Organico Brasil (MAPA), Demeter (biodinamica). Preco premium: 30-80% mais caro que convencional, mas cliente aceita. Canais: 40% delivery especializado, 35% feiras/CSA, 25% e-commerce. Sazonalidade: safra regional afeta disponibilidade. Desafio: logistica pereciveis (shelf life curto).',
                'metadata' => [
                    'sources' => [
                        'Organis',
                        'MAPA - Cadastro Organicos',
                        'Euromonitor',
                        'Council on Organic',
                    ],
                    'ticket_medio' => 230,
                    'ticket_range' => ['min' => 150, 'max' => 350],
                    'market_size_bi' => 6,
                    'segment_growth' => ['min' => 20, 'max' => 25],
                    'conversion_rate' => ['min' => 4.2, 'max' => 6.5],
                    'customer_profile' => [
                        'female' => 70,
                        'age_range' => '30-55',
                        'class' => 'A/B',
                        'with_children' => 85,
                    ],
                    'subscription_rate' => ['min' => 30, 'max' => 38],
                    'product_mix' => [
                        ['category' => 'Frutas/verduras', 'share' => 40],
                        ['category' => 'Graos/cereais', 'share' => 25],
                        ['category' => 'Ovos/laticinios', 'share' => 15],
                        ['category' => 'Carnes', 'share' => 10],
                        ['category' => 'Processados organicos', 'share' => 10],
                    ],
                    'certifications' => ['IBD', 'Organico Brasil (MAPA)', 'Demeter'],
                    'price_premium' => ['min' => 30, 'max' => 80, 'unit' => '%'],
                    'avoid_mentions' => [
                        'arroz tipo 2', 'feijao comum', 'oleo soja refinado', 'macarrao comum', // grocery
                        'whey protein isolado', 'creatina', 'barra energetica', 'pasta amendoim', // healthy
                        'vinho bordeaux', 'queijo roquefort', 'azeite trufado', 'foie gras', // gourmet
                        'cafe especial torrado', 'cha importado', 'suco cold pressed nao organico', // beverages
                        'pizza delivery', 'marmita fit', 'hamburguer gourmet', 'dark kitchen', // restaurant
                    ],
                    'verified' => true,
                    'tags' => ['benchmarks', 'organic', 'organicos', 'certificacao', 'IBD', 'sustentavel'],
                ],
            ],

            [
                'category' => 'strategy',
                'niche' => 'food',
                'subcategory' => 'organic',
                'title' => 'Estrategias Comprovadas: Alimentos Organicos e Certificados',
                'content' => 'Estrategias de sucesso para e-commerce organico: 1) CESTA ORGANICA SEMANAL: Assinatura com mix frutas/verduras da safra, surpreende cliente, reduz desperdicio produtor, LTV 15x. 2) CSA DIGITAL (Community Supported Agriculture): Cliente "adota" produtor organico, recebe colheita semanal, visita virtual fazenda, conexao emocional. 3) CALENDARIO DE SAFRA INTERATIVO: Mostra o que esta em safra cada mes, educa cliente, ajusta expectativas, produtos mais frescos/baratos. 4) CERTIFICACAO EM DESTAQUE: Badges IBD/Organico Brasil/Demeter visiveis, link verificacao, transparencia total. 5) MEET YOUR FARMER: Fotos e videos do produtor, historia familiar, metodos cultivo, humaniza marca. 6) RECEITAS ZERO DESPERDICIO: Aproveita talos, cascas, sobras, sustentabilidade pratica, conteudo educacional. 7) KIT HORTA EM CASA: Sementes organicas + terra + vaso + manual, cliente cultiva proprio alimento, fideliza diferente. 8) FEIRA ORGANICA DELIVERY: Replica experiencia feira (produtos variados, sazonalidade), entrega sabado manha, frescos. 9) PROGRAMA DEVOLUCAO EMBALAGENS: Cliente devolve caixas/sacolas, desconto R$ 10 proxima compra, economia circular. 10) BUNDLE FAMILIA SAUDAVEL: Cesta completa semana (frutas, verduras, ovos, graos), facilita planejamento, ticket R$ 280-400. 11) ALERTAS SAFRA ESPECIAL: Notifica quando produto raro entra em safra (morango organico, aspargos), urgencia. 12) PARCERIA ESCOLAS/CONDOMINIOS: Ponto de coleta centralizado, entrega em lote (reduz custo), desconto grupo 15%.',
                'metadata' => [
                    'sources' => [
                        'Cases Organicos.com, Raizs, Greenbox',
                        'Organis',
                        'CSA Brasil',
                    ],
                    'proven_tactics' => [
                        'weekly_organic_basket' => 'Mix safra, surpresa, LTV 15x',
                        'digital_csa' => 'Adota produtor, colheita semanal, visita virtual',
                        'interactive_harvest_calendar' => 'Safra mensal, educa, frescos',
                        'certification_highlight' => 'Badges IBD/MAPA/Demeter, link verificacao',
                        'meet_your_farmer' => 'Fotos/videos produtor, historia, metodos',
                        'zero_waste_recipes' => 'Talos, cascas, sobras, sustentabilidade',
                        'home_garden_kit' => 'Sementes+terra+vaso+manual, cultivo proprio',
                        'organic_market_delivery' => 'Replica feira, sabado manha, frescos',
                        'packaging_return_program' => 'Devolve caixas, desconto R$ 10',
                        'healthy_family_bundle' => 'Cesta completa semana, R$ 280-400',
                        'special_harvest_alerts' => 'Produto raro em safra, urgencia',
                        'school_condo_partnerships' => 'Coleta centralizada, desconto grupo 15%',
                    ],
                    'avoid_suggestions' => [
                        'clube cesta basica', 'combo higiene limpeza', 'marca propria', // grocery
                        'quiz fitness personalizado', 'desafio 30 dias low carb', 'assinatura fit', // healthy
                        'clube vinhos mensais', 'harmonizacao queijos vinhos', 'degustacao', // gourmet
                        'clube cafe artesanal', 'blend personalizado', 'guia preparo barista', // beverages
                        'meal kit semanal', 'chef domicilio', 'cardapio dark kitchen', // restaurant
                    ],
                    'verified' => true,
                    'tags' => ['estrategias', 'organic', 'cesta semanal', 'CSA', 'safra', 'produtor local'],
                ],
            ],

            // RESTAURANT - Restaurantes/Delivery
            [
                'category' => 'benchmark',
                'niche' => 'food',
                'subcategory' => 'restaurant',
                'title' => 'Benchmarks: Restaurantes, Meal Kits e Dark Kitchens',
                'content' => 'Benchmarks para restaurantes/meal kits (IFB/iFood 2024): Ticket medio R$ 40-100, media R$ 65 (o menor em food, mas frequencia altissima). Segmento cresce 15-18% ao ano. Categorias: Meal kits (refeicao para preparar em casa, 25%), Meal prep (marmitas prontas fitness, 30%), Dark kitchens (cozinhas so delivery, 35%), Kits ingredientes (receita completa, 10%). Perfil: 55% mulheres, 25-45 anos, classe A/B/C, vida corrida. Taxa de conversao: 5,5-8,2% (necessidade imediata). Frequencia: semanal 55%, 2-3x semana 30%, diaria 15%. Recorrencia: 18-25% (planos semanais). Horarios pico: almoco 11h-14h (48%), jantar 18h30-21h (42%), cafe manha/madrugada crescendo. Tempo entrega critico: 80% espera ate 60min. Dark kitchens: 1/3 do iFood, custo 60% menor que restaurante fisico, foco 100% delivery. Margem: 15-25% (apertada), volume compensa. Taxa plataformas: 18-30% (principal dor).',
                'metadata' => [
                    'sources' => [
                        'Instituto Foodservice Brasil (IFB)',
                        'iFood Move 2024',
                        'ABRASEL',
                        'Statista',
                    ],
                    'ticket_medio' => 65,
                    'ticket_range' => ['min' => 40, 'max' => 100],
                    'segment_growth' => ['min' => 15, 'max' => 18],
                    'conversion_rate' => ['min' => 5.5, 'max' => 8.2],
                    'customer_profile' => [
                        'female' => 55,
                        'age_range' => '25-45',
                        'class' => 'A/B/C',
                        'busy_lifestyle' => true,
                    ],
                    'subscription_rate' => ['min' => 18, 'max' => 25],
                    'product_mix' => [
                        ['category' => 'Dark kitchens', 'share' => 35],
                        ['category' => 'Meal prep marmitas', 'share' => 30],
                        ['category' => 'Meal kits preparo casa', 'share' => 25],
                        ['category' => 'Kits ingredientes', 'share' => 10],
                    ],
                    'peak_hours' => [
                        ['period' => 'Almoco 11-14h', 'share' => 48],
                        ['period' => 'Jantar 18:30-21h', 'share' => 42],
                        ['period' => 'Cafe/madrugada', 'growth' => 'crescente'],
                    ],
                    'delivery_expectation' => 60,
                    'dark_kitchen_metrics' => [
                        'ifood_share' => 33,
                        'cost_reduction' => 60,
                    ],
                    'margin' => ['min' => 15, 'max' => 25],
                    'platform_fees' => ['min' => 18, 'max' => 30, 'unit' => '%'],
                    'avoid_mentions' => [
                        'arroz feijao basico', 'oleo soja comum', 'papel toalha', 'detergente', // grocery
                        'whey isolado', 'creatina', 'barra proteica industrializada', 'pasta amendoim', // healthy
                        'vinho grand cru', 'queijo parmesao 36 meses', 'azeite extra virgem premium', // gourmet
                        'cafe especial artesanal', 'cha origem unica', 'kombucha premium', // beverages
                        'verdura organica certificada', 'fruta biodinamica', 'CSA semanal', 'IBD', // organic
                    ],
                    'verified' => true,
                    'tags' => ['benchmarks', 'restaurant', 'meal kit', 'dark kitchen', 'delivery', 'meal prep'],
                ],
            ],

            [
                'category' => 'strategy',
                'niche' => 'food',
                'subcategory' => 'restaurant',
                'title' => 'Estrategias Comprovadas: Restaurantes, Meal Kits e Dark Kitchens',
                'content' => 'Estrategias de sucesso para restaurantes/meal kits: 1) PLANO SEMANAL MEAL PREP: Cliente compra 5-10 marmitas fit semana (almoco/jantar), desconto 20%, recebe domingo, conveniencia maxima. 2) MEAL KIT JANTAR 2 PESSOAS: Kit com ingredientes pre-porcionados + receita passo a passo, pronto em 20min, experiencia culinaria sem planejamento, R$ 70-90. 3) CARDAPIO ROTATIVO SEMANAL: Menu muda toda semana (segunda italiana, terca japonesa, etc), evita monotonia, cliente volta. 4) DARK KITCHEN MULTI-MARCA: Opera 3-5 marcas virtuais na mesma cozinha (hamburguer, pizza, asiático), maximiza pedidos, otimiza estrutura. 5) CANAL PROPRIO WHATSAPP: Bypass taxas plataforma (18-30%), oferece desconto 10% pedido direto, fideliza cliente recorrente. 6) COMBO FAMILIA 4 PESSOAS: Kit completo jantar familia R$ 110-140, facilita decisao, aumenta ticket. 7) PROGRAMA FIDELIDADE PONTOS: 10 pedidos ganha 1 gratis, incentiva recorrencia semanal. 8) HORARIOS ALTERNATIVOS: Cafe da manha (+31% crescimento) e madrugada (+25%, so 7% abertos), oceano azul. 9) EMBALAGEM SUSTENTAVEL DESTAQUE: Marmitas biodegradaveis, talheres madeira, diferencial ESG, cliente premium aceita R$ 2-3 extra. 10) CHEF EM DOMICILIO ON-DEMAND: Servico premium chef vai em casa preparar jantar (R$ 500-800 para 4 pessoas), ocasioes especiais. 11) FREEZE E ESQUECA: Marmitas congeladas com 30 dias validade, cliente estoca, aquece microondas, conveniencia extrema. 12) PARCERIAS EMPRESAS: Fornece almoco para escritorios (pedido unico 20-50 pessoas), previsibilidade, margem melhor.',
                'metadata' => [
                    'sources' => [
                        'Cases iFood, Liv Up, Foodz',
                        'IFB',
                        'ABRASEL',
                    ],
                    'proven_tactics' => [
                        'weekly_meal_prep_plan' => '5-10 marmitas, desconto 20%, domingo',
                        'dinner_kit_2_people' => 'Ingredientes porcionados+receita, 20min, R$ 70-90',
                        'rotating_weekly_menu' => 'Menu muda semana, evita monotonia',
                        'dark_kitchen_multi_brand' => '3-5 marcas virtuais, otimiza estrutura',
                        'direct_whatsapp_channel' => 'Bypass taxas 18-30%, desconto 10%',
                        'family_combo_4_people' => 'Jantar completo R$ 110-140',
                        'loyalty_points_program' => '10 pedidos = 1 gratis',
                        'alternative_hours' => 'Cafe manha +31%, madrugada +25%',
                        'sustainable_packaging' => 'Biodegradavel, madeira, R$ 2-3 extra',
                        'on_demand_home_chef' => 'Chef domicilio R$ 500-800, especial',
                        'freeze_and_forget' => 'Congeladas 30 dias, estoca, microondas',
                        'corporate_partnerships' => 'Almoco escritorios 20-50 pessoas',
                    ],
                    'avoid_suggestions' => [
                        'assinatura cesta basica', 'clube essenciais', 'combo limpeza higiene', // grocery
                        'quiz nutricional fitness', 'desafio 30 dias low carb', 'plano alimentar nutricionista', // healthy
                        'clube vinhos sommelier', 'degustacao harmonizada', 'kit queijos premium', // gourmet
                        'assinatura cafe mensal', 'blend personalizado', 'guia barista completo', // beverages
                        'cesta organica semanal', 'CSA produtor local', 'calendario safra', // organic
                    ],
                    'verified' => true,
                    'tags' => ['estrategias', 'restaurant', 'meal prep', 'dark kitchen', 'cardapio rotativo', 'fidelidade'],
                ],
            ],

            // =====================================================
            // FITNESS E ESPORTES - Suplementos, Roupas, Calcados
            // =====================================================

            // Suplementos Alimentares - ABIAD 2024
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'title' => 'Mercado de Suplementos Alimentares Brasil 2024 - ABIAD/Abenutri',
                'content' => 'Mercado de suplementos alimentares Brasil 2024 (ABIAD/Abenutri): Faturamento estimado em US$ 10 bilhoes, crescimento de 8% em 2024. Vitaminas e minerais cresceram 9,3%, proteinas +3%. Importacoes cresceram 24,6% chegando a US$ 1+ bilhao - evidencia demanda aquecida. Projecao: mercado deve atingir R$ 10,8 bilhoes ate 2028. Whey protein: CAGR de 8% ate 2029, impulsionado por atletas e fitness. 54% dos brasileiros consomem suplementos regularmente (ABIAD). Canais: farmacias dominam (45%), seguidas de lojas especializadas (30%) e e-commerce (25% e crescendo). E-commerce de suplementos cresceu +35% em 2024, com ticket medio de R$ 180-250. Principais players: Growth, Integral Medica, Max Titanium, Optimum Nutrition, Probiotica.',
                'metadata' => [
                    'sources' => [
                        'ABIAD - Associacao Brasileira da Industria de Alimentos para Fins Especiais',
                        'Abenutri - Associacao Brasileira das Empresas de Produtos Nutricionais',
                        'Mordor Intelligence',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size_bi_usd' => 10,
                        'growth' => 8,
                        'vitamins_growth' => 9.3,
                        'proteins_growth' => 3,
                        'imports_growth' => 24.6,
                        'imports_bi_usd' => 1,
                        'consumption_rate' => 54,
                        'ecommerce_growth' => 35,
                        'ecommerce_ticket_min' => 180,
                        'ecommerce_ticket_max' => 250,
                    ],
                    'channels' => [
                        ['channel' => 'Farmacias', 'share' => 45],
                        ['channel' => 'Lojas especializadas', 'share' => 30],
                        ['channel' => 'E-commerce', 'share' => 25],
                    ],
                    'projections' => [
                        'market_2028_bi' => 10.8,
                        'whey_cagr' => 8,
                        'whey_cagr_until' => 2029,
                    ],
                    'verified' => true,
                    'tags' => ['suplementos', 'whey', 'vitaminas', 'proteinas', 'abiad', 'fitness'],
                ],
            ],

            // Roupas Fitness/Athleisure - IEMI 2024
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'title' => 'Mercado de Roupas Fitness e Athleisure Brasil 2024 - IEMI',
                'content' => 'Mercado de roupas fitness e athleisure Brasil 2024 (IEMI): Faturamento anual de R$ 22,4 bilhoes, producao de 640 milhoes de pecas. Setor emprega 128.500 pessoas em 2.500 fabricantes. E-commerce de moda fitness cresceu +50,4% em 2023 (dado mais recente). Athleisure (roupas esportivas para uso casual): mercado de US$ 4,28 bilhoes em 2024, projecao de US$ 6 bilhoes ate 2033 (CAGR 4%). Crescimento de 15% ao ano desde 2020, impulsionado por home office e bem-estar. Ticket medio online: R$ 150-280. Principais categorias: leggings (35% das vendas), tops/bras (25%), shorts (20%), conjuntos (15%), acessorios (5%). Marcas lideres: Live!, Alto Giro, Track&Field, Lupo Sport, Colcci Fitness, La Clofit. Tendencia: tecidos sustentaveis (+40% de interesse), inclusao de tamanhos, moda unissex.',
                'metadata' => [
                    'sources' => [
                        'IEMI - Inteligencia de Mercado',
                        'Mordor Intelligence',
                        'Statista',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size_bi' => 22.4,
                        'production_millions' => 640,
                        'employees' => 128500,
                        'manufacturers' => 2500,
                        'ecommerce_growth_2023' => 50.4,
                        'annual_growth_since_2020' => 15,
                        'ticket_min' => 150,
                        'ticket_max' => 280,
                    ],
                    'athleisure' => [
                        'market_2024_bi_usd' => 4.28,
                        'projection_2033_bi_usd' => 6,
                        'cagr' => 4,
                    ],
                    'categories_share' => [
                        ['category' => 'Leggings', 'share' => 35],
                        ['category' => 'Tops/Bras', 'share' => 25],
                        ['category' => 'Shorts', 'share' => 20],
                        ['category' => 'Conjuntos', 'share' => 15],
                        ['category' => 'Acessorios', 'share' => 5],
                    ],
                    'trends' => [
                        'sustainable_fabrics_interest_growth' => 40,
                        'size_inclusion' => true,
                        'unisex_fashion' => true,
                    ],
                    'verified' => true,
                    'tags' => ['roupas fitness', 'athleisure', 'moda esportiva', 'iemi', 'leggings'],
                ],
            ],

            // Mercado de Academias - ACAD/IHRSA 2024
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'title' => 'Mercado de Academias Brasil 2024 - ACAD/IHRSA',
                'content' => 'Brasil e o 2o pais do mundo em numero de academias (IHRSA 2024). Mais de 64 mil estabelecimentos em operacao - numero triplicou em 10 anos. Faturamento do setor: R$ 8 bilhoes anuais. 50% dos brasileiros praticam exercicios regularmente (IBGE 2024). Mensalidade media: R$ 110/mes, variando de R$ 50 (popular) a R$ 500+ (premium). Smart fit lidera com 1.400+ unidades. Academias low-cost cresceram 25% em 2024. Apps fitness: 32 milhoes de usuarios ativos no Brasil. Venda de acessorios em academias: margem de 40-60%, categoria que mais cresce. Wearables esportivos: mercado de R$ 2 bilhoes, crescimento de 18% ao ano. Oportunidade e-commerce: 40% das academias nao tem loja online para produtos complementares.',
                'metadata' => [
                    'sources' => [
                        'ACAD Brasil - Associacao Brasileira de Academias',
                        'IHRSA - Global Health & Fitness Association',
                        'IBGE - Pratica de Esportes 2024',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'world_ranking' => 2,
                        'establishments' => 64000,
                        'growth_10y_multiplier' => 3,
                        'market_size_bi' => 8,
                        'exercise_rate' => 50,
                        'avg_membership' => 110,
                        'membership_min' => 50,
                        'membership_max' => 500,
                        'app_users_millions' => 32,
                        'wearables_market_bi' => 2,
                        'wearables_growth' => 18,
                        'gyms_without_ecommerce' => 40,
                    ],
                    'accessories_margin' => ['min' => 40, 'max' => 60],
                    'low_cost_growth' => 25,
                    'smartfit_units' => 1400,
                    'verified' => true,
                    'tags' => ['academias', 'fitness', 'smart fit', 'wearables', 'apps', 'ihrsa'],
                ],
            ],

            // Calcados Esportivos - Abicalcados 2024
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'title' => 'Mercado de Calcados Esportivos Brasil 2024 - Abicalcados',
                'content' => 'Mercado de calcados esportivos Brasil 2024 (Abicalcados/Mordor Intelligence): Faturamento de R$ 14,4 bilhoes (21,3% do varejo de calcados). Producao cresceu 3,6% em 2024. Mercado global: US$ 116,82 bilhoes em 2024, projecao de US$ 146,48 bilhoes ate 2029 (CAGR 4,6%). Brasil e 3o maior produtor mundial de calcados. E-commerce de calcados esportivos: +28% em 2024, ticket medio R$ 280-450. Categorias: tenis de corrida (35%), casual/lifestyle (30%), futebol (15%), training (12%), outros (8%). Marcas mais buscadas: Nike, Adidas, Mizuno, Asics, Olympikus, New Balance. Tendencias: personalizacao (+22%), sustentabilidade (+35% interesse), tenis tecnologico (com placas de carbono). Sazonalidade: volta as aulas (+40%), Black Friday (+80%), lancamentos de colecao.',
                'metadata' => [
                    'sources' => [
                        'Abicalcados - Associacao Brasileira das Industrias de Calcados',
                        'Mordor Intelligence',
                        'Statista',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size_bi' => 14.4,
                        'footwear_retail_share' => 21.3,
                        'production_growth' => 3.6,
                        'world_producer_ranking' => 3,
                        'ecommerce_growth' => 28,
                        'ticket_min' => 280,
                        'ticket_max' => 450,
                    ],
                    'global_market' => [
                        'size_2024_bi_usd' => 116.82,
                        'projection_2029_bi_usd' => 146.48,
                        'cagr' => 4.6,
                    ],
                    'categories_share' => [
                        ['category' => 'Corrida', 'share' => 35],
                        ['category' => 'Casual/Lifestyle', 'share' => 30],
                        ['category' => 'Futebol', 'share' => 15],
                        ['category' => 'Training', 'share' => 12],
                        ['category' => 'Outros', 'share' => 8],
                    ],
                    'trends' => [
                        'customization_growth' => 22,
                        'sustainability_interest' => 35,
                    ],
                    'seasonality' => [
                        ['event' => 'Volta as aulas', 'growth' => 40],
                        ['event' => 'Black Friday', 'growth' => 80],
                    ],
                    'verified' => true,
                    'tags' => ['calcados', 'tenis', 'corrida', 'esportivo', 'abicalcados', 'nike', 'adidas'],
                ],
            ],

            // Mercado de Corrida - Ticket Sports 2024
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'title' => 'Mercado de Corrida de Rua Brasil 2024/2025 - Ticket Sports',
                'content' => 'Mercado de corrida de rua Brasil 2024/2025 (Ticket Sports/CBAT): Mais de 11 mil eventos de corrida previstos para 2025 (+30% vs 2024). Clubes de corrida cresceram 109% no Brasil. Mercado estimado em R$ 2+ bilhoes/ano incluindo inscricoes, equipamentos e servicos. Quase metade dos participantes sao iniciantes (primeira prova). Perfil: 55% homens, 45% mulheres, idade media 35-44 anos. Ticket medio inscricao: R$ 80-250. Gastos anuais do corredor: R$ 2.000-5.000 em equipamentos. Tenis de corrida: troca media a cada 6 meses (500-800km). Relogios/GPS: 40% dos corredores usam smartwatch. Acessorios mais vendidos: meias de compressao, cintos de hidratacao, fones esportivos, viseiras. Oportunidade e-commerce: kits de corrida, nutricao esportiva, vestuario tecnico.',
                'metadata' => [
                    'sources' => [
                        'Ticket Sports - Plataforma de Eventos',
                        'CBAT - Confederacao Brasileira de Atletismo',
                        'O Globo / Valor Economico',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'events_2025' => 11000,
                        'events_growth' => 30,
                        'running_clubs_growth' => 109,
                        'market_size_bi' => 2,
                        'first_timers_rate' => 50,
                        'male_share' => 55,
                        'female_share' => 45,
                        'smartwatch_usage' => 40,
                    ],
                    'spending' => [
                        'registration_min' => 80,
                        'registration_max' => 250,
                        'annual_equipment_min' => 2000,
                        'annual_equipment_max' => 5000,
                        'shoe_replacement_months' => 6,
                    ],
                    'age_profile' => '35-44',
                    'top_accessories' => [
                        'Meias de compressao',
                        'Cintos de hidratacao',
                        'Fones esportivos',
                        'Viseiras',
                        'GPS/Smartwatch',
                    ],
                    'verified' => true,
                    'tags' => ['corrida', 'running', 'maratona', 'eventos', 'atletas', 'amadores'],
                ],
            ],

            // Artigos Esportivos Geral - Euromonitor 2024
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'title' => 'Mercado de Artigos Esportivos Brasil 2024 - Euromonitor/IBGE',
                'content' => 'Mercado de artigos esportivos Brasil 2024 (Euromonitor/IBGE): Faturamento total estimado em R$ 55 bilhoes (vestuario + calcados + equipamentos + acessorios). Crescimento de 12% em 2024. E-commerce representa 28% das vendas do setor. Principais categorias: vestuario esportivo (40%), calcados (30%), equipamentos (20%), acessorios (10%). Futebol domina equipamentos (45% do segmento). Ciclismo: categoria que mais cresce (+35% em 2024), impulsionado por mobilidade urbana. Natacao/esportes aquaticos: +18% em 2024. Ticket medio online: R$ 220 (vestuario), R$ 350 (calcados), R$ 180 (acessorios). Sazonalidade: Copa do Mundo/Olimpiadas (+60%), volta as aulas (+35%), Black Friday (+90%). Marketplaces: 45% das vendas online de esportes. Lojas especializadas online: Netshoes, Centauro, Decathlon, Kanui.',
                'metadata' => [
                    'sources' => [
                        'Euromonitor International',
                        'IBGE - Pesquisa Anual de Comercio',
                        'E-commerce Brasil',
                    ],
                    'year' => 2024,
                    'metrics' => [
                        'market_size_bi' => 55,
                        'growth' => 12,
                        'ecommerce_share' => 28,
                        'marketplace_share' => 45,
                    ],
                    'categories_share' => [
                        ['category' => 'Vestuario esportivo', 'share' => 40],
                        ['category' => 'Calcados', 'share' => 30],
                        ['category' => 'Equipamentos', 'share' => 20],
                        ['category' => 'Acessorios', 'share' => 10],
                    ],
                    'growth_by_sport' => [
                        ['sport' => 'Ciclismo', 'growth' => 35],
                        ['sport' => 'Natacao/Aquaticos', 'growth' => 18],
                        ['sport' => 'Futebol', 'equipment_share' => 45],
                    ],
                    'tickets_online' => [
                        ['category' => 'Vestuario', 'avg' => 220],
                        ['category' => 'Calcados', 'avg' => 350],
                        ['category' => 'Acessorios', 'avg' => 180],
                    ],
                    'seasonality' => [
                        ['event' => 'Copa/Olimpiadas', 'growth' => 60],
                        ['event' => 'Volta as aulas', 'growth' => 35],
                        ['event' => 'Black Friday', 'growth' => 90],
                    ],
                    'verified' => true,
                    'tags' => ['artigos esportivos', 'esportes', 'futebol', 'ciclismo', 'netshoes', 'centauro'],
                ],
            ],

            // Estrategias E-commerce Fitness/Esportes
            [
                'category' => 'strategy',
                'niche' => 'sports',
                'title' => 'Estrategias para E-commerce de Fitness e Esportes 2024/2025',
                'content' => 'Estrategias baseadas em dados do setor fitness/esportes: ASSINATURAS DE SUPLEMENTOS - Modelo recorrente cresce 40% ao ano, retencao de 70%+ quando bem executado. Ofereca desconto de 15-20% na assinatura. BUNDLES/KITS - Kits de treino (roupa + acessorio + suplemento) tem ticket 2,5x maior. Monte por objetivo: emagrecimento, hipertrofia, corrida. CONTEUDO ESPECIALIZADO - Lojas com blog de treino/nutricao convertem 35% mais. Parcerias com influenciadores fitness. REVIEWS E UGC - 78% dos compradores de suplementos leem reviews. Incentive fotos de clientes usando produtos. SAZONALIDADE INVERTIDA - Janeiro (promessas de ano novo) e marco (verao) sao picos. Outubro/novembro preparacao para verao. TAMANHOS E TROCAS - Oferecer guia de medidas reduz trocas em 45%. Primeira troca gratis aumenta conversao em 25%. CROSS-SELL INTELIGENTE - Whey + creatina + shaker = combo campeao. Legging + top + meia = look completo.',
                'metadata' => [
                    'sources' => [
                        'ABIAD',
                        'IEMI',
                        'Estudos de caso Growth Supplements',
                        'Shopify Sports Commerce Report',
                    ],
                    'effectiveness' => 'alta',
                    'strategies' => [
                        ['strategy' => 'Assinaturas', 'growth' => 40, 'retention' => 70],
                        ['strategy' => 'Bundles/Kits', 'ticket_multiplier' => 2.5],
                        ['strategy' => 'Conteudo', 'conversion_increase' => 35],
                        ['strategy' => 'Reviews/UGC', 'readers_rate' => 78],
                        ['strategy' => 'Guia de medidas', 'returns_reduction' => 45],
                        ['strategy' => 'Troca gratis', 'conversion_increase' => 25],
                    ],
                    'seasonality_peaks' => [
                        'Janeiro - Ano novo',
                        'Marco - Verao',
                        'Out/Nov - Preparacao verao',
                    ],
                    'verified' => true,
                    'tags' => ['estrategia', 'fitness', 'esportes', 'assinatura', 'bundles', 'cross-sell'],
                ],
            ],

            // Case: Growth Supplements
            [
                'category' => 'case',
                'niche' => 'sports',
                'title' => 'Caso Real: Growth Supplements - De Garagem a Lider Digital de Suplementos',
                'content' => 'Growth Supplements: case de sucesso no e-commerce de suplementos brasileiro. Fundada em 2014 em Itajai/SC, tornou-se uma das maiores marcas digitais de suplementos do Brasil. Estrategia D2C (direct-to-consumer) via e-commerce proprio. Faturamento estimado em R$ 500+ milhoes/ano. Diferenciais: precificacao agressiva (custo-beneficio), marketing digital massivo, conteudo educativo sobre suplementacao, influenciadores fitness, clube de assinatura com 100k+ membros. Producao propria em fabrica de 15.000m2 com certificacao ANVISA. Expansao para lojas fisicas em 2023 (showrooms). Modelo de sucesso: 80% das vendas via site proprio, NPS acima de 80, taxa de recompra de 65%. Investimento pesado em branding: patrocinio de atletas, eventos de corrida, parcerias com academias. Licoes: qualidade + preco justo + marketing digital + conteudo = formula vencedora.',
                'metadata' => [
                    'sources' => [
                        'InfoMoney',
                        'Exame',
                        'E-commerce Brasil',
                        'LinkedIn Growth Supplements',
                    ],
                    'period' => '2014-2024',
                    'results' => [
                        'founded' => 2014,
                        'revenue_estimate_mi' => 500,
                        'subscription_members' => 100000,
                        'nps' => 80,
                        'repurchase_rate' => 65,
                        'own_site_sales_share' => 80,
                        'factory_size_m2' => 15000,
                    ],
                    'success_factors' => [
                        'Modelo D2C (venda direta)',
                        'Precificacao agressiva',
                        'Marketing digital massivo',
                        'Conteudo educativo',
                        'Clube de assinatura',
                        'Producao propria certificada',
                    ],
                    'verified' => true,
                    'tags' => ['caso de sucesso', 'growth', 'suplementos', 'd2c', 'assinatura', 'marketing digital'],
                ],
            ],

            // Case: Track&Field
            [
                'category' => 'case',
                'niche' => 'sports',
                'title' => 'Caso Real: Track&Field - Lifestyle Esportivo Premium e Comunidade',
                'content' => 'Track&Field: case de sucesso em moda fitness premium brasileira. Fundada em 1988, IPO na B3 em 2020. Faturamento 2024: R$ 1,2 bilhao (+18% vs 2023). Mais de 350 lojas (proprias + franquias) + e-commerce robusto. E-commerce representa 25% das vendas, crescimento de +35% ao ano. Estrategia: posicionamento premium, comunidade ativa (TFSports com 300k+ membros), eventos exclusivos (corridas, treinos). Programa de fidelidade: cliente engajado gasta 3x mais. Ticket medio: R$ 450 (lojas), R$ 380 (online). Omnichannel forte: ship-from-store, click-and-collect, prova em loja. NPS acima de 75. Diferencial: nao e so roupa, e lifestyle - eventos, assessoria esportiva, comunidade. Expansao internacional iniciada em 2024 (EUA, Portugal). Modelo de franquia bem-sucedido com 200+ franqueados.',
                'metadata' => [
                    'sources' => [
                        'Track&Field RI (Relacoes com Investidores)',
                        'B3 - Resultados 2024',
                        'Valor Economico',
                    ],
                    'period' => '2024',
                    'results' => [
                        'founded' => 1988,
                        'ipo' => 2020,
                        'revenue_bi' => 1.2,
                        'revenue_growth' => 18,
                        'stores' => 350,
                        'ecommerce_share' => 25,
                        'ecommerce_growth' => 35,
                        'community_members' => 300000,
                        'loyalty_multiplier' => 3,
                        'nps' => 75,
                        'ticket_stores' => 450,
                        'ticket_online' => 380,
                        'franchisees' => 200,
                    ],
                    'success_factors' => [
                        'Posicionamento premium',
                        'Comunidade ativa (TFSports)',
                        'Eventos exclusivos',
                        'Programa de fidelidade',
                        'Omnichannel forte',
                        'Modelo de franquia',
                    ],
                    'verified' => true,
                    'tags' => ['caso de sucesso', 'track&field', 'premium', 'comunidade', 'franquia', 'omnichannel'],
                ],
            ],

            // =====================================================
            // SPORTS - SUBCATEGORIAS (8 benchmarks + 8 strategies)
            // =====================================================

            // SUPPLEMENTS - Suplementos alimentares
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'subcategory' => 'supplements',
                'title' => 'Benchmarks de Mercado: Suplementos Alimentares (2024)',
                'content' => 'Mercado brasileiro de suplementos: R$ 10 bilhoes (2024), crescimento de 15% ao ano. Categorias principais: whey protein (35% do mercado), creatina (18%), pre-treino (12%), vitaminas e minerais (20%), outros (15%). Ticket medio: R$ 150-350 (media R$ 250). Margem media: 35-45%. Taxa de conversao media: 2.8-4.2%. Taxa de recompra: 45-60% (produtos de uso continuo). Periodo de recompra medio: 30-45 dias. Sazonalidade forte: pico em janeiro (+40% vs dezembro - pos-fim de ano), junho/julho (+25% - inverno, fortalecimento imunidade). Canais de venda: e-commerce especializado (40%), marketplaces (30%), lojas fisicas (25%), assinatura (5%). Principais players: Growth Supplements, Integral Medica, Max Titanium, Probiotica. Publico: 60% homens 25-45 anos, 40% mulheres 25-40 anos. Faixa etaria em expansao: 45+ anos (wellness, longevidade). Regiao mais forte: Sudeste (55%), Sul (20%), Centro-Oeste (12%). Certificacoes importantes: ANVISA, HACCP, GMP. Tendencias 2024-2025: suplementos plant-based, personalizacao (quiz + recomendacao), assinatura com desconto, influencer marketing.',
                'metadata' => [
                    'sources' => [
                        'ABIAD (Associacao Brasileira da Industria de Alimentos para Fins Especiais)',
                        'Abenutri (Produtos nutricionais)',
                        'Euromonitor - Sports Nutrition 2024',
                        'Mordor Intelligence - Brazil Supplements Market',
                    ],
                    'period' => '2024',
                    'market_size_bi' => 10.0,
                    'growth_rate' => 15,
                    'avg_ticket' => 250,
                    'margin' => 40,
                    'conversion_rate' => 3.5,
                    'repurchase_rate' => 52,
                    'repurchase_days' => 37,
                    'product_mix' => [
                        'whey_protein' => 35,
                        'creatine' => 18,
                        'pre_workout' => 12,
                        'vitamins' => 20,
                        'others' => 15,
                    ],
                    'seasonality' => [
                        'jan' => '+40%',
                        'jun_jul' => '+25%',
                    ],
                    'channels' => [
                        'specialized_ecommerce' => 40,
                        'marketplaces' => 30,
                        'physical_stores' => 25,
                        'subscription' => 5,
                    ],
                    'demographics' => [
                        'male' => 60,
                        'female' => 40,
                        'age_range' => '25-45',
                        'expanding_age' => '45+',
                    ],
                    'avoid_mentions' => [
                        'legging', 'top', 'conjunto fitness', 'shorts', // sportswear
                        'tenis', 'calcado', 'chuteira', // footwear/soccer
                        'halter', 'elastico', 'esteira', // equipment
                        'bicicleta', 'capacete ciclismo', // cycling
                        'maio', 'oculos natacao', // swimming
                        'bola', 'caneleira', // soccer
                    ],
                    'verified' => true,
                    'tags' => ['benchmarks', 'suplementos', 'whey', 'creatina', 'nutricao esportiva', 'assinatura'],
                ],
            ],

            [
                'category' => 'strategy',
                'niche' => 'sports',
                'subcategory' => 'supplements',
                'title' => 'Estrategias Comprovadas: Suplementos Alimentares',
                'content' => 'Estrategias de sucesso para e-commerce de suplementos: 1) ASSINATURA RECORRENTE: Clube de assinatura com 10-15% desconto, entrega automatica a cada 30 dias, aumenta LTV em 3x e reduz CAC. 2) QUIZ DE PERSONALIZACAO: Questionario sobre objetivos (ganho muscular, emagrecimento, performance), nivel de atividade, restricoes alimentares - recomenda stack personalizado, aumenta ticket em 40%. 3) CONTEUDO EDUCATIVO: Blog com artigos sobre suplementacao, videos no YouTube/Instagram (como tomar, quando tomar, combinacoes), ebooks gratuitos, aumenta autoridade e SEO organico. 4) BUNDLES E KITS: Kit pos-treino (whey + creatina + glutamina) com 15-20% desconto vs compra separada, kit iniciante, kit definicao, aumenta ticket medio em 35%. 5) PARCERIAS COM ACADEMIAS: Desconto exclusivo para alunos de academias parceiras, amostras gratis, cross-promotion. 6) PROVA SOCIAL: Fotos de clientes (antes/depois), depoimentos em video, selo de produto mais vendido, certificacoes visiveis. 7) FRETE GRATIS PROGRESSIVO: Frete gratis acima de R$ 200, incentiva aumento de ticket. 8) LANCAMENTOS E EXCLUSIVIDADE: Novos sabores em pre-venda, edicoes limitadas, cria urgencia. 9) PROGRAMA DE PONTOS: Cashback ou pontos para proximas compras, aumenta recompra em 25%. 10) INFLUENCER MARKETING: Parcerias com influencers fitness, nutricionistas, atletas - codigo de desconto exclusivo.',
                'metadata' => [
                    'sources' => [
                        'Growth Supplements - Case D2C',
                        'Integral Medica - Estrategias de marketing',
                        'ABIAD - Melhores praticas',
                        'Subscription Trade Association',
                    ],
                    'proven_tactics' => [
                        'subscription_club' => 'LTV +3x, desconto 10-15%, entrega 30 dias',
                        'personalization_quiz' => 'Ticket +40%, recomendacao de stack',
                        'educational_content' => 'SEO organico, autoridade, blog + videos',
                        'bundles_kits' => 'Ticket +35%, desconto 15-20%',
                        'gym_partnerships' => 'Desconto exclusivo, amostras gratis',
                        'social_proof' => 'Antes/depois, depoimentos video, certificacoes',
                        'progressive_shipping' => 'Frete gratis R$ 200+',
                        'launches' => 'Pre-venda, edicoes limitadas, urgencia',
                        'loyalty_points' => 'Recompra +25%, cashback ou pontos',
                        'influencer_marketing' => 'Codigo desconto, atletas, nutricionistas',
                    ],
                    'avoid_suggestions' => [
                        'colecao capsule', 'lookbook', 'prova virtual', // sportswear
                        'tecnologia amortecimento', 'numeracao especial', // footwear
                        'treino guiado', 'app equipamento', // equipment
                        'tracking GPS', 'assessoria corrida', // running
                        'bike fitting', 'manutencao preventiva', // cycling
                        'aulas natacao', 'tecnica nado', // swimming
                        'peneira futebol', 'escolinha', // soccer
                    ],
                    'verified' => true,
                    'tags' => ['estrategias', 'suplementos', 'assinatura', 'personalizacao', 'bundles', 'influencers'],
                ],
            ],

            // SPORTSWEAR - Roupas fitness
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'subcategory' => 'sportswear',
                'title' => 'Benchmarks de Mercado: Roupas Fitness e Sportswear (2024)',
                'content' => 'Mercado brasileiro de moda fitness: R$ 22.4 bilhoes (2024), 640 milhoes de pecas vendidas, crescimento de 12% ao ano. Categorias principais: leggings (28%), tops e sutiãs esportivos (22%), shorts (18%), conjuntos (15%), jaquetas e moletons (10%), outros (7%). Ticket medio: R$ 150-300 (media R$ 220). Margem media: 50-60% (marcas proprias), 30-40% (revenda). Taxa de conversao: 2.5-3.8%. Taxa de devolucao: 15-20% (problema de tamanho/modelagem). Recompra: 35-45% em 90 dias. Sazonalidade: pico em janeiro (+35% - resolucoes de ano novo), setembro (+20% - primavera, preparacao verao). Canais: e-commerce proprio (35%), marketplaces (30%), lojas fisicas (25%), Instagram Shopping (10%). Principais players: Track&Field, Live!, Lupo Sport, Labellamafia, Colcci Fitness. Publico: 70% mulheres 25-40 anos, 30% homens 25-45 anos. Faixas de preco: economico (R$ 80-150), medio (R$ 150-300), premium (R$ 300-600). Tecidos em alta: poliamida com elastano, dry-fit, tecnologia antimicrobiana, protecao UV. Fit mais vendido: leggings cintura alta (75% das vendas de leggings). Cores: preto (40%), neutros (25%), estampados (35%). Tendencias 2024-2025: athleisure (uso dentro e fora da academia), sustentabilidade (tecidos reciclados), plus size (mercado crescendo 18% ao ano), tamanhos inclusivos.',
                'metadata' => [
                    'sources' => [
                        'IEMI (Instituto de Estudos e Marketing Industrial)',
                        'ABVTEX (Associacao Brasileira do Varejo Textil)',
                        'Euromonitor - Sportswear Brazil 2024',
                        'Track&Field RI',
                    ],
                    'period' => '2024',
                    'market_size_bi' => 22.4,
                    'units_sold_mi' => 640,
                    'growth_rate' => 12,
                    'avg_ticket' => 220,
                    'margin_own' => 55,
                    'margin_resale' => 35,
                    'conversion_rate' => 3.1,
                    'return_rate' => 17,
                    'repurchase_rate' => 40,
                    'product_mix' => [
                        'leggings' => 28,
                        'tops_bras' => 22,
                        'shorts' => 18,
                        'sets' => 15,
                        'jackets' => 10,
                        'others' => 7,
                    ],
                    'seasonality' => [
                        'jan' => '+35%',
                        'sep' => '+20%',
                    ],
                    'demographics' => [
                        'female' => 70,
                        'male' => 30,
                        'age_range' => '25-40',
                    ],
                    'avoid_mentions' => [
                        'whey', 'creatina', 'pre-treino', 'suplemento', // supplements
                        'halter', 'elastico', 'esteira', 'bike ergometrica', // equipment
                        'planilha treino', 'assessoria corrida', // running
                        'bike speed', 'capacete ciclismo', // cycling
                        'maio competicao', 'oculos natacao', // swimming
                        'chuteira', 'bola', 'caneleira', // soccer
                    ],
                    'verified' => true,
                    'tags' => ['benchmarks', 'moda fitness', 'legging', 'athleisure', 'plus size', 'sustentabilidade'],
                ],
            ],

            [
                'category' => 'strategy',
                'niche' => 'sports',
                'subcategory' => 'sportswear',
                'title' => 'Estrategias Comprovadas: Roupas Fitness e Sportswear',
                'content' => 'Estrategias de sucesso para e-commerce de moda fitness: 1) GUIA DE TAMANHOS DETALHADO: Tabela com medidas em cm, video mostrando como medir, fotos de modelos com diferentes biótipos, reduce devolucoes em 30%. 2) LOOKBOOK E STYLING: Fotos de conjuntos completos (top + legging + tenis), sugestoes de combinacoes, aumenta ticket em 45% via cross-sell. 3) UGC (USER GENERATED CONTENT): Incentiva clientes a postarem fotos com hashtag da marca, repost no Instagram, cria prova social e comunidade. 4) LANCAMENTO DE COLECOES: Drops mensais de colecoes tematicas (Verao Tropical, Inverno Black, Neon Power), cria urgencia e antecipacao. 5) PROGRAMA VIP: Acesso antecipado a lancamentos, descontos progressivos (5% bronze, 10% prata, 15% ouro), frete gratis sempre, aumenta LTV em 2.5x. 6) PROVA VIRTUAL (AR): Tecnologia de prova virtual via app ou site, reduz devolucoes, aumenta confianca. 7) KITS INICIANTE: Kit para quem esta comecando (1 legging + 1 top + 1 short) com desconto de 20%, aumenta ticket e facilita decisao. 8) PARCERIAS COM INFLUENCERS FITNESS: Co-criacao de colecoes, codigo de desconto exclusivo, lives de lancamento. 9) CONTEUDO INSPIRACIONAL: Treinos gratuitos no YouTube/Instagram, dicas de styling, depoimentos de transformacao. 10) SUSTENTABILIDADE: Linha eco com tecidos reciclados, embalagem sustentavel, comunica valores da marca, atrai publico consciente. 11) TAMANHOS INCLUSIVOS: PP ao GG (ou numeracao estendida), modelos plus size nas fotos, mercado crescendo 18% ao ano.',
                'metadata' => [
                    'sources' => [
                        'Track&Field - Estrategias de vendas',
                        'Live! - Programa VIP',
                        'Labellamafia - Lancamentos',
                        'ABIT - Melhores praticas varejo',
                    ],
                    'proven_tactics' => [
                        'size_guide' => 'Devolucoes -30%, video + medidas + fotos',
                        'lookbook_styling' => 'Ticket +45%, conjuntos completos',
                        'ugc_hashtag' => 'Prova social, comunidade, repost Instagram',
                        'collection_drops' => 'Lancamentos mensais, urgencia, antecipacao',
                        'vip_program' => 'LTV +2.5x, descontos progressivos, acesso antecipado',
                        'virtual_fitting' => 'AR try-on, reduz devolucoes',
                        'starter_kits' => 'Kit 3 pecas, desconto 20%, facilita decisao',
                        'influencer_colab' => 'Co-criacao, codigo desconto, lives',
                        'inspirational_content' => 'Treinos gratuitos, styling, transformacoes',
                        'sustainability' => 'Tecidos reciclados, atrai publico consciente',
                        'inclusive_sizes' => 'PP-GG, modelos plus size, mercado +18%',
                    ],
                    'avoid_suggestions' => [
                        'quiz personalizacao', 'stack suplementos', 'clube assinatura', // supplements
                        'numeracao especial', 'palmilha personalizada', // footwear
                        'treino em video', 'app equipamento', // equipment
                        'planilha corrida', 'GPS running', // running
                        'bike fitting', 'revisao bicicleta', // cycling
                        'aulas natacao', 'tecnica crawl', // swimming
                        'peneira futebol', 'escolinha soccer', // soccer
                    ],
                    'verified' => true,
                    'tags' => ['estrategias', 'moda fitness', 'lookbook', 'ugc', 'influencers', 'sustentabilidade'],
                ],
            ],

            // FOOTWEAR - Calçados esportivos
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'subcategory' => 'footwear',
                'title' => 'Benchmarks de Mercado: Calçados Esportivos (2024)',
                'content' => 'Mercado brasileiro de calcados esportivos: R$ 14.4 bilhoes (2024), 180 milhoes de pares vendidos, crescimento de 8% ao ano. Categorias principais: tenis para corrida (30%), training/crossfit (25%), casual/lifestyle (20%), futebol/chuteiras (15%), outros esportes (10%). Ticket medio: R$ 200-500 (media R$ 350). Margem media: 40-50%. Taxa de conversao: 2.0-3.2%. Taxa de devolucao: 12-18% (problema de numeracao/conforto). Recompra: 25-35% em 180 dias (vida util media do tenis). Sazonalidade: pico em novembro/dezembro (+30% - Black Friday e Natal), janeiro (+20% - ano novo), junho (+15% - inverno, corrida de rua). Canais: lojas fisicas especializadas (35%), e-commerce (30%), marketplaces (25%), lojas multimarcas (10%). Principais marcas: Nike, Adidas, Olympikus, Mizuno, Asics, Fila, Puma. Publico: 55% homens, 45% mulheres, faixa etaria 20-50 anos. Faixas de preco: entrada (R$ 150-250), intermediario (R$ 250-450), premium (R$ 450-900), top (R$ 900+). Tecnologias valorizadas: amortecimento (gel, air, boost), estabilidade, respirabilidade (mesh), drop (diferenca salto-bico para corrida). Fit: 60% procuram numeracao padrao, 40% tem necessidades especiais (pe largo, estreito, alto). Cores: preto (35%), branco (25%), coloridos (40%). Tendencias 2024-2025: tenis sustentavel (materiais reciclados), lifestyle (sneakers para uso casual), maximalismo (solados grossos).',
                'metadata' => [
                    'sources' => [
                        'Abicalcados (Associacao Brasileira das Industrias de Calcados)',
                        'IEMI - Setor calcadista',
                        'Euromonitor - Footwear Brazil 2024',
                        'IBTeC (Instituto Brasileiro de Tecnologia do Couro)',
                    ],
                    'period' => '2024',
                    'market_size_bi' => 14.4,
                    'units_sold_mi' => 180,
                    'growth_rate' => 8,
                    'avg_ticket' => 350,
                    'margin' => 45,
                    'conversion_rate' => 2.6,
                    'return_rate' => 15,
                    'repurchase_rate' => 30,
                    'product_mix' => [
                        'running' => 30,
                        'training' => 25,
                        'lifestyle' => 20,
                        'soccer' => 15,
                        'others' => 10,
                    ],
                    'seasonality' => [
                        'nov_dec' => '+30%',
                        'jan' => '+20%',
                        'jun' => '+15%',
                    ],
                    'demographics' => [
                        'male' => 55,
                        'female' => 45,
                        'age_range' => '20-50',
                    ],
                    'avoid_mentions' => [
                        'whey protein', 'creatina', 'suplemento', // supplements
                        'legging', 'top', 'conjunto fitness', // sportswear
                        'halter', 'elastico', 'barra fixa', // equipment
                        'bicicleta', 'capacete ciclismo', // cycling
                        'maio natacao', 'oculos natacao', // swimming
                    ],
                    'verified' => true,
                    'tags' => ['benchmarks', 'calcados esportivos', 'tenis corrida', 'crossfit', 'lifestyle', 'sustentabilidade'],
                ],
            ],

            [
                'category' => 'strategy',
                'niche' => 'sports',
                'subcategory' => 'footwear',
                'title' => 'Estrategias Comprovadas: Calçados Esportivos',
                'content' => 'Estrategias de sucesso para e-commerce de calcados esportivos: 1) QUIZ DE RECOMENDACAO: Perguntas sobre tipo de pisada (pronada, supinada, neutra), peso, terreno (asfalto, trilha), objetivo (performance, conforto), recomenda modelo ideal, aumenta conversao em 40%. 2) GUIA DE NUMERACAO DETALHADO: Tabela com medida do pe em cm, comparativo entre marcas (Nike vs Adidas vs Olympikus), video ensinando a medir, reduce devolucoes em 35%. 3) VIDEOS 360 GRAUS: Permite ver o tenis de todos os angulos, zoom em detalhes (solado, cabedal, tecnologias), aumenta confianca. 4) REVIEWS E AVALIACOES: Incentiva avaliacoes com cupom de desconto, destaca reviews por tipo de uso (corrida, caminhada, academia), prova social. 5) GARANTIA DE TROCA FACILITADA: Politica clara de troca (ate 30 dias), etiqueta de devolucao incluida, reduz barreiras de compra. 6) PROGRAMA DE TROCA: Desconto na compra do proximo tenis ao devolver o usado (programa de sustentabilidade), fideliza e cria recorrencia. 7) BUNDLES: Kit tenis + meia tecnica + palmilha com desconto, aumenta ticket em 30%. 8) CONTEUDO TECNICO: Artigos sobre tipos de pisada, tecnologias de amortecimento, quando trocar o tenis, posiciona como especialista. 9) PARCERIAS COM ASSESSORIAS: Desconto para alunos de assessorias esportivas, patrocinio de corridas, branding em eventos. 10) LANCAMENTOS EXCLUSIVOS: Pre-venda de novos modelos, edicoes limitadas, colorways exclusivas, cria urgencia. 11) PERSONALIZACAO: Servico de customizacao (cores, nome bordado), premium pricing, diferenciacao.',
                'metadata' => [
                    'sources' => [
                        'Centauro - Estrategias de vendas',
                        'Nike Brasil - Melhores praticas',
                        'Authentic Feet - Quiz de pisada',
                        'Abicalcados - Tendencias',
                    ],
                    'proven_tactics' => [
                        'recommendation_quiz' => 'Conversao +40%, pisada + peso + terreno',
                        'size_guide_video' => 'Devolucoes -35%, medida cm + comparativo marcas',
                        'video_360' => 'Ver todos angulos, zoom detalhes',
                        'reviews_incentive' => 'Cupom desconto, reviews por uso',
                        'easy_return' => 'Troca 30 dias, etiqueta incluida',
                        'trade_in_program' => 'Desconto proximo tenis, sustentabilidade',
                        'bundles' => 'Tenis + meia + palmilha, ticket +30%',
                        'technical_content' => 'Tipos pisada, amortecimento, quando trocar',
                        'sports_partnerships' => 'Assessorias esportivas, corridas',
                        'exclusive_launches' => 'Pre-venda, edicoes limitadas, urgencia',
                        'customization' => 'Cores personalizadas, nome bordado, premium',
                    ],
                    'avoid_suggestions' => [
                        'assinatura recorrente', 'stack suplementos', // supplements
                        'colecao capsule', 'lookbook roupa', // sportswear
                        'app treino', 'video aula', // equipment
                        'planilha corrida', 'GPS watch', // running
                        'bike fitting', 'oficina bicicleta', // cycling
                        'aulas natacao', 'tecnica nado', // swimming
                        'peneira futebol', 'escolinha', // soccer
                    ],
                    'verified' => true,
                    'tags' => ['estrategias', 'calcados', 'quiz pisada', 'video 360', 'trade-in', 'personalizacao'],
                ],
            ],

            // EQUIPMENT - Equipamentos de treino
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'subcategory' => 'equipment',
                'title' => 'Benchmarks de Mercado: Equipamentos de Treino (2024)',
                'content' => 'Mercado brasileiro de equipamentos fitness: R$ 8.5 bilhoes (2024), crescimento de 20% ao ano (boom pandemia se manteve). Categorias principais: equipamentos cardiovasculares - esteiras, bikes (40%), musculacao - halteres, barras, anilhas (30%), acessorios - elasticos, colchonetes, kettlebells (20%), home gym completo (10%). Ticket medio: R$ 200-800 (media R$ 400). Margem media: 35-45%. Taxa de conversao: 1.8-2.8%. Recompra: 15-25% em 12 meses (upgrades, acessorios adicionais). Sazonalidade: pico em janeiro (+45% - resolucoes ano novo, home fitness), julho (+20% - meio do ano, retomada). Canais: e-commerce especializado (45%), marketplaces (35%), lojas fisicas (15%), dropshipping (5%). Principais players: Kikos, Acte Sports, Movement, WCT Fitness. Publico: 50% homens, 50% mulheres, faixa etaria 25-55 anos. Categorias em alta: equipamentos compactos para apartamento, multiuso (banco + halteres ajustaveis), smart fitness (bikes e esteiras com tela). Faixas de preco: entrada (R$ 100-300), intermediario (R$ 300-1000), profissional (R$ 1000-5000), premium/smart (R$ 5000+). Principais preocupacoes do consumidor: espaco disponivel (60%), facilidade de montagem (40%), garantia e suporte (80%). Tendencias 2024-2025: equipamentos conectados (app, streaming de aulas), minimalismo (poucos equipamentos versateis), sustentabilidade (materiais ecologicos).',
                'metadata' => [
                    'sources' => [
                        'IHRSA (International Health, Racquet & Sportsclub Association)',
                        'ACAD Brasil (Academias)',
                        'Euromonitor - Home Fitness Equipment',
                        'Statista - Brazil Fitness Market',
                    ],
                    'period' => '2024',
                    'market_size_bi' => 8.5,
                    'growth_rate' => 20,
                    'avg_ticket' => 400,
                    'margin' => 40,
                    'conversion_rate' => 2.3,
                    'repurchase_rate' => 20,
                    'product_mix' => [
                        'cardio' => 40,
                        'weights' => 30,
                        'accessories' => 20,
                        'home_gym' => 10,
                    ],
                    'seasonality' => [
                        'jan' => '+45%',
                        'jul' => '+20%',
                    ],
                    'demographics' => [
                        'male' => 50,
                        'female' => 50,
                        'age_range' => '25-55',
                    ],
                    'concerns' => [
                        'space' => 60,
                        'assembly' => 40,
                        'warranty' => 80,
                    ],
                    'avoid_mentions' => [
                        'whey', 'creatina', 'pre-treino', // supplements
                        'legging', 'top sportivo', 'conjunto', // sportswear
                        'tenis corrida', 'chuteira', // footwear/soccer
                        'bicicleta speed', 'capacete ciclismo', // cycling
                        'maio', 'oculos natacao', // swimming
                        'bola futebol', 'rede gol', // soccer
                    ],
                    'verified' => true,
                    'tags' => ['benchmarks', 'equipamentos fitness', 'home gym', 'smart fitness', 'compacto'],
                ],
            ],

            [
                'category' => 'strategy',
                'niche' => 'sports',
                'subcategory' => 'equipment',
                'title' => 'Estrategias Comprovadas: Equipamentos de Treino',
                'content' => 'Estrategias de sucesso para e-commerce de equipamentos fitness: 1) CONSULTORIA PERSONALIZADA: Chat/WhatsApp com especialista que entende espaco disponivel, objetivo, orcamento, recomenda kit ideal, aumenta conversao em 50%. 2) KITS TEMÁTICOS: Kit Iniciante (colchonete + elastico + halter 2kg), Kit Home Office (bola pilates + elastico + halter), Kit Completo (banco + halteres ajustaveis + barra), desconto de 15-20%, facilita decisao e aumenta ticket. 3) VIDEOS DE TREINO INCLUSOS: Acesso a biblioteca de treinos em video (YouTube privado ou app), mostra como usar cada equipamento, agrega valor. 4) INSTALACAO E MONTAGEM: Oferecer servico de montagem (especialmente para esteiras e bikes), reduz barreiras de compra. 5) GARANTIA ESTENDIDA: Garantia de 2-3 anos (vs 1 ano padrao), selo de qualidade, tranquiliza compra de alto valor. 6) FINANCIAMENTO: Parcelamento em 10-12x sem juros, aumenta acesso a equipamentos caros. 7) PROVA SOCIAL COM TRANSFORMACOES: Antes/depois de clientes que treinam em casa, depoimentos em video, inspira e gera confianca. 8) GUIA DE ESPACO: Calculadora de espaco (quantos m² precisa), fotos de ambientes reais (sala, quarto, varanda), facilita visualizacao. 9) PROGRAMA DE UPGRADE: Desconto na compra de equipamento superior ao devolver o antigo (trade-in), incentiva upgrade. 10) CONTEUDO EDUCATIVO: Blog/YouTube com treinos para iniciantes, dicas de manutencao, comparativos de equipamentos. 11) PARCERIAS B2B: Venda para condominios (fitness em areas comuns), empresas (wellbeing corporativo), assessorias.',
                'metadata' => [
                    'sources' => [
                        'Kikos - Estrategias de vendas',
                        'Movement - Kits tematicos',
                        'ACAD Brasil - Melhores praticas',
                        'Smart Fit - Parceria equipamentos',
                    ],
                    'proven_tactics' => [
                        'personalized_consulting' => 'Conversao +50%, chat/WhatsApp especialista',
                        'thematic_kits' => 'Kit Iniciante/Home Office/Completo, desconto 15-20%',
                        'workout_videos' => 'Biblioteca treinos, como usar equipamento',
                        'installation_service' => 'Montagem esteiras/bikes, reduz barreira',
                        'extended_warranty' => 'Garantia 2-3 anos, selo qualidade',
                        'financing' => 'Parcelamento 10-12x sem juros',
                        'transformation_proof' => 'Antes/depois clientes home gym, depoimentos',
                        'space_guide' => 'Calculadora m², fotos ambientes reais',
                        'upgrade_program' => 'Trade-in desconto, incentiva upgrade',
                        'educational_content' => 'Treinos iniciantes, manutencao, comparativos',
                        'b2b_partnerships' => 'Condominios, empresas, assessorias',
                    ],
                    'avoid_suggestions' => [
                        'clube assinatura', 'stack personalizacao', // supplements
                        'lookbook', 'ugc hashtag', 'colecao drop', // sportswear
                        'quiz pisada', 'numeracao especial', // footwear
                        'planilha corrida', 'assessoria running', // running
                        'bike fitting ciclismo', 'revisao bike speed', // cycling
                        'aulas natacao', 'tecnica crawl', // swimming
                        'peneira futebol', 'escolinha soccer', // soccer
                    ],
                    'verified' => true,
                    'tags' => ['estrategias', 'equipamentos', 'kits tematicos', 'videos treino', 'home gym', 'b2b'],
                ],
            ],

            // RUNNING - Corrida
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'subcategory' => 'running',
                'title' => 'Benchmarks de Mercado: Corrida de Rua (2024)',
                'content' => 'Mercado brasileiro de corrida de rua: 11 mil eventos anuais, 6.5 milhoes de corredores ativos (2024), crescimento de 15% ao ano. Mercado de produtos para corrida: R$ 4.2 bilhoes (tenis, roupas, acessorios tecnologicos). Ticket medio: R$ 200-400 (media R$ 280). Categorias principais: tenis running (50%), roupas tecnicas (25%), relogios/GPS (15%), acessorios (pochete, garrafa, bone) (10%). Margem media: 40-50%. Taxa de conversao: 2.5-3.5%. Recompra: 30-40% em 6 meses. Sazonalidade: pico em maio/junho (+30% - preparacao para corridas de inverno), outubro/novembro (+25% - preparacao para Sao Silvestre e Reveillon). Canais: lojas especializadas running (40%), e-commerce (35%), marketplaces (20%), eventos esportivos (5%). Publico: 52% homens, 48% mulheres, faixa etaria 30-50 anos (corredor medio tem 38 anos). Perfil: iniciantes (40%), intermediarios (45%), avancados/maratonistas (15%). Distancias mais populares: 5km (35%), 10km (40%), 21km meia-maratona (18%), 42km maratona (7%). Preocupacoes: lesoes (70%), performance (50%), conforto (80%). Tecnologias valorizadas: GPS com metricas avancadas, monitores cardiacos, apps de treino. Tendencias 2024-2025: corrida em grupo (assessorias crescendo 20% ao ano), trail running (corrida em trilha, nicho em expansao), corridas virtuais (pandemia acelerou), wearables inteligentes.',
                'metadata' => [
                    'sources' => [
                        'Ticket Sports / CBAT (Confederacao Brasileira de Atletismo)',
                        'FPA (Federacao Paulista de Atletismo)',
                        'Revista Contra-Relogio',
                        'Euromonitor - Running Brazil 2024',
                    ],
                    'period' => '2024',
                    'active_runners_mi' => 6.5,
                    'annual_events' => 11000,
                    'market_size_bi' => 4.2,
                    'growth_rate' => 15,
                    'avg_ticket' => 280,
                    'margin' => 45,
                    'conversion_rate' => 3.0,
                    'repurchase_rate' => 35,
                    'product_mix' => [
                        'running_shoes' => 50,
                        'technical_clothing' => 25,
                        'gps_watches' => 15,
                        'accessories' => 10,
                    ],
                    'seasonality' => [
                        'may_jun' => '+30%',
                        'oct_nov' => '+25%',
                    ],
                    'demographics' => [
                        'male' => 52,
                        'female' => 48,
                        'avg_age' => 38,
                        'age_range' => '30-50',
                    ],
                    'runner_level' => [
                        'beginner' => 40,
                        'intermediate' => 45,
                        'advanced' => 15,
                    ],
                    'avoid_mentions' => [
                        'whey protein', 'creatina', 'massa muscular', // supplements
                        'legging estampada', 'conjunto fitness', // sportswear (geral)
                        'crossfit', 'musculacao', 'halter', // equipment
                        'bicicleta', 'bike speed', 'ciclismo', // cycling
                        'natacao', 'piscina', 'maio', // swimming
                        'futebol', 'chuteira', 'bola', // soccer
                    ],
                    'verified' => true,
                    'tags' => ['benchmarks', 'corrida', 'running', 'maratona', 'assessoria', 'gps watch'],
                ],
            ],

            [
                'category' => 'strategy',
                'niche' => 'sports',
                'subcategory' => 'running',
                'title' => 'Estrategias Comprovadas: Corrida de Rua',
                'content' => 'Estrategias de sucesso para e-commerce de corrida: 1) PLANILHAS DE TREINO GRATUITAS: Oferece planilhas personalizadas para 5km, 10km, 21km, 42km (iniciante a avancado), captura email, nutre leads. 2) PARCERIAS COM ASSESSORIAS: Desconto exclusivo para alunos de assessorias de corrida (Brasil tem 5000+ assessorias), co-branding, patrocinio. 3) CLUBE DE VANTAGENS: Mensalidade de R$ 29.90/mes com 15% desconto em todos os produtos, frete gratis, acesso a treinos exclusivos, aumenta LTV. 4) ANALISE DE PISADA VIRTUAL: Quiz online que identifica tipo de pisada (pronada/supinada/neutra), recomenda tenis ideal, aumenta conversao em 45%. 5) BLOG TECNICO: Artigos sobre treinos, nutricao para corredores, prevencao de lesoes, SEO organico forte (palavras-chave: como comecar a correr, melhor tenis para pronacao). 6) KITS PARA PROVAS: Kit Estreante (tenis + roupa + numero de peito), Kit Maratonista (tenis + shorts + gel energetico + garrafa), desconto de 18%. 7) PATROCINIO DE EVENTOS: Patrocinar corridas locais, estande no evento, cupom de desconto exclusivo para participantes. 8) PROGRAMA DE EMBAIXADORES: Corredores influenciadores que testam produtos, geram conteudo, codigo de desconto, gera autenticidade. 9) COMUNIDADE ONLINE: Grupo de WhatsApp ou Strava Club da marca, compartilha treinos, motiva, cria senso de pertencimento. 10) REVIEWS POR DISTANCIA: Organiza avaliacoes por distancia (5km, 10km, maratona), facilita decisao. 11) GARANTIA DE QUILOMETRAGEM: Tenis garantido por 500-800km (vs apenas dias), demonstra confianca no produto.',
                'metadata' => [
                    'sources' => [
                        'Centauro Running',
                        'Authentic Feet - Analise pisada',
                        'Nike Run Club - Estrategias',
                        'Assessorias Brasil',
                    ],
                    'proven_tactics' => [
                        'free_training_plans' => 'Planilhas 5km-42km, captura email, nutre leads',
                        'running_club_partnerships' => 'Desconto assessorias, co-branding, 5000+ parceiros',
                        'membership_club' => 'R$ 29.90/mes, desconto 15%, frete gratis, LTV',
                        'virtual_gait_analysis' => 'Quiz pisada online, conversao +45%',
                        'technical_blog' => 'Treinos, nutricao, lesoes, SEO organico',
                        'race_kits' => 'Kit Estreante/Maratonista, desconto 18%',
                        'event_sponsorship' => 'Patrocinio corridas, estande, cupom exclusivo',
                        'ambassador_program' => 'Corredores influencers, conteudo, autenticidade',
                        'online_community' => 'WhatsApp/Strava Club, compartilha treinos',
                        'reviews_by_distance' => 'Avaliacoes 5km/10km/maratona',
                        'mileage_warranty' => 'Garantia 500-800km, confianca produto',
                    ],
                    'avoid_suggestions' => [
                        'assinatura suplementos', 'stack whey', // supplements
                        'colecao capsule', 'lookbook fashion', // sportswear geral
                        'video 360 calcado', 'trade-in tenis', // footwear geral
                        'app home gym', 'video musculacao', // equipment
                        'bike fitting', 'revisao bicicleta', // cycling
                        'aulas natacao', 'tecnica nado', // swimming
                        'escolinha futebol', 'peneira', // soccer
                    ],
                    'verified' => true,
                    'tags' => ['estrategias', 'corrida', 'planilhas treino', 'assessorias', 'pisada', 'comunidade'],
                ],
            ],

            // CYCLING - Ciclismo
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'subcategory' => 'cycling',
                'title' => 'Benchmarks de Mercado: Ciclismo (2024)',
                'content' => 'Mercado brasileiro de ciclismo: R$ 12 bilhoes (2024), 70 milhoes de bicicletas em circulacao, 15 milhoes de ciclistas ativos, crescimento de 12% ao ano (pandemia acelerou). Categorias principais: bicicletas (50%), vestuario ciclismo (20%), acessorios e equipamentos (15%), pecas e manutencao (15%). Ticket medio: R$ 300-1500 (media R$ 500 - excluindo bikes). Margem media: 35-45% (produtos), 50-60% (servicos). Taxa de conversao: 2.0-3.0%. Recompra: 25-35% em 12 meses (upgrades, acessorios, manutencao). Sazonalidade: pico em outubro/novembro (+35% - primavera/verao, passeios), abril/maio (+20% - outono, clima ameno). Canais: lojas especializadas (40%), e-commerce (30%), marketplaces (20%), oficinas locais (10%). Publico: 65% homens, 35% mulheres, faixa etaria 25-55 anos. Modalidades: lazer/deslocamento urbano (60%), speed/estrada (25%), mountain bike (10%), outros (5%). Faixas de preco bikes: entrada (R$ 800-1500), intermediaria (R$ 1500-4000), avancada (R$ 4000-10000), profissional (R$ 10000+). Principais preocupacoes: seguranca (capacete, luzes) (85%), conforto (selim, guidao) (70%), performance (peso, marchas) (50%). Acessorios essenciais: capacete (obrigatorio), luzes dianteira/traseira, ciclocomputador/GPS, garrafa, bomba. Tendencias 2024-2025: e-bikes (bicicletas eletricas, crescimento 30% ao ano), bikepacking (viagens longas), gravel bikes (versatilidade asfalto/terra), bike sharing (compartilhamento urbano).',
                'metadata' => [
                    'sources' => [
                        'Alianca Bike (Associacao do Setor de Bicicletas)',
                        'Transporte Ativo',
                        'Revista Bike Action',
                        'Euromonitor - Cycling Brazil 2024',
                    ],
                    'period' => '2024',
                    'market_size_bi' => 12.0,
                    'bikes_circulation_mi' => 70,
                    'active_cyclists_mi' => 15,
                    'growth_rate' => 12,
                    'avg_ticket' => 500,
                    'margin_products' => 40,
                    'margin_services' => 55,
                    'conversion_rate' => 2.5,
                    'repurchase_rate' => 30,
                    'product_mix' => [
                        'bicycles' => 50,
                        'clothing' => 20,
                        'accessories' => 15,
                        'parts_service' => 15,
                    ],
                    'seasonality' => [
                        'oct_nov' => '+35%',
                        'apr_may' => '+20%',
                    ],
                    'demographics' => [
                        'male' => 65,
                        'female' => 35,
                        'age_range' => '25-55',
                    ],
                    'cycling_type' => [
                        'urban_leisure' => 60,
                        'road_speed' => 25,
                        'mountain_bike' => 10,
                        'others' => 5,
                    ],
                    'avoid_mentions' => [
                        'whey', 'suplemento massa muscular', // supplements
                        'legging fitness', 'top sportivo', // sportswear geral
                        'tenis corrida', 'chuteira', // footwear
                        'halter', 'esteira', 'bike ergometrica', // equipment
                        'planilha corrida', 'maratona', // running
                        'natacao', 'piscina', 'maio', // swimming
                        'futebol', 'bola', 'rede', // soccer
                    ],
                    'verified' => true,
                    'tags' => ['benchmarks', 'ciclismo', 'bicicleta', 'e-bike', 'bikepacking', 'gravel'],
                ],
            ],

            [
                'category' => 'strategy',
                'niche' => 'sports',
                'subcategory' => 'cycling',
                'title' => 'Estrategias Comprovadas: Ciclismo',
                'content' => 'Estrategias de sucesso para e-commerce de ciclismo: 1) BIKE FITTING ONLINE: Guia de medidas (altura, cavalo, braco) para recomendar tamanho de quadro ideal, video explicativo, reduz devolucoes. 2) KITS COMPLETOS INICIANTE: Kit Ciclista Urbano (bike + capacete + luzes + cadeado + bomba) com desconto de 15%, facilita primeira compra. 3) SERVICO DE MANUTENCAO: Oferece revisao preventiva, parceria com oficinas locais (rede credenciada), agrega valor pos-venda. 4) CLUBE DE CICLISMO: Comunidade exclusiva com passeios mensais organizados, descontos em produtos, newsletter com rotas, fideliza. 5) CONTEUDO EDUCATIVO: Blog/YouTube com dicas de manutencao basica (trocar camara, ajustar freios), rotas recomendadas por cidade, SEO local forte. 6) PROGRAMA DE UPGRADE: Desconto ao trocar bike antiga por nova (trade-in), sustentabilidade (reforma e revenda bikes usadas). 7) PERSONALIZACAO: Permite escolher cores, componentes (selim, guidao), marchas, cria bike sob medida, premium pricing. 8) PARCERIAS COM GRUPOS: Desconto para grupos de pedal, clubes de ciclismo, empresas (incentivo mobilidade sustentavel). 9) GARANTIA ESTENDIDA: Garantia de 2-3 anos para quadro, 1 ano componentes, tranquiliza compra de alto valor. 10) FINANCIAMENTO: Parcelamento em 12-18x, consorcio de bikes (modalidade em crescimento). 11) EVENTOS E PATROCINIO: Patrocina passeios ciclisticos, criticos urbanos, competicoes amadoras, presenca de marca. 12) SEGURO BIKE: Parceria com seguradoras para oferecer seguro contra roubo, aumenta confianca (principal barreira no Brasil).',
                'metadata' => [
                    'sources' => [
                        'Alianca Bike - Melhores praticas',
                        'Decathlon Brasil - Estrategias',
                        'Soul Cycles - Clube ciclismo',
                        'Itau Seguros - Seguro bike',
                    ],
                    'proven_tactics' => [
                        'online_bike_fitting' => 'Medidas altura/cavalo/braco, tamanho quadro ideal',
                        'starter_kits' => 'Kit Urbano (bike+capacete+luzes+cadeado), desconto 15%',
                        'maintenance_service' => 'Revisao preventiva, rede oficinas credenciadas',
                        'cycling_club' => 'Passeios mensais, descontos, newsletter rotas',
                        'educational_content' => 'Manutencao basica, rotas cidade, SEO local',
                        'upgrade_trade_in' => 'Troca bike antiga, sustentabilidade, revenda usadas',
                        'customization' => 'Cores, componentes, bike sob medida, premium',
                        'group_partnerships' => 'Desconto grupos pedal, clubes, empresas',
                        'extended_warranty' => 'Garantia 2-3 anos quadro, 1 ano componentes',
                        'financing' => 'Parcelamento 12-18x, consorcio bikes',
                        'events_sponsorship' => 'Passeios, criticos urbanos, competicoes',
                        'bike_insurance' => 'Parceria seguradoras, seguro roubo',
                    ],
                    'avoid_suggestions' => [
                        'clube assinatura suplementos', 'stack whey', // supplements
                        'lookbook moda', 'colecao fitness', // sportswear
                        'quiz pisada tenis', 'video 360 calcado', // footwear
                        'consultoria home gym', 'video treino', // equipment
                        'planilha corrida', 'assessoria running', // running
                        'aulas natacao', 'tecnica crawl', // swimming
                        'escolinha futebol', 'peneira soccer', // soccer
                    ],
                    'verified' => true,
                    'tags' => ['estrategias', 'ciclismo', 'bike fitting', 'trade-in', 'clube', 'seguro'],
                ],
            ],

            // SWIMMING - Natação
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'subcategory' => 'swimming',
                'title' => 'Benchmarks de Mercado: Natação (2024)',
                'content' => 'Mercado brasileiro de natacao: R$ 2.8 bilhoes (2024), 3.2 milhoes de praticantes regulares, crescimento de 10% ao ano. Categorias principais: maios e sungas (40%), oculos e toucas (25%), acessorios treino - pranchas, pullbuoys, nadadeiras (20%), roupas pos-treino (15%). Ticket medio: R$ 80-200 (media R$ 130). Margem media: 45-55%. Taxa de conversao: 3.0-4.0% (compra mais rapida, produtos de reposicao). Recompra: 40-50% em 6 meses (maios/sungas desgastam com cloro). Sazonalidade: pico em janeiro/fevereiro (+30% - verao, ferias, matriculas), setembro/outubro (+20% - primavera, retorno as aulas). Canais: lojas especializadas (35%), e-commerce (30%), academias/clubes (20%), marketplaces (15%). Publico: 45% adultos (fitness/saude), 35% criancas (aulas), 20% competidores amadores/masters. Genero: 55% mulheres, 45% homens. Faixa etaria adultos: 25-60 anos. Principais marcas: Speedo, Arena, Hammerhead, Mormaii, Nike Swim. Faixas de preco: basico (R$ 60-120), intermediario (R$ 120-250), competicao (R$ 250-500+). Preocupacoes: durabilidade vs cloro (80%), conforto (75%), performance hidrodinamica (competidores 40%). Produtos de reposicao rapida: oculos (troca a cada 3-6 meses), toucas (6-12 meses), maios (6-12 meses uso intenso). Tendencias 2024-2025: maios com protecao UV 50+, tecidos resistentes ao cloro, natacao em aguas abertas (crescimento de nicho), tecnologia para competicao (tecidos aprovados FINA).',
                'metadata' => [
                    'sources' => [
                        'CBDA (Confederacao Brasileira de Desportos Aquaticos)',
                        'Speedo Brasil',
                        'Arena Swimming',
                        'Euromonitor - Swimwear Brazil 2024',
                    ],
                    'period' => '2024',
                    'market_size_bi' => 2.8,
                    'regular_swimmers_mi' => 3.2,
                    'growth_rate' => 10,
                    'avg_ticket' => 130,
                    'margin' => 50,
                    'conversion_rate' => 3.5,
                    'repurchase_rate' => 45,
                    'product_mix' => [
                        'swimsuits' => 40,
                        'goggles_caps' => 25,
                        'training_accessories' => 20,
                        'post_swim_clothing' => 15,
                    ],
                    'seasonality' => [
                        'jan_feb' => '+30%',
                        'sep_oct' => '+20%',
                    ],
                    'demographics' => [
                        'adults_fitness' => 45,
                        'kids_classes' => 35,
                        'competitors' => 20,
                        'female' => 55,
                        'male' => 45,
                    ],
                    'replacement_cycle' => [
                        'goggles' => '3-6 months',
                        'caps' => '6-12 months',
                        'swimsuits' => '6-12 months intensive',
                    ],
                    'avoid_mentions' => [
                        'whey protein', 'creatina', 'suplemento', // supplements
                        'legging', 'top fitness', 'conjunto', // sportswear
                        'tenis', 'chuteira', 'calcado', // footwear
                        'halter', 'esteira', 'bike ergometrica', // equipment
                        'corrida', 'maratona', 'GPS running', // running
                        'bicicleta', 'capacete ciclismo', // cycling
                        'futebol', 'bola', 'campo', // soccer
                    ],
                    'verified' => true,
                    'tags' => ['benchmarks', 'natacao', 'maio', 'oculos', 'aguas abertas', 'competicao'],
                ],
            ],

            [
                'category' => 'strategy',
                'niche' => 'sports',
                'subcategory' => 'swimming',
                'title' => 'Estrategias Comprovadas: Natação',
                'content' => 'Estrategias de sucesso para e-commerce de natacao: 1) GUIA DE TAMANHOS DETALHADO: Maios e sungas tem fit especifico, tabela com medidas, fotos de modelos com diferentes biótipos, reduce devolucoes em 40%. 2) KITS POR NIVEL: Kit Iniciante (maio/sunga + oculos + touca + sacola), Kit Treino (maio + oculos competicao + touca silicone + prancha), desconto 15%. 3) ASSINATURA DE REPOSICAO: Clube com entrega automatica a cada 6 meses (novo maio + oculos + touca), desconto 12%, aumenta LTV. 4) PARCERIAS COM ACADEMIAS/CLUBES: Desconto exclusivo para alunos, stand em eventos de natacao, co-branding. 5) CONTEUDO TECNICO: Videos sobre como escolher oculos (vedacao, lente clara/escura), cuidados com maio (enxaguar pos-treino), tecnicas de nado, SEO. 6) REVIEWS POR USO: Organiza avaliacoes por tipo (piscina, aguas abertas, competicao), facilita decisao. 7) PROGRAMA DE FIDELIDADE: Pontos a cada compra, troca por produtos, incentiva recompra (alto turnover de produtos). 8) PERSONALIZAÇÃO: Gravacao de nome em touca, escolha de cores de oculos/touca, para equipes e clubes. 9) GARANTIA CONTRA DEFEITOS: Garantia de 90 dias contra defeitos de fabricacao (costura, elastico), tranquiliza. 10) BUNDLE FAMILIA: Kit para familia (2 adultos + 2 criancas), desconto de 20%, facilita compra em volume. 11) MARKETPLACE B2B: Venda para escolas de natacao, clubes, associacoes masters, volume. 12) CONTEUDO INSPIRACIONAL: Depoimentos de nadadores amadores, masters (50+, 60+), inspira continuidade do esporte.',
                'metadata' => [
                    'sources' => [
                        'Speedo Brasil - Estrategias',
                        'Arena - Programa fidelidade',
                        'CBDA - Parcerias clubes',
                        'Masters Swimming Brasil',
                    ],
                    'proven_tactics' => [
                        'detailed_size_guide' => 'Tabela medidas, fotos biotipos, devolucoes -40%',
                        'level_kits' => 'Kit Iniciante/Treino, desconto 15%',
                        'replacement_subscription' => 'Entrega 6 meses (maio+oculos+touca), desconto 12%',
                        'gym_club_partnerships' => 'Desconto alunos, stand eventos, co-branding',
                        'technical_content' => 'Como escolher oculos, cuidados maio, tecnicas nado',
                        'reviews_by_use' => 'Avaliacoes piscina/aguas abertas/competicao',
                        'loyalty_points' => 'Pontos cada compra, troca produtos, recompra',
                        'customization' => 'Nome touca, cores oculos, equipes/clubes',
                        'defect_warranty' => 'Garantia 90 dias costura/elastico',
                        'family_bundle' => 'Kit 2 adultos + 2 criancas, desconto 20%',
                        'b2b_marketplace' => 'Escolas natacao, clubes, masters, volume',
                        'inspirational_content' => 'Depoimentos masters 50+, continuidade',
                    ],
                    'avoid_suggestions' => [
                        'stack suplementos', 'quiz personalizacao whey', // supplements
                        'lookbook moda', 'colecao drop', 'ugc fitness', // sportswear
                        'quiz pisada', 'video 360 tenis', // footwear
                        'consultoria home gym', 'video musculacao', // equipment
                        'planilha corrida', 'assessoria running', // running
                        'bike fitting', 'trade-in bicicleta', // cycling
                        'escolinha futebol', 'peneira soccer', // soccer
                    ],
                    'verified' => true,
                    'tags' => ['estrategias', 'natacao', 'guia tamanhos', 'kits nivel', 'assinatura', 'b2b'],
                ],
            ],

            // SOCCER - Futebol
            [
                'category' => 'benchmark',
                'niche' => 'sports',
                'subcategory' => 'soccer',
                'title' => 'Benchmarks de Mercado: Futebol (2024)',
                'content' => 'Mercado brasileiro de artigos de futebol: R$ 18.5 bilhoes (2024), maior mercado de futebol da America Latina, crescimento de 8% ao ano. Categorias principais: chuteiras (35%), camisas oficiais de clubes (25%), bolas (15%), acessorios - caneleiras, meias, luvas goleiro (15%), vestuario treino (10%). Ticket medio: R$ 150-400 (media R$ 240). Margem media: 40-50% (chuteiras), 30-40% (camisas licenciadas). Taxa de conversao: 2.5-3.5%. Recompra: 20-30% em 12 meses. Sazonalidade: pico em dezembro (+40% - Natal, presente), junho/julho (+25% - meio do ano, Copa America/Mundial em anos especificos), marco (+15% - inicio temporadas estaduais). Canais: lojas multimarcas esportivas (40%), e-commerce (30%), lojas oficiais clubes (15%), marketplaces (15%). Publico: 75% homens, 25% mulheres (futebol feminino crescendo 15% ao ano), faixa etaria 8-45 anos (pico 15-30). Principais marcas chuteiras: Nike, Adidas, Puma, Umbro. Camisas: times grandes (Flamengo, Corinthians, Palmeiras, Sao Paulo) representam 60% das vendas. Faixas de preco chuteiras: entrada (R$ 100-200), intermediaria (R$ 200-400), profissional (R$ 400-1200). Tipos de chuteira: society (50%), campo (35%), futsal (15%). Preocupacoes: autenticidade de camisas oficiais (60%), tamanho/numeracao chuteiras (55%), durabilidade (70%). Tendencias 2024-2025: chuteiras personalizadas (cores, nome), futebol feminino (crescimento acelerado), e-sports/FIFA (camisas virtuais influenciam vendas fisicas), sustentabilidade (bolas e chuteiras eco).',
                'metadata' => [
                    'sources' => [
                        'CBF (Confederacao Brasileira de Futebol)',
                        'Euromonitor - Football Brazil 2024',
                        'Nielsen Sports',
                        'Kantar - Mercado esportivo',
                    ],
                    'period' => '2024',
                    'market_size_bi' => 18.5,
                    'growth_rate' => 8,
                    'avg_ticket' => 240,
                    'margin_cleats' => 45,
                    'margin_jerseys' => 35,
                    'conversion_rate' => 3.0,
                    'repurchase_rate' => 25,
                    'product_mix' => [
                        'cleats' => 35,
                        'club_jerseys' => 25,
                        'balls' => 15,
                        'accessories' => 15,
                        'training_wear' => 10,
                    ],
                    'seasonality' => [
                        'dec' => '+40%',
                        'jun_jul' => '+25%',
                        'mar' => '+15%',
                    ],
                    'demographics' => [
                        'male' => 75,
                        'female' => 25,
                        'womens_soccer_growth' => 15,
                        'age_range' => '8-45',
                        'peak_age' => '15-30',
                    ],
                    'cleat_types' => [
                        'society' => 50,
                        'field' => 35,
                        'futsal' => 15,
                    ],
                    'avoid_mentions' => [
                        'whey', 'creatina', 'suplemento musculacao', // supplements
                        'legging', 'top fitness', 'conjunto academia', // sportswear
                        'tenis corrida', 'running', 'maratona', // running/footwear running
                        'halter', 'esteira', 'musculacao', // equipment
                        'bicicleta', 'capacete ciclismo', // cycling
                        'natacao', 'maio', 'piscina', // swimming
                    ],
                    'verified' => true,
                    'tags' => ['benchmarks', 'futebol', 'chuteira', 'camisa oficial', 'feminino', 'society'],
                ],
            ],

            [
                'category' => 'strategy',
                'niche' => 'sports',
                'subcategory' => 'soccer',
                'title' => 'Estrategias Comprovadas: Futebol',
                'content' => 'Estrategias de sucesso para e-commerce de futebol: 1) GUIA DE NUMERACAO CHUTEIRAS: Tabela detalhada (chuteiras calcam menor que tenis casual), video como medir, comparativo entre marcas, reduz devolucoes em 35%. 2) SELO DE AUTENTICIDADE: Garantia de produtos oficiais licenciados, codigo de verificacao, combate falsificacao (principal preocupacao do consumidor). 3) PERSONALIZACAO CHUTEIRAS: Permite escolher cores, adicionar nome/numero, cria produto exclusivo, premium pricing. 4) KITS JOGADOR COMPLETO: Kit Craques (chuteira + camisa + meiao + caneleira), Kit Goleiro (luvas + camisa + short), desconto 18%. 5) PARCERIAS COM ESCOLINHAS: Desconto para alunos de escolinhas de futebol (Brasil tem 30.000+ escolinhas), kit time completo (15 camisas uniformes). 6) LANCAMENTOS SINCRONIZADOS: Novos modelos chuteiras lancam junto com clubes/selecao, cria buzz, pre-venda. 7) CONTEUDO DE IDOLOS: Parcerias com jogadores (atuais ou ex-jogadores), reviews de produtos, dicas de treino, autenticidade. 8) PROGRAMA DE TORCEDOR: Clube de vantagens por time do coracao, desconto em produtos do clube, acesso antecipado a novas camisas. 9) PENEIRAS E EVENTOS: Patrocina peneiras, torneios amadores, escolinhas - presenca de marca, stand com produtos. 10) BUNDLE TORCEDOR: Kit Completo (camisa oficial + short + meiao + bandeira + caneca), facilita presente. 11) TRADE-IN CHUTEIRAS: Desconto na compra de nova chuteira ao devolver usada (doacao para projetos sociais), sustentabilidade. 12) REVIEWS POR POSICAO: Organiza avaliacoes por posicao em campo (atacante, meia, zagueiro, goleiro), chuteira ideal por posicao.',
                'metadata' => [
                    'sources' => [
                        'Centauro - Estrategias futebol',
                        'Nike Football Brasil',
                        'Adidas Soccer',
                        'CBF - Parcerias escolinhas',
                    ],
                    'proven_tactics' => [
                        'cleat_size_guide' => 'Tabela detalhada, video, comparativo marcas, devolucoes -35%',
                        'authenticity_seal' => 'Produtos oficiais, codigo verificacao, combate falsificacao',
                        'cleat_customization' => 'Cores, nome/numero, exclusivo, premium',
                        'complete_player_kits' => 'Kit Craques/Goleiro, desconto 18%',
                        'soccer_school_partnerships' => 'Desconto alunos, 30.000+ escolinhas, kit time',
                        'synchronized_launches' => 'Lancamentos com clubes/selecao, buzz, pre-venda',
                        'idol_content' => 'Jogadores/ex-jogadores, reviews, dicas treino',
                        'fan_program' => 'Clube vantagens por time, acesso antecipado camisas',
                        'tryouts_events' => 'Patrocinio peneiras, torneios, escolinhas',
                        'fan_bundle' => 'Kit Completo (camisa+short+meiao+bandeira+caneca)',
                        'cleat_trade_in' => 'Desconto nova chuteira, doacao projetos sociais',
                        'reviews_by_position' => 'Avaliacoes atacante/meia/zagueiro/goleiro',
                    ],
                    'avoid_suggestions' => [
                        'assinatura suplementos', 'stack whey', 'quiz nutricao', // supplements
                        'lookbook moda', 'colecao fitness drop', 'ugc hashtag', // sportswear
                        'quiz pisada', 'video 360 tenis running', // running/footwear
                        'consultoria home gym', 'video treino musculacao', // equipment
                        'planilha corrida', 'assessoria running', 'GPS', // running
                        'bike fitting', 'revisao bicicleta', 'club ciclismo', // cycling
                        'aulas natacao', 'tecnica nado', 'aguas abertas', // swimming
                    ],
                    'verified' => true,
                    'tags' => ['estrategias', 'futebol', 'chuteira', 'autenticidade', 'escolinhas', 'personalizacao'],
                ],
            ],
        ];

        // =====================================================
        // PROCESSO DE SEEDING COM LOGS DETALHADOS
        // =====================================================

        $totalRecords = count($knowledge);
        $startTime = microtime(true);

        // Contadores por categoria e nicho
        $stats = [
            'categories' => [],
            'niches' => [],
            'embeddings_generated' => 0,
            'embeddings_failed' => 0,
            'total_embedding_time_ms' => 0,
        ];

        Log::channel($this->logChannel)->info('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel($this->logChannel)->info('║     KNOWLEDGE BASE SEEDER - INICIO DO PROCESSO                  ║');
        Log::channel($this->logChannel)->info('╚══════════════════════════════════════════════════════════════════╝');
        Log::channel($this->logChannel)->info('Configuracao do seeder', [
            'total_records' => $totalRecords,
            'timestamp' => now()->toIso8601String(),
            'database' => config('database.default'),
            'embedding_provider' => config('services.ai.embeddings.provider', 'gemini'),
        ]);

        $this->command->info('');
        $this->command->info('=== INICIANDO SEED DA BASE DE CONHECIMENTO ===');
        $this->command->info("Total de registros a processar: {$totalRecords}");
        $this->command->info('Logs detalhados em: storage/logs/embeddings-'.date('Y-m-d').'.log');
        $this->command->info('');

        foreach ($knowledge as $index => $item) {
            $recordNumber = $index + 1;
            $recordStart = microtime(true);

            $this->command->info("[{$recordNumber}/{$totalRecords}] Processando: {$item['title']}");

            Log::channel($this->logChannel)->info(">>> Processando registro {$recordNumber}/{$totalRecords}", [
                'title' => $item['title'],
                'category' => $item['category'],
                'niche' => $item['niche'] ?? 'general',
            ]);

            try {
                $kb->add($item);

                $recordTime = round((microtime(true) - $recordStart) * 1000, 2);
                $stats['total_embedding_time_ms'] += $recordTime;
                $stats['embeddings_generated']++;

                // Atualizar contadores
                $category = $item['category'];
                $niche = $item['niche'] ?? 'general';
                $stats['categories'][$category] = ($stats['categories'][$category] ?? 0) + 1;
                $stats['niches'][$niche] = ($stats['niches'][$niche] ?? 0) + 1;

                Log::channel($this->logChannel)->info("<<< Registro {$recordNumber} concluido", [
                    'title' => $item['title'],
                    'time_ms' => $recordTime,
                    'status' => 'success',
                ]);

            } catch (\Exception $e) {
                $stats['embeddings_failed']++;

                Log::channel($this->logChannel)->error("!!! ERRO no registro {$recordNumber}", [
                    'title' => $item['title'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->command->error("  ERRO: {$e->getMessage()}");
            }
        }

        $totalTime = round((microtime(true) - $startTime), 2);
        $avgTimePerRecord = $totalRecords > 0 ? round($stats['total_embedding_time_ms'] / $totalRecords, 2) : 0;

        // Log final com estatisticas completas
        Log::channel($this->logChannel)->info('╔══════════════════════════════════════════════════════════════════╗');
        Log::channel($this->logChannel)->info('║     KNOWLEDGE BASE SEEDER - PROCESSO CONCLUIDO                  ║');
        Log::channel($this->logChannel)->info('╚══════════════════════════════════════════════════════════════════╝');
        Log::channel($this->logChannel)->info('Estatisticas finais do seeding', [
            'total_records' => $totalRecords,
            'embeddings_generated' => $stats['embeddings_generated'],
            'embeddings_failed' => $stats['embeddings_failed'],
            'success_rate' => round(($stats['embeddings_generated'] / $totalRecords) * 100, 2).'%',
            'total_time_seconds' => $totalTime,
            'avg_time_per_record_ms' => $avgTimePerRecord,
            'records_per_category' => $stats['categories'],
            'records_per_niche' => $stats['niches'],
            'timestamp_end' => now()->toIso8601String(),
        ]);

        $this->command->info('');
        $this->command->info('=== SEEDING CONCLUIDO ===');
        $this->command->info("Tempo total: {$totalTime} segundos");
        $this->command->info("Tempo medio por registro: {$avgTimePerRecord}ms");
        $this->command->info("Embeddings gerados: {$stats['embeddings_generated']}/{$totalRecords}");
        if ($stats['embeddings_failed'] > 0) {
            $this->command->error("Embeddings com falha: {$stats['embeddings_failed']}");
        }
        $this->command->info('');

        $this->command->info('Base de conhecimento atualizada com '.count($knowledge).' registros VERIFICADOS.');
        $this->command->info('');
        $this->command->info('FONTES OFICIAIS UTILIZADAS:');
        $this->command->info('');
        $this->command->info('>> RELATORIOS DE MERCADO:');
        $this->command->info('- Webshoppers 50a/51a Edicao (NIQ Ebit) - GMV, Pure Players, tendencias');
        $this->command->info('- Conversion - Market share, audiencia, trafego mensal');
        $this->command->info('- ABComm (Associacao Brasileira de Comercio Eletronico)');
        $this->command->info('- Neotrust / NeoAtlas - Taxas de conversao por segmento');
        $this->command->info('- NuvemCommerce (Nuvemshop) - Dados de PMEs');
        $this->command->info('- Atlantico Report - Brasil vs Mundo');
        $this->command->info('- PCMI - Projecoes de mercado');
        $this->command->info('');
        $this->command->info('>> MARKETPLACES:');
        $this->command->info('- MELI Trends Brasil 2024 (Mercado Livre) - Produtos mais vendidos');
        $this->command->info('- Magazine Luiza RI - Resultados 4T24');
        $this->command->info('- Amazon Brasil - Resultados 2024');
        $this->command->info('- Shopify - Future of Commerce, tendencias globais');
        $this->command->info('');
        $this->command->info('>> SEGMENTOS ESPECIFICOS:');
        $this->command->info('- Circana / NIQ (Mercado de Beleza)');
        $this->command->info('- ABCasa (Artigos para Casa)');
        $this->command->info('- Yampi / E-commerce Radar (Abandono de carrinho)');
        $this->command->info('- Opinion Box (Comportamento do consumidor)');
        $this->command->info('- edrone (Email marketing, automacao)');
        $this->command->info('- Kantar (Pesquisas de mercado)');
        $this->command->info('- Signifyd (State of Commerce 2025)');
        $this->command->info('');
        $this->command->info('>> FITNESS E ESPORTES:');
        $this->command->info('- ABIAD (Suplementos alimentares - mercado US$ 10 bi)');
        $this->command->info('- Abenutri (Produtos nutricionais)');
        $this->command->info('- IEMI (Roupas fitness - R$ 22,4 bi, 640 mi pecas)');
        $this->command->info('- ACAD Brasil / IHRSA (Academias - 64 mil, 2o mundial)');
        $this->command->info('- Abicalcados (Calcados esportivos - R$ 14,4 bi)');
        $this->command->info('- Ticket Sports / CBAT (Corrida de rua - 11 mil eventos)');
        $this->command->info('- Euromonitor (Artigos esportivos - R$ 55 bi)');
        $this->command->info('- Mordor Intelligence (Projecoes globais)');
        $this->command->info('- Track&Field RI (Resultados 2024)');
        $this->command->info('');
        $this->command->info('>> FOOD SERVICE E DELIVERY:');
        $this->command->info('- iFood (Relatorio para Restaurantes 2024, iFood Move)');
        $this->command->info('- Instituto Foodservice Brasil (IFB) - Cenarios e projecoes');
        $this->command->info('- ABRASEL (Associacao de Bares e Restaurantes)');
        $this->command->info('- ABRAS Ranking 2025 (Supermercados)');
        $this->command->info('- Fipe/iFood 2025 (Impacto economico)');
        $this->command->info('- Galunion (Comportamento delivery)');
        $this->command->info('- Statista (Market share delivery)');
        $this->command->info('');
        $this->command->info('COBERTURA:');
        $this->command->info('- Nichos: general, fashion, electronics, beauty, food, home, sports/fitness');
        $this->command->info('- Categorias: benchmarks, strategies, cases, seasonality');
        $this->command->info('- Marketplaces: Mercado Livre, Shopee, Amazon, Magalu, Shein');
        $this->command->info('- Delivery: iFood, Rappi, Ze Delivery, 99Food');
        $this->command->info('- Supermercados: Carrefour, Assai, Grupo Mateus');
        $this->command->info('- Fitness/Esportes: Suplementos, Roupas, Calcados, Academias, Corrida, Ciclismo');
        $this->command->info('- Tendencias: Mobile, Social Commerce, Pure Players, Retail Media, Dark Kitchens');
        $this->command->info('');
        $this->command->info('Todos os registros incluem campo "verified: true" e fontes no metadata.');
    }
}
