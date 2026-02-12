<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import api from '../services/api';
import BaseCard from '../components/common/BaseCard.vue';
import BaseButton from '../components/common/BaseButton.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import { useFormatters } from '../composables/useFormatters';
import {
    CurrencyDollarIcon,
    MagnifyingGlassIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    XMarkIcon,
    DocumentTextIcon,
    MapPinIcon,
    UserIcon,
    SparklesIcon,
    ArrowDownTrayIcon,
    FunnelIcon,
    ChartBarIcon,
    CalendarIcon,
    ChevronDownIcon,
} from '@heroicons/vue/24/outline';

const { formatCurrency } = useFormatters();

const orders = ref([]);
const isLoading = ref(false);
const searchQuery = ref('');
const currentPage = ref(1);
const totalPages = ref(1);
const totalItems = ref(0);
const perPage = ref(10);

// Filters
const statusFilter = ref('');
const couponFilter = ref('');
const countryFilter = ref('');
const stateFilter = ref('');
const cityFilter = ref('');

// Period filter state
const showPeriodDropdown = ref(false);
const selectedPeriod = ref(null); // null = sem filtro de data
const customStartDate = ref('');
const customEndDate = ref('');

const periodOptions = [
    { value: 'today', label: 'Hoje' },
    { value: 'last_7_days', label: 'Últimos 7 dias' },
    { value: 'last_15_days', label: 'Últimos 15 dias' },
    { value: 'last_30_days', label: 'Últimos 30 dias' },
    { value: 'this_month', label: 'Este mês' },
    { value: 'last_month', label: 'Último mês' },
    { value: 'custom', label: 'Personalizado' },
];

// Filter options from API
const filterOptions = ref({
    payment_statuses: [],
    coupons: [],
    countries: [],
    states: [],
    cities: [],
});

// Loading states for dynamic selects
const isLoadingStates = ref(false);
const isLoadingCities = ref(false);

// Order detail modal
const showDetailModal = ref(false);
const selectedOrder = ref(null);

const statusLabels = {
    pending: 'Pendente',
    paid: 'Pago',
    refunded: 'Reembolsado',
    voided: 'Recusado',
    failed: 'Falhou',
    cancelled: 'Cancelado',
};

const perPageOptions = [10, 20, 50, 100];

async function fetchOrders() {
    isLoading.value = true;
    try {
        const params = {
            search: searchQuery.value,
            payment_status: statusFilter.value,
            coupon: couponFilter.value,
            country: countryFilter.value,
            state: stateFilter.value,
            city: cityFilter.value,
            page: currentPage.value,
            per_page: perPage.value,
        };

        // Adicionar filtros de data apenas se um período foi selecionado
        if (selectedPeriod.value) {
            if (selectedPeriod.value === 'custom') {
                if (customStartDate.value) params.start_date = customStartDate.value;
                if (customEndDate.value) params.end_date = customEndDate.value;
            } else {
                const { startDate, endDate } = getDateRange(selectedPeriod.value);
                if (startDate) params.start_date = startDate;
                if (endDate) params.end_date = endDate;
            }
        }

        const response = await api.get('/orders', { params });
        orders.value = response.data.data;
        totalPages.value = response.data.last_page;
        totalItems.value = response.data.total;
    } catch (err) {
        orders.value = [];
    } finally {
        isLoading.value = false;
    }
}

async function fetchFilterOptions() {
    try {
        const response = await api.get('/orders/filters');
        filterOptions.value = response.data;
    } catch {
        // Keep default empty arrays
    }
}

async function fetchStates() {
    isLoadingStates.value = true;
    try {
        const response = await api.get('/locations/states');
        filterOptions.value.states = response.data.data || response.data || [];
    } catch {
        filterOptions.value.states = [];
    } finally {
        isLoadingStates.value = false;
    }
}

async function fetchCities(uf) {
    if (!uf) {
        filterOptions.value.cities = [];
        return;
    }

    isLoadingCities.value = true;
    try {
        const response = await api.get(`/locations/cities/${uf}`);
        filterOptions.value.cities = response.data.data || response.data || [];
    } catch {
        filterOptions.value.cities = [];
    } finally {
        isLoadingCities.value = false;
    }
}

function handleSearch() {
    currentPage.value = 1;
    fetchOrders();
}

