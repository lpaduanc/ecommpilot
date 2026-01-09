<script setup>
import { ref, onMounted, computed } from 'vue';
import { useDiscountStore } from '../stores/discountStore';
import { useFormatters } from '../composables/useFormatters';
import BaseCard from '../components/common/BaseCard.vue';
import BaseButton from '../components/common/BaseButton.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import {
    MagnifyingGlassIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ArrowDownTrayIcon,
    TagIcon,
    SparklesIcon,
    ChartBarIcon,
    ArrowsUpDownIcon,
} from '@heroicons/vue/24/outline';

const discountStore = useDiscountStore();
const { formatCurrency, formatPercentage } = useFormatters();

const searchQuery = ref('');
const isExporting = ref(false);
const sortColumn = ref('total_sold');
const sortDirection = ref('desc');

const stats = computed(() => discountStore.stats);
const coupons = computed(() => discountStore.coupons);
const isLoading = computed(() => discountStore.isLoading);
const totals = computed(() => discountStore.totals);
const hasSelection = computed(() => discountStore.hasSelection);

const statCards = computed(() => {
    if (!stats.value) return [];

    return [
        {
            label: 'Pedidos com Desconto',
            value: stats.value.orders_with_discount || 0,
            color: 'from-primary-500 to-primary-600',
        },
        {
            label: 'Total Vendido com Descontos',
            value: formatCurrency(stats.value.total_sold_with_discounts || 0),
            color: 'from-success-500 to-success-600',
        },
        {
            label: 'Total de Descontos',
            value: formatCurrency(stats.value.total_discounts || 0),
            color: 'from-warning-500 to-warning-600',
        },
        {
            label: 'Pedidos com Cupom',
            value: stats.value.orders_with_coupon || 0,
            color: 'from-accent-500 to-accent-600',
        },
    ];
});

function handleSearch() {
    discountStore.setSearchQuery(searchQuery.value);
    discountStore.fetchCoupons();
}

function goToPage(page) {
    discountStore.goToPage(page);
}

function toggleSelection(couponCode) {
    discountStore.toggleCouponSelection(couponCode);
}

function isSelected(couponCode) {
    return discountStore.selectedCoupons.includes(couponCode);
}

function formatNumber(value) {
    if (!value) return '0';
    return new Intl.NumberFormat('pt-BR').format(value);
}

function sortBy(column) {
    if (sortColumn.value === column) {
        // Toggle direction
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = column;
        sortDirection.value = 'desc';
    }

    discountStore.setSorting(sortColumn.value, sortDirection.value);
    discountStore.fetchCoupons();
}

function getSortIcon(column) {
    if (sortColumn.value !== column) return '';
    return sortDirection.value === 'asc' ? '↑' : '↓';
}

async function handleExport() {
    isExporting.value = true;
    try {
        await discountStore.exportData();
    } catch (error) {
        console.error('Erro ao exportar:', error);
    } finally {
        isExporting.value = false;
    }
}

onMounted(() => {
    discountStore.fetchAllData();
});
</script>

