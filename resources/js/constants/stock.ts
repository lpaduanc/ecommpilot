/**
 * Stock Management Constants
 *
 * Centralized constants for stock status, thresholds and configurations
 * to ensure consistent stock handling across the application.
 */

/**
 * Stock status enum
 * Represents the current availability status of a product
 */
export enum StockStatus {
  OutOfStock = 'out_of_stock',
  LowStock = 'low_stock',
  InStock = 'in_stock'
}

/**
 * Stock threshold constants
 * Define the boundaries for stock status determination
 */
export const STOCK_THRESHOLDS = {
  /** Products with quantity <= 0 are considered out of stock */
  OUT_OF_STOCK: 0,
  /** Products with quantity <= 10 are considered low stock */
  LOW_STOCK: 10
} as const;

/**
 * Stock status configuration
 * Maps each status to its display label and color for UI rendering
 */
export const STOCK_STATUS_CONFIG = {
  [StockStatus.OutOfStock]: {
    label: 'Fora de Estoque',
    color: 'red',
    variant: 'error' as const
  },
  [StockStatus.LowStock]: {
    label: 'Estoque Baixo',
    color: 'orange',
    variant: 'warning' as const
  },
  [StockStatus.InStock]: {
    label: 'Em Estoque',
    color: 'green',
    variant: 'success' as const
  }
} as const;

/**
 * Helper function to determine stock status based on quantity
 * @param quantity - Current stock quantity
 * @returns StockStatus enum value
 */
export function getStockStatus(quantity: number): StockStatus {
  if (quantity <= STOCK_THRESHOLDS.OUT_OF_STOCK) {
    return StockStatus.OutOfStock;
  }
  if (quantity <= STOCK_THRESHOLDS.LOW_STOCK) {
    return StockStatus.LowStock;
  }
  return StockStatus.InStock;
}

/**
 * Type helper for stock status config values
 */
export type StockStatusConfig = typeof STOCK_STATUS_CONFIG[StockStatus];
