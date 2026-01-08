<?php

namespace App\Services;

class EcommerceKnowledgeBase
{
    private array $knowledge;

    public function __construct()
    {
        $this->loadKnowledge();
    }

    /**
     * Load knowledge base from JSON file
     */
    private function loadKnowledge(): void
    {
        $path = database_path('knowledge/ecommerce_strategies.json');

        if (file_exists($path)) {
            $content = file_get_contents($path);
            $this->knowledge = json_decode($content, true) ?? [];
        } else {
            $this->knowledge = [];
        }
    }

    /**
     * Get relevant strategies based on store data analysis
     */
    public function getRelevantStrategies(array $storeData): array
    {
        $relevantStrategies = [];

        // Check retention issues
        $repeatRate = $storeData['customer_insights']['repeat_purchase_rate'] ?? 0;
        if ($repeatRate < 15) {
            $relevantStrategies[] = $this->getStrategySection('retention', [
                'reason' => "Taxa de recompra baixa ({$repeatRate}%)",
                'priority' => 'high',
            ]);
        }

        // Check inventory issues
        $outOfStock = $storeData['inventory_alerts']['out_of_stock']['count'] ?? 0;
        $lowStock = $storeData['inventory_alerts']['low_stock']['count'] ?? 0;
        if ($outOfStock > 3 || $lowStock > 5) {
            $relevantStrategies[] = $this->getStrategySection('inventory_critical', [
                'reason' => "{$outOfStock} produtos sem estoque, {$lowStock} com estoque baixo",
                'priority' => 'high',
            ]);
        }

        // Check revenue decline
        $revenueTrend = $storeData['trends']['revenue']['trend'] ?? 'stable';
        $revenueChange = $storeData['trends']['revenue']['change_percent'] ?? 0;
        if (in_array($revenueTrend, ['decline', 'strong_decline'])) {
            $relevantStrategies[] = $this->getStrategySection('revenue_decline', [
                'reason' => "Receita em queda ({$revenueChange}%)",
                'priority' => $revenueTrend === 'strong_decline' ? 'critical' : 'high',
            ]);
        }

        // Check high cancellation/refund rates
        $cancellationRate = $storeData['order_patterns']['cancellation_rate'] ?? 0;
        $refundRate = $storeData['order_patterns']['refund_rate'] ?? 0;
        if ($cancellationRate > 5 || $refundRate > 3) {
            $relevantStrategies[] = $this->getStrategySection('high_cancellation', [
                'reason' => "Taxa de cancelamento: {$cancellationRate}%, reembolso: {$refundRate}%",
                'priority' => 'high',
            ]);
        }

        // Check products with no sales
        $noSalesProducts = $storeData['product_performance']['no_sales_products'] ?? [];
        if (count($noSalesProducts) > 3) {
            $totalPotential = array_sum(array_column($noSalesProducts, 'potential_revenue'));
            $relevantStrategies[] = $this->getStrategySection('products_no_sales', [
                'reason' => count($noSalesProducts).' produtos parados (R$ '.number_format($totalPotential, 2, ',', '.').' em potencial)',
                'priority' => 'medium',
            ]);
        }

        // Check growth opportunity
        if (in_array($revenueTrend, ['growth', 'strong_growth'])) {
            $relevantStrategies[] = $this->getStrategySection('growth_acceleration', [
                'reason' => "Receita em crescimento (+{$revenueChange}%) - oportunidade de acelerar",
                'priority' => 'medium',
            ]);
        }

        // Check customer segments
        $segments = $storeData['customer_insights']['segments'] ?? [];
        $vipCount = $segments['vip'] ?? 0;
        $occasionalCount = $segments['occasional'] ?? 0;
        $totalCustomers = $storeData['customer_insights']['total_customers'] ?? 1;

        if ($vipCount > 0 || ($occasionalCount / max($totalCustomers, 1)) > 0.5) {
            $relevantStrategies[] = $this->getStrategySection('customer_segments', [
                'reason' => "VIPs: {$vipCount}, Ocasionais: {$occasionalCount} - potencial de segmentação",
                'priority' => 'medium',
            ]);
        }

        // Sort by priority
        usort($relevantStrategies, function ($a, $b) {
            $priorityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

            return ($priorityOrder[$a['priority']] ?? 4) <=> ($priorityOrder[$b['priority']] ?? 4);
        });

        // Limit to top 3 most relevant
        return array_slice($relevantStrategies, 0, 3);
    }

    /**
     * Get a strategy section with context
     */
    private function getStrategySection(string $category, array $context): array
    {
        $section = $this->knowledge[$category] ?? null;

        if (! $section) {
            return [];
        }

        return [
            'category' => $category,
            'description' => $section['description'] ?? '',
            'reason' => $context['reason'] ?? '',
            'priority' => $context['priority'] ?? 'medium',
            'strategies' => array_slice($section['strategies'] ?? [], 0, 2), // Top 2 strategies per category
        ];
    }

    /**
     * Get benchmarks for comparison
     */
    public function getBenchmarks(): array
    {
        return $this->knowledge['benchmarks']['data'] ?? [];
    }

    /**
     * Format strategies for prompt inclusion
     */
    public function formatForPrompt(array $relevantStrategies): string
    {
        if (empty($relevantStrategies)) {
            return '';
        }

        $output = "## ESTRATEGIAS RECOMENDADAS (Base de Conhecimento)\n\n";
        $output .= "Com base nos dados da loja, estas estrategias comprovadas sao relevantes:\n\n";

        foreach ($relevantStrategies as $section) {
            if (empty($section)) {
                continue;
            }

            $priority = match ($section['priority']) {
                'critical' => '🔴 CRITICO',
                'high' => '🟠 ALTA',
                'medium' => '🟡 MEDIA',
                default => '🟢 BAIXA',
            };

            $output .= "### {$priority}: {$section['description']}\n";
            $output .= "**Motivo**: {$section['reason']}\n\n";

            foreach ($section['strategies'] as $strategy) {
                $output .= "**{$strategy['title']}**\n";
                $output .= "- {$strategy['description']}\n";
                $output .= "- Impacto esperado: {$strategy['expected_impact']}\n";

                if (isset($strategy['benchmark'])) {
                    $output .= "- Benchmark: {$strategy['benchmark']}\n";
                }

                $output .= "\n";
            }
        }

        $output .= "---\n";
        $output .= "Use estas estrategias como REFERENCIA para criar sugestoes ESPECIFICAS para os dados desta loja.\n";
        $output .= "NAO copie as estrategias literalmente - adapte-as aos dados especificos.\n";

        return $output;
    }
}
