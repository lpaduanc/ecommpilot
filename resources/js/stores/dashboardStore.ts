/**
 * Dashboard Store
 *
 * Manages dashboard data, statistics, and charts with proper error handling,
 * request deduplication, and loading states.
 *
 * Example of using the new standardized error handling pattern.
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api, { createCancelableRequest } from '../services/api';
import { handleApiCall, type Result } from '../utils/apiHelpers';
import { dedupeRequest, buildCacheKey, clearRequestCache } from '../utils/requestCache';
import { useNotificationStore } from './notificationStore';
import { logger } from '../utils/logger';

/**
 * Types for dashboard data
 */
interface DashboardStats {
  total_revenue: number;
  total_orders: number;
  total_products: number;
  total_customers: number;
  average_ticket: number;
  conversion_rate: number;
  revenue_change: number | null;
  orders_change: number | null;
  customers_change: number | null;
  has_store: boolean;
}

interface ChartDataPoint {
  date: string;
  value: number;
}

interface TopProduct {
  id: string;  // UUID
  name: string;
  sales: number;
  revenue: number;
}

interface CategoryData {
  category: string;
  count: number;
}

interface PaymentMethodData {
  method: string;
  count: number;
  percentage: number;
}

interface LowStockProduct {
  id: string;  // UUID
  name: string;
  sku: string;
  stock_quantity: number;
}

interface DashboardFilters {
  period: string;
  startDate: string | null;
  endDate: string | null;
  categories: string[];
  paymentStatus: string[];
}

