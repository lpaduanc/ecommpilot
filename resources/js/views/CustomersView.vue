<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import api from '../services/api';
import BaseCard from '../components/common/BaseCard.vue';
import BaseButton from '../components/common/BaseButton.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import LazyChart from '../components/dashboard/LazyChart.vue';
import { useFormatters } from '../composables/useFormatters';
import { useWhatsApp } from '../composables/useWhatsApp';
import {
    UsersIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    ChartBarIcon,
    ChevronDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    XMarkIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';

const { formatCurrency, formatDate } = useFormatters();
const { isBrazilianMobile, getWhatsAppLink } = useWhatsApp();

// Debounce
let searchDebounceTimer = null;
const DEBOUNCE_DELAY = 300;

function debounce(fn, delay) {
    return (...args) => {
        if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => fn(...args), delay);
    };
}

// State - listagem
const customers = ref([]);
const isLoading = ref(true);
const searchQuery = ref('');
const currentPage = ref(1);
const totalPages = ref(1);
const totalItems = ref(0);
const perPage = ref(10);

const perPageOptions = [10, 20, 50, 100];

// RFM Accordion
const isRfmAccordionOpen = ref(false);
const rfmSummary = ref(null);
const isLoadingRfm = ref(false);

// Filter options (from backend)
const filterOptions = ref({
    segments: [],
    orders_range: { min: 0, max: 0 },
    spent_range: { min: 0, max: 0 },
});

// Filters
const rfmSegmentFilter = ref('');
const firstOrderStart = ref('');
const firstOrderEnd = ref('');
const lastOrderStart = ref('');
const lastOrderEnd = ref('');
const daysWithoutPurchaseMin = ref('');
const daysWithoutPurchaseMax = ref('');
const minOrders = ref('');
const maxOrders = ref('');

// Computed: has active filters
const hasActiveFilters = computed(() => {
    return !!(
        searchQuery.value ||
        rfmSegmentFilter.value ||
        firstOrderStart.value ||
        firstOrderEnd.value ||
        lastOrderStart.value ||
        lastOrderEnd.value ||
        daysWithoutPurchaseMin.value ||
        daysWithoutPurchaseMax.value ||
        minOrders.value ||
        maxOrders.value
    );
});

function isDarkMode() {
    return document.documentElement.classList.contains('dark');
}

// Single source of truth for RFM segment colors.
// Ordered from best (cold tones) to worst (warm tones), following the RFM priority
// defined in docs/rfm-segments.md. The hex color drives both the badge tags and the
// chart series so they are always in sync regardless of the order the API returns data.
const RFM_COLOR_MAP = {
    'Campeões':           '#059669', // emerald-600  — top tier, cold green
    'Clientes Fiéis':     '#16a34a', // green-600
    'Potenciais Fiéis':   '#2563eb', // blue-600     — growth potential, cool blue
    'Novos Clientes':     '#0891b2', // cyan-600     — fresh, cyan
    'Promissores':        '#7c3aed', // violet-600   — neutral/promising, purple
    'Precisam de Atenção':'#d97706', // amber-600    — caution, warm amber
    'Quase Dormindo':     '#ea580c', // orange-600   — warning, orange
    'Em Risco':           '#dc2626', // red-600      — danger zone
    'Não Pode Perder':    '#991b1b', // red-800      — critical, deep red
    'Hibernando':         '#9f1239', // rose-800     — nearly lost, rose-red
    'Perdidos':           '#6b21a8', // purple-800   — lost cause, dark purple
};

const RFM_FALLBACK_COLOR = '#6b7280'; // gray-500

function getSegmentColor(segment) {
    return RFM_COLOR_MAP[segment] ?? RFM_FALLBACK_COLOR;
}

