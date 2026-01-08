/**
 * Dashboard Type Definitions
 *
 * Defines all dashboard-related TypeScript interfaces for analytics and statistics.
 */

/**
 * Dashboard statistics interface
 */
export interface DashboardStats {
  total_revenue: number;
  total_orders: number;
  total_products: number;
  total_customers: number;
  average_ticket: number;
  conversion_rate: number;
  revenue_change: number | null;
  orders_change: number | null;
  customers_change: number | null;
}

/**
 * Revenue chart data point
 */
export interface RevenueDataPoint {
  date: string;
  revenue: number;
  orders: number;
}

/**
 * Payment method chart data
 */
export interface PaymentMethodData {
  payment_method: string;
  count: number;
  total: number;
  percentage: number;
}

/**
 * Category chart data
 */
export interface CategoryData {
  category: string;
  count: number;
  revenue: number;
  percentage: number;
}

/**
 * Top product interface
 */
export interface TopProduct {
  id: number;
  name: string;
  sku: string | null;
  sales_count: number;
  revenue: number;
  stock_quantity: number;
}

/**
 * Date range filter
 */
export type DateRange = 'today' | 'yesterday' | 'last_7_days' | 'last_30_days' | 'this_month' | 'last_month' | 'custom';

/**
 * Dashboard filters
 */
export interface DashboardFilters {
  date_range: DateRange;
  start_date?: string;
  end_date?: string;
  store_id?: number;
}

/**
 * Stat card data
 */
export interface StatCard {
  title: string;
  value: string | number;
  change?: number;
  icon?: any;
  color?: 'primary' | 'success' | 'danger' | 'warning' | 'info';
  trend?: 'up' | 'down' | 'neutral';
}