<template>
    <div class="min-h-screen -m-8 -mt-8">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 px-8 py-12">
            <!-- Background Elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 rounded-full blur-3xl"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-accent-500/10 rounded-full blur-3xl"></div>
                <!-- Grid Pattern -->
                <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
            </div>

            <div class="relative z-10 max-w-7xl mx-auto">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/30">
                                <TagIcon class="w-7 h-7 text-white" />
                            </div>
                            <div>
                                <h1 class="text-3xl lg:text-4xl font-display font-bold text-white">
                                    Descontos
                                </h1>
                                <p class="text-primary-200/80 text-sm lg:text-base">
                                    Análise de cupons e descontos aplicados
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Search Bar -->
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1 max-w-md">
                            <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" aria-hidden="true" />
                            <input
                                v-model="searchQuery"
                                @keyup.enter="handleSearch"
                                type="search"
                                placeholder="Buscar cupom..."
                                aria-label="Buscar cupom por código"
                                class="w-full pl-12 pr-4 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white placeholder-white/60 focus:bg-white/20 focus:border-white/30 focus:ring-2 focus:ring-primary-500/50 focus:outline-none transition-all"
                            />
                        </div>
                        <button
                            @click="handleSearch"
                            type="button"
                            aria-label="Buscar cupons"
                            class="px-6 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white font-medium hover:bg-white/20 transition-all focus:outline-none focus:ring-2 focus:ring-white/50"
                        >
                            <MagnifyingGlassIcon class="w-5 h-5" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 min-h-[calc(100vh-200px)]">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Stats Cards -->
                <div v-if="stats" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div
                        v-for="stat in statCards"
                        :key="stat.label"
                        class="relative overflow-hidden rounded-2xl shadow-lg"
                    >
                        <div :class="['absolute inset-0 bg-gradient-to-br', stat.color]"></div>
                        <div class="relative p-6 text-white">
                            <p class="text-white/80 text-sm font-medium mb-2">{{ stat.label }}</p>
                            <p class="text-3xl font-display font-bold">{{ stat.value }}</p>
                        </div>
                    </div>
                </div>

                <!-- Selection Message -->
                <div
                    v-if="!hasSelection"
                    class="bg-gradient-to-r from-primary-50 to-secondary-50 border border-primary-200 rounded-xl p-4 flex items-center gap-3"
                >
                    <ChartBarIcon class="w-6 h-6 text-primary-600 flex-shrink-0" />
                    <p class="text-primary-900 text-sm">
                        Para visualizar o gráfico, selecione pelo menos um item na tabela
                    </p>
                </div>

                <!-- Coupons Table -->
                <BaseCard padding="none" class="overflow-hidden">
                    <!-- Table Header with Export Button -->
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">Cupons de Desconto</h3>
                        <BaseButton
                            variant="secondary"
                            size="sm"
                            :loading="isExporting"
                            @click="handleExport"
                        >
                            <ArrowDownTrayIcon class="w-4 h-4" />
                            Baixar
                        </BaseButton>
                    </div>

                    <!-- Loading -->
                    <div v-if="isLoading" class="flex items-center justify-center py-20">
                        <div class="relative">
                            <div class="w-16 h-16 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500"></div>
                            <LoadingSpinner size="lg" class="absolute inset-0 m-auto text-white" />
                        </div>
                    </div>

                    <!-- Table -->
                    <div v-else-if="coupons.length > 0" class="overflow-x-auto">
                        <table class="min-w-full w-full">
                            <thead class="bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200 sticky top-0 z-10">
                                <tr>
                                    <th class="text-center px-4 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap w-16">
                                        Sel.
                                    </th>
                                    <th
                                        class="text-left px-4 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap cursor-pointer hover:bg-gray-200 transition-colors"
                                        @click="sortBy('coupon_code')"
                                    >
                                        Cupom {{ getSortIcon('coupon_code') }}
                                    </th>
                                    <th
                                        class="text-right px-4 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap cursor-pointer hover:bg-gray-200 transition-colors"
                                        @click="sortBy('product_revenue')"
                                    >
                                        Receita de Produtos {{ getSortIcon('product_revenue') }}
                                    </th>
                                    <th
                                        class="text-right px-4 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap cursor-pointer hover:bg-gray-200 transition-colors"
                                        @click="sortBy('shipping_revenue')"
                                    >
                                        Receita de Frete {{ getSortIcon('shipping_revenue') }}
                                    </th>
                                    <th
                                        class="text-right px-4 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap cursor-pointer hover:bg-gray-200 transition-colors"
                                        @click="sortBy('total_sold')"
                                    >
                                        Total Vendido {{ getSortIcon('total_sold') }}
                                    </th>
                                    <th
                                        class="text-right px-4 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap cursor-pointer hover:bg-gray-200 transition-colors"
                                        @click="sortBy('total_discounts')"
                                    >
                                        Total de Descontos {{ getSortIcon('total_discounts') }}
                                    </th>
                                    <th
                                        class="text-right px-4 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap cursor-pointer hover:bg-gray-200 transition-colors"
                                        @click="sortBy('order_count')"
                                    >
                                        Nº de Pedidos {{ getSortIcon('order_count') }}
                                    </th>
                                    <th
                                        class="text-right px-4 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap cursor-pointer hover:bg-gray-200 transition-colors"
                                        @click="sortBy('discount_percentage')"
                                    >
                                        % Desconto {{ getSortIcon('discount_percentage') }}
                                    </th>
                                    <th
                                        class="text-right px-4 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap cursor-pointer hover:bg-gray-200 transition-colors"
                                        @click="sortBy('avg_discount_per_order')"
                                    >
                                        Desconto Médio/Pedido {{ getSortIcon('avg_discount_per_order') }}
                                    </th>
                                    <th
                                        class="text-right px-4 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap cursor-pointer hover:bg-gray-200 transition-colors"
                                        @click="sortBy('avg_ticket')"
                                    >
                                        Ticket Médio {{ getSortIcon('avg_ticket') }}
                                    </th>
                                    <th
                                        class="text-right px-4 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap cursor-pointer hover:bg-gray-200 transition-colors"
                                        @click="sortBy('new_customers')"
                                    >
                                        Novos Clientes {{ getSortIcon('new_customers') }}
                                    </th>
                                    <th
                                        class="text-right px-4 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap cursor-pointer hover:bg-gray-200 transition-colors"
                                        @click="sortBy('repurchase_rate')"
                                    >
                                        % Recompra {{ getSortIcon('repurchase_rate') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr
                                    v-for="coupon in coupons"
                                    :key="coupon.coupon_code"
                                    :class="[
                                        'hover:bg-gradient-to-r hover:from-primary-50/50 hover:to-transparent transition-all duration-200',
                                        isSelected(coupon.coupon_code) ? 'bg-primary-50' : ''
                                    ]"
                                >
                                    <!-- Checkbox -->
                                    <td class="px-4 py-4 text-center">
                                        <input
                                            type="checkbox"
                                            :checked="isSelected(coupon.coupon_code)"
                                            @change="toggleSelection(coupon.coupon_code)"
                                            class="w-4 h-4 text-primary-600 bg-gray-100 border-gray-300 rounded focus:ring-primary-500 focus:ring-2 cursor-pointer"
                                        />
                                    </td>

                                    <!-- Cupom -->
                                    <td class="px-4 py-4">
                                        <span class="font-medium text-gray-900">{{ coupon.coupon_code || '-' }}</span>
                                    </td>

                                    <!-- Receita de Produtos -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900">
                                        {{ formatCurrency(coupon.product_revenue || 0) }}
                                    </td>

                                    <!-- Receita de Frete -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900">
                                        {{ formatCurrency(coupon.shipping_revenue || 0) }}
                                    </td>

                                    <!-- Total Vendido -->
                                    <td class="px-4 py-4 text-right text-sm font-semibold text-gray-900">
                                        {{ formatCurrency(coupon.total_sold || 0) }}
                                    </td>

                                    <!-- Total de Descontos -->
                                    <td class="px-4 py-4 text-right text-sm font-semibold text-warning-600">
                                        {{ formatCurrency(coupon.total_discounts || 0) }}
                                    </td>

                                    <!-- Número de Pedidos -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900">
                                        {{ formatNumber(coupon.order_count || 0) }}
                                    </td>

                                    <!-- Percentual de Desconto -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900">
                                        {{ coupon.discount_percentage ? formatPercentage(coupon.discount_percentage) : '-' }}
                                    </td>

                                    <!-- Desconto Médio por Pedido -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900">
                                        {{ formatCurrency(coupon.avg_discount_per_order || 0) }}
                                    </td>

                                    <!-- Ticket Médio -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900">
                                        {{ formatCurrency(coupon.avg_ticket || 0) }}
                                    </td>

                                    <!-- Novos Clientes -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900">
                                        {{ formatNumber(coupon.new_customers || 0) }}
                                    </td>

                                    <!-- % Recompra -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900">
                                        {{ coupon.repurchase_rate ? formatPercentage(coupon.repurchase_rate) : '-' }}
                                    </td>
                                </tr>

                                <!-- Linha de Totais -->
                                <tr v-if="totals" class="bg-gradient-to-r from-gray-100 to-gray-50 font-semibold border-t-2 border-gray-300">
                                    <td class="px-4 py-4 text-sm text-gray-900" colspan="2">
                                        TOTAL
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900">
                                        {{ formatCurrency(totals.product_revenue) }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900">
                                        {{ formatCurrency(totals.shipping_revenue) }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900">
                                        {{ formatCurrency(totals.total_sold) }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-warning-600">
                                        {{ formatCurrency(totals.total_discounts) }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900">
                                        {{ formatNumber(totals.order_count) }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900" colspan="4">
                                        -
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900">
                                        {{ formatNumber(totals.new_customers) }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900">
                                        -
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-20">
                        <div class="relative inline-block mb-6">
                            <div class="w-32 h-32 rounded-3xl bg-gradient-to-br from-primary-100 to-secondary-100 flex items-center justify-center">
                                <TagIcon class="w-16 h-16 text-primary-400" />
                            </div>
                            <div class="absolute -bottom-2 -right-2 w-8 h-8 rounded-full bg-accent-400 flex items-center justify-center">
                                <SparklesIcon class="w-4 h-4 text-white" />
                            </div>
                        </div>
                        <h3 class="text-2xl font-display font-bold text-gray-900 mb-3">
                            Nenhum cupom encontrado
                        </h3>
                        <p class="text-gray-500">
                            {{ searchQuery ? 'Tente uma busca diferente' : 'Nenhum cupom de desconto foi utilizado ainda' }}
                        </p>
                    </div>

                    <!-- Pagination -->
                    <div v-if="discountStore.totalPages > 1" class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50/50">
                        <p class="text-sm text-gray-500" role="status" aria-live="polite">
                            Mostrando {{ (discountStore.currentPage - 1) * discountStore.perPage + 1 }} a {{ Math.min(discountStore.currentPage * discountStore.perPage, discountStore.totalItems) }} de {{ discountStore.totalItems }} cupons
                        </p>
                        <nav class="flex items-center gap-2" role="navigation" aria-label="Paginação de cupons">
                            <BaseButton
                                variant="ghost"
                                size="sm"
                                :disabled="discountStore.currentPage === 1"
                                :aria-label="`Ir para página anterior`"
                                @click="goToPage(discountStore.currentPage - 1)"
                            >
                                <ChevronLeftIcon class="w-4 h-4" aria-hidden="true" />
                            </BaseButton>
                            <span class="text-sm text-gray-600 px-3" aria-current="page">
                                Página {{ discountStore.currentPage }} de {{ discountStore.totalPages }}
                            </span>
                            <BaseButton
                                variant="ghost"
                                size="sm"
                                :disabled="discountStore.currentPage === discountStore.totalPages"
                                :aria-label="`Ir para próxima página`"
                                @click="goToPage(discountStore.currentPage + 1)"
                            >
                                <ChevronRightIcon class="w-4 h-4" aria-hidden="true" />
                            </BaseButton>
                        </nav>
                    </div>
                </BaseCard>
            </div>
        </div>
    </div>
</template>
