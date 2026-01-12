<script setup>
import { ref, onMounted, computed, watch, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import api from '../services/api';
import BaseCard from '../components/common/BaseCard.vue';
import BaseButton from '../components/common/BaseButton.vue';
import BaseInput from '../components/common/BaseInput.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import ProductDetailPanel from '../components/dashboard/ProductDetailPanel.vue';
import { useKeyboardNavigation } from '../composables/useKeyboard';
import { useFormatters } from '../composables/useFormatters';
import {
    CubeIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    SparklesIcon,
    ChartBarIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();
const { formatCurrency, formatPercentage } = useFormatters();

// Debounce utility for search optimization
let searchDebounceTimer = null;
const DEBOUNCE_DELAY = 300; // ms

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
    // Limit cache size to prevent memory issues
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
});

const products = ref([]);
const selectedProduct = ref(null);
const isLoading = ref(false);
const searchQuery = ref(route.query.search || '');
const currentPage = ref(1);
const totalPages = ref(1);
const totalItems = ref(0);
const perPage = ref(10);

const perPageOptions = [10, 20, 50, 100];

// Filtros
const selectedAbcCategory = ref(null);
const selectedStockHealth = ref(null);

// Dados agregados da API
const apiTotals = ref(null);
const apiAbcAnalysis = ref(null);

const showDetailPanel = computed(() => !!selectedProduct.value);

// Navegação por teclado
const { currentIndex } = useKeyboardNavigation(
    products,
    (product) => selectProduct(product),
    { loop: true, disabled: isLoading }
);

// Análise ABC - usando dados da API
const abcAnalysis = computed(() => {
    if (apiAbcAnalysis.value) {
        return {
            a: apiAbcAnalysis.value.category_a?.percentage || 0,
            b: apiAbcAnalysis.value.category_b?.percentage || 0,
            c: apiAbcAnalysis.value.category_c?.percentage || 0,
            countA: apiAbcAnalysis.value.category_a?.count || 0,
            countB: apiAbcAnalysis.value.category_b?.count || 0,
            countC: apiAbcAnalysis.value.category_c?.count || 0,
        };
    }
    return { a: 0, b: 0, c: 0, countA: 0, countB: 0, countC: 0 };
});

// Totalizadores - usando dados da API
const totals = computed(() => {
    if (apiTotals.value) {
        return {
            total_products: apiTotals.value.total_products || 0,
            units_sold: apiTotals.value.total_units_sold || 0,
            total_revenue: apiTotals.value.total_revenue || 0,
            total_profit: apiTotals.value.total_profit || 0,
            average_margin: apiTotals.value.average_margin || 0,
        };
    }
    return {
        total_products: 0,
        units_sold: 0,
        total_revenue: 0,
        total_profit: 0,
        average_margin: 0,
    };
});

// Filtros ativos
const hasActiveFilters = computed(() => {
    return selectedAbcCategory.value || selectedStockHealth.value;
});

async function fetchProducts() {
    isLoading.value = true;
    try {
        const response = await api.get('/products', {
            params: {
                search: searchQuery.value,
                page: currentPage.value,
                per_page: perPage.value,
                abc_category: selectedAbcCategory.value,
                stock_health: selectedStockHealth.value,
            },
        });
        products.value = response.data.data;
        totalPages.value = response.data.last_page;
        totalItems.value = response.data.total;
        apiTotals.value = response.data.totals || null;
        apiAbcAnalysis.value = response.data.abc_analysis || null;
    } catch {
        products.value = [];
        apiTotals.value = null;
        apiAbcAnalysis.value = null;
    } finally {
        isLoading.value = false;
    }
}

function selectProduct(product) {
    selectedProduct.value = product;
}

function closeDetailPanel() {
    selectedProduct.value = null;
}

function handleSearch() {
    currentPage.value = 1;
    fetchProducts();
}

// Debounced search for input typing - prevents excessive API calls
const debouncedSearch = debounce(() => {
    currentPage.value = 1;
    fetchProducts();
}, DEBOUNCE_DELAY);

function handleSearchInput() {
    debouncedSearch();
}

function goToPage(page) {
    if (page < 1 || page > totalPages.value) return;
    currentPage.value = page;
    fetchProducts();
}

