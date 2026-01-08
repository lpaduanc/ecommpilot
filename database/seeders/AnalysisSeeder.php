<?php

namespace Database\Seeders;

use App\Enums\AnalysisStatus;
use App\Models\Analysis;
use App\Models\Store;
use Illuminate\Database\Seeder;

class AnalysisSeeder extends Seeder
{
    public function run(): void
    {
        $stores = Store::with('user')->get();

        foreach ($stores as $store) {
            // Create 3-5 analyses per store
            $numAnalyses = rand(3, 5);

            for ($i = 0; $i < $numAnalyses; $i++) {
                $daysAgo = $i * 7 + rand(0, 3); // Roughly weekly analyses
                $analysisDate = now()->subDays($daysAgo);

                $periodEnd = $analysisDate->copy();
                $periodStart = $periodEnd->copy()->subDays(30);

                $healthScore = rand(60, 95);

                Analysis::create([
                    'user_id' => $store->user_id,
                    'store_id' => $store->id,
                    'status' => $i === 0 ? AnalysisStatus::Completed : ($i === 1 ? AnalysisStatus::Completed : AnalysisStatus::Completed),
                    'summary' => $this->generateSummary($healthScore, $store->name),
                    'suggestions' => $this->generateSuggestions(),
                    'alerts' => $this->generateAlerts(),
                    'opportunities' => $this->generateOpportunities(),
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'credits_used' => rand(1, 3),
                    'completed_at' => $analysisDate,
                    'created_at' => $analysisDate,
                    'updated_at' => $analysisDate,
                ]);
            }
        }
    }

    private function generateSummary(int $healthScore, string $storeName): array
    {
        $revenueGrowth = rand(-10, 30);
        $ordersGrowth = rand(-5, 25);
        $ticketGrowth = rand(-8, 15);

        return [
            'health_score' => $healthScore,
            'health_label' => match (true) {
                $healthScore >= 80 => 'Excelente',
                $healthScore >= 60 => 'Bom',
                $healthScore >= 40 => 'Regular',
                default => 'Precisa de Atenção',
            },
            'overview' => "A loja {$storeName} apresentou desempenho ".($healthScore >= 70 ? 'positivo' : 'moderado').' no período analisado.',
            'revenue' => [
                'total' => rand(15000, 150000),
                'growth_percentage' => $revenueGrowth,
                'trend' => $revenueGrowth > 0 ? 'up' : ($revenueGrowth < 0 ? 'down' : 'stable'),
            ],
            'orders' => [
                'total' => rand(50, 500),
                'growth_percentage' => $ordersGrowth,
                'trend' => $ordersGrowth > 0 ? 'up' : ($ordersGrowth < 0 ? 'down' : 'stable'),
            ],
            'average_ticket' => [
                'value' => rand(150, 450),
                'growth_percentage' => $ticketGrowth,
                'trend' => $ticketGrowth > 0 ? 'up' : ($ticketGrowth < 0 ? 'down' : 'stable'),
            ],
            'conversion_rate' => rand(15, 45) / 10, // 1.5% - 4.5%
            'highlights' => [
                'Aumento de '.rand(10, 30).'% nas vendas via PIX',
                'Ticket médio em alta nos finais de semana',
                rand(3, 8).' produtos com estoque baixo',
            ],
        ];
    }

    private function generateSuggestions(): array
    {
        $allSuggestions = [
            [
                'id' => 'sug-001',
                'category' => 'marketing',
                'priority' => 'high',
                'title' => 'Ativar campanha de remarketing',
                'description' => 'Identificamos que 35% dos visitantes abandonaram o carrinho nos últimos 7 dias. Uma campanha de remarketing pode recuperar essas vendas.',
                'expected_impact' => 'Potencial de recuperar R$ 5.000 - R$ 8.000 em vendas',
                'action_steps' => [
                    'Configurar pixel de rastreamento',
                    'Criar sequência de e-mails para carrinhos abandonados',
                    'Implementar retargeting no Google e Facebook Ads',
                ],
                'is_done' => false,
            ],
            [
                'id' => 'sug-002',
                'category' => 'pricing',
                'priority' => 'medium',
                'title' => 'Revisar precificação de produtos menos vendidos',
                'description' => 'Alguns produtos não vendem há mais de 30 dias. Considere revisar os preços ou criar promoções específicas.',
                'expected_impact' => 'Liberar capital de estoque parado',
                'action_steps' => [
                    'Identificar produtos sem vendas há 30+ dias',
                    'Analisar preços da concorrência',
                    'Criar promoção "Queima de Estoque"',
                ],
                'is_done' => false,
            ],
            [
                'id' => 'sug-003',
                'category' => 'operations',
                'priority' => 'high',
                'title' => 'Repor estoque dos produtos mais vendidos',
                'description' => 'Seus 5 produtos mais vendidos estão com estoque baixo. Repor urgentemente para não perder vendas.',
                'expected_impact' => 'Evitar perda de R$ 3.000 - R$ 6.000 em vendas',
                'action_steps' => [
                    'Verificar produtos com menos de 10 unidades',
                    'Contatar fornecedores para reposição',
                    'Considerar pré-venda se necessário',
                ],
                'is_done' => rand(0, 1) === 1,
            ],
            [
                'id' => 'sug-004',
                'category' => 'marketing',
                'priority' => 'medium',
                'title' => 'Criar kit de produtos complementares',
                'description' => 'Análise mostra que clientes que compram o Produto A frequentemente também se interessam pelo Produto B.',
                'expected_impact' => 'Aumento de 15-20% no ticket médio',
                'action_steps' => [
                    'Identificar produtos frequentemente comprados juntos',
                    'Criar bundles com desconto de 10-15%',
                    'Destacar kits na página inicial',
                ],
                'is_done' => false,
            ],
            [
                'id' => 'sug-005',
                'category' => 'customer_experience',
                'priority' => 'low',
                'title' => 'Implementar programa de fidelidade',
                'description' => 'Seus clientes recorrentes representam 40% das vendas. Um programa de fidelidade pode aumentar ainda mais.',
                'expected_impact' => 'Aumento de 25% em compras recorrentes',
                'action_steps' => [
                    'Definir estrutura de pontos/cashback',
                    'Configurar sistema de fidelidade',
                    'Comunicar programa aos clientes',
                ],
                'is_done' => false,
            ],
            [
                'id' => 'sug-006',
                'category' => 'marketing',
                'priority' => 'high',
                'title' => 'Otimizar descrições de produtos para SEO',
                'description' => 'Muitos produtos têm descrições genéricas. Melhorar pode aumentar tráfego orgânico.',
                'expected_impact' => 'Aumento de 30% no tráfego orgânico',
                'action_steps' => [
                    'Identificar produtos sem descrição otimizada',
                    'Pesquisar palavras-chave relevantes',
                    'Reescrever descrições com foco em SEO',
                ],
                'is_done' => false,
            ],
        ];

        // Return 3-5 random suggestions
        $shuffled = collect($allSuggestions)->shuffle();

        return $shuffled->take(rand(3, 5))->values()->toArray();
    }

