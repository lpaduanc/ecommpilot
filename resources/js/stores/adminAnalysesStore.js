import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';

export const useAdminAnalysesStore = defineStore('adminAnalyses', () => {
    const analyses = ref([]);
    const currentAnalysis = ref(null);
    const stats = ref(null);
    const isLoading = ref(false);
    const isLoadingDetail = ref(false);
    const isLoadingStats = ref(false);
    const error = ref(null);

    // Filters
    const filters = ref({
        store_id: null,
        user_id: null,
        status: null,
        date_from: null,
        date_to: null,
        search: null,
    });

    // Pagination
    const pagination = ref({
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
    });

    // Computed
    const hasFilters = computed(() => {
        return (
            filters.value.store_id !== null ||
            filters.value.user_id !== null ||
            filters.value.status !== null ||
            filters.value.date_from !== null ||
            filters.value.date_to !== null ||
            (filters.value.search !== null && filters.value.search.trim() !== '')
        );
    });

    // Actions
    async function fetchAnalyses(page = 1) {
        isLoading.value = true;
        error.value = null;

        try {
            const params = {
                page,
                per_page: pagination.value.per_page,
                ...buildFilterParams(),
            };

            const response = await api.get('/admin/analyses', { params });
            analyses.value = response.data.data;
            pagination.value = {
                current_page: response.data.current_page,
                last_page: response.data.last_page,
                per_page: response.data.per_page,
                total: response.data.total,
            };
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao carregar análises';
            analyses.value = [];
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchAnalysis(id) {
        isLoadingDetail.value = true;
        error.value = null;

        try {
            const response = await api.get(`/admin/analyses/${id}`);
            currentAnalysis.value = response.data;
            return response.data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao carregar detalhes da análise';
            currentAnalysis.value = null;
            return null;
        } finally {
            isLoadingDetail.value = false;
        }
    }

    async function fetchStats() {
        isLoadingStats.value = true;

        try {
            const params = buildFilterParams();
            const response = await api.get('/admin/analyses/stats', { params });
            stats.value = response.data;
        } catch (err) {
            console.error('Error fetching stats:', err);
            stats.value = null;
        } finally {
            isLoadingStats.value = false;
        }
    }

    function buildFilterParams() {
        const params = {};

        if (filters.value.store_id) {
            params.store_id = filters.value.store_id;
        }
        if (filters.value.user_id) {
            params.user_id = filters.value.user_id;
        }
        if (filters.value.status) {
            params.status = filters.value.status;
        }
        if (filters.value.date_from) {
            params.date_from = filters.value.date_from;
        }
        if (filters.value.date_to) {
            params.date_to = filters.value.date_to;
        }
        if (filters.value.search && filters.value.search.trim() !== '') {
            params.search = filters.value.search.trim();
        }

        return params;
    }

    function setFilter(key, value) {
        filters.value[key] = value;
    }

    function clearFilters() {
        filters.value = {
            store_id: null,
            user_id: null,
            status: null,
            date_from: null,
            date_to: null,
            search: null,
        };
    }

    function resetCurrentAnalysis() {
        currentAnalysis.value = null;
    }

    return {
        // State
        analyses,
        currentAnalysis,
        stats,
        isLoading,
        isLoadingDetail,
        isLoadingStats,
        error,
        filters,
        pagination,
        hasFilters,

        // Actions
        fetchAnalyses,
        fetchAnalysis,
        fetchStats,
        setFilter,
        clearFilters,
        resetCurrentAnalysis,
    };
});
