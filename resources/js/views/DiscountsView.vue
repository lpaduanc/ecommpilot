<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { useDiscountStore } from '../stores/discountStore';
import { useFormatters } from '../composables/useFormatters';
import BaseCard from '../components/common/BaseCard.vue';
import BaseButton from '../components/common/BaseButton.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import {
    MagnifyingGlassIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ChevronUpDownIcon,
    TagIcon,
    SparklesIcon,
    ChartBarIcon,
    XMarkIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    FunnelIcon,
    CalendarIcon,
    ChevronDownIcon,
} from '@heroicons/vue/24/outline';

const discountStore = useDiscountStore();
const { formatCurrency, formatPercentage } = useFormatters();

// Period selector state
const showPeriodDropdown = ref(false);
const selectedPeriod = ref('last_15_days');
const customStartDate = ref('');
const customEndDate = ref('');

const periodOptions = [
    { value: 'today', label: 'Hoje' },
    { value: 'last_7_days', label: 'Últimos 7 dias' },
    { value: 'last_15_days', label: 'Últimos 15 dias' },
    { value: 'last_30_days', label: 'Últimos 30 dias' },
    { value: 'this_month', label: 'Este mês' },
    { value: 'last_month', label: 'Último mês' },
    { value: 'all_time', label: 'Todo o período' },
    { value: 'custom', label: 'Personalizado' },
];

const perPageOptions = [10, 20, 50, 100];

const currentPeriodLabel = computed(() => {
    const option = periodOptions.find(o => o.value === selectedPeriod.value);
    return option?.label || 'Últimos 15 dias';
});

const isCustomPeriod = computed(() => selectedPeriod.value === 'custom');

function selectPeriod(period) {
    selectedPeriod.value = period;
    if (period !== 'custom') {
        discountStore.setPeriod(period);
        discountStore.fetchAllData();
        showPeriodDropdown.value = false;
    }
}

function applyCustomPeriod() {
    if (customStartDate.value && customEndDate.value) {
        discountStore.setCustomDates(customStartDate.value, customEndDate.value);
        discountStore.fetchAllData();
        showPeriodDropdown.value = false;
    }
}

// Click outside to close period dropdown
function handleClickOutside(event) {
    const dropdown = document.getElementById('period-dropdown');
    const button = document.getElementById('period-button');
    if (dropdown && button && !dropdown.contains(event.target) && !button.contains(event.target)) {
        showPeriodDropdown.value = false;
    }
}

// Debounce utility for search optimization
let searchDebounceTimer = null;
const DEBOUNCE_DELAY = 300;

function debounce(fn, delay) {
    return (...args) => {
        if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => fn(...args), delay);
    };
}

// Memoization cache for expensive formatting operations
const formatCache = new Map();
const CACHE_MAX_SIZE = 500;

function memoizedFormat(key, value, formatter) {
    const cacheKey = `${key}:${value}`;
    if (formatCache.has(cacheKey)) {
        return formatCache.get(cacheKey);
    }
    const result = formatter(value);
    if (formatCache.size >= CACHE_MAX_SIZE) {
        const firstKey = formatCache.keys().next().value;
        formatCache.delete(firstKey);
    }
    formatCache.set(cacheKey, result);
    return result;
}

// Cleanup on unmount
onUnmounted(() => {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    formatCache.clear();
    document.removeEventListener('click', handleClickOutside);
});


const searchQuery = ref('');
const showFilters = ref(false);

const stats = computed(() => discountStore.stats);
const coupons = computed(() => discountStore.coupons);
const isLoading = computed(() => discountStore.isLoading);
const totals = computed(() => discountStore.totals);
const hasSelection = computed(() => discountStore.hasSelection);
const hasActiveFilters = computed(() => discountStore.hasActiveFilters);

// Cached Intl formatters
const numberFormatter = new Intl.NumberFormat('pt-BR');

function formatNumber(value) {
    if (!value) return '0';
    return memoizedFormat('num', value, (v) => numberFormatter.format(v));
}