function changePerPage(newPerPage) {
    perPage.value = newPerPage;
    currentPage.value = 1;
    fetchProducts();
}

// Pre-defined static mappings for O(1) lookup (no function calls in render)
const STOCK_HEALTH_MAP = {
    'alto': { label: 'Alto', color: 'success' },
    'adequado': { label: 'Adequado', color: 'primary' },
    'baixo': { label: 'Baixo', color: 'warning' },
    'crítico': { label: 'Crítico', color: 'danger' },
};

const ABC_CATEGORY_MAP = {
    'A': { label: 'A', color: 'success' },
    'B': { label: 'B', color: 'warning' },
    'C': { label: 'C', color: 'danger' },
};

const DEFAULT_BADGE = { label: '-', color: 'secondary' };

// Cached Intl formatters (created once, reused)
const numberFormatter = new Intl.NumberFormat('pt-BR');

// Memoized formatting functions for table cells
function formatNumber(value) {
    if (!value) return '0';
    return memoizedFormat('num', value, (v) => numberFormatter.format(v));
}

function getStockStatus(quantity) {
    if (quantity === 0) return { label: 'Sem Estoque', color: 'danger' };
    if (quantity < 10) return { label: 'Estoque Baixo', color: 'warning' };
    return { label: 'Em Estoque', color: 'success' };
}

// O(1) lookup with pre-defined map
function getStockHealthStatus(health) {
    if (!health) return DEFAULT_BADGE;
    return STOCK_HEALTH_MAP[health.toLowerCase()] || { label: health, color: 'secondary' };
}

// O(1) lookup with pre-defined map
function getAbcCategoryBadge(category) {
    if (!category) return DEFAULT_BADGE;
    return ABC_CATEGORY_MAP[category] || DEFAULT_BADGE;
}

// Simple conditionals - no function call overhead
function getMarginColor(margin) {
    if (margin >= 30) return 'text-success-600 dark:text-success-400';
    if (margin >= 15) return 'text-warning-600 dark:text-warning-400';
    return 'text-danger-600 dark:text-danger-400';
}

function applyAbcFilter(category) {
    if (selectedAbcCategory.value === category) {
        selectedAbcCategory.value = null;
    } else {
        selectedAbcCategory.value = category;
    }
    currentPage.value = 1;
    fetchProducts();
}

function applyStockHealthFilter(health) {
    if (selectedStockHealth.value === health) {
        selectedStockHealth.value = null;
    } else {
        selectedStockHealth.value = health;
    }
    currentPage.value = 1;
    fetchProducts();
}

function clearFilters() {
    selectedAbcCategory.value = null;
    selectedStockHealth.value = null;
    currentPage.value = 1;
    fetchProducts();
}

watch(() => route.query.search, (newSearch) => {
    if (newSearch) {
        searchQuery.value = newSearch;
        handleSearch();
    }
});

