/**
 * Request Cache Utilities
 *
 * This module provides request deduplication functionality to prevent
 * multiple identical API calls from being made simultaneously.
 *
 * Use case: When a component mounts multiple times or a function is called
 * rapidly, we deduplicate requests to avoid unnecessary network traffic.
 */

/**
 * Map to store pending requests by key
 */
const pendingRequests = new Map<string, Promise<any>>();

/**
 * Deduplicate API requests
 *
 * If a request with the same key is already in progress, return the existing promise.
 * Otherwise, execute the request and cache the promise.
 *
 * @param key - Unique identifier for the request
 * @param request - Function that returns a Promise (the actual API call)
 * @param ttl - Time to live in milliseconds (default: 5000ms)
 * @returns Promise<T> - The deduplicated request promise
 *
 * @example
 * ```typescript
 * // In a Pinia store
 * async function fetchStats() {
 *   isLoading.value = true;
 *   error.value = null;
 *
 *   try {
 *     const params = buildFilterParams();
 *     const cacheKey = `dashboard-stats-${JSON.stringify(params)}`;
 *
 *     // If this is called multiple times, only one request will be made
 *     const response = await dedupeRequest(
 *       cacheKey,
 *       () => api.get('/dashboard/stats', { params })
 *     );
 *
 *     stats.value = response.data;
 *   } catch (err) {
 *     error.value = 'Erro ao carregar estatísticas';
 *   } finally {
 *     isLoading.value = false;
 *   }
 * }
 * ```
 */
export function dedupeRequest<T>(
  key: string,
  request: () => Promise<T>,
  ttl: number = 5000
): Promise<T> {
  // If request is already pending, return the existing promise
  if (pendingRequests.has(key)) {
    if (import.meta.env.DEV) {
      console.log(`[Request Cache] Returning cached request for key: ${key}`);
    }
    return pendingRequests.get(key)!;
  }

  if (import.meta.env.DEV) {
    console.log(`[Request Cache] Creating new request for key: ${key}`);
  }

  // Create new promise and cache it
  const promise = request()
    .then((result) => {
      // Success: keep in cache for TTL, then remove
      setTimeout(() => {
        if (pendingRequests.get(key) === promise) {
          pendingRequests.delete(key);
          if (import.meta.env.DEV) {
            console.log(`[Request Cache] Expired cache for key: ${key}`);
          }
        }
      }, ttl);
      return result;
    })
    .catch((error) => {
      // Error: remove from cache immediately so it can be retried
      pendingRequests.delete(key);
      if (import.meta.env.DEV) {
        console.log(`[Request Cache] Error, removing cache for key: ${key}`);
      }
      throw error;
    });

  pendingRequests.set(key, promise);
  return promise;
}

/**
 * Clear a specific cached request
 *
 * @param key - The key of the request to clear
 *
 * @example
 * ```typescript
 * // Clear cache when data is mutated
 * async function updateProduct(id: number, data: ProductData) {
 *   await api.put(`/products/${id}`, data);
 *   clearRequestCache(`products-${id}`);
 *   clearRequestCache('products-list');
 * }
 * ```
 */
export function clearRequestCache(key: string): void {
  const deleted = pendingRequests.delete(key);
  if (import.meta.env.DEV && deleted) {
    console.log(`[Request Cache] Manually cleared cache for key: ${key}`);
  }
}

/**
 * Clear all cached requests
 *
 * Useful when logging out or when you need to invalidate all caches
 *
 * @example
 * ```typescript
 * function logout() {
 *   clearAllRequestCache();
 *   // ... rest of logout logic
 * }
 * ```
 */
export function clearAllRequestCache(): void {
  const count = pendingRequests.size;
  pendingRequests.clear();
  if (import.meta.env.DEV && count > 0) {
    console.log(`[Request Cache] Cleared all ${count} cached requests`);
  }
}

/**
 * Get cache statistics (useful for debugging)
 */
export function getCacheStats(): { size: number; keys: string[] } {
  return {
    size: pendingRequests.size,
    keys: Array.from(pendingRequests.keys()),
  };
}

/**
 * Build cache key from parameters
 * Helper function to create consistent cache keys
 *
 * @example
 * ```typescript
 * const cacheKey = buildCacheKey('dashboard-stats', { period: 'last_30_days' });
 * // Returns: "dashboard-stats:period=last_30_days"
 * ```
 */
export function buildCacheKey(prefix: string, params?: Record<string, any>): string {
  if (!params || Object.keys(params).length === 0) {
    return prefix;
  }

  // Sort keys for consistent cache keys
  const sortedParams = Object.keys(params)
    .sort()
    .map((key) => `${key}=${JSON.stringify(params[key])}`)
    .join('&');

  return `${prefix}:${sortedParams}`;
}
