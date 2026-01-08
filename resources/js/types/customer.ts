/**
 * Customer Type Definitions
 *
 * Defines all customer-related TypeScript interfaces.
 */

/**
 * Synced Customer interface
 */
export interface SyncedCustomer {
  id: number;
  store_id: number;
  customer_id: string;
  name: string;
  email: string;
  phone?: string | null;
  total_orders: number;
  total_spent: number;
  first_order_at?: string | null;
  last_order_at?: string | null;
  created_at: string;
  updated_at: string;
}

/**
 * Customer filter interface
 */
export interface CustomerFilter {
  search?: string;
  min_orders?: number;
  min_spent?: number;
  has_orders?: boolean;
  sort_by?: 'name' | 'total_orders' | 'total_spent' | 'last_order_at';
  sort_order?: 'asc' | 'desc';
}

/**
 * Customer stats interface
 */
export interface CustomerStats {
  total_customers: number;
  new_customers_this_month: number;
  active_customers: number;
  average_customer_value: number;
  repeat_customer_rate: number;
}

/**
 * Top customer interface
 */
export interface TopCustomer {
  id: number;
  name: string;
  email: string;
  total_orders: number;
  total_spent: number;
}
