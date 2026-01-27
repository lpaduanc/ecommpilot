/**
 * Store Type Definitions
 *
 * Defines all store-related TypeScript interfaces for integrated e-commerce platforms.
 */

/**
 * E-commerce platform enum
 */
export type Platform = 'nuvemshop' | 'shopify' | 'woocommerce';

/**
 * Sync status enum
 */
export type SyncStatus = 'Pending' | 'Syncing' | 'Completed' | 'Failed';

/**
 * Store interface
 */
export interface Store {
  id: string;  // UUID
  user_id: string;  // UUID
  platform: Platform;
  store_id: string;
  store_name: string;
  access_token: string;
  sync_status: SyncStatus;
  last_sync_at: string | null;
  error_message: string | null;
  created_at: string;
  updated_at: string;
}

/**
 * Store stats interface
 */
export interface StoreStats {
  products_count: number;
  orders_count: number;
  customers_count: number;
}

/**
 * Store connection config
 */
export interface StoreConnectionConfig {
  platform: Platform;
  store_url?: string;
  api_key?: string;
  api_secret?: string;
}
