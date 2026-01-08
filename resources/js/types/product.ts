/**
 * Product Type Definitions
 *
 * Defines all product-related TypeScript interfaces.
 */

/**
 * Product image interface
 */
export interface ProductImage {
  id?: number;
  url: string;
  alt?: string;
  position?: number;
}

/**
 * Product variant interface
 */
export interface ProductVariant {
  id?: number;
  sku: string;
  price: number;
  compare_at_price?: number | null;
  stock_quantity: number;
  weight?: number;
  options?: Record<string, string>;
}

/**
 * Synced Product interface
 */
export interface SyncedProduct {
  id: number;
  store_id: number;
  product_id: string;
  name: string;
  description?: string | null;
  sku: string | null;
  price: number;
  compare_at_price: number | null;
  stock_quantity: number;
  images: string[];
  category?: string | null;
  tags?: string[];
  variants?: ProductVariant[];
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

/**
 * Product filter interface
 */
export interface ProductFilter {
  search?: string;
  category?: string;
  min_price?: number;
  max_price?: number;
  in_stock?: boolean;
  is_active?: boolean;
  sort_by?: 'name' | 'price' | 'stock' | 'created_at';
  sort_order?: 'asc' | 'desc';
}

/**
 * Low stock product interface
 */
export interface LowStockProduct {
  id: number;
  name: string;
  sku: string | null;
  stock_quantity: number;
  price: number;
}

/**
 * Product stats interface
 */
export interface ProductStats {
  total_products: number;
  active_products: number;
  low_stock_count: number;
  out_of_stock_count: number;
  total_value: number;
}