    private function generateAlerts(): array
    {
        $allAlerts = [
            [
                'id' => 'alert-001',
                'type' => 'warning',
                'title' => 'Produtos com estoque baixo',
                'message' => '5 produtos estão com menos de 10 unidades em estoque e podem ficar indisponíveis em breve.',
                'action' => 'Ver produtos',
                'action_url' => '/products?filter=low_stock',
            ],
            [
                'id' => 'alert-002',
                'type' => 'danger',
                'title' => 'Taxa de cancelamento alta',
                'message' => 'A taxa de cancelamento está 15% acima da média do período anterior. Recomendamos investigar.',
                'action' => 'Ver pedidos cancelados',
                'action_url' => '/orders?status=cancelled',
            ],
            [
                'id' => 'alert-003',
                'type' => 'info',
                'title' => 'Novo concorrente identificado',
                'message' => 'Detectamos um novo concorrente oferecendo produtos similares com preços 10% menores.',
                'action' => null,
                'action_url' => null,
            ],
            [
                'id' => 'alert-004',
                'type' => 'warning',
                'title' => 'Atraso em entregas',
                'message' => '8 pedidos estão com entrega atrasada. Isso pode afetar a satisfação dos clientes.',
                'action' => 'Ver pedidos atrasados',
                'action_url' => '/orders?shipping=delayed',
            ],
            [
                'id' => 'alert-005',
                'type' => 'success',
                'title' => 'Meta de vendas atingida',
                'message' => 'Parabéns! Você atingiu 105% da meta de vendas do mês.',
                'action' => null,
                'action_url' => null,
            ],
        ];

        // Return 1-3 random alerts
        $shuffled = collect($allAlerts)->shuffle();

        return $shuffled->take(rand(1, 3))->values()->toArray();
    }

    private function generateOpportunities(): array
    {
        $allOpportunities = [
            [
                'id' => 'opp-001',
                'category' => 'seasonal',
                'title' => 'Black Friday se aproximando',
                'description' => 'Faltam 45 dias para a Black Friday. É hora de preparar estoque e campanhas.',
                'potential_revenue' => rand(10000, 50000),
                'tips' => [
                    'Negocie com fornecedores antecipadamente',
                    'Prepare landing pages especiais',
                    'Configure e-mails de aquecimento',
                ],
            ],
            [
                'id' => 'opp-002',
                'category' => 'trending',
                'title' => 'Categoria em alta: Acessórios',
                'description' => 'A categoria de acessórios cresceu 45% no mercado. Considere expandir seu catálogo.',
                'potential_revenue' => rand(5000, 20000),
                'tips' => [
                    'Pesquise fornecedores de acessórios',
                    'Analise tendências no Google Trends',
                    'Considere dropshipping para testar',
                ],
            ],
            [
                'id' => 'opp-003',
                'category' => 'expansion',
                'title' => 'Expansão para marketplace',
                'description' => 'Seus produtos têm potencial para vender no Mercado Livre e Amazon. Considere expandir.',
                'potential_revenue' => rand(15000, 40000),
                'tips' => [
                    'Crie conta nos marketplaces',
                    'Configure integração de estoque',
                    'Comece com top 10 produtos',
                ],
            ],
            [
                'id' => 'opp-004',
                'category' => 'upsell',
                'title' => 'Oportunidade de upsell identificada',
                'description' => 'Clientes que compraram o Produto X poderiam ter interesse na versão premium.',
                'potential_revenue' => rand(3000, 15000),
                'tips' => [
                    'Crie comparativo entre versões',
                    'Ofereça upgrade com desconto',
                    'Envie e-mail segmentado',
                ],
            ],
        ];

        // Return 2-3 random opportunities
        $shuffled = collect($allOpportunities)->shuffle();

        return $shuffled->take(rand(2, 3))->values()->toArray();
    }
}