// Convert a hex color to an rgba string for badge backgrounds with reduced opacity.
function hexToRgba(hex, alpha) {
    const h = hex.replace('#', '');
    const r = parseInt(h.substring(0, 2), 16);
    const g = parseInt(h.substring(2, 4), 16);
    const b = parseInt(h.substring(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

// Returns inline style object for badge tags so color is always synced with charts.
function getSegmentBadgeStyle(segment) {
    const color = getSegmentColor(segment);
    const dark = isDarkMode();
    return {
        backgroundColor: hexToRgba(color, dark ? 0.25 : 0.12),
        color: dark ? lightenHex(color, 80) : darkenHex(color, 10),
        borderColor: hexToRgba(color, dark ? 0.45 : 0.25),
    };
}

// Lighten a hex color by mixing toward white (percent: 0–100).
function lightenHex(hex, percent) {
    const h = hex.replace('#', '');
    const num = parseInt(h, 16);
    const r = Math.min(255, (num >> 16) + Math.round((255 - (num >> 16)) * percent / 100));
    const g = Math.min(255, ((num >> 8) & 0xff) + Math.round((255 - ((num >> 8) & 0xff)) * percent / 100));
    const b = Math.min(255, (num & 0xff) + Math.round((255 - (num & 0xff)) * percent / 100));
    return `#${((1 << 24) | (r << 16) | (g << 8) | b).toString(16).slice(1)}`;
}

// Darken a hex color by mixing toward black (percent: 0–100).
function darkenHex(hex, percent) {
    const h = hex.replace('#', '');
    const num = parseInt(h, 16);
    const factor = 1 - percent / 100;
    const r = Math.round(((num >> 16) & 0xff) * factor);
    const g = Math.round(((num >> 8) & 0xff) * factor);
    const b = Math.round((num & 0xff) * factor);
    return `#${((1 << 24) | (r << 16) | (g << 8) | b).toString(16).slice(1)}`;
}

// Chart configs

// Resolve the chart color array from the labels returned by the API so the chart
// color always matches the badge color for the same segment, regardless of order.
function resolveChartColors(labels) {
    return labels.map(label => getSegmentColor(label));
}

const donutChartOptions = computed(() => {
    const dark = isDarkMode();
    const labels = rfmSummary.value?.segments_distribution?.map(s => s.segment) || [];
    const total = rfmSummary.value?.segments_distribution?.reduce((acc, s) => acc + s.count, 0) || 0;
    return {
        chart: {
            type: 'donut',
            fontFamily: 'inherit',
            background: 'transparent',
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 600,
            },
        },
        labels,
        colors: resolveChartColors(labels),
        stroke: {
            width: 0,
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '13px',
                            fontFamily: 'inherit',
                            color: dark ? '#9ca3af' : '#6b7280',
                            offsetY: -6,
                        },
                        value: {
                            show: true,
                            fontSize: '22px',
                            fontFamily: 'inherit',
                            fontWeight: 700,
                            color: dark ? '#f3f4f6' : '#111827',
                            offsetY: 6,
                            formatter: (val) => `${val}`,
                        },
                        total: {
                            show: true,
                            showAlways: true,
                            label: 'Total',
                            fontSize: '13px',
                            fontFamily: 'inherit',
                            color: dark ? '#9ca3af' : '#6b7280',
                            formatter: () => `${total}`,
                        },
                    },
                },
                expandOnClick: false,
            },
        },
        legend: {
            position: 'bottom',
            offsetY: 4,
            fontSize: '12px',
            fontFamily: 'inherit',
            itemMargin: { horizontal: 6, vertical: 3 },
            labels: {
                colors: dark ? '#d1d5db' : '#374151',
            },
            markers: {
                size: 8,
                shape: 'circle',
                offsetX: -2,
            },
        },
        tooltip: {
            theme: dark ? 'dark' : 'light',
            fillSeriesColor: false,
            style: { fontFamily: 'inherit', fontSize: '13px' },
            y: { formatter: (val) => `${val} clientes` },
        },
        dataLabels: { enabled: false },
        states: {
            hover: { filter: { type: 'brighten', value: 0.08 } },
            active: { filter: { type: 'darken', value: 0.15 } },
        },
    };
});

const donutChartSeries = computed(() =>
    rfmSummary.value?.segments_distribution?.map(s => s.count) || []
);

