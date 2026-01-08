/**
 * Notification System Constants
 *
 * Centralized constants for notification types and durations
 * to ensure consistency across the application.
 */

/**
 * Notification types enum
 * Used to define the visual style and severity of notifications
 */
export enum NotificationType {
  Success = 'success',
  Error = 'error',
  Warning = 'warning',
  Info = 'info'
}

/**
 * Notification duration constants (in milliseconds)
 *
 * - SHORT: Quick feedback for minor actions (3s)
 * - MEDIUM: Standard duration for most notifications (5s)
 * - LONG: Important messages that need more attention (7s)
 * - PERMANENT: Notifications that require manual dismissal (0 = no auto-close)
 */
export const NOTIFICATION_DURATION = {
  SHORT: 3000,
  MEDIUM: 5000,
  LONG: 7000,
  PERMANENT: 0
} as const;

/**
 * Type helper for notification duration values
 */
export type NotificationDuration = typeof NOTIFICATION_DURATION[keyof typeof NOTIFICATION_DURATION];