function handleFilterChange() {
    currentPage.value = 1;
    fetchOrders();
}

async function handleStateChange() {
    // Limpar cidade selecionada ao trocar de estado
    cityFilter.value = '';

    // Carregar cidades do novo estado
    if (stateFilter.value) {
        await fetchCities(stateFilter.value);
    } else {
        filterOptions.value.cities = [];
    }

    // Aplicar filtros
    handleFilterChange();
}

function clearFilters() {
    statusFilter.value = '';
    couponFilter.value = '';
    countryFilter.value = '';
    stateFilter.value = '';
    cityFilter.value = '';
    searchQuery.value = '';
    selectedPeriod.value = null;
    customStartDate.value = '';
    customEndDate.value = '';
    filterOptions.value.cities = [];
    currentPage.value = 1;
    fetchOrders();
}

function goToPage(page) {
    if (page < 1 || page > totalPages.value) return;
    currentPage.value = page;
    fetchOrders();
}

function changePerPage(newPerPage) {
    perPage.value = newPerPage;
    currentPage.value = 1;
    fetchOrders();
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('pt-BR');
}

function getStatusConfig(status) {
    const configs = {
        pending: { label: 'Pendente', color: 'warning' },
        paid: { label: 'Pago', color: 'success' },
        shipped: { label: 'Enviado', color: 'primary' },
        delivered: { label: 'Entregue', color: 'success' },
        cancelled: { label: 'Cancelado', color: 'danger' },
        open: { label: 'Aberto', color: 'warning' },
        closed: { label: 'Fechado', color: 'success' },
    };
    return configs[status] || { label: status, color: 'gray' };
}

function getPaymentStatusConfig(status) {
    const configs = {
        pending: { label: 'Pendente', color: 'warning' },
        paid: { label: 'Pago', color: 'success' },
        refunded: { label: 'Reembolsado', color: 'gray' },
        voided: { label: 'Recusado', color: 'danger' },
        failed: { label: 'Falhou', color: 'danger' },
        cancelled: { label: 'Cancelado', color: 'danger' },
    };
    return configs[status] || { label: status, color: 'gray' };
}

function getPaymentMethodLabel(method) {
    const labels = {
        pix: 'PIX',
        credit_card: 'Cartão de Crédito',
        debit_card: 'Cartão de Débito',
        boleto: 'Boleto',
    };
    return labels[method] || method;
}

function getMarginColor(margin) {
    if (margin === null || margin === undefined) return 'text-gray-400';
    if (margin >= 30) return 'text-success-600';
    if (margin >= 15) return 'text-warning-600';
    return 'text-danger-600';
}

function formatPhone(phone) {
    if (!phone) return null;
    return phone.replace(/\D/g, '');
}

function getWhatsAppLink(phone) {
    const cleanPhone = formatPhone(phone);
    if (!cleanPhone) return null;
    const phoneWithCode = cleanPhone.startsWith('55') ? cleanPhone : `55${cleanPhone}`;
    return `https://wa.me/${phoneWithCode}`;
}

function viewOrderDetail(order) {
    selectedOrder.value = order;
    showDetailModal.value = true;
}