export const useDashboardStore = defineStore('dashboard', () => {
  /**
   * State
   */
  const stats = ref<DashboardStats | null>(null);
  const revenueChart = ref<ChartDataPoint[]>([]);
  const ordersStatusChart = ref<any[]>([]);
  const topProducts = ref<TopProduct[]>([]);
  const paymentMethodsChart = ref<PaymentMethodData[]>([]);
  const categoryChart = ref<CategoryData[]>([]);
  const lowStockProducts = ref<LowStockProduct[]>([]);
  const isLoading = ref(false);
  const error = ref<string | null>(null);

  const filters = ref<DashboardFilters>({
    period: 'yesterday',
    startDate: null,
    endDate: null,
    categories: [],
    paymentStatus: [],
  });

  /**
   * Computed
   */
  const hasStore = computed(() => stats.value?.has_store || false);

  /**
   * Actions
   */

  /**
   * Fetch dashboard statistics with error handling and deduplication
   *
   * EXAMPLE: Using handleApiCall + dedupeRequest pattern
   */
  async function fetchStats(): Promise<Result<DashboardStats>> {
    isLoading.value = true;
    error.value = null;

    try {
      const params = buildFilterParams();
      const cacheKey = buildCacheKey('dashboard-stats', params);

      // Deduplicate requests - if called multiple times, only one request is made
      const response = await dedupeRequest(
        cacheKey,
        () => api.get('/dashboard/stats', { params }),
        5000 // TTL: 5 seconds
      );

      // Use handleApiCall for standardized error handling
      const result = await handleApiCall<DashboardStats>(
        async () => response
      );

      if (result.success) {
        stats.value = result.data;
      } else {
        error.value = result.error.message;
      }

      return result;
    } catch (err: any) {
      const errorMessage = err.message || 'Erro ao carregar estatísticas';
      error.value = errorMessage;

      return {
        success: false,
        error: {
          message: errorMessage,
          status: err.response?.status,
        },
      };
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * Fetch revenue chart data
   *
   * EXAMPLE: Using createCancelableRequest for request cancellation
   */
  async function fetchRevenueChart(): Promise<void> {
    const { cancelToken, cleanup } = createCancelableRequest('dashboard-revenue-chart');

    try {
      const params = buildFilterParams();
      const cacheKey = buildCacheKey('dashboard-revenue-chart', params);

      const response = await dedupeRequest(
        cacheKey,
        () => api.get('/dashboard/charts/revenue', { params, cancelToken })
      );

      revenueChart.value = response.data;
    } catch (err) {
      // Only log if not cancelled
      if (!api.isCancel?.(err)) {
        logger.error('Error fetching revenue chart:', err);
        revenueChart.value = [];
      }
    } finally {
      cleanup();
    }
  }

  /**
   * Fetch orders status chart
   */
  async function fetchOrdersStatusChart(): Promise<void> {
    try {
      const params = buildFilterParams();
      const cacheKey = buildCacheKey('dashboard-orders-status', params);

      const response = await dedupeRequest(
        cacheKey,
        () => api.get('/dashboard/charts/orders-status', { params })
      );

      ordersStatusChart.value = response.data;
    } catch {
      ordersStatusChart.value = [];
    }
  }

  /**
   * Fetch top products
   */
  async function fetchTopProducts(): Promise<void> {
    try {
      const params = buildFilterParams();
      const cacheKey = buildCacheKey('dashboard-top-products', params);

      const response = await dedupeRequest(
        cacheKey,
        () => api.get('/dashboard/charts/top-products', { params })
      );

      topProducts.value = response.data;
    } catch {
      topProducts.value = [];
    }
  }

  /**
   * Fetch payment methods chart
   */
  async function fetchPaymentMethodsChart(): Promise<void> {
    try {
      const params = buildFilterParams();
      const cacheKey = buildCacheKey('dashboard-payment-methods', params);

      const response = await dedupeRequest(
        cacheKey,
        () => api.get('/dashboard/charts/payment-methods', { params })
      );

      paymentMethodsChart.value = response.data;
    } catch {
      paymentMethodsChart.value = [];
    }
  }

  /**
   * Fetch category chart
   */
  async function fetchCategoryChart(): Promise<void> {
    try {
      const params = buildFilterParams();
      const cacheKey = buildCacheKey('dashboard-category', params);

      const response = await dedupeRequest(
        cacheKey,
        () => api.get('/dashboard/charts/categories', { params })
      );

      categoryChart.value = response.data;
    } catch {
      categoryChart.value = [];
    }
  }

  /**
   * Fetch low stock products
   */
  async function fetchLowStockProducts(): Promise<void> {
    try {
      const cacheKey = 'dashboard-low-stock';

      const response = await dedupeRequest(
        cacheKey,
        () => api.get('/dashboard/low-stock'),
        10000 // Longer TTL since this doesn't change as frequently
      );

      lowStockProducts.value = response.data;
    } catch {
      lowStockProducts.value = [];
    }
  }

  /**
   * Fetch all dashboard data in parallel
   *
   * EXAMPLE: Parallel requests with proper error handling
   */
  async function fetchAllData(): Promise<void> {
    // All requests run in parallel and are deduplicated
    await Promise.allSettled([
      fetchStats(),
      fetchRevenueChart(),
      fetchOrdersStatusChart(),
      fetchTopProducts(),
      fetchPaymentMethodsChart(),
      fetchCategoryChart(),
      fetchLowStockProducts(),
    ]);
  }

  /**
   * Build filter parameters for API requests
   */
  function buildFilterParams(): Record<string, any> {
    const params: Record<string, any> = {};

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

  /**
   * Set filters and invalidate cache
   */
  function setFilters(newFilters: Partial<DashboardFilters>): void {
    filters.value = { ...filters.value, ...newFilters };

    // Clear cache when filters change so fresh data is fetched
    clearRequestCache('dashboard-stats');
    clearRequestCache('dashboard-revenue-chart');
    clearRequestCache('dashboard-orders-status');
    clearRequestCache('dashboard-top-products');
    clearRequestCache('dashboard-payment-methods');
    clearRequestCache('dashboard-category');
  }

  /**
   * Reset filters to defaults
   */
  function resetFilters(): void {
    filters.value = {
      period: 'yesterday',
      startDate: null,
      endDate: null,
      categories: [],
      paymentStatus: [],
    };

    // Clear all dashboard caches
    clearRequestCache('dashboard-stats');
    clearRequestCache('dashboard-revenue-chart');
    clearRequestCache('dashboard-orders-status');
    clearRequestCache('dashboard-top-products');
    clearRequestCache('dashboard-payment-methods');
    clearRequestCache('dashboard-category');
  }

  return {
    // State
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

    // Computed
    hasStore,

    // Actions
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
