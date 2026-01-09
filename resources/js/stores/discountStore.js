import { defineStore } from 'pinia';
import api from '../services/api';

export const useDiscountStore = defineStore('discount', {
    state: () => ({
        coupons: [],
        stats: null,
        isLoading: false,
        error: null,

        // Filters
        searchQuery: '',
        sortBy: 'total_sold',
        sortOrder: 'desc',

        // Pagination
        currentPage: 1,
        totalPages: 1,
        totalItems: 0,
        perPage: 10,

        // Selection for chart
        selectedCoupons: [],
    }),

    getters: {
        hasData: (state) => state.coupons.length > 0,
        hasSelection: (state) => state.selectedCoupons.length > 0,

        // Calculate totals row
        totals: (state) => {
            if (!state.coupons.length) return null;

            return state.coupons.reduce((acc, coupon) => ({
                product_revenue: acc.product_revenue + (coupon.product_revenue || 0),
                shipping_revenue: acc.shipping_revenue + (coupon.shipping_revenue || 0),
                total_sold: acc.total_sold + (coupon.total_sold || 0),
                total_discounts: acc.total_discounts + (coupon.total_discounts || 0),
                order_count: acc.order_count + (coupon.order_count || 0),
                new_customers: acc.new_customers + (coupon.new_customers || 0),
            }), {
                product_revenue: 0,
                shipping_revenue: 0,
                total_sold: 0,
                total_discounts: 0,
                order_count: 0,
                new_customers: 0,
            });
        },

        // Get selected coupons data for chart
        selectedCouponsData: (state) => {
            return state.coupons.filter(coupon =>
                state.selectedCoupons.includes(coupon.coupon_code)
            );
        },
    },

    actions: {
        async fetchStats() {
            try {
                const response = await api.get('/discounts/stats');
                this.stats = response.data;
            } catch (error) {
                console.error('Error fetching discount stats:', error);
                this.error = 'Erro ao carregar estatísticas de descontos';
            }
        },

        async fetchCoupons() {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await api.get('/discounts', {
                    params: {
                        search: this.searchQuery,
                        sort_by: this.sortBy,
                        sort_order: this.sortOrder,
                        page: this.currentPage,
                        per_page: this.perPage,
                    },
                });

                this.coupons = response.data.data;
                this.totalPages = response.data.last_page;
                this.totalItems = response.data.total;
                this.currentPage = response.data.current_page;
            } catch (error) {
                console.error('Error fetching coupons:', error);
                this.error = 'Erro ao carregar cupons de desconto';
                this.coupons = [];
            } finally {
                this.isLoading = false;
            }
        },

        async fetchAllData() {
            await Promise.all([
                this.fetchStats(),
                this.fetchCoupons(),
            ]);
        },

        setSearchQuery(query) {
            this.searchQuery = query;
            this.currentPage = 1;
        },

        setSorting(sortBy, sortOrder) {
            this.sortBy = sortBy;
            this.sortOrder = sortOrder;
            this.currentPage = 1;
        },

        goToPage(page) {
            if (page < 1 || page > this.totalPages) return;
            this.currentPage = page;
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
            this.selectedCoupons = this.coupons.map(c => c.coupon_code);
        },

        async exportData() {
            try {
                const response = await api.get('/discounts/export', {
                    params: {
                        search: this.searchQuery,
                        sort_by: this.sortBy,
                        sort_order: this.sortOrder,
                    },
                    responseType: 'blob',
                });

                // Create download link
                const url = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', `descontos-${new Date().toISOString().split('T')[0]}.xlsx`);
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);
            } catch (error) {
                console.error('Error exporting data:', error);
                throw new Error('Erro ao exportar dados');
            }
        },
    },
});