const barChartOptions = computed(() => {
    const dark = isDarkMode();
    const labels = rfmSummary.value?.monetary_by_segment?.map(s => s.segment) || [];
    return {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            fontFamily: 'inherit',
            background: 'transparent',
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 600,
            },
        },
        plotOptions: {
            bar: {
                horizontal: true,
                barHeight: '55%',
                borderRadius: 6,
                borderRadiusApplication: 'end',
                distributed: true,
            },
        },
        colors: resolveChartColors(labels),
        dataLabels: { enabled: false },
        xaxis: {
            labels: {
                formatter: (val) => {
                    if (val >= 1_000_000) return `R$ ${(val / 1_000_000).toFixed(1).replace('.', ',')}M`;
                    if (val >= 1_000) return `R$ ${(val / 1_000).toFixed(0)}k`;
                    return `R$ ${val}`;
                },
                style: {
                    colors: dark ? '#9ca3af' : '#6b7280',
                    fontSize: '11px',
                    fontFamily: 'inherit',
                },
                rotate: 0,
                hideOverlappingLabels: true,
            },
            tickAmount: 5,
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            labels: {
                style: {
                    colors: dark ? '#d1d5db' : '#374151',
                    fontSize: '12px',
                    fontFamily: 'inherit',
                },
                maxWidth: 150,
            },
        },
        tooltip: {
            theme: dark ? 'dark' : 'light',
            fillSeriesColor: false,
            style: { fontFamily: 'inherit', fontSize: '13px' },
            y: { formatter: (val) => formatCurrency(val) },
        },
        legend: { show: false },
        grid: {
            borderColor: dark ? '#1f2937' : '#f3f4f6',
            strokeDashArray: 3,
            padding: { right: 8 },
            xaxis: { lines: { show: true } },
            yaxis: { lines: { show: false } },
        },
        states: {
            hover: { filter: { type: 'brighten', value: 0.06 } },
            active: { filter: { type: 'darken', value: 0.12 } },
        },
    };
});

const barChartSeries = computed(() => [{
    name: 'Valor Total',
    data: rfmSummary.value?.monetary_by_segment?.map(s => ({
        x: s.segment,
        y: s.total_spent,
    })) || [],
}]);

// API calls
async function fetchCustomers() {
    isLoading.value = true;
    try {
        const params = {
            search: searchQuery.value || undefined,
            page: currentPage.value,
            per_page: perPage.value,
            rfm_segment: rfmSegmentFilter.value || undefined,
            first_order_start: firstOrderStart.value || undefined,
            first_order_end: firstOrderEnd.value || undefined,
            last_order_start: lastOrderStart.value || undefined,
            last_order_end: lastOrderEnd.value || undefined,
            days_without_purchase_min: daysWithoutPurchaseMin.value || undefined,
            days_without_purchase_max: daysWithoutPurchaseMax.value || undefined,
            min_orders: minOrders.value || undefined,
            max_orders: maxOrders.value || undefined,
        };
        const response = await api.get('/customers', { params });
        customers.value = response.data.data;
        totalItems.value = response.data.total;
        totalPages.value = response.data.last_page;
        currentPage.value = response.data.current_page;
    } catch (error) {
        console.error('Error fetching customers:', error);
        customers.value = [];
    } finally {
        isLoading.value = false;
    }
}

async function fetchFilterOptions() {
    try {
        const response = await api.get('/customers/filters');
        filterOptions.value = response.data;
    } catch (error) {
        console.error('Error fetching filter options:', error);
    }
}

async function fetchRfmSummary() {
    isLoadingRfm.value = true;
    try {
        const response = await api.get('/customers/rfm-summary');
        rfmSummary.value = response.data;
    } catch (error) {
        console.error('Error fetching RFM summary:', error);
    } finally {
        isLoadingRfm.value = false;
    }
}

// Interaction helpers
const debouncedSearch = debounce(() => {
    currentPage.value = 1;
    fetchCustomers();
}, DEBOUNCE_DELAY);

function handleSearchInput() {
    debouncedSearch();
}

function handleSearch() {
    currentPage.value = 1;
    fetchCustomers();
}