async function exportOrders() {
    try {
        const params = new URLSearchParams();
        if (searchQuery.value) params.append('search', searchQuery.value);
        if (statusFilter.value) params.append('payment_status', statusFilter.value);
        if (couponFilter.value) params.append('coupon', couponFilter.value);
        if (countryFilter.value) params.append('country', countryFilter.value);
        if (stateFilter.value) params.append('state', stateFilter.value);
        if (cityFilter.value) params.append('city', cityFilter.value);

        // Adicionar filtros de data
        if (selectedPeriod.value) {
            if (selectedPeriod.value === 'custom') {
                if (customStartDate.value) params.append('start_date', customStartDate.value);
                if (customEndDate.value) params.append('end_date', customEndDate.value);
            } else {
                const { startDate, endDate } = getDateRange(selectedPeriod.value);
                if (startDate) params.append('start_date', startDate);
                if (endDate) params.append('end_date', endDate);
            }
        }

        const url = `/orders/export?${params.toString()}`;
        const response = await api.get(url, { responseType: 'blob' });

        const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `pedidos_${new Date().toISOString().split('T')[0]}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    } catch (error) {
        console.error('Erro ao exportar pedidos:', error);
    }
}

const visiblePages = computed(() => {
    const pages = [];
    const maxVisible = 5;
    let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages.value, start + maxVisible - 1);

    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    return pages;
});

const hasActiveFilters = computed(() => {
    return statusFilter.value || couponFilter.value || countryFilter.value || stateFilter.value || cityFilter.value || selectedPeriod.value;
});

const currentPeriodLabel = computed(() => {
    if (!selectedPeriod.value) return 'Período';
    if (selectedPeriod.value === 'custom' && customStartDate.value && customEndDate.value) {
        // Usar T00:00:00 para evitar problema de timezone (UTC vs local)
        const start = new Date(customStartDate.value + 'T00:00:00');
        const end = new Date(customEndDate.value + 'T00:00:00');
        return `${start.toLocaleDateString('pt-BR')} - ${end.toLocaleDateString('pt-BR')}`;
    }
    const option = periodOptions.find(o => o.value === selectedPeriod.value);
    return option?.label || 'Período';
});

const isCustomPeriod = computed(() => selectedPeriod.value === 'custom');

function getDateRange(period) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    let startDate = null;
    let endDate = new Date(today);
    endDate.setHours(23, 59, 59, 999);

    switch (period) {
        case 'today':
            startDate = new Date(today);
            break;
        case 'last_7_days':
            startDate = new Date(today);
            startDate.setDate(startDate.getDate() - 6);
            break;
        case 'last_15_days':
            startDate = new Date(today);
            startDate.setDate(startDate.getDate() - 14);
            break;
        case 'last_30_days':
            startDate = new Date(today);
            startDate.setDate(startDate.getDate() - 29);
            break;
        case 'this_month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            break;
        case 'last_month':
            startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            endDate = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
        default:
            return { startDate: null, endDate: null };
    }

    return {
        startDate: startDate.toISOString().split('T')[0],
        endDate: endDate.toISOString().split('T')[0],
    };
}

function selectPeriod(period) {
    selectedPeriod.value = period;
    if (period !== 'custom') {
        showPeriodDropdown.value = false;
        currentPage.value = 1;
        fetchOrders();
    }
}

function applyCustomPeriod() {
    if (customStartDate.value && customEndDate.value) {
        showPeriodDropdown.value = false;
        currentPage.value = 1;
        fetchOrders();
    }
}

function clearPeriodFilter() {
    selectedPeriod.value = null;
    customStartDate.value = '';
    customEndDate.value = '';
    showPeriodDropdown.value = false;
    currentPage.value = 1;
    fetchOrders();
}

function handleClickOutside(event) {
    const dropdown = document.querySelector('[class*="fixed w-80 rounded-2xl"]');
    const button = event.target.closest('button');
    if (showPeriodDropdown.value && dropdown && !dropdown.contains(event.target) && (!button || !button.textContent.includes(currentPeriodLabel.value))) {
        showPeriodDropdown.value = false;
    }
}

onMounted(() => {
    fetchOrders();
    fetchFilterOptions();
    fetchStates();
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="min-h-screen">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-4 sm:px-6 lg:px-8 py-6 sm:py-8 lg:py-12 -mx-4 sm:-mx-6 lg:-mx-8 -mt-4 sm:-mt-6 lg:-mt-8">
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
                                <CurrencyDollarIcon class="w-7 h-7 text-white" />
                            </div>
                            <div>
                                <h1 class="text-3xl lg:text-4xl font-display font-bold text-white dark:text-gray-100">
                                    Pedidos
                                </h1>
                                <p class="text-primary-200/80 dark:text-gray-400 text-sm lg:text-base">
                                    {{ totalItems }} pedidos sincronizados
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Search Bar -->
                    <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                        <div class="relative flex-1 min-w-[200px] max-w-full sm:max-w-md">
                            <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="searchQuery"
                                @keyup.enter="handleSearch"
                                type="search"
                                placeholder="Buscar por pedido, cliente ou email..."
                                class="w-full pl-12 pr-4 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white placeholder-white/60 focus:bg-white/20 focus:border-white/30 focus:ring-2 focus:ring-primary-500/50 focus:outline-none transition-all"
                            />
                        </div>

                        <!-- Period Selector -->
                        <div class="relative">
                            <button
                                @click.stop="showPeriodDropdown = !showPeriodDropdown"
                                type="button"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 rounded-xl backdrop-blur-sm border font-medium transition-all focus:outline-none focus:ring-2 focus:ring-white/50',
                                    selectedPeriod
                                        ? 'bg-primary-500/30 border-primary-400/50 text-white'
                                        : 'bg-white/10 border-white/20 text-white hover:bg-white/20'
                                ]"
                            >
                                <CalendarIcon class="w-5 h-5" />
                                <span class="hidden sm:inline">{{ currentPeriodLabel }}</span>
                                <ChevronDownIcon class="w-4 h-4" />
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
                                        class="fixed w-80 rounded-2xl bg-white dark:bg-gray-800 shadow-xl ring-1 ring-black/5 dark:ring-white/10 z-[9999] p-4"
                                        style="top: 120px; right: 200px;"
                                    >
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Período</h3>
                                            <button @click="showPeriodDropdown = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                <XMarkIcon class="w-5 h-5" />
                                            </button>
                                        </div>

                                        <!-- Clear Period Button -->
                                        <button
                                            v-if="selectedPeriod"
                                            @click="clearPeriodFilter"
                                            class="w-full mb-3 px-3 py-2 rounded-lg text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                                        >
                                            Limpar período
                                        </button>

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

                        <button
                            @click="exportOrders"
                            type="button"
                            class="px-6 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white font-medium hover:bg-white/20 transition-all focus:outline-none focus:ring-2 focus:ring-white/50 flex items-center gap-2"
                        >
                            <ArrowDownTrayIcon class="w-5 h-5" />
                            <span class="hidden lg:inline">Baixar</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="py-4 sm:py-6 lg:py-8 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">
            <div class="w-full">
                <!-- Filters Section -->
                <BaseCard v-if="!isLoading" class="mb-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center flex-shrink-0">
                            <FunnelIcon class="w-6 h-6 text-primary-600" />
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Filtros</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Filtre os pedidos por status, localização ou cupom</p>
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

                            <!-- Filters Row -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:flex xl:flex-wrap items-center gap-3">
                                <!-- Status Filter -->
                                <select
                                    v-model="statusFilter"
                                    @change="handleFilterChange"
                                    class="px-4 py-2.5 pr-10 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 appearance-none bg-no-repeat bg-right cursor-pointer"
                                    style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236B7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0.5rem center; background-size: 1.5em 1.5em;"
                                >
                                    <option value="">Todos os Status</option>
                                    <option v-for="status in filterOptions.payment_statuses" :key="status.value" :value="status.value">
                                        {{ status.label }}
                                    </option>
                                </select>

                                <!-- Coupon Filter -->
                                <select
                                    v-model="couponFilter"
                                    @change="handleFilterChange"
                                    class="px-4 py-2.5 pr-10 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 appearance-none bg-no-repeat bg-right cursor-pointer"
                                    style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236B7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0.5rem center; background-size: 1.5em 1.5em;"
                                >
                                    <option value="">Cupom de Desconto</option>
                                    <option v-for="coupon in filterOptions.coupons" :key="coupon" :value="coupon">
                                        {{ coupon }}
                                    </option>
                                </select>

                                <!-- Country Filter -->
                                <select
                                    v-model="countryFilter"
                                    @change="handleFilterChange"
                                    class="px-4 py-2.5 pr-10 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 appearance-none bg-no-repeat bg-right cursor-pointer"
                                    style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236B7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0.5rem center; background-size: 1.5em 1.5em;"
                                >
                                    <option value="">País</option>
                                    <option v-for="country in filterOptions.countries" :key="country" :value="country">
                                        {{ country }}
                                    </option>
                                </select>

                                <!-- State Filter -->
                                <select
                                    v-model="stateFilter"
                                    @change="handleStateChange"
                                    :disabled="isLoadingStates"
                                    class="px-4 py-2.5 pr-10 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 appearance-none bg-no-repeat bg-right cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                    style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236B7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0.5rem center; background-size: 1.5em 1.5em;"
                                >
                                    <option value="">{{ isLoadingStates ? 'Carregando estados...' : 'Estados' }}</option>
                                    <option v-for="state in filterOptions.states" :key="state.id" :value="state.sigla">
                                        {{ state.nome }}
                                    </option>
                                </select>

                                <!-- City Filter -->
                                <select
                                    v-model="cityFilter"
                                    @change="handleFilterChange"
                                    :disabled="!stateFilter || isLoadingCities"
                                    class="px-4 py-2.5 pr-10 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 appearance-none bg-no-repeat bg-right cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                    style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236B7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0.5rem center; background-size: 1.5em 1.5em;"
                                >
                                    <option value="">
                                        <template v-if="!stateFilter">Selecione um estado</template>
                                        <template v-else-if="isLoadingCities">Carregando cidades...</template>
                                        <template v-else>Cidades</template>
                                    </option>
                                    <option v-for="city in filterOptions.cities" :key="city.id" :value="city.nome">
                                        {{ city.nome }}
                                    </option>
                                </select>

                                <!-- Per Page Selector -->
                                <div class="flex items-center gap-2 ml-auto">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Exibir:</span>
                                    <select
                                        :value="perPage"
                                        @change="changePerPage(Number($event.target.value))"
                                        class="px-3 py-2.5 pr-10 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 appearance-none bg-no-repeat bg-right cursor-pointer"
                                        style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236B7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0.5rem center; background-size: 1.5em 1.5em;"
                                    >
                                        <option v-for="option in perPageOptions" :key="option" :value="option">
                                            {{ option }} por página
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </BaseCard>

                <!-- Orders Table -->
                <BaseCard padding="none" class="overflow-hidden">
                    <!-- Loading -->
                    <div v-if="isLoading" class="flex items-center justify-center py-20">
                        <div class="relative">
                            <div class="w-16 h-16 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500"></div>
                            <LoadingSpinner size="lg" class="absolute inset-0 m-auto text-white" />
                        </div>
                    </div>

                    <!-- Mobile/Tablet Cards -->
                    <div v-else-if="orders.length > 0" class="xl:hidden">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                            <div
                                v-for="order in orders"
                                :key="'card-' + order.id"
                                @click="viewOrderDetail(order)"
                                class="bg-white dark:bg-gray-800 rounded-xl p-4 cursor-pointer transition-all duration-200 border border-gray-200 dark:border-gray-700 hover:shadow-lg hover:border-primary-300 dark:hover:border-primary-600"
                            >
                                <!-- Header: Order Number + Status -->
                                <div class="flex items-center justify-between mb-3">
                                    <div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Pedido</p>
                                        <p class="font-bold text-gray-900 dark:text-gray-100">{{ order.order_number }}</p>
                                    </div>
                                    <span :class="['badge badge-sm', `badge-${getPaymentStatusConfig(order.payment_status).color}`]">
                                        {{ getPaymentStatusConfig(order.payment_status).label }}
                                    </span>
                                </div>

                                <!-- Customer Info -->
                                <div class="mb-3 pb-3 border-b border-gray-100 dark:border-gray-700">
                                    <p class="font-medium text-gray-900 dark:text-gray-100 text-sm truncate">{{ order.customer_name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ order.customer_email }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ formatDate(order.external_created_at) }}</p>
                                </div>

                                <!-- Stats Grid -->
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Total</p>
                                        <p class="font-bold text-gray-900 dark:text-gray-100">{{ formatCurrency(order.total) }}</p>
                                    </div>
                                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Itens</p>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ order.items_count }}</p>
                                    </div>
                                    <div v-if="order.cost > 0" class="bg-success-50 dark:bg-success-900/30 rounded-lg p-2.5">
                                        <p class="text-xs text-success-600 dark:text-success-400">Lucro</p>
                                        <p class="font-semibold text-success-700 dark:text-success-300">{{ formatCurrency(order.gross_profit) }}</p>
                                    </div>
                                    <div v-if="order.margin !== null && order.cost > 0" class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-2.5">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Margem</p>
                                        <p :class="['font-semibold', getMarginColor(order.margin)]">{{ order.margin.toFixed(0) }}%</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table -->
                    <div v-if="orders.length > 0" class="hidden xl:block overflow-x-auto">
                        <table class="w-full table-fixed">
                            <colgroup>
                                <col style="width: 100px;">  <!-- Pedido -->
                                <col style="width: 100px;">  <!-- Data -->
                                <col style="width: 90px;">   <!-- Status -->
                                <col style="width: 160px;">  <!-- Cliente -->
                                <col style="width: 200px;">  <!-- Email -->
                                <col style="width: 140px;">  <!-- Telefone -->
                                <col style="width: 120px;">  <!-- Total Vendido -->
                                <col style="width: 70px;">   <!-- Itens -->
                                <col style="width: 100px;">  <!-- Custo -->
                                <col style="width: 120px;">  <!-- Lucro Bruto -->
                                <col style="width: 90px;">   <!-- Margem -->
                            </colgroup>
                            <thead class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10">
                                <tr>
                                    <th class="text-left px-5 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Pedido
                                    </th>
                                    <th class="text-left px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Data
                                    </th>
                                    <th class="text-left px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="text-left px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Cliente
                                    </th>
                                    <th class="text-left px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Email
                                    </th>
                                    <th class="text-left px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Telefone
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Total Vendido
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Itens
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Custo
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Lucro Bruto
                                    </th>
                                    <th class="text-right px-4 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Margem
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr
                                    v-for="order in orders"
                                    :key="order.id"
                                    @click="viewOrderDetail(order)"
                                    class="hover:bg-gradient-to-r hover:from-primary-50/50 dark:hover:from-primary-900/30 hover:to-transparent cursor-pointer transition-all duration-200"
                                >
                                    <td class="px-5 py-4">
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ order.order_number }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">
                                        {{ formatDate(order.external_created_at) }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <span :class="['badge', `badge-${getPaymentStatusConfig(order.payment_status).color}`]">
                                            {{ getPaymentStatusConfig(order.payment_status).label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="text-sm text-gray-900 dark:text-gray-100 truncate block">{{ order.customer_name }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="text-sm text-gray-600 dark:text-gray-300 truncate block">{{ order.customer_email }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <a
                                            v-if="order.customer_phone"
                                            :href="getWhatsAppLink(order.customer_phone)"
                                            target="_blank"
                                            class="text-sm text-primary-600 hover:text-primary-800 hover:underline"
                                            @click.stop
                                        >
                                            {{ order.customer_phone }}
                                        </a>
                                        <span v-else class="text-sm text-gray-400">-</span>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(order.total) }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-right text-sm text-gray-600 dark:text-gray-300">
                                        {{ order.items_count }}
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <span v-if="order.cost > 0" class="text-sm text-gray-600 dark:text-gray-300">{{ formatCurrency(order.cost) }}</span>
                                        <span v-else class="text-sm text-gray-400">-</span>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <span v-if="order.cost > 0" class="text-sm font-semibold text-success-600">{{ formatCurrency(order.gross_profit) }}</span>
                                        <span v-else class="text-sm text-gray-400">-</span>
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <span
                                            v-if="order.margin !== null && order.cost > 0"
                                            class="text-sm font-medium"
                                            :class="getMarginColor(order.margin)"
                                        >
                                            {{ order.margin.toFixed(0) }}%
                                        </span>
                                        <span v-else class="text-sm text-gray-400">-</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-20">
                        <div class="relative inline-block mb-6">
                            <div class="w-32 h-32 rounded-3xl bg-gradient-to-br from-primary-100 to-secondary-100 flex items-center justify-center">
                                <CurrencyDollarIcon class="w-16 h-16 text-primary-400" />
                            </div>
                            <div class="absolute -bottom-2 -right-2 w-8 h-8 rounded-full bg-accent-400 flex items-center justify-center">
                                <SparklesIcon class="w-4 h-4 text-white" />
                            </div>
                        </div>
                        <h3 class="text-2xl font-display font-bold text-gray-900 dark:text-gray-100 mb-3">
                            Nenhum pedido encontrado
                        </h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">
                            <template v-if="hasActiveFilters">
                                Nenhum pedido corresponde aos filtros selecionados
                            </template>
                            <template v-else-if="searchQuery">
                                Tente uma busca diferente
                            </template>
                            <template v-else>
                                Conecte sua loja para sincronizar pedidos
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
                    <div v-if="totalPages > 1" class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 sm:px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                        <p class="text-sm text-gray-500 dark:text-gray-400 text-center sm:text-left">
                            <span class="hidden sm:inline">Mostrando </span>{{ (currentPage - 1) * perPage + 1 }}-{{ Math.min(currentPage * perPage, totalItems) }} de {{ totalItems }}
                        </p>
                        <div class="flex items-center gap-2">
                            <BaseButton
                                variant="ghost"
                                size="sm"
                                :disabled="currentPage === 1"
                                @click="goToPage(currentPage - 1)"
                            >
                                <ChevronLeftIcon class="w-4 h-4" />
                            </BaseButton>
                            <div class="hidden sm:flex items-center gap-1">
                                <button
                                    v-for="page in visiblePages"
                                    :key="page"
                                    @click="goToPage(page)"
                                    :class="[
                                        'w-8 h-8 flex items-center justify-center rounded text-sm font-medium transition-colors',
                                        page === currentPage
                                            ? 'bg-primary-600 text-white'
                                            : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'
                                    ]"
                                >
                                    {{ page }}
                                </button>
                            </div>
                            <span class="sm:hidden text-sm text-gray-600 dark:text-gray-300 px-2">
                                {{ currentPage }}/{{ totalPages }}
                            </span>
                            <BaseButton
                                variant="ghost"
                                size="sm"
                                :disabled="currentPage === totalPages"
                                @click="goToPage(currentPage + 1)"
                            >
                                <ChevronRightIcon class="w-4 h-4" />
                            </BaseButton>
                        </div>
                    </div>
                </BaseCard>
            </div>
        </div>

        <!-- Order Detail Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="showDetailModal && selectedOrder"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                >
                    <!-- Backdrop -->
                    <div
                        class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"
                        @click="showDetailModal = false"
                    ></div>

                    <!-- Modal -->
                    <div class="relative w-full max-w-3xl max-h-[90vh] overflow-hidden bg-white dark:bg-gray-800 rounded-2xl sm:rounded-3xl shadow-2xl mx-2 sm:mx-4">
                        <!-- Header with Gradient -->
                        <div class="relative px-8 py-6 bg-gradient-to-r from-primary-500 to-secondary-500 overflow-hidden">
                            <!-- Background Pattern -->
                            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(rgba(255,255,255,0.3) 1px, transparent 1px); background-size: 20px 20px;"></div>

                            <!-- Close Button -->
                            <button
                                @click="showDetailModal = false"
                                class="absolute top-4 right-4 p-2 rounded-full bg-white dark:bg-gray-800/20 hover:bg-white dark:bg-gray-800/30 transition-colors"
                            >
                                <XMarkIcon class="w-5 h-5 text-white" />
                            </button>

                            <div class="relative flex items-center justify-between">
                                <div>
                                    <p class="text-white/80 text-sm mb-1">Pedido</p>
                                    <p class="text-2xl font-display font-bold text-white">{{ selectedOrder.order_number }}</p>
                                </div>
                                <span :class="['badge bg-white dark:bg-gray-800/20 text-white border-white/30', `badge-${getPaymentStatusConfig(selectedOrder.payment_status).color}`]">
                                    {{ getPaymentStatusConfig(selectedOrder.payment_status).label }}
                                </span>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="px-8 py-6 max-h-[60vh] overflow-y-auto scrollbar-thin space-y-6">
                            <!-- Customer Info -->
                            <div class="space-y-3">
                                <h4 class="font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-primary-100 flex items-center justify-center">
                                        <UserIcon class="w-4 h-4 text-primary-600" />
                                    </div>
                                    Informações do Cliente
                                </h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-gray-50 to-transparent rounded-xl">
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Nome</p>
                                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ selectedOrder.customer_name }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 p-3 bg-gradient-to-r from-gray-50 to-transparent rounded-xl">
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">E-mail</p>
                                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ selectedOrder.customer_email }}</p>
                                        </div>
                                    </div>
                                    <div v-if="selectedOrder.customer_phone" class="flex items-center gap-3 p-3 bg-gradient-to-r from-gray-50 to-transparent rounded-xl">
                                        <div>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Telefone</p>
                                            <a
                                                :href="getWhatsAppLink(selectedOrder.customer_phone)"
                                                target="_blank"
                                                class="font-medium text-primary-600 hover:underline"
                                            >
                                                {{ selectedOrder.customer_phone }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Shipping Address -->
                            <div v-if="selectedOrder.shipping_address" class="space-y-3">
                                <h4 class="font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-success-100 flex items-center justify-center">
                                        <MapPinIcon class="w-4 h-4 text-success-600" />
                                    </div>
                                    Endereço de Entrega
                                </h4>
                                <div class="p-4 bg-gradient-to-r from-gray-50 to-transparent rounded-xl border border-gray-200 dark:border-gray-700">
                                    <p class="text-gray-900 dark:text-gray-100">
                                        {{ selectedOrder.shipping_address.street }}, {{ selectedOrder.shipping_address.number }}
                                        <span v-if="selectedOrder.shipping_address.complement"> - {{ selectedOrder.shipping_address.complement }}</span>
                                    </p>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        {{ selectedOrder.shipping_address.neighborhood }} - {{ selectedOrder.shipping_address.city }}/{{ selectedOrder.shipping_address.province || selectedOrder.shipping_address.state }}
                                    </p>
                                    <p class="text-gray-500 dark:text-gray-400">CEP: {{ selectedOrder.shipping_address.zip_code || selectedOrder.shipping_address.zipcode }}</p>
                                </div>
                            </div>

                            <!-- Order Items -->
                            <div class="space-y-3">
                                <h4 class="font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-accent-100 flex items-center justify-center">
                                        <DocumentTextIcon class="w-4 h-4 text-accent-600" />
                                    </div>
                                    Itens do Pedido
                                </h4>
                                <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                                    <table class="w-full text-sm">
                                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                            <tr>
                                                <th class="text-left px-4 py-2 text-gray-500 dark:text-gray-400 font-medium">Produto</th>
                                                <th class="text-center px-4 py-2 text-gray-500 dark:text-gray-400 font-medium">Qtd</th>
                                                <th class="text-right px-4 py-2 text-gray-500 dark:text-gray-400 font-medium">Preço</th>
                                                <th class="text-right px-4 py-2 text-gray-500 dark:text-gray-400 font-medium">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="(item, index) in selectedOrder.items" :key="index" class="hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900">
                                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ item.product_name || item.name }}</td>
                                                <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400">{{ item.quantity }}</td>
                                                <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">{{ formatCurrency(item.unit_price || item.price) }}</td>
                                                <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100">{{ formatCurrency(item.total || (item.quantity * (item.unit_price || item.price))) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Order Summary -->
                            <div class="space-y-2 p-4 bg-gradient-to-r from-primary-50 to-secondary-50 rounded-xl border border-primary-100">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Subtotal</span>
                                    <span class="text-gray-900 dark:text-gray-100 font-medium">{{ formatCurrency(selectedOrder.subtotal) }}</span>
                                </div>
                                <div v-if="selectedOrder.discount > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Desconto</span>
                                    <span class="text-success-600 font-medium">-{{ formatCurrency(selectedOrder.discount) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Frete</span>
                                    <span class="text-gray-900 dark:text-gray-100 font-medium">{{ selectedOrder.shipping > 0 ? formatCurrency(selectedOrder.shipping) : 'Grátis' }}</span>
                                </div>
                                <div v-if="selectedOrder.cost > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Custo dos Produtos</span>
                                    <span class="text-gray-900 dark:text-gray-100 font-medium">{{ formatCurrency(selectedOrder.cost) }}</span>
                                </div>
                                <div class="flex justify-between text-lg font-bold pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <span class="text-gray-900 dark:text-gray-100">Total</span>
                                    <span class="text-gray-900 dark:text-gray-100">{{ formatCurrency(selectedOrder.total) }}</span>
                                </div>
                                <div v-if="selectedOrder.cost > 0" class="flex justify-between text-sm pt-2">
                                    <span class="text-gray-500 dark:text-gray-400">Lucro Bruto</span>
                                    <span class="font-medium text-success-600">{{ formatCurrency(selectedOrder.gross_profit) }} ({{ selectedOrder.margin?.toFixed(1) }}%)</span>
                                </div>
                            </div>

                            <!-- Payment Info -->
                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-transparent rounded-xl border border-gray-200 dark:border-gray-700">
                                <div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Método de Pagamento</p>
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ getPaymentMethodLabel(selectedOrder.payment_method) }}</p>
                                </div>
                                <span :class="['badge', `badge-${getPaymentStatusConfig(selectedOrder.payment_status).color}`]">
                                    {{ getPaymentStatusConfig(selectedOrder.payment_status).label }}
                                </span>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-8 py-5 bg-gray-50 dark:bg-gray-900 border-t border-gray-100 dark:border-gray-700">
                            <div class="flex justify-end">
                                <BaseButton variant="secondary" @click="showDetailModal = false">
                                    Fechar
                                </BaseButton>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