const statCards = computed(() => {
    if (!stats.value) return [];

    return [
        {
            label: 'Pedidos com Desconto',
            value: formatNumber(stats.value.orders_with_discount || 0),
            color: 'from-primary-500 to-primary-600',
        },
        {
            label: 'Total Vendido com Descontos',
            value: formatCurrency(stats.value.total_revenue || 0),
            color: 'from-success-500 to-success-600',
        },
        {
            label: 'Total de Descontos',
            value: formatCurrency(stats.value.total_discount || 0),
            color: 'from-warning-500 to-warning-600',
        },
        {
            label: 'Pedidos com Cupom de Desconto',
            value: formatNumber(stats.value.orders_with_coupon || 0),
            color: 'from-accent-500 to-accent-600',
        },
    ];
});

// Type mapping for display
const TYPE_MAP = {
    'percentage': { label: 'Percentual', color: 'primary' },
    'absolute': { label: 'Valor Fixo', color: 'success' },
    'shipping': { label: 'Frete', color: 'warning' },
};

const STATUS_MAP = {
    'active': { label: 'Ativo', color: 'success' },
    'expired': { label: 'Expirado', color: 'danger' },
    'invalid': { label: 'Inválido', color: 'secondary' },
};

function getTypeBadge(type) {
    return TYPE_MAP[type] || { label: type, color: 'secondary' };
}

function getStatusBadge(coupon) {
    if (!coupon.valid) return STATUS_MAP['invalid'];
    if (coupon.end_date && new Date(coupon.end_date) < new Date()) return STATUS_MAP['expired'];
    return STATUS_MAP['active'];
}

function formatCouponValue(coupon) {
    if (coupon.type === 'percentage') {
        return formatPercentage(coupon.value);
    }
    if (coupon.type === 'shipping') {
        return 'Frete Grátis';
    }
    return formatCurrency(coupon.value);
}

// Helper to get analytics value safely
function getAnalytics(coupon, key) {
    return coupon?.analytics?.[key] ?? 0;
}

function handleSearch() {
    discountStore.setSearchQuery(searchQuery.value);
    discountStore.fetchAllData();
}

// Debounced search for input typing
const debouncedSearch = debounce(() => {
    discountStore.setSearchQuery(searchQuery.value);
    discountStore.fetchAllData();
}, DEBOUNCE_DELAY);

function handleSearchInput() {
    debouncedSearch();
}

function goToPage(page) {
    discountStore.goToPage(page);
}

function changePerPage(newPerPage) {
    discountStore.setPerPage(newPerPage);
}

function toggleSelection(couponCode) {
    discountStore.toggleCouponSelection(couponCode);
}

function isSelected(couponCode) {
    return discountStore.selectedCoupons.includes(couponCode);
}

function applyStatusFilter(status) {
    if (discountStore.statusFilter === status) {
        discountStore.setStatusFilter(null);
    } else {
        discountStore.setStatusFilter(status);
    }
    discountStore.fetchAllData();
}

function applyTypeFilter(type) {
    if (discountStore.typeFilter === type) {
        discountStore.setTypeFilter(null);
    } else {
        discountStore.setTypeFilter(type);
    }
    discountStore.fetchAllData();
}

function clearFilters() {
    searchQuery.value = '';
    discountStore.resetFilters();
    discountStore.fetchAllData();
}

function handleSort(column) {
    const currentSortBy = discountStore.sortBy;
    const currentSortOrder = discountStore.sortOrder;

    if (currentSortBy === column) {
        discountStore.setSorting(column, currentSortOrder === 'desc' ? 'asc' : 'desc');
    } else {
        discountStore.setSorting(column, 'desc');
    }
    discountStore.fetchCoupons();
}

function getSortIcon(column) {
    if (discountStore.sortBy !== column) return ChevronUpDownIcon;
    return discountStore.sortOrder === 'desc' ? ArrowDownIcon : ArrowUpIcon;
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    discountStore.fetchAllData();
});
</script>

