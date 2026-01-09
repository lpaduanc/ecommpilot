<script setup>
import { onMounted, computed } from 'vue';
import { useDashboardStore } from '../stores/dashboardStore';
import BaseCard from '../components/common/BaseCard.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import StatCard from '../components/dashboard/StatCard.vue';
import RevenueChart from '../components/dashboard/RevenueChart.vue';
import OrdersStatusChart from '../components/dashboard/OrdersStatusChart.vue';
import TopProductsChart from '../components/dashboard/TopProductsChart.vue';
import LowStockAlert from '../components/dashboard/LowStockAlert.vue';
import DashboardFilters from '../components/dashboard/DashboardFilters.vue';
import EmptyStoreState from '../components/dashboard/EmptyStoreState.vue';
import {
    ChartBarIcon,
    CurrencyDollarIcon,
    ShoppingCartIcon,
    CubeIcon,
    UsersIcon,
    TicketIcon,
    ArrowTrendingUpIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';

const dashboardStore = useDashboardStore();

const stats = computed(() => dashboardStore.stats);
const isLoading = computed(() => dashboardStore.isLoading);
const hasStore = computed(() => dashboardStore.hasStore);

const statCards = computed(() => {
    if (!stats.value) return [];
    
    return [
        {
            title: 'Receita Total',
            value: formatCurrency(stats.value.total_revenue || 0),
            change: stats.value.revenue_change || 0,
            icon: CurrencyDollarIcon,
            color: 'primary',
        },
        {
            title: 'Total de Pedidos',
            value: stats.value.total_orders || 0,
            change: stats.value.orders_change || 0,
            icon: ShoppingCartIcon,
            color: 'success',
        },
        {
            title: 'Total de Produtos',
            value: stats.value.total_products || 0,
            change: null,
            icon: CubeIcon,
            color: 'secondary',
        },
        {
            title: 'Total de Clientes',
            value: stats.value.total_customers || 0,
            change: stats.value.customers_change || 0,
            icon: UsersIcon,
            color: 'accent',
        },
        {
            title: 'Ticket Médio',
            value: formatCurrency(stats.value.average_ticket || 0),
            change: stats.value.ticket_change || 0,
            icon: TicketIcon,
            color: 'primary',
        },
        {
            title: 'Taxa de Conversão',
            value: `${(stats.value.conversion_rate || 0).toFixed(2)}%`,
            change: stats.value.conversion_change || 0,
            icon: ArrowTrendingUpIcon,
            color: 'success',
        },
    ];
});

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
}

async function handleFiltersChange() {
    await dashboardStore.fetchAllData();
}

onMounted(() => {
    dashboardStore.fetchAllData();
});
</script>

<template>
    <div class="min-h-screen -m-8 -mt-8">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-8 py-12">
            <!-- Background Elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 dark:bg-primary-500/10 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 dark:bg-secondary-500/10 rounded-full blur-3xl"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-accent-500/10 dark:bg-accent-500/5 rounded-full blur-3xl"></div>
                <!-- Grid Pattern -->
                <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
            </div>
            
            <div class="relative z-10 max-w-7xl mx-auto">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/30">
                                <ChartBarIcon class="w-7 h-7 text-white" />
                            </div>
                            <div>
                                <h1 class="text-3xl lg:text-4xl font-display font-bold text-white dark:text-gray-100">
                                    Dashboard
                                </h1>
                                <p class="text-primary-200/80 dark:text-gray-400 text-sm lg:text-base">
                                    Visão geral completa da sua loja
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center">
                        <DashboardFilters @change="handleFiltersChange" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">
            <!-- Loading State -->
            <div v-if="isLoading && !stats" class="flex flex-col items-center justify-center py-32">
                <div class="relative">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500"></div>
                    <LoadingSpinner size="xl" class="absolute inset-0 m-auto text-white" />
                </div>
                <p class="text-gray-500 dark:text-gray-400 mt-6 font-medium">Carregando dados...</p>
            </div>

            <!-- Empty State - No Store Connected -->
            <EmptyStoreState v-else-if="!hasStore" />

            <!-- Dashboard Content -->
            <template v-else>
                <div class="max-w-7xl mx-auto space-y-8">
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <StatCard
                            v-for="(stat, index) in statCards"
                            :key="stat.title"
                            :title="stat.title"
                            :value="stat.value"
                            :change="stat.change"
                            :icon="stat.icon"
                            :color="stat.color"
                        />
                    </div>

                    <!-- Charts Row 1 -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <BaseCard padding="normal">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center">
                                    <ArrowTrendingUpIcon class="w-4 h-4 text-white" />
                                </div>
                                Receita ao Longo do Tempo
                            </h3>
                            <RevenueChart :data="dashboardStore.revenueChart" />
                        </BaseCard>

                        <BaseCard padding="normal">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-success-500 to-success-600 flex items-center justify-center">
                                    <ShoppingCartIcon class="w-4 h-4 text-white" />
                                </div>
                                Pedidos por Status
                            </h3>
                            <OrdersStatusChart :data="dashboardStore.ordersStatusChart" />
                        </BaseCard>
                    </div>

                    <!-- Charts Row 2 -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <BaseCard padding="normal" class="lg:col-span-2">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-accent-500 to-accent-600 flex items-center justify-center">
                                    <CubeIcon class="w-4 h-4 text-white" />
                                </div>
                                Produtos Mais Vendidos
                            </h3>
                            <TopProductsChart :data="dashboardStore.topProducts" />
                        </BaseCard>

                        <BaseCard padding="normal">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-danger-500 to-danger-600 flex items-center justify-center">
                                    <SparklesIcon class="w-4 h-4 text-white" />
                                </div>
                                Alerta de Estoque
                            </h3>
                            <LowStockAlert :products="dashboardStore.lowStockProducts" />
                        </BaseCard>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
