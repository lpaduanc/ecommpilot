/**
 * API Helpers - Utilities for standardized API error handling
 *
 * This module provides a consistent pattern for handling API calls
 * across all stores in the application.
 */

import type { ApiResponse, ApiError, Result } from '../types/api';
import { logger } from './logger';

/**
 * Re-export types for convenience
 */
export type { ApiResponse, ApiError, Result };

/**
 * Handle API call with standardized error handling
 *
 * @param apiCall - Function that returns a Promise (usually an axios call)
 * @returns Promise<Result<T>> - Standardized result object
 *
 * @example
 * ```typescript
 * const result = await handleApiCall<User>(
 *   () => api.post('/auth/login', credentials)
 * );
 *
 * if (result.success) {
 *   user.value = result.data.user;
 *   return result;
 * } else {
 *   notificationStore.error(result.error.message);
 *   return result;
 * }
 * ```
 */
export async function handleApiCall<T>(
  apiCall: () => Promise<any>
): Promise<Result<T>> {
  try {
    const response = await apiCall();

    // Extract data from axios response
    return {
      success: true,
      data: response.data as T,
    };
  } catch (error: any) {
    // Handle axios error structure
    const apiError: ApiError = {
      message: error.response?.data?.message || error.message || 'Erro inesperado',
      errors: error.response?.data?.errors,
      status: error.response?.status,
      code: error.code,
    };

    // Log error in development mode
    if (import.meta.env.DEV) {
      logger.error('[API Error]', {
        message: apiError.message,
        status: apiError.status,
        errors: apiError.errors,
        originalError: error,
      });
    }

    return {
      success: false,
      error: apiError,
    };
  }
}

/**
 * Type guard to check if result is successful
 * Useful for narrowing types in conditional blocks
 *
 * @example
 * ```typescript
 * const result = await handleApiCall<User>(...);
 *
 * if (isSuccess(result)) {
 *   // TypeScript knows result.data exists here
 *   console.log(result.data);
 * } else {
 *   // TypeScript knows result.error exists here
 *   console.log(result.error.message);
 * }
 * ```
 */
export function isSuccess<T>(result: Result<T>): result is { success: true; data: T } {
  return result.success === true;
}

/**
 * Type guard to check if result is an error
 */
export function isError<T>(result: Result<T>): result is { success: false; error: ApiError } {
  return result.success === false;
}

/**
 * Extract data from result or throw error
 * Useful when you want to handle errors with try/catch
 *
 * @example
 * ```typescript
 * try {
 *   const data = unwrapResult(await handleApiCall<User>(...));
 *   console.log(data);
 * } catch (error) {
 *   console.error(error.message);
 * }
 * ```
 */
export function unwrapResult<T>(result: Result<T>): T {
  if (result.success) {
    return result.data;
  }
  throw new Error(result.error.message);
}

/**
 * Get error message from result
 * Returns null if result is successful
 */
export function getErrorMessage<T>(result: Result<T>): string | null {
  return result.success ? null : result.error.message;
}

/**
 * Get validation errors from result
 * Returns null if result is successful or has no validation errors
 */
export function getValidationErrors<T>(result: Result<T>): Record<string, string[]> | null {
  return result.success ? null : (result.error.errors || null);
}
