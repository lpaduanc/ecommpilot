/**
 * API Service Configuration
 *
 * This module configures axios instances with interceptors for:
 * - Automatic retry with exponential backoff
 * - Request cancellation
 * - Timeout handling
 * - Error handling and notifications
 * - CSRF token management
 */

import axios, { AxiosInstance, AxiosError, InternalAxiosRequestConfig, AxiosResponse, CancelTokenSource } from 'axios';
import { useNotificationStore } from '../stores/notificationStore';
import { useAuthStore } from '../stores/authStore';
import { retryRequest } from '../utils/retryRequest';
import { logger } from '../utils/logger';

/**
 * Interface for retry configuration on axios requests
 */
interface RetryConfig extends InternalAxiosRequestConfig {
  _retry?: boolean;
  _retryCount?: number;
}

/**
 * Map to store cancel tokens for cancelable requests
 */
const cancelTokens = new Map<string, CancelTokenSource>();

/**
 * Default timeout in milliseconds
 */
const DEFAULT_TIMEOUT = 30000; // 30 seconds

/**
 * Create base axios instance with common configuration
 */
function createApiInstance(timeout: number = DEFAULT_TIMEOUT): AxiosInstance {
  const instance = axios.create({
    baseURL: '/api',
    timeout,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
  });

  return instance;
}

/**
 * Main API instance with default timeout (30s)
 */
const api = createApiInstance();

/**
 * Request interceptor for adding CSRF token and authorization
 */
api.interceptors.request.use(
  async (config: InternalAxiosRequestConfig) => {
    // For methods that modify data, ensure CSRF token is present
    if (config.method && ['post', 'put', 'patch', 'delete'].includes(config.method)) {
      // Get CSRF token from cookie
      const csrfToken = document.cookie
        .split('; ')
        .find((row) => row.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];

      if (csrfToken) {
        config.headers['X-XSRF-TOKEN'] = decodeURIComponent(csrfToken);
      } else {
        // If no CSRF token, request one from Laravel Sanctum
        try {
          await axios.get('/sanctum/csrf-cookie');
        } catch (error) {
          logger.warn('[API] Failed to fetch CSRF cookie:', error);
        }
      }
    }

    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

/**
 * Response interceptor for error handling and automatic retry
 */
api.interceptors.response.use(
  (response: AxiosResponse) => response,
  async (error: AxiosError) => {
    const config = error.config as RetryConfig;
    const notificationStore = useNotificationStore();

    // If request was cancelled, don't show error
    if (axios.isCancel(error)) {
      if (import.meta.env.DEV) {
        logger.log('[API] Request cancelled:', error.message);
      }
      return Promise.reject(error);
    }

    // Handle network errors and retryable status codes
    const shouldRetry = !config._retry && isRetryableError(error);
    const isTimeout = error.code === 'ECONNABORTED';

    if (shouldRetry && !isTimeout) {
      config._retry = true;

      if (import.meta.env.DEV) {
        logger.log('[API] Retrying request:', config.url);
      }

      return retryRequest(() => api.request(config), {
        maxRetries: 2,
        delay: 1000,
        backoff: true,
        retryOn: [408, 429, 500, 502, 503, 504],
      });
    }

    // Handle specific error responses
    if (error.response) {
      switch (error.response.status) {
        case 401:
          // Unauthorized - logout user
          const authStore = useAuthStore();
          authStore.logout();
          window.location.href = '/login';
          break;

        case 403:
          notificationStore.error('Você não tem permissão para realizar esta ação.');
          break;

        case 404:
          notificationStore.error('Recurso não encontrado.');
          break;

        case 422:
          // Validation error - handled by the calling code
          // Don't show generic notification
          break;

        case 429:
          notificationStore.warning('Muitas tentativas. Por favor, aguarde um momento.');
          break;

        case 500:
          notificationStore.error('Erro interno do servidor. Tente novamente mais tarde.');
          break;

        case 503:
          notificationStore.error('Serviço temporariamente indisponível. Tente novamente em breve.');
          break;

        default:
          // Don't show error for retryable errors that will be retried
          if (!shouldRetry) {
            notificationStore.error('Ocorreu um erro. Tente novamente.');
          }
      }
    } else if (error.request) {
      // Network error
      if (isTimeout) {
        notificationStore.error('Tempo de resposta esgotado. Verifique sua conexão.');
      } else {
        notificationStore.error('Erro de conexão. Verifique sua internet.');
      }
    }

    return Promise.reject(error);
  }
);

/**
 * Check if error is retryable
 */
function isRetryableError(error: AxiosError): boolean {
  const status = error.response?.status;

  // Network errors (no response) are retryable
  if (!error.response) {
    return true;
  }

  // Only retry specific status codes
  return [408, 429, 500, 502, 503, 504].includes(status || 0);
}

/**
 * Create a cancelable request
 *
 * @param key - Unique key to identify the request
 * @returns Object with cancelToken and cleanup function
 *
 * @example
 * ```typescript
 * async function fetchStats() {
 *   const { cancelToken, cleanup } = createCancelableRequest('dashboard-stats');
 *
 *   try {
 *     const response = await api.get('/dashboard/stats', { cancelToken });
 *     stats.value = response.data;
 *   } catch (error) {
 *     if (!axios.isCancel(error)) {
 *       console.error('Error:', error);
 *     }
 *   } finally {
 *     cleanup();
 *   }
 * }
 * ```
 */
export function createCancelableRequest(key: string) {
  // Cancel any existing request with the same key
  if (cancelTokens.has(key)) {
    const existingSource = cancelTokens.get(key)!;
    existingSource.cancel(`Request cancelled: new request for key "${key}" initiated`);
    cancelTokens.delete(key);
  }

  // Create new cancel token
  const source = axios.CancelToken.source();
  cancelTokens.set(key, source);

  return {
    cancelToken: source.token,
    cleanup: () => {
      cancelTokens.delete(key);
    },
    cancel: (message?: string) => {
      source.cancel(message || `Request cancelled for key: ${key}`);
      cancelTokens.delete(key);
    },
  };
}

/**
 * Cancel all pending requests
 *
 * Useful when logging out or navigating away from a page
 */
export function cancelAllRequests(message: string = 'All requests cancelled'): void {
  cancelTokens.forEach((source, key) => {
    source.cancel(`${message} (key: ${key})`);
  });
  cancelTokens.clear();

  if (import.meta.env.DEV) {
    logger.log('[API] Cancelled all pending requests');
  }
}

/**
 * API instances with different timeout configurations
 */
export const apiWithCustomTimeout = {
  /** Short timeout (5 seconds) - for quick operations like autocomplete */
  short: createApiInstance(5000),

  /** Medium timeout (15 seconds) - for standard operations */
  medium: createApiInstance(15000),

  /** Long timeout (60 seconds) - for heavy operations like AI analysis */
  long: createApiInstance(60000),

  /** Extra long timeout (120 seconds) - for very heavy operations */
  extraLong: createApiInstance(120000),
};

// Apply the same interceptors to custom timeout instances
[apiWithCustomTimeout.short, apiWithCustomTimeout.medium, apiWithCustomTimeout.long, apiWithCustomTimeout.extraLong].forEach(
  (instance) => {
    instance.interceptors.request = api.interceptors.request;
    instance.interceptors.response = api.interceptors.response;
  }
);

/**
 * Default export - main API instance
 */
export default api;

/**
 * Named exports for convenience
 */
export { api, axios };