onMounted(() => {
    fetchProducts();
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
                    <div class="space-y-3 sm:space-y-4">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-10 sm:w-12 lg:w-14 h-10 sm:h-12 lg:h-14 rounded-xl sm:rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/30 flex-shrink-0">
                                <CubeIcon class="w-5 sm:w-6 lg:w-7 h-5 sm:h-6 lg:h-7 text-white" />
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-xl sm:text-2xl lg:text-4xl font-display font-bold text-white dark:text-gray-100 truncate">
                                    Produtos
                                </h1>
                                <p class="text-primary-200/80 dark:text-gray-400 text-xs sm:text-sm lg:text-base">
                                    {{ totalItems }} produtos sincronizados
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search Bar -->
                    <div class="flex items-center gap-3 w-full lg:w-auto">
                        <div class="relative flex-1 max-w-full sm:max-w-md">
                            <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" aria-hidden="true" />
                            <input
                                v-model="searchQuery"
                                @input="handleSearchInput"
                                @keyup.enter="handleSearch"
                                type="search"
                                placeholder="Buscar produto por nome ou SKU..."
                                aria-label="Buscar produtos por nome ou SKU"
                                class="w-full pl-12 pr-4 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white placeholder-white/60 focus:bg-white/20 focus:border-white/30 focus:ring-2 focus:ring-primary-500/50 focus:outline-none transition-all"
                            />
                        </div>
                        <button
                            @click="handleSearch"
                            type="button"
                            aria-label="Buscar produtos"
                            class="px-6 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white font-medium hover:bg-white/20 transition-all focus:outline-none focus:ring-2 focus:ring-white/50"
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
                <div class="flex gap-6">
                    <!-- Products Table -->
                    <div class="flex-1 transition-all duration-300">
                        <!-- Summary Totals -->
                        <div v-if="!isLoading && totals.total_products > 0" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-2 sm:gap-3 lg:gap-4 mb-4 sm:mb-6">
                            <BaseCard padding="sm" class="!p-3 sm:!p-4">
                                <div class="flex flex-col sm:flex-row sm:items-start gap-2 sm:gap-3">
                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30 flex items-center justify-center flex-shrink-0">
                                        <CubeIcon class="w-4 h-4 sm:w-5 sm:h-5 text-primary-600 dark:text-primary-400" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 mb-0.5 sm:mb-1 truncate">Produtos</p>
                                        <p class="text-sm sm:text-lg font-bold text-gray-900 dark:text-gray-100">{{ formatNumber(totals.total_products) }}</p>
                                    </div>
                                </div>
                            </BaseCard>

                            <BaseCard padding="sm" class="!p-3 sm:!p-4">
                                <div class="flex flex-col sm:flex-row sm:items-start gap-2 sm:gap-3">
                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30 flex items-center justify-center flex-shrink-0">
                                        <ChartBarIcon class="w-4 h-4 sm:w-5 sm:h-5 text-primary-600 dark:text-primary-400" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 mb-0.5 sm:mb-1 truncate">Vendidas</p>
                                        <p class="text-sm sm:text-lg font-bold text-gray-900 dark:text-gray-100">{{ formatNumber(totals.units_sold) }}</p>
                                    </div>
                                </div>
                            </BaseCard>

                            <BaseCard padding="sm" class="!p-3 sm:!p-4">
                                <div class="flex flex-col sm:flex-row sm:items-start gap-2 sm:gap-3">
                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-gradient-to-br from-success-100 to-success-200 dark:from-success-900/30 dark:to-success-800/30 flex items-center justify-center flex-shrink-0">
                                        <ChartBarIcon class="w-4 h-4 sm:w-5 sm:h-5 text-success-600 dark:text-success-400" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 mb-0.5 sm:mb-1 truncate">Receita</p>
                                        <p class="text-sm sm:text-lg font-bold text-gray-900 dark:text-gray-100 truncate">{{ formatCurrency(totals.total_revenue) }}</p>
                                    </div>
                                </div>
                            </BaseCard>

                            <BaseCard padding="sm" class="!p-3 sm:!p-4">
                                <div class="flex flex-col sm:flex-row sm:items-start gap-2 sm:gap-3">
                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-gradient-to-br from-success-100 to-success-200 dark:from-success-900/30 dark:to-success-800/30 flex items-center justify-center flex-shrink-0">
                                        <ChartBarIcon class="w-4 h-4 sm:w-5 sm:h-5 text-success-600 dark:text-success-400" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 mb-0.5 sm:mb-1 truncate">Lucro</p>
                                        <p class="text-sm sm:text-lg font-bold text-success-600 dark:text-success-400 truncate">{{ formatCurrency(totals.total_profit) }}</p>
                                    </div>
                                </div>
                            </BaseCard>

                            <BaseCard padding="sm" class="!p-3 sm:!p-4 col-span-2 sm:col-span-1">
                                <div class="flex flex-col sm:flex-row sm:items-start gap-2 sm:gap-3">
                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-gradient-to-br from-warning-100 to-warning-200 dark:from-warning-900/30 dark:to-warning-800/30 flex items-center justify-center flex-shrink-0">
                                        <ChartBarIcon class="w-4 h-4 sm:w-5 sm:h-5 text-warning-600 dark:text-warning-400" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 mb-0.5 sm:mb-1 truncate">Margem Média</p>
                                        <p class="text-sm sm:text-lg font-bold text-gray-900 dark:text-gray-100">{{ formatPercentage(totals.average_margin) }}</p>
                                    </div>
                                </div>
                            </BaseCard>
                        </div>

                        <!-- ABC Analysis and Filters -->
                        <BaseCard v-if="!isLoading && products.length > 0" class="mb-4 sm:mb-6">
                            <div class="flex flex-col sm:flex-row sm:items-start gap-3 sm:gap-4">
                                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30 flex items-center justify-center flex-shrink-0">
                                    <ChartBarIcon class="w-5 h-5 sm:w-6 sm:h-6 text-primary-600 dark:text-primary-400" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-4 mb-3 sm:mb-4">
                                        <div>
                                            <h3 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-gray-100 mb-0.5 sm:mb-1">Análise ABC</h3>
                                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Distribuição por categoria de faturamento</p>
                                        </div>
                                        <BaseButton
                                            v-if="hasActiveFilters"
                                            variant="ghost"
                                            size="sm"
                                            @click="clearFilters"
                                            class="self-start sm:self-auto"
                                        >
                                            <XMarkIcon class="w-4 h-4 mr-1" />
                                            <span class="hidden sm:inline">Limpar Filtros</span>
                                            <span class="sm:hidden">Limpar</span>
                                        </BaseButton>
                                    </div>

                                    <!-- ABC Progress Bar -->
                                    <div class="mb-4">
                                        <div class="flex h-8 rounded-lg overflow-hidden shadow-sm">
                                            <button
                                                type="button"
                                                @click="applyAbcFilter('A')"
                                                :style="{ width: `${abcAnalysis.a}%` }"
                                                :class="[
                                                    'flex items-center justify-center text-white text-xs font-medium transition-all hover:brightness-110',
                                                    selectedAbcCategory === 'A' ? 'bg-gradient-to-r from-success-600 to-success-700 ring-2 ring-success-600' : 'bg-gradient-to-r from-success-500 to-success-600'
                                                ]"
                                                :aria-label="`Filtrar por Categoria A - ${abcAnalysis.countA} produtos`"
                                            >
                                                <span v-if="abcAnalysis.a > 5">{{ abcAnalysis.a.toFixed(0) }}%</span>
                                            </button>
                                            <button
                                                type="button"
                                                @click="applyAbcFilter('B')"
                                                :style="{ width: `${abcAnalysis.b}%` }"
                                                :class="[
                                                    'flex items-center justify-center text-white text-xs font-medium transition-all hover:brightness-110',
                                                    selectedAbcCategory === 'B' ? 'bg-gradient-to-r from-warning-500 to-warning-600 ring-2 ring-warning-600' : 'bg-gradient-to-r from-warning-400 to-warning-500'
                                                ]"
                                                :aria-label="`Filtrar por Categoria B - ${abcAnalysis.countB} produtos`"
                                            >
                                                <span v-if="abcAnalysis.b > 5">{{ abcAnalysis.b.toFixed(0) }}%</span>
                                            </button>
                                            <button
                                                type="button"
                                                @click="applyAbcFilter('C')"
                                                :style="{ width: `${abcAnalysis.c}%` }"
                                                :class="[
                                                    'flex items-center justify-center text-white text-xs font-medium transition-all hover:brightness-110',
                                                    selectedAbcCategory === 'C' ? 'bg-gradient-to-r from-danger-600 to-danger-700 ring-2 ring-danger-600' : 'bg-gradient-to-r from-danger-400 to-danger-500'
                                                ]"
                                                :aria-label="`Filtrar por Categoria C - ${abcAnalysis.countC} produtos`"
                                            >
                                                <span v-if="abcAnalysis.c > 5">{{ abcAnalysis.c.toFixed(0) }}%</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Legendas com Contadores -->
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-4">
                                        <button
                                            type="button"
                                            @click="applyAbcFilter('A')"
                                            :class="[
                                                'flex items-center gap-3 p-3 rounded-lg border-2 transition-all',
                                                selectedAbcCategory === 'A' ? 'border-success-500 bg-success-50 dark:bg-success-900/30' : 'border-transparent hover:border-success-200 dark:hover:border-success-700 hover:bg-success-50/50 dark:hover:bg-success-900/20'
                                            ]"
                                        >
                                            <div class="w-4 h-4 rounded bg-gradient-to-br from-success-500 to-success-600 flex-shrink-0"></div>
                                            <div class="text-left">
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Categoria A</p>
                                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ abcAnalysis.countA }} produtos ({{ abcAnalysis.a.toFixed(1) }}%)</p>
                                            </div>
                                        </button>
                                        <button
                                            type="button"
                                            @click="applyAbcFilter('B')"
                                            :class="[
                                                'flex items-center gap-3 p-3 rounded-lg border-2 transition-all',
                                                selectedAbcCategory === 'B' ? 'border-warning-500 bg-warning-50 dark:bg-warning-900/30' : 'border-transparent hover:border-warning-200 dark:hover:border-warning-700 hover:bg-warning-50/50 dark:hover:bg-warning-900/20'
                                            ]"
                                        >
                                            <div class="w-4 h-4 rounded bg-gradient-to-br from-warning-400 to-warning-500 flex-shrink-0"></div>
                                            <div class="text-left">
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Categoria B</p>
                                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ abcAnalysis.countB }} produtos ({{ abcAnalysis.b.toFixed(1) }}%)</p>
                                            </div>
                                        </button>
                                        <button
                                            type="button"
                                            @click="applyAbcFilter('C')"
                                            :class="[
                                                'flex items-center gap-3 p-3 rounded-lg border-2 transition-all',
                                                selectedAbcCategory === 'C' ? 'border-danger-500 bg-danger-50 dark:bg-danger-900/30' : 'border-transparent hover:border-danger-200 dark:hover:border-danger-700 hover:bg-danger-50/50 dark:hover:bg-danger-900/20'
                                            ]"
                                        >
                                            <div class="w-4 h-4 rounded bg-gradient-to-br from-danger-400 to-danger-500 flex-shrink-0"></div>
                                            <div class="text-left">
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Categoria C</p>
                                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ abcAnalysis.countC }} produtos ({{ abcAnalysis.c.toFixed(1) }}%)</p>
                                            </div>
                                        </button>
                                    </div>

                                    <!-- Filtro de Saúde do Estoque -->
                                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Filtrar por Saúde do Estoque</p>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                @click="applyStockHealthFilter('Alto')"
                                                :class="[
                                                    'px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
                                                    selectedStockHealth === 'Alto' ? 'bg-success-500 text-white ring-2 ring-success-600' : 'bg-success-100 text-success-700 hover:bg-success-200'
                                                ]"
                                            >
                                                Alto
                                            </button>
                                            <button
                                                type="button"
                                                @click="applyStockHealthFilter('Adequado')"
                                                :class="[
                                                    'px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
                                                    selectedStockHealth === 'Adequado' ? 'bg-primary-500 text-white ring-2 ring-primary-600' : 'bg-primary-100 text-primary-700 hover:bg-primary-200'
                                                ]"
                                            >
                                                Adequado
                                            </button>
                                            <button
                                                type="button"
                                                @click="applyStockHealthFilter('Baixo')"
                                                :class="[
                                                    'px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
                                                    selectedStockHealth === 'Baixo' ? 'bg-warning-500 text-white ring-2 ring-warning-600' : 'bg-warning-100 text-warning-700 hover:bg-warning-200'
                                                ]"
                                            >
                                                Baixo
                                            </button>
                                            <button
                                                type="button"
                                                @click="applyStockHealthFilter('Crítico')"
                                                :class="[
                                                    'px-3 py-1.5 rounded-lg text-sm font-medium transition-all',
                                                    selectedStockHealth === 'Crítico' ? 'bg-danger-500 text-white ring-2 ring-danger-600' : 'bg-danger-100 text-danger-700 hover:bg-danger-200'
                                                ]"
                                            >
                                                Crítico
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Botão Limpar Filtros -->
                                    <div v-if="hasActiveFilters" class="pt-4 border-t border-gray-200 dark:border-gray-700">
                                        <button
                                            type="button"
                                            @click="clearFilters"
                                            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium transition-all"
                                        >
                                            <XMarkIcon class="w-4 h-4" />
                                            Limpar Todos os Filtros
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </BaseCard>

                        <BaseCard padding="none" class="overflow-hidden">
                            <!-- Loading -->
                            <div v-if="isLoading" class="flex items-center justify-center py-20">
                                <div class="relative">
                                    <div class="w-16 h-16 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500"></div>
                                    <LoadingSpinner size="lg" class="absolute inset-0 m-auto text-white" />
                                </div>
                            </div>

                            <!-- Mobile/Tablet Cards -->
                            <div v-else-if="products.length > 0" class="xl:hidden">
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                                    <div
                                        v-for="product in products"
                                        :key="'card-' + product.id"
                                        @click="selectProduct(product)"
                                        :class="[
                                            'bg-white dark:bg-gray-800 rounded-xl p-4 cursor-pointer transition-all duration-200 border border-gray-200 dark:border-gray-700',
                                            'hover:shadow-lg hover:border-primary-300 dark:hover:border-primary-600',
                                            selectedProduct?.id === product.id ? 'ring-2 ring-primary-500 border-primary-500' : ''
                                        ]"
                                    >
                                        <!-- Header: Image + Name + Badges -->
                                        <div class="flex items-start gap-3 mb-3">
                                            <div class="w-14 h-14 rounded-lg bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-600 flex items-center justify-center overflow-hidden shadow-sm flex-shrink-0">
                                                <img
                                                    v-if="product.images?.[0]"
                                                    :src="product.images[0]"
                                                    :alt="product.name"
                                                    class="w-full h-full object-cover"
                                                />
                                                <CubeIcon v-else class="w-6 h-6 text-gray-400" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="font-semibold text-gray-900 dark:text-gray-100 text-sm line-clamp-2">{{ product.name }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">{{ product.sku || 'Sem SKU' }}</p>
                                                <div class="flex items-center gap-2 mt-1.5">
                                                    <span
                                                        v-if="product.analytics?.abc_category"
                                                        :class="['badge badge-sm', `badge-${getAbcCategoryBadge(product.analytics.abc_category).color}`]"
                                                    >
                                                        {{ getAbcCategoryBadge(product.analytics.abc_category).label }}
                                                    </span>
                                                    <span
                                                        v-if="product.analytics?.stock_health"
                                                        :class="['badge badge-sm', `badge-${getStockHealthStatus(product.analytics.stock_health).color}`]"
                                                    >
                                                        {{ getStockHealthStatus(product.analytics.stock_health).label }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Stats Grid -->
                                        <div class="grid grid-cols-2 gap-3 text-sm">
                                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Preço</p>
                                                <p class="font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(product.price || 0) }}</p>
                                            </div>
                                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Estoque</p>
                                                <p class="font-semibold text-gray-900 dark:text-gray-100">{{ formatNumber(product.stock || 0) }}</p>
                                            </div>
                                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Vendidas</p>
                                                <p class="font-semibold text-gray-900 dark:text-gray-100">{{ formatNumber(product.analytics?.units_sold || 0) }}</p>
                                            </div>
                                            <div class="bg-success-50 dark:bg-success-900/30 rounded-lg p-2.5">
                                                <p class="text-xs text-success-600 dark:text-success-400">Receita</p>
                                                <p class="font-semibold text-success-700 dark:text-success-300">{{ formatCurrency(product.analytics?.total_sold || 0) }}</p>
                                            </div>
                                        </div>

                                        <!-- Footer: Margin -->
                                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                            <span class="text-xs text-gray-500 dark:text-gray-400">Margem</span>
                                            <span
                                                v-if="product.analytics?.margin !== null && product.analytics?.margin !== undefined"
                                                :class="['font-semibold text-sm', getMarginColor(product.analytics.margin)]"
                                            >
                                                {{ formatPercentage(product.analytics.margin) }}
                                            </span>
                                            <span v-else class="text-gray-400 text-sm">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Desktop Table -->
                            <div v-if="products.length > 0" class="hidden xl:block overflow-x-auto">
                                <table class="w-full table-fixed">
                                    <colgroup>
                                        <col style="width: 280px"><!-- Nome -->
                                        <col style="width: 90px"><!-- Curva ABC -->
                                        <col style="width: 120px"><!-- Saúde Estoque -->
                                        <col style="width: 120px"><!-- Unid. Vendidas -->
                                        <col style="width: 110px"><!-- Taxa Conv. -->
                                        <col style="width: 100px"><!-- % Vendas -->
                                        <col style="width: 140px"><!-- Total Vendido -->
                                        <col style="width: 140px"><!-- Lucro Total -->
                                        <col style="width: 120px"><!-- Preço -->
                                        <col style="width: 120px"><!-- Preço Médio -->
                                        <col style="width: 120px"><!-- Custo -->
                                        <col style="width: 100px"><!-- Margem -->
                                        <col style="width: 100px"><!-- Estoque -->
                                    </colgroup>
                                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10">
                                        <tr>
                                            <th class="text-left px-5 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                                Produto
                                            </th>
                                            <th class="text-center px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                                Curva ABC
                                            </th>
                                            <th class="text-center px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                                Saúde Estoque
                                            </th>
                                            <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                                Unid. Vendidas
                                            </th>
                                            <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                                Taxa Conv.
                                            </th>
                                            <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                                % Vendas
                                            </th>
                                            <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                                Total Vendido
                                            </th>
                                            <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                                Lucro Total
                                            </th>
                                            <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                                Preço
                                            </th>
                                            <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                                Preço Médio
                                            </th>
                                            <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                                Custo
                                            </th>
                                            <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                                Margem
                                            </th>
                                            <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                                Estoque
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                        <tr
                                            v-for="(product, index) in products"
                                            :key="product.id"
                                            tabindex="0"
                                            role="button"
                                            :aria-label="`Ver detalhes do produto ${product.name}`"
                                            @click="selectProduct(product)"
                                            @keydown.enter="selectProduct(product)"
                                            @keydown.space.prevent="selectProduct(product)"
                                            :class="[
                                                'hover:bg-gradient-to-r hover:from-primary-50/50 dark:hover:from-primary-900/30 hover:to-transparent cursor-pointer transition-all duration-200',
                                                'focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-inset focus:bg-primary-50/30 dark:focus:bg-primary-900/30',
                                                selectedProduct?.id === product.id ? 'bg-primary-50 dark:bg-primary-900/30' : '',
                                                currentIndex === index ? 'ring-2 ring-primary-400 ring-inset' : ''
                                            ]"
                                        >
                                            <!-- Nome -->
                                            <td class="px-5 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center overflow-hidden shadow-sm flex-shrink-0">
                                                        <img
                                                            v-if="product.images?.[0]"
                                                            :src="product.images[0]"
                                                            :alt="product.name"
                                                            class="w-full h-full object-cover"
                                                        />
                                                        <CubeIcon v-else class="w-5 h-5 text-gray-400" />
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="font-medium text-gray-900 dark:text-gray-100 truncate text-sm">{{ product.name }}</p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ product.sku || '-' }}</p>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Curva ABC -->
                                            <td class="px-4 py-4 text-center">
                                                <span
                                                    v-if="product.analytics?.abc_category"
                                                    :class="[
                                                        'badge',
                                                        `badge-${getAbcCategoryBadge(product.analytics.abc_category).color}`
                                                    ]"
                                                >
                                                    {{ getAbcCategoryBadge(product.analytics.abc_category).label }}
                                                </span>
                                                <span v-else class="text-gray-400 text-sm">-</span>
                                            </td>

                                            <!-- Saúde do Estoque -->
                                            <td class="px-4 py-4 text-center">
                                                <span
                                                    v-if="product.analytics?.stock_health"
                                                    :class="[
                                                        'badge',
                                                        `badge-${getStockHealthStatus(product.analytics.stock_health).color}`
                                                    ]"
                                                >
                                                    {{ getStockHealthStatus(product.analytics.stock_health).label }}
                                                </span>
                                                <span v-else class="text-gray-400 text-sm">-</span>
                                            </td>

                                            <!-- Unidades Vendidas -->
                                            <td class="px-4 py-4 text-right text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ formatNumber(product.analytics?.units_sold || 0) }}
                                            </td>

                                            <!-- Taxa de Conversão -->
                                            <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                                {{ product.analytics?.conversion_rate ? formatPercentage(product.analytics.conversion_rate) : '-' }}
                                            </td>

                                            <!-- Porcentagem de Vendas -->
                                            <td class="px-4 py-4 text-right text-sm text-gray-600 dark:text-gray-300">
                                                {{ product.analytics?.sales_percentage ? formatPercentage(product.analytics.sales_percentage) : '-' }}
                                            </td>

                                            <!-- Total Vendido (Receita) -->
                                            <td class="px-4 py-4 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                {{ formatCurrency(product.analytics?.total_sold || 0) }}
                                            </td>

                                            <!-- Lucro Total -->
                                            <td class="px-4 py-4 text-right text-sm font-semibold text-success-600">
                                                {{ formatCurrency(product.analytics?.total_profit || 0) }}
                                            </td>

                                            <!-- Preço -->
                                            <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                                {{ formatCurrency(product.price || 0) }}
                                            </td>

                                            <!-- Preço Médio -->
                                            <td class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                                {{ product.analytics?.average_price ? formatCurrency(product.analytics.average_price) : '-' }}
                                            </td>

                                            <!-- Custo -->
                                            <td class="px-4 py-4 text-right text-sm text-gray-900 dark:text-gray-100">
                                                {{ formatCurrency(product.cost || 0) }}
                                            </td>

                                            <!-- Margem -->
                                            <td class="px-4 py-4 text-right text-sm font-medium">
                                                <span
                                                    v-if="product.analytics?.margin !== null && product.analytics?.margin !== undefined"
                                                    :class="getMarginColor(product.analytics.margin)"
                                                >
                                                    {{ formatPercentage(product.analytics.margin) }}
                                                </span>
                                                <span v-else class="text-gray-400">-</span>
                                            </td>

                                            <!-- Estoque -->
                                            <td class="px-4 py-4 text-right text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ formatNumber(product.stock || 0) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <div v-else class="text-center py-20">
                                <div class="relative inline-block mb-6">
                                    <div class="w-32 h-32 rounded-3xl bg-gradient-to-br from-primary-100 to-secondary-100 flex items-center justify-center">
                                        <CubeIcon class="w-16 h-16 text-primary-400" />
                                    </div>
                                    <div class="absolute -bottom-2 -right-2 w-8 h-8 rounded-full bg-accent-400 flex items-center justify-center">
                                        <SparklesIcon class="w-4 h-4 text-white" />
                                    </div>
                                </div>
                                <h3 class="text-2xl font-display font-bold text-gray-900 dark:text-gray-100 mb-3">
                                    Nenhum produto encontrado
                                </h3>
                                <p class="text-gray-500 mb-4">
                                    <template v-if="hasActiveFilters">
                                        Nenhum produto corresponde aos filtros selecionados
                                    </template>
                                    <template v-else-if="searchQuery">
                                        Tente uma busca diferente
                                    </template>
                                    <template v-else>
                                        Conecte sua loja para sincronizar produtos
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
                            <div v-if="totalPages > 1 || totalItems > 0" class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 sm:px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                                <div class="flex flex-col sm:flex-row items-center gap-3 sm:gap-4 w-full sm:w-auto">
                                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center sm:text-left" role="status" aria-live="polite">
                                        <span class="hidden sm:inline">Mostrando </span>{{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, totalItems) }} de {{ totalItems }}
                                    </p>
                                    <select
                                        :value="perPage"
                                        @change="changePerPage(Number($event.target.value))"
                                        class="px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 cursor-pointer"
                                    >
                                        <option v-for="option in perPageOptions" :key="option" :value="option">
                                            {{ option }}/pág
                                        </option>
                                    </select>
                                </div>
                                <nav class="flex items-center gap-2" role="navigation" aria-label="Paginação de produtos">
                                    <BaseButton
                                        variant="ghost"
                                        size="sm"
                                        :disabled="currentPage === 1"
                                        :aria-label="`Ir para página anterior`"
                                        @click="goToPage(currentPage - 1)"
                                    >
                                        <ChevronLeftIcon class="w-4 h-4" aria-hidden="true" />
                                    </BaseButton>
                                    <span class="text-sm text-gray-600 dark:text-gray-300 px-2 sm:px-3" aria-current="page">
                                        {{ currentPage }}/{{ totalPages }}
                                    </span>
                                    <BaseButton
                                        variant="ghost"
                                        size="sm"
                                        :disabled="currentPage === totalPages"
                                        :aria-label="`Ir para próxima página`"
                                        @click="goToPage(currentPage + 1)"
                                    >
                                        <ChevronRightIcon class="w-4 h-4" aria-hidden="true" />
                                    </BaseButton>
                                </nav>
                            </div>
                        </BaseCard>
                    </div>

                    <!-- Detail Panel -->
                    <ProductDetailPanel
                        v-if="showDetailPanel"
                        :product="selectedProduct"
                        @close="closeDetailPanel"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
