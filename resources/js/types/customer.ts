/**
 * Customer Type Definitions
 *
 * Defines all customer-related TypeScript interfaces.
 */

/**
 * Synced Customer interface
 */
export interface SyncedCustomer {
  id: string;  // UUID
  store_id: string;  // UUID
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
  id: string;  // UUID
  name: string;
  email: string;
  total_orders: number;
  total_spent: number;
}

/**
 * RFM Segment names
 */
export type RfmSegment =
  | 'Campeões'
  | 'Clientes Fiéis'
  | 'Potenciais Fiéis'
  | 'Novos Clientes'
  | 'Promissores'
  | 'Precisam de Atenção'
  | 'Quase Dormindo'
  | 'Em Risco'
  | 'Não Pode Perder'
  | 'Hibernando'
  | 'Perdidos';

/**
 * RFM Scores for a customer
 */
export interface RfmScores {
  r: number;  // 1-5
  f: number;  // 1-5
  m: number;  // 1-5
}

/**
 * Customer with RFM data (as returned by API)
 */
export interface CustomerWithRfm {
  id: string;
  name: string;
  email: string;
  phone?: string | null;
  total_orders: number;
  total_spent: number;
  average_order_value: number;
  first_order_at?: string | null;
  last_order_at?: string | null;
  days_without_purchase: number | null;
  external_created_at?: string | null;
  rfm_segment: RfmSegment | null;
  rfm_scores: RfmScores | null;
  /** True when order metrics were computed from synced_orders (real data). False = Nuvemshop API cache. */
  orders_from_real_data: boolean;
}

/**
 * RFM Segment distribution (for charts)
 */
export interface RfmSegmentDistribution {
  segment: RfmSegment;
  count: number;
  percentage: number;
  total_monetary: number;
  color: string;
}

/**
 * RFM Summary response from API
 */
export interface RfmSummary {
  segments_distribution: RfmSegmentDistribution[];
  monetary_by_segment: { segment: string; total_spent: number }[];
  totals: {
    total_customers: number;
    total_with_orders: number;
    avg_recency_days: number;
    avg_frequency: number;
    avg_monetary: number;
  };
}

/**
 * Customer filters response from API
 */
export interface CustomerFiltersResponse {
  segments: RfmSegment[];
  orders_range: { min: number; max: number };
  spent_range: { min: number; max: number };
}