function handleFilterChange() {
    currentPage.value = 1;
    fetchCustomers();
}

function goToPage(page) {
    if (page < 1 || page > totalPages.value) return;
    currentPage.value = page;
    fetchCustomers();
}

function changePerPage(newPerPage) {
    perPage.value = newPerPage;
    currentPage.value = 1;
    fetchCustomers();
}

function clearFilters() {
    searchQuery.value = '';
    rfmSegmentFilter.value = '';
    firstOrderStart.value = '';
    firstOrderEnd.value = '';
    lastOrderStart.value = '';
    lastOrderEnd.value = '';
    daysWithoutPurchaseMin.value = '';
    daysWithoutPurchaseMax.value = '';
    minOrders.value = '';
    maxOrders.value = '';
    currentPage.value = 1;
    fetchCustomers();
}

function toggleRfmAccordion() {
    isRfmAccordionOpen.value = !isRfmAccordionOpen.value;
    if (isRfmAccordionOpen.value && !rfmSummary.value) {
        fetchRfmSummary();
    }
}

// Lifecycle
onMounted(() => {
    fetchCustomers();
    fetchFilterOptions();
});

onUnmounted(() => {
    if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
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
                                <UsersIcon class="w-5 sm:w-6 lg:w-7 h-5 sm:h-6 lg:h-7 text-white" />
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-xl sm:text-2xl lg:text-4xl font-display font-bold text-white dark:text-gray-100 truncate">
                                    Clientes
                                </h1>
                                <p class="text-primary-200/80 dark:text-gray-400 text-xs sm:text-sm lg:text-base">
                                    {{ totalItems }} clientes sincronizados
                                </p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="py-4 sm:py-6 lg:py-8 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">
            <div class="w-full space-y-4 sm:space-y-6">

                <!-- RFM Accordion -->
                <BaseCard padding="none" class="overflow-hidden">
                    <!-- Accordion Header -->
                    <button
                        type="button"
                        @click="toggleRfmAccordion"
                        class="w-full flex items-center justify-between gap-3 px-5 py-4 sm:px-6 sm:py-5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-inset"
                        :aria-expanded="isRfmAccordionOpen"
                        aria-controls="rfm-content"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30 flex items-center justify-center flex-shrink-0">
                                <ChartBarIcon class="w-4 h-4 sm:w-5 sm:h-5 text-primary-600 dark:text-primary-400" />
                            </div>
                            <span class="text-sm sm:text-base font-semibold text-gray-900 dark:text-gray-100">
                                Clique aqui para visualizar a Análise RFM
                            </span>
                        </div>
                        <ChevronDownIcon
                            class="w-5 h-5 text-gray-400 dark:text-gray-500 transition-transform duration-300 flex-shrink-0"
                            :class="{ 'rotate-180': isRfmAccordionOpen }"
                            aria-hidden="true"
                        />
                    </button>

                    <!-- Accordion Content -->
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-[800px]"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 max-h-[800px]"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <div
                            v-show="isRfmAccordionOpen"
                            id="rfm-content"
                            class="overflow-hidden border-t border-gray-100 dark:border-gray-700"
                        >
                            <div class="p-5 sm:p-6">
                                <!-- Charts Grid -->
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- Donut Chart: Segment Distribution (left) -->
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                            Distribuição de Segmentos
                                        </h3>
                                        <LazyChart
                                            type="donut"
                                            :height="320"
                                            :options="donutChartOptions"
                                            :series="donutChartSeries"
                                            :loading="isLoadingRfm"
                                            loadingMessage="Carregando distribuição..."
                                            emptyMessage="Sem dados de distribuição"
                                        />
                                    </div>

                                    <!-- Horizontal Bar Chart: Monetary by Segment (right) -->
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                            Valor Total por Segmento
                                        </h3>
                                        <LazyChart
                                            type="bar"
                                            :height="320"
                                            :options="barChartOptions"
                                            :series="barChartSeries"
                                            :loading="isLoadingRfm"
                                            loadingMessage="Carregando análise RFM..."
                                            emptyMessage="Sem dados de segmentação RFM"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </BaseCard>

                <!-- Filters Section -->
                <BaseCard class="mb-0">
                    <div class="flex flex-col sm:flex-row items-start gap-3 sm:gap-4">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30 flex items-center justify-center flex-shrink-0">
                            <FunnelIcon class="w-5 h-5 sm:w-6 sm:h-6 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div class="flex-1 w-full min-w-0">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                                <div>
                                    <h3 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-gray-100 mb-0.5 sm:mb-1">Filtros</h3>
                                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Filtre clientes por segmento RFM, datas e comportamento de compra</p>
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

                            <!-- Filters Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                                <!-- Name Search -->
                                <div class="sm:col-span-2 lg:col-span-1">
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Nome</label>
                                    <div class="relative">
                                        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" aria-hidden="true" />
                                        <input
                                            v-model="searchQuery"
                                            @input="handleSearchInput"
                                            @keyup.enter="handleSearch"
                                            type="search"
                                            placeholder="Buscar por nome, email ou telefone..."
                                            class="w-full pl-9 pr-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 placeholder-gray-400 dark:placeholder-gray-500"
                                        />
                                    </div>
                                </div>

                                <!-- RFM Segment -->
                                <div class="sm:col-span-2 lg:col-span-1">
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Segmento RFM</label>
                                    <select
                                        v-model="rfmSegmentFilter"
                                        @change="handleFilterChange"
                                        class="w-full px-3 py-2.5 pr-8 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 appearance-none cursor-pointer"
                                        style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236B7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0.5rem center; background-size: 1.5em 1.5em; background-repeat: no-repeat;"
                                    >
                                        <option value="">Todos os Segmentos</option>
                                        <option
                                            v-for="segment in filterOptions.segments"
                                            :key="segment"
                                            :value="segment"
                                        >
                                            {{ segment }}
                                        </option>
                                    </select>
                                </div>

                                <!-- First Order Range -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Primeira Compra - De</label>
                                    <input
                                        v-model="firstOrderStart"
                                        type="date"
                                        @change="handleFilterChange"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Primeira Compra - Até</label>
                                    <input
                                        v-model="firstOrderEnd"
                                        type="date"
                                        @change="handleFilterChange"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    />
                                </div>

                                <!-- Last Order Range -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Última Compra - De</label>
                                    <input
                                        v-model="lastOrderStart"
                                        type="date"
                                        @change="handleFilterChange"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Última Compra - Até</label>
                                    <input
                                        v-model="lastOrderEnd"
                                        type="date"
                                        @change="handleFilterChange"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                    />
                                </div>

                                <!-- Days Without Purchase -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Dias sem Comprar - Mín</label>
                                    <input
                                        v-model="daysWithoutPurchaseMin"
                                        type="number"
                                        min="0"
                                        placeholder="0"
                                        @change="handleFilterChange"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 placeholder-gray-400 dark:placeholder-gray-500"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Dias sem Comprar - Máx</label>
                                    <input
                                        v-model="daysWithoutPurchaseMax"
                                        type="number"
                                        min="0"
                                        placeholder="365"
                                        @change="handleFilterChange"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 placeholder-gray-400 dark:placeholder-gray-500"
                                    />
                                </div>

                                <!-- Orders Range -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Pedidos - Mín</label>
                                    <input
                                        v-model="minOrders"
                                        type="number"
                                        min="0"
                                        placeholder="0"
                                        @change="handleFilterChange"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 placeholder-gray-400 dark:placeholder-gray-500"
                                    />
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Pedidos - Máx</label>
                                    <input
                                        v-model="maxOrders"
                                        type="number"
                                        min="0"
                                        placeholder="999"
                                        @change="handleFilterChange"
                                        class="w-full px-3 py-2.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 placeholder-gray-400 dark:placeholder-gray-500"
                                    />
                                </div>

                                <!-- Per Page Selector -->
                                <div class="sm:col-span-2 lg:col-span-1 flex items-end">
                                    <div class="w-full">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Registros por página</label>
                                        <select
                                            :value="perPage"
                                            @change="changePerPage(Number($event.target.value))"
                                            class="w-full px-3 py-2.5 pr-8 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 appearance-none cursor-pointer"
                                            style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236B7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0.5rem center; background-size: 1.5em 1.5em; background-repeat: no-repeat;"
                                        >
                                            <option v-for="option in perPageOptions" :key="option" :value="option">
                                                {{ option }} por página
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Clear Filters Button -->
                                <div v-if="hasActiveFilters" class="sm:col-span-2 lg:col-span-4 xl:col-span-4 pt-2 border-t border-gray-100 dark:border-gray-700">
                                    <button
                                        type="button"
                                        @click="clearFilters"
                                        class="flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium transition-all"
                                    >
                                        <XMarkIcon class="w-4 h-4" />
                                        Limpar Todos os Filtros
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </BaseCard>

                <!-- Customers Table -->
                <BaseCard padding="none" class="overflow-hidden mt-2 sm:mt-4">
                    <!-- Loading -->
                    <div v-if="isLoading" class="flex items-center justify-center py-20">
                        <div class="relative">
                            <div class="w-16 h-16 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500"></div>
                            <LoadingSpinner size="lg" class="absolute inset-0 m-auto text-white" />
                        </div>
                    </div>

                    <template v-else>
                        <!-- Mobile/Tablet Cards -->
                        <div v-if="customers.length > 0" class="xl:hidden">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                                <div
                                    v-for="customer in customers"
                                    :key="'card-' + customer.id"
                                    class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-lg hover:border-primary-300 dark:hover:border-primary-600 transition-all duration-200"
                                >
                                    <!-- Header: Name + Segment -->
                                    <div class="flex items-start justify-between gap-2 mb-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="font-semibold text-gray-900 dark:text-gray-100 text-sm truncate">
                                                {{ customer.name || 'Cliente sem nome' }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                                {{ customer.email || '-' }}
                                            </p>
                                        </div>
                                        <span
                                            v-if="customer.rfm_segment"
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0 border"
                                            :style="getSegmentBadgeStyle(customer.rfm_segment)"
                                        >
                                            {{ customer.rfm_segment }}
                                        </span>
                                        <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 flex-shrink-0">
                                            Sem segmento
                                        </span>
                                    </div>

                                    <!-- Phone -->
                                    <div v-if="customer.phone" class="flex items-center gap-1.5 mb-3">
                                        <a
                                            v-if="isBrazilianMobile(customer.phone)"
                                            :href="getWhatsAppLink(customer.phone)"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            title="Abrir no WhatsApp"
                                            class="flex-shrink-0 opacity-70 hover:opacity-100 transition-opacity duration-150"
                                            @click.stop
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="#25D366" aria-hidden="true">
                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                            </svg>
                                        </a>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ customer.phone }}</span>
                                    </div>

                                    <!-- Stats Grid -->
                                    <div class="grid grid-cols-2 gap-2 text-sm mb-3">
                                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Pedidos</p>
                                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ customer.total_orders ?? 0 }}</p>
                                        </div>
                                        <div class="bg-success-50 dark:bg-success-900/30 rounded-lg p-2.5">
                                            <p class="text-xs text-success-600 dark:text-success-400">Total Gasto</p>
                                            <p class="font-semibold text-success-700 dark:text-success-300">{{ formatCurrency(customer.total_spent ?? 0) }}</p>
                                        </div>
                                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Dias sem comprar</p>
                                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ customer.days_without_purchase ?? '-' }}</p>
                                        </div>
                                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Última Compra</p>
                                            <p class="font-semibold text-gray-900 dark:text-gray-100 text-xs">
                                                {{ customer.last_order_at ? formatDate(customer.last_order_at) : '-' }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Footer: First Order Date -->
                                    <div class="pt-2 border-t border-gray-100 dark:border-gray-700 flex justify-between items-center">
                                        <span class="text-xs text-gray-500 dark:text-gray-400">Primeira compra</span>
                                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                            {{ customer.first_order_at ? formatDate(customer.first_order_at) : '-' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Desktop Table -->
                        <div v-if="customers.length > 0" class="hidden xl:block overflow-x-auto">
                            <table class="w-full table-fixed">
                                <colgroup>
                                    <col style="width: 200px;"><!-- Nome -->
                                    <col style="width: 220px;"><!-- Email -->
                                    <col style="width: 130px;"><!-- Telefone -->
                                    <col style="width: 150px;"><!-- Segmento RFM -->
                                    <col style="width: 120px;"><!-- Primeira Compra -->
                                    <col style="width: 120px;"><!-- Última Compra -->
                                    <col style="width: 130px;"><!-- Dias sem Comprar -->
                                    <col style="width: 90px;"> <!-- Pedidos -->
                                    <col style="width: 140px;"><!-- Total Vendido -->
                                </colgroup>
                                <thead class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10">
                                    <tr>
                                        <th class="text-left px-5 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                            Nome
                                        </th>
                                        <th class="text-left px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                            Email
                                        </th>
                                        <th class="text-left px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                            Telefone
                                        </th>
                                        <th class="text-center px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                            Segmento RFM
                                        </th>
                                        <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                            Primeira Compra
                                        </th>
                                        <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                            Última Compra
                                        </th>
                                        <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                            Dias sem Comprar
                                        </th>
                                        <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                            Pedidos
                                        </th>
                                        <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">
                                            Total Vendido
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr
                                        v-for="customer in customers"
                                        :key="customer.id"
                                        class="hover:bg-gradient-to-r hover:from-primary-50/50 dark:hover:from-primary-900/30 hover:to-transparent transition-all duration-200"
                                    >
                                        <!-- Nome -->
                                        <td class="px-5 py-4">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                                {{ customer.name || 'Sem nome' }}
                                            </p>
                                        </td>

                                        <!-- Email -->
                                        <td class="px-4 py-4">
                                            <p class="text-sm text-gray-600 dark:text-gray-300 truncate">
                                                {{ customer.email || '-' }}
                                            </p>
                                        </td>

                                        <!-- Telefone -->
                                        <td class="px-4 py-4">
                                            <div v-if="customer.phone" class="flex items-center gap-1.5">
                                                <a
                                                    v-if="isBrazilianMobile(customer.phone)"
                                                    :href="getWhatsAppLink(customer.phone)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    title="Abrir no WhatsApp"
                                                    class="flex-shrink-0 opacity-70 hover:opacity-100 transition-opacity duration-150"
                                                    @click.stop
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="#25D366" aria-hidden="true">
                                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                                    </svg>
                                                </a>
                                                <span class="text-sm text-gray-600 dark:text-gray-300">{{ customer.phone }}</span>
                                            </div>
                                            <span v-else class="text-sm text-gray-400">-</span>
                                        </td>

                                        <!-- Segmento RFM -->
                                        <td class="px-4 py-4 text-center">
                                            <span
                                                v-if="customer.rfm_segment"
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border"
                                                :style="getSegmentBadgeStyle(customer.rfm_segment)"
                                            >
                                                {{ customer.rfm_segment }}
                                            </span>
                                            <span v-else class="text-gray-400 dark:text-gray-500 text-sm">-</span>
                                        </td>

                                        <!-- Primeira Compra -->
                                        <td class="px-4 py-4 text-right text-sm text-gray-600 dark:text-gray-300">
                                            {{ customer.first_order_at ? formatDate(customer.first_order_at) : '-' }}
                                        </td>

                                        <!-- Última Compra -->
                                        <td class="px-4 py-4 text-right text-sm text-gray-600 dark:text-gray-300">
                                            {{ customer.last_order_at ? formatDate(customer.last_order_at) : '-' }}
                                        </td>

                                        <!-- Dias sem Comprar -->
                                        <td class="px-4 py-4 text-right">
                                            <span
                                                v-if="customer.days_without_purchase !== null && customer.days_without_purchase !== undefined"
                                                :class="[
                                                    'text-sm font-medium',
                                                    customer.days_without_purchase > 180 ? 'text-danger-600 dark:text-danger-400' :
                                                    customer.days_without_purchase > 90 ? 'text-warning-600 dark:text-warning-400' :
                                                    'text-gray-900 dark:text-gray-100'
                                                ]"
                                            >
                                                {{ customer.days_without_purchase }}
                                            </span>
                                            <span v-else class="text-gray-400 dark:text-gray-500 text-sm">-</span>
                                        </td>

                                        <!-- Pedidos -->
                                        <td class="px-4 py-4 text-right text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ customer.total_orders ?? 0 }}
                                        </td>

                                        <!-- Total Vendido -->
                                        <td class="px-4 py-4 text-right text-sm font-semibold text-success-600 dark:text-success-400">
                                            {{ formatCurrency(customer.total_spent ?? 0) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Empty State -->
                        <div v-if="customers.length === 0" class="text-center py-20">
                            <div class="relative inline-block mb-6">
                                <div class="w-32 h-32 rounded-3xl bg-gradient-to-br from-primary-100 to-secondary-100 dark:from-primary-900/30 dark:to-secondary-900/30 flex items-center justify-center">
                                    <UsersIcon class="w-16 h-16 text-primary-400 dark:text-primary-500" />
                                </div>
                                <div class="absolute -bottom-2 -right-2 w-8 h-8 rounded-full bg-accent-400 flex items-center justify-center">
                                    <SparklesIcon class="w-4 h-4 text-white" />
                                </div>
                            </div>
                            <h3 class="text-2xl font-display font-bold text-gray-900 dark:text-gray-100 mb-3">
                                Nenhum cliente encontrado
                            </h3>
                            <p class="text-gray-500 dark:text-gray-400 mb-4">
                                <template v-if="hasActiveFilters || searchQuery">
                                    Nenhum cliente corresponde aos filtros selecionados
                                </template>
                                <template v-else>
                                    Conecte sua loja para sincronizar os dados de clientes
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
                        <div
                            v-if="totalItems > 0"
                            class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 sm:px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50"
                        >
                            <p class="text-sm text-gray-500 dark:text-gray-400 text-center sm:text-left" role="status" aria-live="polite">
                                <span class="hidden sm:inline">Mostrando </span>{{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, totalItems) }} de {{ totalItems }} resultados
                            </p>
                            <nav class="flex items-center gap-2" role="navigation" aria-label="Paginação de clientes">
                                <BaseButton
                                    variant="ghost"
                                    size="sm"
                                    :disabled="currentPage === 1"
                                    aria-label="Ir para página anterior"
                                    @click="goToPage(currentPage - 1)"
                                >
                                    <ChevronLeftIcon class="w-4 h-4" aria-hidden="true" />
                                </BaseButton>

                                <!-- Page numbers (up to 5 visible) -->
                                <template v-for="page in totalPages" :key="page">
                                    <button
                                        v-if="page === 1 || page === totalPages || (page >= currentPage - 2 && page <= currentPage + 2)"
                                        type="button"
                                        @click="goToPage(page)"
                                        :class="[
                                            'w-8 h-8 rounded-lg text-sm font-medium transition-all',
                                            page === currentPage
                                                ? 'bg-primary-500 text-white shadow-sm'
                                                : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'
                                        ]"
                                        :aria-label="`Ir para página ${page}`"
                                        :aria-current="page === currentPage ? 'page' : undefined"
                                    >
                                        {{ page }}
                                    </button>
                                    <span
                                        v-else-if="(page === currentPage - 3 && currentPage > 4) || (page === currentPage + 3 && currentPage < totalPages - 3)"
                                        class="text-gray-400 dark:text-gray-500 px-1"
                                    >
                                        ...
                                    </span>
                                </template>

                                <BaseButton
                                    variant="ghost"
                                    size="sm"
                                    :disabled="currentPage === totalPages"
                                    aria-label="Ir para próxima página"
                                    @click="goToPage(currentPage + 1)"
                                >
                                    <ChevronRightIcon class="w-4 h-4" aria-hidden="true" />
                                </BaseButton>
                            </nav>
                        </div>
                    </template>
                </BaseCard>

            </div>
        </div>
    </div>
</template>
