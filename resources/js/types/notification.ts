/**
 * Notification Type Definitions
 *
 * Defines all notification-related TypeScript interfaces.
 */

/**
 * Notification type enum
 */
export type NotificationType = 'success' | 'error' | 'warning' | 'info';

/**
 * Notification interface
 */
export interface Notification {
  id: string;
  type: NotificationType;
  message: string;
  duration?: number;
  timestamp: number;
}

/**
 * Toast notification config
 */
export interface ToastConfig {
  message: string;
  type?: NotificationType;
  duration?: number;
  closable?: boolean;
  icon?: any;
}

/**
 * Notification duration constants
 */
export const NOTIFICATION_DURATION = {
  SHORT: 3000,
  MEDIUM: 5000,
  LONG: 7000,
  PERMANENT: 0,
} as const;
