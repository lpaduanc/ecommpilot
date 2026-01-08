<?php

namespace App\Contracts;

use App\DTOs\MetricsDTO;
use App\Models\Store;
use Illuminate\Support\Collection;

interface DashboardServiceInterface
{
    /**
     * Obtém as estatísticas principais do dashboard
     */
    public function getStats(Store $store, array $filters = []): MetricsDTO;

    /**
     * Obtém dados para o gráfico de receita
     */
    public function getRevenueChart(Store $store, array $filters = []): array;

    /**
     * Obtém dados para o gráfico de status de pedidos
     */
    public function getOrdersStatusChart(Store $store, array $filters = []): array;

    /**
     * Obtém os produtos mais vendidos
     */
    public function getTopProducts(Store $store, array $filters = [], int $limit = 10): Collection;

    /**
     * Obtém dados para o gráfico de métodos de pagamento
     */
    public function getPaymentMethodsChart(Store $store, array $filters = []): array;

    /**
     * Obtém dados para o gráfico de categorias
     */
    public function getCategoriesChart(Store $store, array $filters = []): array;

    /**
     * Obtém produtos com baixo estoque
     */
    public function getLowStockProducts(Store $store, int $threshold = 10): Collection;
}