<template>
    <div class="min-h-screen">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-4 sm:px-6 lg:px-8 py-6 sm:py-8 lg:py-12 -mx-4 sm:-mx-6 lg:-mx-8 -mt-4 sm:-mt-6 lg:-mt-8">
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
                                <TagIcon class="w-7 h-7 text-white" />
                            </div>
                            <div>
                                <h1 class="text-3xl lg:text-4xl font-display font-bold text-white dark:text-gray-100">
                                    Cupons de Descontos
                                </h1>
                                <p class="text-primary-200/80 dark:text-gray-400 text-sm lg:text-base">
                                    {{ discountStore.totalItems }} cupons sincronizados
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Search Bar and Period Selector -->
                    <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                        <!-- Period Selector -->
                        <div class="relative">
                            <button
                                id="period-button"
                                @click.stop="showPeriodDropdown = !showPeriodDropdown"
                                type="button"
                                class="flex items-center gap-2 px-4 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white font-medium transition-all hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white/50"
                            >
                                <CalendarIcon class="w-5 h-5" aria-hidden="true" />
                                <span class="hidden sm:inline">{{ currentPeriodLabel }}</span>
                                <ChevronDownIcon class="w-4 h-4" aria-hidden="true" />
                            </button>

                            <!-- Period Dropdown -->
                            <Teleport to="body">
                                <transition
                                    enter-active-class="transition ease-out duration-200"
                                    enter-from-class="opacity-0 translate-y-1"
                                    enter-to-class="opacity-100 translate-y-0"
                                    leave-active-class="transition ease-in duration-150"
                                    leave-from-class="opacity-100 translate-y-0"
                                    leave-to-class="opacity-0 translate-y-1"
                                >
                                    <div
                                        v-if="showPeriodDropdown"
                                        id="period-dropdown"
                                        class="fixed w-80 rounded-2xl bg-white dark:bg-gray-800 shadow-xl ring-1 ring-black/5 dark:ring-white/10 z-[9999] p-4"
                                        style="top: 120px; right: 40px;"
                                    >
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Período</h3>
                                            <button @click="showPeriodDropdown = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                <XMarkIcon class="w-5 h-5" />
                                            </button>
                                        </div>

                                        <!-- Period Options -->
                                        <div class="grid grid-cols-2 gap-2 mb-4">
                                            <button
                                                v-for="option in periodOptions"
                                                :key="option.value"
                                                @click="selectPeriod(option.value)"
                                                :class="[
                                                    'px-3 py-2 rounded-lg text-sm font-medium transition-colors',
                                                    selectedPeriod === option.value
                                                        ? 'bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300'
                                                        : 'bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'
                                                ]"
                                            >
                                                {{ option.label }}
                                            </button>
                                        </div>

                                        <!-- Custom Date Range -->
                                        <div v-if="isCustomPeriod" class="space-y-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data Inicial</label>
                                                <div class="relative">
                                                    <CalendarIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                                                    <input
                                                        v-model="customStartDate"
                                                        type="date"
                                                        class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                                    />
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data Final</label>
                                                <div class="relative">
                                                    <CalendarIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                                                    <input
                                                        v-model="customEndDate"
                                                        type="date"
                                                        class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                                    />
                                                </div>
                                            </div>
                                            <button
                                                @click="applyCustomPeriod"
                                                :disabled="!customStartDate || !customEndDate"
                                                class="w-full px-4 py-2 rounded-lg bg-primary-500 hover:bg-primary-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-medium transition-colors"
                                            >
                                                Aplicar Período
                                            </button>
                                        </div>
                                    </div>
                                </transition>
                            </Teleport>
                        </div>

                        <!-- Search Input -->
                        <div class="relative flex-1 min-w-[150px] max-w-full sm:max-w-md">
                            <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" aria-hidden="true" />
                            <input
                                v-model="searchQuery"
                                @input="handleSearchInput"
                                @keyup.enter="handleSearch"
                                type="search"
                                placeholder="Pesquisar por nome..."
                                aria-label="Buscar cupom por codigo"
                                class="w-full pl-12 pr-4 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white placeholder-white/60 focus:bg-white/20 focus:border-white/30 focus:ring-2 focus:ring-primary-500/50 focus:outline-none transition-all"
                            />
                        </div>

                        <!-- Filter Toggle -->
                        <button
                            @click="showFilters = !showFilters"
                            type="button"
                            aria-label="Mostrar filtros"
                            :class="[
                                'px-6 py-3 rounded-xl backdrop-blur-sm border text-white font-medium transition-all focus:outline-none focus:ring-2 focus:ring-white/50',
                                showFilters || hasActiveFilters
                                    ? 'bg-primary-500/30 border-primary-400/50'
                                    : 'bg-white/10 border-white/20 hover:bg-white/20'
                            ]"
                        >
                            <FunnelIcon class="w-5 h-5" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="py-4 sm:py-6 lg:py-8 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">
            <div class="w-full">
                <!-- Stats Cards -->
                <div v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
                    <div
                        v-for="stat in statCards"
                        :key="stat.label"
                        class="relative overflow-hidden rounded-2xl shadow-lg"
                    >
                        <div :class="['absolute inset-0 bg-gradient-to-br', stat.color]"></div>
                        <div class="relative p-6 text-white">
                            <p class="text-white/80 text-sm font-medium mb-2">{{ stat.label }}</p>
                            <p class="text-2xl lg:text-3xl font-display font-bold">{{ stat.value }}</p>
                        </div>
                    </div>
                </div>

                <!-- Stats Loading Skeleton -->
                <div v-else-if="discountStore.isLoadingStats" class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
                    <div
                        v-for="i in 4"
                        :key="i"
                        class="relative overflow-hidden rounded-2xl shadow-lg bg-gray-200 dark:bg-gray-800 animate-pulse h-28"
                    ></div>
                </div>

                <!-- Filters Section -->
                <BaseCard v-if="showFilters || hasActiveFilters" class="mb-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/50 dark:to-primary-800/50 flex items-center justify-center flex-shrink-0">
                            <FunnelIcon class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Filtros</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Filtre os cupons por status ou tipo</p>
                                </div>
                                <BaseButton
                                    v-if="hasActiveFilters"
                                    variant="ghost"
                                    size="sm"
                                    @click="clearFilters"
                                >
                                    <XMarkIcon class="w-4 h-4 mr-1" />
                                    Limpar Filtros
                                </BaseButton>
                            </div>

                            <!-- Status Filters -->
                            <div class="mb-4">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Filtrar por Status</p>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        @click="applyStatusFilter('active')"
                                        :class="[
                                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
                                            discountStore.statusFilter === 'active'
                                                ? 'bg-success-500 text-white ring-2 ring-success-600'
                                                : 'bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-300 hover:bg-success-200 dark:hover:bg-success-900/50'
                                        ]"
                                    >
                                        Ativos
                                    </button>
                                    <button
                                        type="button"
                                        @click="applyStatusFilter('expired')"
                                        :class="[
                                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
                                            discountStore.statusFilter === 'expired'
                                                ? 'bg-danger-500 text-white ring-2 ring-danger-600'
                                                : 'bg-danger-100 dark:bg-danger-900/30 text-danger-700 dark:text-danger-300 hover:bg-danger-200 dark:hover:bg-danger-900/50'
                                        ]"
                                    >
                                        Expirados
                                    </button>
                                    <button
                                        type="button"
                                        @click="applyStatusFilter('invalid')"
                                        :class="[
                                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
                                            discountStore.statusFilter === 'invalid'
                                                ? 'bg-gray-500 text-white ring-2 ring-gray-600'
                                                : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'
                                        ]"
                                    >
                                        Inválidos
                                    </button>
                                </div>
                            </div>

                            <!-- Type Filters -->
                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Filtrar por Tipo</p>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        @click="applyTypeFilter('percentage')"
                                        :class="[
                                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
                                            discountStore.typeFilter === 'percentage'
                                                ? 'bg-primary-500 text-white ring-2 ring-primary-600'
                                                : 'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 hover:bg-primary-200 dark:hover:bg-primary-900/50'
                                        ]"
                                    >
                                        Percentual
                                    </button>
                                    <button
                                        type="button"
                                        @click="applyTypeFilter('absolute')"
                                        :class="[
                                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
                                            discountStore.typeFilter === 'absolute'
                                                ? 'bg-success-500 text-white ring-2 ring-success-600'
                                                : 'bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-300 hover:bg-success-200 dark:hover:bg-success-900/50'
                                        ]"
                                    >
                                        Valor Fixo
                                    </button>
                                    <button
                                        type="button"
                                        @click="applyTypeFilter('shipping')"
                                        :class="[
                                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
                                            discountStore.typeFilter === 'shipping'
                                                ? 'bg-warning-500 text-white ring-2 ring-warning-600'
                                                : 'bg-warning-100 dark:bg-warning-900/30 text-warning-700 dark:text-warning-300 hover:bg-warning-200 dark:hover:bg-warning-900/50'
                                        ]"
                                    >
                                        Frete Grátis
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </BaseCard>

                <!-- Selection Message -->
                <div
                    v-if="!hasSelection && coupons.length > 0"
                    class="bg-gradient-to-r from-primary-50 to-secondary-50 dark:from-primary-950/50 dark:to-secondary-950/50 border border-primary-200 dark:border-primary-800 rounded-xl p-4 flex items-center gap-3 mb-6"
                >
                    <ChartBarIcon class="w-6 h-6 text-primary-600 dark:text-primary-400 flex-shrink-0" />
                    <p class="text-primary-900 dark:text-primary-100 text-sm">
                        Para visualizar o gráfico, selecione pelo menos um item na tabela
                    </p>
                </div>

                <!-- Coupons Table -->
                <BaseCard padding="none" class="overflow-hidden">
                    <!-- Loading -->
                    <div v-if="isLoading" class="flex items-center justify-center py-20">
                        <div class="relative">
                            <div class="w-16 h-16 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500"></div>
                            <LoadingSpinner size="lg" class="absolute inset-0 m-auto text-white" />
                        </div>
                    </div>

                    <!-- Mobile/Tablet Cards -->
                    <div v-else-if="coupons.length > 0" class="xl:hidden">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                            <div
                                v-for="coupon in coupons"
                                :key="'card-' + coupon.id"
                                :class="[
                                    'bg-white dark:bg-gray-800 rounded-xl p-4 transition-all duration-200 border border-gray-200 dark:border-gray-700',
                                    isSelected(coupon.code) ? 'ring-2 ring-primary-500 border-primary-500' : ''
                                ]"
                            >
                                <!-- Header: Checkbox + Coupon Code + Status -->
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="flex items-center gap-3">
                                        <input
                                            type="checkbox"
                                            :checked="isSelected(coupon.code)"
                                            @change="toggleSelection(coupon.code)"
                                            class="w-4 h-4 text-primary-600 bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-primary-500 focus:ring-2 cursor-pointer"
                                        />
                                        <div>
                                            <p class="font-bold text-gray-900 dark:text-gray-100">{{ coupon.code }}</p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span
                                                    v-if="coupon.is_active"
                                                    class="badge badge-sm badge-success"
                                                >
                                                    Ativo
                                                </span>
                                                <span :class="['badge badge-sm', `badge-${getTypeBadge(coupon.type).color}`]">
                                                    {{ getTypeBadge(coupon.type).label }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="font-bold text-primary-600 dark:text-primary-400">{{ formatCouponValue(coupon) }}</p>
                                </div>

                                <!-- Stats Grid -->
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Total Vendido</p>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(getAnalytics(coupon, 'total_revenue')) }}</p>
                                    </div>
                                    <div class="bg-warning-50 dark:bg-warning-900/30 rounded-lg p-2.5">
                                        <p class="text-xs text-warning-600 dark:text-warning-400">Descontos</p>
                                        <p class="font-semibold text-warning-700 dark:text-warning-300">{{ formatCurrency(getAnalytics(coupon, 'total_discount')) }}</p>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Pedidos</p>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ formatNumber(getAnalytics(coupon, 'number_of_orders')) }}</p>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Ticket Médio</p>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(getAnalytics(coupon, 'average_ticket')) }}</p>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ formatNumber(getAnalytics(coupon, 'new_customers')) }} novos clientes</span>
                                    <span v-if="getAnalytics(coupon, 'repurchase_rate')">{{ formatPercentage(getAnalytics(coupon, 'repurchase_rate')) }} recompra</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table -->
                    <div v-if="coupons.length > 0" class="hidden xl:block overflow-x-auto">
                        <table class="w-full table-fixed">
                            <colgroup>
                                <col style="width: 50px"><!-- Checkbox -->
                                <col style="width: 180px"><!-- Cupom -->
                                <col style="width: 130px"><!-- Receita Produtos -->
                                <col style="width: 120px"><!-- Receita Frete -->
                                <col style="width: 130px"><!-- Total Vendido -->
                                <col style="width: 130px"><!-- Total Descontos -->
                                <col style="width: 100px"><!-- Qtde Pedidos -->
                                <col style="width: 100px"><!-- % Desconto -->
                                <col style="width: 130px"><!-- Desc. Médio Pedido -->
                                <col style="width: 130px"><!-- Ticket Médio -->
                                <col style="width: 120px"><!-- Novos Clientes -->
                                <col style="width: 100px"><!-- % Recompra -->
                            </colgroup>
                            <thead class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10">
                                <tr>
                                    <th class="text-center px-3 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                        <span class="sr-only">Selecionar</span>
                                    </th>
                                    <th class="text-left px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                        <button
                                            @click="handleSort('code')"
                                            class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
                                        >
                                            Cupom
                                            <component :is="getSortIcon('code')" class="w-4 h-4" />
                                        </button>
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                        Receita de Produtos
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                        Receita de Frete
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                        Total Vendido
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                        Total de Descontos
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                        <button
                                            @click="handleSort('used')"
                                            class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200 transition-colors ml-auto"
                                        >
                                            Qtde de Pedidos
                                            <component :is="getSortIcon('used')" class="w-4 h-4" />
                                        </button>
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                        Percentual de Desconto
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                        Desconto Médio por Pedido
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                        Ticket médio de Pedidos
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                        Número de Novos Clientes
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                        % Recompra
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr
                                    v-for="coupon in coupons"
                                    :key="coupon.id"
                                    :class="[
                                        'hover:bg-gradient-to-r hover:from-primary-50/50 dark:hover:from-primary-900/30 hover:to-transparent transition-all duration-200',
                                        isSelected(coupon.code) ? 'bg-primary-50 dark:bg-primary-900/30' : ''
                                    ]"
                                >
                                    <!-- Checkbox -->
                                    <td class="px-3 py-4 text-center">
                                        <input
                                            type="checkbox"
                                            :checked="isSelected(coupon.code)"
                                            @change="toggleSelection(coupon.code)"
                                            class="w-4 h-4 text-primary-600 bg-gray-100 dark:bg-gray-700 border-gray-300 dark:border-gray-600 rounded focus:ring-primary-500 focus:ring-2 cursor-pointer"
                                        />
                                    </td>

                                    <!-- Cupom -->
                                    <td class="px-4 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ coupon.code || '-' }}</span>
                                            <span
                                                v-if="coupon.is_active"
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-success-100 dark:bg-success-900/30 text-success-800 dark:text-success-300"
                                            >
                                                Ativo
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Receita de Produtos -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        {{ formatCurrency(getAnalytics(coupon, 'revenue_products')) }}
                                    </td>

                                    <!-- Receita de Frete -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        {{ formatCurrency(getAnalytics(coupon, 'revenue_shipping')) }}
                                    </td>

                                    <!-- Total Vendido -->
                                    <td class="px-4 py-4 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ formatCurrency(getAnalytics(coupon, 'total_revenue')) }}
                                    </td>

                                    <!-- Total de Descontos -->
                                    <td class="px-4 py-4 text-right text-sm font-semibold text-warning-600 dark:text-warning-400">
                                        {{ formatCurrency(getAnalytics(coupon, 'total_discount')) }}
                                    </td>

                                    <!-- Numero de Pedidos -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        {{ formatNumber(getAnalytics(coupon, 'number_of_orders')) }}
                                    </td>

                                    <!-- Percentual de Desconto -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        {{ getAnalytics(coupon, 'discount_percentage') ? formatPercentage(getAnalytics(coupon, 'discount_percentage')) : '-' }}
                                    </td>

                                    <!-- Desconto Médio por Pedido -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        {{ formatCurrency(getAnalytics(coupon, 'average_discount_per_order')) }}
                                    </td>

                                    <!-- Ticket Medio -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        {{ formatCurrency(getAnalytics(coupon, 'average_ticket')) }}
                                    </td>

                                    <!-- Novos Clientes -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        {{ formatNumber(getAnalytics(coupon, 'new_customers')) }}
                                    </td>

                                    <!-- % Recompra -->
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        {{ getAnalytics(coupon, 'repurchase_rate') ? formatPercentage(getAnalytics(coupon, 'repurchase_rate')) : '-' }}
                                    </td>
                                </tr>

                                <!-- Linha de Totais -->
                                <tr v-if="totals" class="bg-gradient-to-r from-gray-100 to-gray-50 dark:from-gray-800 dark:to-gray-900 font-semibold border-t-2 border-gray-300 dark:border-gray-600">
                                    <td class="px-3 py-4"></td>
                                    <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100">
                                        TOTAL (página atual)
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        {{ formatCurrency(totals.revenue_products) }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        {{ formatCurrency(totals.revenue_shipping) }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        {{ formatCurrency(totals.total_revenue) }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-warning-600 dark:text-warning-400">
                                        {{ formatCurrency(totals.total_discount) }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        {{ formatNumber(totals.number_of_orders) }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        -
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        -
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        -
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        {{ formatNumber(totals.new_customers) }}
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                        -
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-20">
                        <div class="relative inline-block mb-6">
                            <div class="w-32 h-32 rounded-3xl bg-gradient-to-br from-primary-100 to-secondary-100 dark:from-primary-900 dark:to-secondary-900 flex items-center justify-center">
                                <TagIcon class="w-16 h-16 text-primary-400" />
                            </div>
                            <div class="absolute -bottom-2 -right-2 w-8 h-8 rounded-full bg-accent-400 flex items-center justify-center">
                                <SparklesIcon class="w-4 h-4 text-white" />
                            </div>
                        </div>
                        <h3 class="text-2xl font-display font-bold text-gray-900 dark:text-gray-100 mb-3">
                            Nenhum cupom encontrado
                        </h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">
                            <template v-if="hasActiveFilters">
                                Nenhum cupom corresponde aos filtros selecionados
                            </template>
                            <template v-else-if="searchQuery">
                                Tente uma busca diferente
                            </template>
                            <template v-else>
                                Nenhum cupom de desconto foi utilizado ainda
                            </template>
                        </p>
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            @click="clearFilters"
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-500 hover:bg-primary-600 text-white rounded-lg text-sm font-medium transition-all"
                        >
                            <XMarkIcon class="w-4 h-4" />
                            Limpar Filtros
                        </button>
                    </div>

                    <!-- Pagination -->
                    <div v-if="discountStore.totalPages > 1 || discountStore.totalItems > 0" class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 sm:px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                        <div class="flex flex-col sm:flex-row items-center gap-3 sm:gap-4 w-full sm:w-auto">
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center sm:text-left" role="status" aria-live="polite">
                                <span class="hidden sm:inline">Mostrando </span>{{ (discountStore.currentPage - 1) * discountStore.perPage + 1 }}-{{ Math.min(discountStore.currentPage * discountStore.perPage, discountStore.totalItems) }} de {{ discountStore.totalItems }}
                            </p>
                            <select
                                :value="discountStore.perPage"
                                @change="changePerPage(Number($event.target.value))"
                                class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 cursor-pointer"
                            >
                                <option v-for="option in perPageOptions" :key="option" :value="option">
                                    {{ option }}/pág
                                </option>
                            </select>
                        </div>
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
                            <span class="text-sm text-gray-600 dark:text-gray-300 px-2 sm:px-3" aria-current="page">
                                {{ discountStore.currentPage }}/{{ discountStore.totalPages }}
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
