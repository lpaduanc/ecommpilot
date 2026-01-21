import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';

export const useDashboardStore = defineStore('dashboard', () => {
    const stats = ref(null);
    const revenueChart = ref([]);
    const ordersStatusChart = ref([]);
    const topProducts = ref([]);
    const paymentMethodsChart = ref([]);
    const categoryChart = ref([]);
    const lowStockProducts = ref([]);
    const isLoading = ref(false);
    const error = ref(null);
    const upgradeRequired = ref(false);

    const filters = ref({
        period: 'last_15_days',
        startDate: null,
        endDate: null,
        categories: [],
        paymentStatus: [],
    });

    const hasStore = computed(() => stats.value?.has_store || false);

    /**
     * Fetch all dashboard data in a single API call (bulk endpoint)
     * This reduces 7 API calls to 1, improving performance by ~85%
     */
    async function fetchAllData() {
        isLoading.value = true;
        error.value = null;

        try {
            const params = buildFilterParams();
            const response = await api.get('/dashboard/bulk', { params });
            const data = response.data;

            // Check if user has a store connected (has_store is at root level)
            if (data.has_store === false) {
                // No store connected - set stats with has_store flag
                stats.value = { has_store: false };
                revenueChart.value = [];
                ordersStatusChart.value = [];
                topProducts.value = [];
                paymentMethodsChart.value = [];
                categoryChart.value = [];
                lowStockProducts.value = [];
                return;
            }

            // Populate all state from single response
            // Include has_store in stats object for backward compatibility
            stats.value = data.stats ? { ...data.stats, has_store: true } : { has_store: true };
            revenueChart.value = data.revenue_chart || [];
            ordersStatusChart.value = data.orders_status_chart || [];
            topProducts.value = data.top_products || [];
            paymentMethodsChart.value = data.payment_methods_chart || [];
            categoryChart.value = data.categories_chart || [];
            lowStockProducts.value = data.low_stock_products || [];
        } catch (err) {
            // Check for upgrade required error (403 with upgrade_required flag)
            if (err.response?.status === 403 && err.response?.data?.upgrade_required) {
                upgradeRequired.value = true;
                error.value = err.response?.data?.message || 'Upgrade de plano necessário';
            } else {
                error.value = err.response?.data?.message || 'Erro ao carregar dados do dashboard';
            }

            // Reset all values on error
            stats.value = null;
            revenueChart.value = [];
            ordersStatusChart.value = [];
            topProducts.value = [];
            paymentMethodsChart.value = [];
            categoryChart.value = [];
            lowStockProducts.value = [];
        } finally {
            isLoading.value = false;
        }
    }

    // Individual fetch functions kept for backward compatibility
    // but now they use the bulk endpoint internally for consistency
    async function fetchStats() {
        await fetchAllData();
    }

    async function fetchRevenueChart() {
        if (revenueChart.value.length === 0) {
            await fetchAllData();
        }
    }

    async function fetchOrdersStatusChart() {
        if (ordersStatusChart.value.length === 0) {
            await fetchAllData();
        }
    }

    async function fetchTopProducts() {
        if (topProducts.value.length === 0) {
            await fetchAllData();
        }
    }

    async function fetchPaymentMethodsChart() {
        if (paymentMethodsChart.value.length === 0) {
            await fetchAllData();
        }
    }

    async function fetchCategoryChart() {
        if (categoryChart.value.length === 0) {
            await fetchAllData();
        }
    }

    async function fetchLowStockProducts() {
        if (lowStockProducts.value.length === 0) {
            await fetchAllData();
        }
    }
    
    function buildFilterParams() {
        const params = {};

        const period = filters.value.period || 'last_15_days';
        params.period = period;

        // Always send dates when period is custom
        if (period === 'custom') {
            // Ensure dates are sent even if they're empty (backend will validate)
            params.start_date = filters.value.startDate || '';
            params.end_date = filters.value.endDate || '';
        }

        if (filters.value.categories.length > 0) {
            params.categories = filters.value.categories.join(',');
        }

        if (filters.value.paymentStatus.length > 0) {
            params.payment_status = filters.value.paymentStatus.join(',');
        }

        return params;
    }
    
    function setFilters(newFilters) {
        filters.value = { ...filters.value, ...newFilters };
    }
    
    function resetFilters() {
        filters.value = {
            period: 'last_15_days',
            startDate: null,
            endDate: null,
            categories: [],
            paymentStatus: [],
        };
    }
    
    return {
        stats,
        revenueChart,
        ordersStatusChart,
        topProducts,
        paymentMethodsChart,
        categoryChart,
        lowStockProducts,
        isLoading,
        error,
        upgradeRequired,
        filters,
        hasStore,
        fetchStats,
        fetchRevenueChart,
        fetchOrdersStatusChart,
        fetchTopProducts,
        fetchPaymentMethodsChart,
        fetchCategoryChart,
        fetchLowStockProducts,
        fetchAllData,
        setFilters,
        resetFilters,
    };
});

