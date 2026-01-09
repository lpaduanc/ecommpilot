<?php

namespace App\Services;

use App\Models\Store;
use App\Models\SyncedOrder;
use App\Models\SyncedProduct;
use Illuminate\Support\Collection;

class ProductAnalyticsService
{
    /**
     * Calculate analytics for products in a store
     */
    public function calculateProductAnalytics(Store $store, Collection $products, int $periodDays = 30): array
    {
        // Get all paid orders from the store (last N days for velocity calculations)
        $orders = SyncedOrder::where('store_id', $store->id)
            ->paid()
            ->where('external_created_at', '>=', now()->subDays($periodDays))
            ->get();

        $totalOrders = $orders->count();

        // Calculate sales data for each product
        $productData = [];
        $totalRevenue = 0;

        foreach ($products as $product) {
            $data = $this->calculateProductMetrics($product, $orders, $totalOrders, $periodDays);
            $productData[$product->id] = $data;
            $totalRevenue += $data['total_sold'];
        }

        // Calculate sales percentage for each product
        foreach ($productData as &$data) {
            $data['sales_percentage'] = $totalRevenue > 0
                ? round(($data['total_sold'] / $totalRevenue) * 100, 2)
                : 0;
        }

        // Sort products by revenue for ABC analysis
        uasort($productData, fn ($a, $b) => $b['total_sold'] <=> $a['total_sold']);

        // Calculate ABC categories
        $this->assignABCCategories($productData, $totalRevenue);

        // Calculate stock health
        $this->assignStockHealth($productData, $products, $periodDays);

        // Calculate BCG classification (optional - can be set to null if not needed)
        $this->assignBCGClassification($productData);

        // Calculate totals and ABC analysis
        $totals = $this->calculateTotals($productData);
        $abcAnalysis = $this->calculateABCAnalysis($productData);

        return [
            'products' => $productData,
            'totals' => $totals,
            'abc_analysis' => $abcAnalysis,
        ];
    }

    /**
     * Calculate metrics for a single product
     */
    private function calculateProductMetrics(
        SyncedProduct $product,
        Collection $orders,
        int $totalOrders,
        int $periodDays
    ): array {
        $unitsSold = 0;
        $totalSold = 0;
        $ordersWithProduct = 0;

        // Convert product external_id to string for comparison
        $productExternalId = (string) $product->external_id;

        foreach ($orders as $order) {
            $items = $order->items ?? [];
            $productFoundInOrder = false;

            foreach ($items as $item) {
                // Convert item product_id to string for comparison
                $itemProductId = isset($item['product_id']) ? (string) $item['product_id'] : null;
                $itemName = $item['product_name'] ?? $item['name'] ?? null;

                // Match by external_id or name (case-insensitive)
                $matchesId = $itemProductId !== null && $itemProductId === $productExternalId;
                $matchesName = $itemName !== null && strcasecmp($itemName, $product->name) === 0;

                if ($matchesId || $matchesName) {
                    $quantity = $item['quantity'] ?? 1;
                    // Use 'total' if available, otherwise calculate from unit_price * quantity
                    if (isset($item['total'])) {
                        $itemTotal = (float) $item['total'];
                    } else {
                        $price = $item['unit_price'] ?? $item['price'] ?? 0;
                        $itemTotal = $quantity * $price;
                    }

                    $unitsSold += $quantity;
                    $totalSold += $itemTotal;
                    $productFoundInOrder = true;
                }
            }

            if ($productFoundInOrder) {
                $ordersWithProduct++;
            }
        }

        // Calculate conversion rate: (orders with product / total orders) * 100
        $conversionRate = $totalOrders > 0
            ? round(($ordersWithProduct / $totalOrders) * 100, 2)
            : 0;

        // Calculate average price
        $averagePrice = $unitsSold > 0 ? $totalSold / $unitsSold : (float) $product->price;

        // Use product cost if available, otherwise use 0
        $cost = $product->cost !== null ? (float) $product->cost : 0.0;

        // Calculate profit and margin
        $totalCost = $cost * $unitsSold;
        $totalProfit = $totalSold - $totalCost;

        // Margin calculation: if no sales, margin is 0; if no cost, margin is 100%
        if ($totalSold > 0) {
            $margin = (($totalSold - $totalCost) / $totalSold) * 100;
        } else {
            $margin = 0;
        }

        return [
            'name' => $product->name,
            'sessions' => 0, // Not available - would need web analytics integration
            'units_sold' => $unitsSold,
            'conversion_rate' => $conversionRate,
            'total_sold' => round($totalSold, 2),
            'sales_percentage' => 0, // Will be assigned later
            'total_profit' => round($totalProfit, 2),
            'average_price' => round($averagePrice, 2),
            'cost' => round($cost, 2),
            'margin' => round($margin, 2),
            'stock' => $product->stock_quantity,
            'abc_category' => null, // Will be assigned later
            'stock_health' => null, // Will be assigned later
            'classification' => null, // Will be assigned later (BCG Matrix)
            'orders_with_product' => $ordersWithProduct,
        ];
    }

