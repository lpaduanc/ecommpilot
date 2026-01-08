/**
 * Retry Request Utilities
 *
 * This module provides retry logic for failed API requests with
 * exponential backoff and configurable retry conditions.
 */

/**
 * Retry options configuration
 */
export interface RetryOptions {
  /** Maximum number of retry attempts (default: 3) */
  maxRetries?: number;

  /** Initial delay between retries in milliseconds (default: 1000) */
  delay?: number;

  /** Use exponential backoff (delay * 2^attempt) (default: true) */
  backoff?: boolean;

  /** HTTP status codes that should trigger a retry (default: [408, 429, 500, 502, 503, 504]) */
  retryOn?: number[];

  /** Callback function called before each retry attempt */
  onRetry?: (attempt: number, error: any) => void;
}

/**
 * Default retry options
 */
const DEFAULT_RETRY_OPTIONS: Required<RetryOptions> = {
  maxRetries: 3,
  delay: 1000,
  backoff: true,
  retryOn: [408, 429, 500, 502, 503, 504],
  onRetry: () => {},
};

/**
 * Retry a failed request with exponential backoff
 *
 * @param fn - Function that returns a Promise (the API call to retry)
 * @param options - Retry configuration options
 * @returns Promise<T> - The result of the successful request
 * @throws The last error if all retry attempts fail
 *
 * @example
 * ```typescript
 * // Basic usage
 * const data = await retryRequest(
 *   () => api.get('/dashboard/stats'),
 *   { maxRetries: 3, delay: 1000 }
 * );
 *
 * // With custom retry logic
 * const data = await retryRequest(
 *   () => api.post('/analysis/request'),
 *   {
 *     maxRetries: 5,
 *     delay: 2000,
 *     backoff: true,
 *     retryOn: [500, 502, 503, 504], // Only retry on server errors
 *     onRetry: (attempt, error) => {
 *       console.log(`Retry attempt ${attempt}:`, error.message);
 *     }
 *   }
 * );
 * ```
 */
export async function retryRequest<T>(
  fn: () => Promise<T>,
  options: RetryOptions = {}
): Promise<T> {
  const config = { ...DEFAULT_RETRY_OPTIONS, ...options };
  let lastError: any;

  for (let attempt = 0; attempt <= config.maxRetries; attempt++) {
    try {
      // Attempt the request
      return await fn();
    } catch (error: any) {
      lastError = error;

      // Check if we should retry
      const status = error.response?.status;
      const shouldRetry = !status || config.retryOn.includes(status);

      // Don't retry on client errors (4xx except 408 and 429)
      const isClientError = status && status >= 400 && status < 500;
      const isRetryableClientError = status === 408 || status === 429;
      if (isClientError && !isRetryableClientError) {
        throw error;
      }

      // If this was the last attempt or shouldn't retry, throw the error
      if (attempt >= config.maxRetries || !shouldRetry) {
        if (import.meta.env.DEV) {
          console.error(
            `[Retry Request] Failed after ${attempt} attempts:`,
            error.message
          );
        }
        throw error;
      }

      // Calculate wait time with exponential backoff
      const waitTime = config.backoff
        ? config.delay * Math.pow(2, attempt)
        : config.delay;

      if (import.meta.env.DEV) {
        console.warn(
          `[Retry Request] Attempt ${attempt + 1}/${config.maxRetries} failed. ` +
            `Retrying in ${waitTime}ms...`,
          { status, message: error.message }
        );
      }

      // Call onRetry callback
      config.onRetry(attempt + 1, error);

      // Wait before retrying
      await delay(waitTime);
    }
  }

  // This should never be reached, but TypeScript needs it
  throw lastError!;
}

/**
 * Create a retry wrapper function with pre-configured options
 *
 * Useful for creating reusable retry configurations
 *
 * @example
 * ```typescript
 * // Create a custom retry function for critical operations
 * const criticalRetry = createRetryWrapper({
 *   maxRetries: 5,
 *   delay: 2000,
 *   backoff: true,
 * });
 *
 * // Use it in multiple places
 * const stats = await criticalRetry(() => api.get('/dashboard/stats'));
 * const analysis = await criticalRetry(() => api.post('/analysis/request'));
 * ```
 */
export function createRetryWrapper(defaultOptions: RetryOptions) {
  return <T>(fn: () => Promise<T>, options?: RetryOptions): Promise<T> => {
    return retryRequest(fn, { ...defaultOptions, ...options });
  };
}

/**
 * Delay helper function
 */
function delay(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Check if an error is retryable
 *
 * @param error - The error to check
 * @param retryOn - Array of status codes that should be retried
 * @returns boolean - True if the error should be retried
 */
export function isRetryableError(error: any, retryOn: number[] = DEFAULT_RETRY_OPTIONS.retryOn): boolean {
  const status = error.response?.status;

  // Network errors (no status) are retryable
  if (!status) {
    return true;
  }

  // Check if status is in the retry list
  return retryOn.includes(status);
}

/**
 * Get retry delay with jitter
 *
 * Adds randomness to retry delays to prevent thundering herd problem
 *
 * @param baseDelay - Base delay in milliseconds
 * @param attempt - Current attempt number
 * @param useBackoff - Whether to use exponential backoff
 * @param jitterFactor - Jitter factor (0-1, default: 0.1 = ±10%)
 * @returns number - Delay in milliseconds with jitter applied
 */
export function getRetryDelayWithJitter(
  baseDelay: number,
  attempt: number,
  useBackoff: boolean = true,
  jitterFactor: number = 0.1
): number {
  // Calculate base delay with optional exponential backoff
  const delay = useBackoff ? baseDelay * Math.pow(2, attempt) : baseDelay;

  // Add jitter (±jitterFactor)
  const jitter = delay * jitterFactor * (Math.random() * 2 - 1);

  return Math.floor(delay + jitter);
}
