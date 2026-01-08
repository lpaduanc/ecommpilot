/**
 * API Type Definitions
 *
 * Defines all API-related TypeScript interfaces and types for the ecommpilot application.
 * These types ensure type safety when working with API responses and error handling.
 */

/**
 * Generic API response wrapper
 * @template T - The type of data returned by the API
 */
export interface ApiResponse<T = any> {
  data: T;
  message?: string;
  meta?: {
    current_page?: number;
    from?: number;
    last_page?: number;
    per_page?: number;
    to?: number;
    total?: number;
  };
}

/**
 * Paginated API response
 * @template T - The type of items in the paginated list
 */
export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
  };
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
}

/**
 * API Error interface for structured error handling
 */
export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
  status?: number;
  code?: string;
}

/**
 * Result type for operations that can succeed or fail
 * This pattern allows explicit error handling without try/catch
 * @template T - The type of data on success
 *
 * @example
 * ```typescript
 * const result = await login(credentials);
 * if (result.success) {
 *   console.log('User:', result.data);
 * } else {
 *   console.error('Error:', result.error.message);
 * }
 * ```
 */
export type Result<T> =
  | { success: true; data: T; message?: string }
  | { success: false; error: ApiError };

/**
 * Validation error structure
 */
export interface ValidationError {
  field: string;
  messages: string[];
}

/**
 * HTTP method types
 */
export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

/**
 * Request config interface
 */
export interface RequestConfig {
  params?: Record<string, any>;
  headers?: Record<string, string>;
  timeout?: number;
  signal?: AbortSignal;
}

/**
 * Upload progress callback
 */
export type UploadProgressCallback = (progressEvent: {
  loaded: number;
  total: number;
  percentage: number;
}) => void;

/**
 * File upload config
 */
export interface UploadConfig extends RequestConfig {
  onUploadProgress?: UploadProgressCallback;
}
