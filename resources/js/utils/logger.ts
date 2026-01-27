/**
 * Safe logging utility
 *
 * Only logs in development mode to prevent exposing sensitive data in production.
 * Use this instead of console.log/console.error directly.
 */

const isDev = import.meta.env.DEV;

export const logger = {
  /**
   * Log general information (only in dev)
   */
  log(...args: any[]): void {
    if (isDev) {
      console.log(...args);
    }
  },

  /**
   * Log errors (always logged, but sanitized in production)
   */
  error(...args: any[]): void {
    if (isDev) {
      console.error(...args);
    } else {
      // In production, only log generic error message without details
      console.error('An error occurred. Please check the application logs.');
    }
  },

  /**
   * Log warnings (only in dev)
   */
  warn(...args: any[]): void {
    if (isDev) {
      console.warn(...args);
    }
  },

  /**
   * Log debug information (only in dev)
   */
  debug(...args: any[]): void {
    if (isDev) {
      console.debug(...args);
    }
  },

  /**
   * Log information messages (only in dev)
   */
  info(...args: any[]): void {
    if (isDev) {
      console.info(...args);
    }
  },
};

export default logger;
