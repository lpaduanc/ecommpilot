/**
 * Order Type Definitions
 *
 * Defines all order-related TypeScript interfaces.
 */

/**
 * Order status enum
 */
export type OrderStatus =
  | 'Pending'
  | 'Processing'
  | 'Shipped'
  | 'Delivered'
  | 'Cancelled'
  | 'Refunded';

/**
 * Payment status enum
 */
export type PaymentStatus = 'Pending' | 'Paid' | 'Failed' | 'Refunded';

/**
 * Payment method enum
 */
export type PaymentMethod =
  | 'credit_card'
  | 'debit_card'
  | 'pix'
  | 'boleto'
  | 'paypal'
  | 'other';

/**
 * Order item interface
 */
export interface OrderItem {
  id?: string;  // UUID
  order_id?: string;  // UUID
  product_id: string;
  product_name: string;
  sku?: string | null;
  quantity: number;
  unit_price: number;
  total_price: number;
}

/**
 * Shipping address interface
 */
export interface ShippingAddress {
  street: string;
  number: string;
  complement?: string;
  neighborhood: string;
  city: string;
  state: string;
  country: string;
  postal_code: string;
}

/**
 * Synced Order interface
 */
export interface SyncedOrder {
  id: string;  // UUID
  store_id: string;  // UUID
  order_id: string;
  order_number: string;
  customer_name: string;
  customer_email: string;
  customer_phone?: string | null;
  status: OrderStatus;
  payment_status: PaymentStatus;
  payment_method: PaymentMethod;
  subtotal: number;
  shipping_cost: number;
  tax: number;
  discount: number;
  total: number;
  items: OrderItem[];
  shipping_address?: ShippingAddress | null;
  ordered_at: string;
  created_at: string;
  updated_at: string;
}

/**
 * Order filter interface
 */
export interface OrderFilter {
  search?: string;
  status?: OrderStatus;
  payment_status?: PaymentStatus;
  payment_method?: PaymentMethod;
  min_total?: number;
  max_total?: number;
  start_date?: string;
  end_date?: string;
  sort_by?: 'order_number' | 'total' | 'ordered_at' | 'status';
  sort_order?: 'asc' | 'desc';
}

/**
 * Order stats interface
 */
export interface OrderStats {
  total_orders: number;
  pending_orders: number;
  processing_orders: number;
  completed_orders: number;
  cancelled_orders: number;
  total_revenue: number;
  average_order_value: number;
}

/**
 * Orders by status chart data
 */
export interface OrdersByStatus {
  status: OrderStatus;
  count: number;
  percentage: number;
}
