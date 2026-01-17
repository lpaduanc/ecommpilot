<?php

namespace App\Services\AI;

class ProductTableFormatter
{
    /**
     * Converte array de produtos para tabela Markdown compacta
     * Reduz significativamente tokens vs JSON detalhado
     */
    public static function formatTopSellers(array $products, int $limit = 10): string
    {
        if (empty($products)) {
            return "Sem dados de vendas para este periodo.\n";
        }

        $header = "| Rank | Produto | Units | Receita | Ordens |\n";
        $header .= "|------|---------|-------|---------|--------|\n";

        $rows = '';
        $products = array_slice($products, 0, $limit);

        foreach ($products as $index => $product) {
            $rank = $index + 1;
            $name = self::truncateName($product['name'] ?? 'Produto', 25);
            $units = $product['units_sold'] ?? 0;
            $revenue = number_format($product['revenue'] ?? 0, 0, ',', '.');
            $orders = $product['orders_count'] ?? 0;

            $rows .= "| {$rank} | {$name} | {$units} | R\$ {$revenue} | {$orders} |\n";
        }

        return $header.$rows;
    }

    public static function formatNoSalesProducts(array $products, int $limit = 10): string
    {
        if (empty($products)) {
            return "Todos os produtos com estoque tiveram vendas neste periodo.\n";
        }

        $header = "| Rank | Produto | Stock | Preco | Potencial |\n";
        $header .= "|------|---------|-------|-------|----------|\n";

        $rows = '';
        $products = array_slice($products, 0, $limit);

        foreach ($products as $index => $product) {
            $rank = $index + 1;
            $name = self::truncateName($product['name'] ?? 'Produto', 25);
            $stock = $product['stock'] ?? 0;
            $price = number_format($product['price'] ?? 0, 0, ',', '.');
            $potential = number_format($product['potential_revenue'] ?? 0, 0, ',', '.');

            $rows .= "| {$rank} | {$name} | {$stock} | R\$ {$price} | R\$ {$potential} |\n";
        }

        return $header.$rows;
    }

    public static function formatInventorySummary(array $alerts): string
    {
        $outOfStock = $alerts['out_of_stock']['count'] ?? 0;
        $lowStock = $alerts['low_stock']['count'] ?? 0;
        $healthyStock = $alerts['healthy_stock_count'] ?? 0;
        $healthRate = $alerts['health_rate'] ?? 0;

        return <<<TEXT
**Saude do Estoque:** {$healthRate}%
- Saudavel (>=10 unidades): {$healthyStock}
- Baixo (1-9 unidades): {$lowStock}
- Fora de estoque: {$outOfStock}
TEXT;
    }

    /**
     * Trunca nome do produto para caber na tabela
     */
    private static function truncateName(string $name, int $maxLength = 25): string
    {
        if (strlen($name) <= $maxLength) {
            return $name;
        }

        return substr($name, 0, $maxLength - 3).'...';
    }
}
