import { defineStore } from 'pinia';
import api from '../services/api';

export const useDiscountStore = defineStore('discount', {
    state: () => ({
        coupons: [],
        stats: null,
        filters: null,
        isLoading: false,
        isLoadingStats: false,
        error: null,

        // Filters
        searchQuery: '',
        statusFilter: null,
        typeFilter: null,
        sortBy: 'used',
        sortOrder: 'desc',

        // Period filter - default to last 15 days
        period: 'last_15_days',
        startDate: null,
        endDate: null,

        // Pagination
        currentPage: 1,
        totalPages: 1,
        totalItems: 0,
        perPage: 10,

        // Page totals from API
        pageTotals: null,
        hasOrderData: false,

        // Selection for chart
        selectedCoupons: [],
    }),

    getters: {
        hasData: (state) => state.coupons.length > 0,
        hasSelection: (state) => state.selectedCoupons.length > 0,

        // Get totals from API response
        totals: (state) => state.pageTotals,

        // Get selected coupons data for chart
        selectedCouponsData: (state) => {
            return state.coupons.filter(coupon =>
                state.selectedCoupons.includes(coupon.code)
            );
        },

        // Check if any filter is active
        hasActiveFilters: (state) => {
            return state.statusFilter || state.typeFilter || state.searchQuery;
        },

        // Available filter options
        typeOptions: (state) => state.filters?.types || [],
        statusOptions: () => ['active', 'expired', 'invalid'],

        // Period label for display
        periodLabel: (state) => {
            const labels = {
                'today': 'Hoje',
                'last_7_days': 'Últimos 7 dias',
                'last_15_days': 'Últimos 15 dias',
                'last_30_days': 'Últimos 30 dias',
                'this_month': 'Este mês',
                'last_month': 'Último mês',
                'all_time': 'Todo o período',
                'custom': 'Personalizado',
            };
            return labels[state.period] || 'Últimos 15 dias';
        },

        isCustomPeriod: (state) => state.period === 'custom',
    },

    actions: {
        /**
         * Fetch all data in a single request (recommended).
         * Uses the bulk endpoint for optimal performance.
         */
        async fetchAllData() {
            this.isLoading = true;
            this.isLoadingStats = true;
            this.error = null;

            try {
                const response = await api.get('/discounts/bulk', {
                    params: {
                        search: this.searchQuery || undefined,
                        status: this.statusFilter || undefined,
                        type: this.typeFilter || undefined,
                        page: this.currentPage,
                        per_page: this.perPage,
                        sort_by: this.sortBy,
                        sort_order: this.sortOrder,
                        period: this.period,
                        start_date: this.isCustomPeriod ? this.startDate : undefined,
                        end_date: this.isCustomPeriod ? this.endDate : undefined,
                    },
                });

                // Update stats
                this.stats = response.data.stats;

                // Update coupons
                const couponsData = response.data.coupons;
                this.coupons = couponsData.data;
                this.totalPages = couponsData.last_page;
                this.totalItems = couponsData.total;
                this.currentPage = couponsData.current_page;
                this.pageTotals = couponsData.totals || null;
                this.hasOrderData = couponsData.has_order_data || false;

                // Update filter options
                this.filters = response.data.filters;
            } catch (error) {
                console.error('Error fetching discount data:', error);
                this.error = 'Erro ao carregar dados de descontos';
                this.coupons = [];
                this.stats = null;
            } finally {
                this.isLoading = false;
                this.isLoadingStats = false;
            }
        },

        /**
         * Fetch only stats (for refresh without reloading coupons).
         */
        async fetchStats() {
            this.isLoadingStats = true;

            try {
                const response = await api.get('/discounts/stats', {
                    params: {
                        period: this.period,
                        start_date: this.isCustomPeriod ? this.startDate : undefined,
                        end_date: this.isCustomPeriod ? this.endDate : undefined,
                    },
                });
                this.stats = response.data;
            } catch (error) {
                console.error('Error fetching discount stats:', error);
            } finally {
                this.isLoadingStats = false;
            }
        },

        /**
         * Fetch only coupons (for pagination/filtering).
         */
        async fetchCoupons() {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await api.get('/discounts', {
                    params: {
                        search: this.searchQuery || undefined,
                        status: this.statusFilter || undefined,
                        type: this.typeFilter || undefined,
                        page: this.currentPage,
                        per_page: this.perPage,
                        sort_by: this.sortBy,
                        sort_order: this.sortOrder,
                        period: this.period,
                        start_date: this.isCustomPeriod ? this.startDate : undefined,
                        end_date: this.isCustomPeriod ? this.endDate : undefined,
                    },
                });

                this.coupons = response.data.data;
                this.totalPages = response.data.last_page;
                this.totalItems = response.data.total;
                this.currentPage = response.data.current_page;
                this.pageTotals = response.data.totals || null;
                this.hasOrderData = response.data.has_order_data || false;
            } catch (error) {
                console.error('Error fetching coupons:', error);
                this.error = 'Erro ao carregar cupons de desconto';
                this.coupons = [];
            } finally {
                this.isLoading = false;
            }
        },

        setSearchQuery(query) {
            this.searchQuery = query;
            this.currentPage = 1;
        },

        setStatusFilter(status) {
            this.statusFilter = status;
            this.currentPage = 1;
        },

        setTypeFilter(type) {
            this.typeFilter = type;
            this.currentPage = 1;
        },

        setSorting(sortBy, sortOrder = 'desc') {
            this.sortBy = sortBy;
            this.sortOrder = sortOrder;
            this.currentPage = 1;
        },

        setPeriod(period) {
            this.period = period;
            if (period !== 'custom') {
                this.startDate = null;
                this.endDate = null;
            }
            this.currentPage = 1;
        },

        setCustomDates(startDate, endDate) {
            this.period = 'custom';
            this.startDate = startDate;
            this.endDate = endDate;
            this.currentPage = 1;
        },

        goToPage(page) {
            if (page < 1 || page > this.totalPages) return;
            this.currentPage = page;
            this.fetchCoupons();
        },

        setPerPage(perPage) {
            this.perPage = perPage;
            this.currentPage = 1;
            this.fetchCoupons();
        },

        toggleCouponSelection(couponCode) {
            const index = this.selectedCoupons.indexOf(couponCode);
            if (index > -1) {
                this.selectedCoupons.splice(index, 1);
            } else {
                this.selectedCoupons.push(couponCode);
            }
        },

        clearSelection() {
            this.selectedCoupons = [];
        },

        selectAllVisible() {
            this.selectedCoupons = this.coupons.map(c => c.code);
        },

        resetFilters() {
            this.searchQuery = '';
            this.statusFilter = null;
            this.typeFilter = null;
            this.sortBy = 'used';
            this.sortOrder = 'desc';
            this.period = 'last_15_days';
            this.startDate = null;
            this.endDate = null;
            this.currentPage = 1;
        },
    },
});