    /**
     * Assign ABC categories based on cumulative revenue
     */
    private function assignABCCategories(array &$productData, float $totalRevenue): void
    {
        if ($totalRevenue <= 0) {
            foreach ($productData as &$data) {
                $data['abc_category'] = 'C';
            }

            return;
        }

        $cumulativeRevenue = 0;

        foreach ($productData as &$data) {
            $cumulativeRevenue += $data['total_sold'];
            $cumulativePercentage = ($cumulativeRevenue / $totalRevenue) * 100;

            if ($cumulativePercentage <= 80) {
                $data['abc_category'] = 'A';
            } elseif ($cumulativePercentage <= 95) {
                $data['abc_category'] = 'B';
            } else {
                $data['abc_category'] = 'C';
            }
        }
    }

    /**
     * Assign stock health based on sales velocity
     *
     * Health levels based on days of stock remaining:
     * - Alto: > 30 days
     * - Adequado: 14-30 days
     * - Baixo: 7-14 days
     * - Crítico: < 7 days
     */
    private function assignStockHealth(array &$productData, Collection $products, int $periodDays): void
    {
        foreach ($productData as $productId => &$data) {
            $product = $products->firstWhere('id', $productId);
            if (! $product) {
                $data['stock_health'] = 'Crítico';
                $data['days_of_stock'] = 0;

                continue;
            }

            $stock = $product->stock_quantity;
            $unitsSold = $data['units_sold'];

            // Calculate average sales per day (based on analysis period)
            $avgSalesPerDay = $periodDays > 0 ? $unitsSold / $periodDays : 0;

            // Calculate days of stock remaining
            if ($avgSalesPerDay > 0) {
                $daysOfStock = $stock / $avgSalesPerDay;
            } else {
                // No sales in period - if has stock, consider "Alto", otherwise "Crítico"
                $daysOfStock = $stock > 0 ? 999 : 0;
            }

            // Store days of stock for reference
            $data['days_of_stock'] = round($daysOfStock, 1);

            // Assign health level based on days remaining
            if ($daysOfStock > 30) {
                $data['stock_health'] = 'Alto';
            } elseif ($daysOfStock >= 14) {
                $data['stock_health'] = 'Adequado';
            } elseif ($daysOfStock >= 7) {
                $data['stock_health'] = 'Baixo';
            } else {
                $data['stock_health'] = 'Crítico';
            }
        }
    }

    /**
     * Assign BCG Matrix classification
     * - Estrela (Star): High growth, high market share
     * - Vaca Leiteira (Cash Cow): Low growth, high market share
     * - Interrogação (Question Mark): High growth, low market share
     * - Abacaxi (Dog): Low growth, low market share
     */
    private function assignBCGClassification(array &$productData): void
    {
        if (empty($productData)) {
            return;
        }

        // Calculate median sales and margin
        $sales = array_column($productData, 'total_sold');
        $margins = array_column($productData, 'margin');

        sort($sales);
        sort($margins);

        $medianSales = $this->getMedian($sales);
        $medianMargin = $this->getMedian($margins);

        foreach ($productData as &$data) {
            $highSales = $data['total_sold'] >= $medianSales;
            $highMargin = $data['margin'] >= $medianMargin;

            if ($highSales && $highMargin) {
                $data['classification'] = 'Estrela';
            } elseif (! $highSales && $highMargin) {
                $data['classification'] = 'Vaca Leiteira';
            } elseif ($highSales && ! $highMargin) {
                $data['classification'] = 'Interrogação';
            } else {
                $data['classification'] = 'Abacaxi';
            }
        }
    }

    /**
     * Calculate median value from array
     */
    private function getMedian(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0;
        }

        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * Calculate totals for all products
     */
    private function calculateTotals(array $productData): array
    {
        return [
            'total_products' => count($productData),
            'total_sessions' => array_sum(array_column($productData, 'sessions')),
            'total_units_sold' => array_sum(array_column($productData, 'units_sold')),
            'total_revenue' => round(array_sum(array_column($productData, 'total_sold')), 2),
            'total_profit' => round(array_sum(array_column($productData, 'total_profit')), 2),
            'average_margin' => count($productData) > 0
                ? round(array_sum(array_column($productData, 'margin')) / count($productData), 2)
                : 0,
        ];
    }

    /**
     * Calculate ABC analysis summary
     */
    private function calculateABCAnalysis(array $productData): array
    {
        $total = count($productData);
        $categoryA = count(array_filter($productData, fn ($p) => $p['abc_category'] === 'A'));
        $categoryB = count(array_filter($productData, fn ($p) => $p['abc_category'] === 'B'));
        $categoryC = count(array_filter($productData, fn ($p) => $p['abc_category'] === 'C'));

        return [
            'category_a' => [
                'count' => $categoryA,
                'percentage' => $total > 0 ? round(($categoryA / $total) * 100, 2) : 0,
            ],
            'category_b' => [
                'count' => $categoryB,
                'percentage' => $total > 0 ? round(($categoryB / $total) * 100, 2) : 0,
            ],
            'category_c' => [
                'count' => $categoryC,
                'percentage' => $total > 0 ? round(($categoryC / $total) * 100, 2) : 0,
            ],
        ];
    }
}
