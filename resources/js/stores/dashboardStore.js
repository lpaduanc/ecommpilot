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
    
    const filters = ref({
        period: 'last_30_days',
        startDate: null,
        endDate: null,
        categories: [],
        paymentStatus: [],
    });
    
    const hasStore = computed(() => stats.value?.has_store || false);
    
    async function fetchStats() {
        isLoading.value = true;
        error.value = null;
        
        try {
            const params = buildFilterParams();
            const response = await api.get('/dashboard/stats', { params });
            stats.value = response.data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao carregar estatísticas';
        } finally {
            isLoading.value = false;
        }
    }
    
    async function fetchRevenueChart() {
        try {
            const params = buildFilterParams();
            const response = await api.get('/dashboard/charts/revenue', { params });
            revenueChart.value = response.data;
        } catch {
            revenueChart.value = [];
        }
    }
    
    async function fetchOrdersStatusChart() {
        try {
            const params = buildFilterParams();
            const response = await api.get('/dashboard/charts/orders-status', { params });
            ordersStatusChart.value = response.data;
        } catch {
            ordersStatusChart.value = [];
        }
    }
    
    async function fetchTopProducts() {
        try {
            const params = buildFilterParams();
            const response = await api.get('/dashboard/charts/top-products', { params });
            topProducts.value = response.data;
        } catch {
            topProducts.value = [];
        }
    }
    
    async function fetchPaymentMethodsChart() {
        try {
            const params = buildFilterParams();
            const response = await api.get('/dashboard/charts/payment-methods', { params });
            paymentMethodsChart.value = response.data;
        } catch {
            paymentMethodsChart.value = [];
        }
    }
    
    async function fetchCategoryChart() {
        try {
            const params = buildFilterParams();
            const response = await api.get('/dashboard/charts/categories', { params });
            categoryChart.value = response.data;
        } catch {
            categoryChart.value = [];
        }
    }
    
    async function fetchLowStockProducts() {
        try {
            const response = await api.get('/dashboard/low-stock');
            lowStockProducts.value = response.data;
        } catch {
            lowStockProducts.value = [];
        }
    }
    
    async function fetchAllData() {
        await Promise.all([
            fetchStats(),
            fetchRevenueChart(),
            fetchOrdersStatusChart(),
            fetchTopProducts(),
            fetchPaymentMethodsChart(),
            fetchCategoryChart(),
            fetchLowStockProducts(),
        ]);
    }
    
    function buildFilterParams() {
        const params = {};
        
        if (filters.value.period) {
            params.period = filters.value.period;
        }
        
        if (filters.value.startDate) {
            params.start_date = filters.value.startDate;
        }
        
        if (filters.value.endDate) {
            params.end_date = filters.value.endDate;
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
            period: 'last_30_days',
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

