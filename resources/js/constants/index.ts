/**
 * Constants Index
 *
 * Central export point for all application constants.
 * This file makes it easier to import multiple constants from a single location.
 *
 * @example
 * import { NotificationType, ROUTE_NAMES, API_ENDPOINTS } from '@/constants';
 */

// Notification constants
export {
  NotificationType,
  NOTIFICATION_DURATION,
  type NotificationDuration
} from './notifications';

// Stock constants
export {
  StockStatus,
  STOCK_THRESHOLDS,
  STOCK_STATUS_CONFIG,
  getStockStatus,
  type StockStatusConfig
} from './stock';

// Route constants
export {
  AUTH_ROUTES,
  APP_ROUTES,
  ADMIN_ROUTES,
  SPECIAL_ROUTES,
  ROUTE_NAMES,
  ROUTE_PATHS,
  ROUTE_PERMISSIONS,
  type AuthRouteName,
  type AppRouteName,
  type AdminRouteName,
  type RouteName
} from './routes';

// API constants
export {
  AUTH_ENDPOINTS,
  DASHBOARD_ENDPOINTS,
  PRODUCTS_ENDPOINTS,
  ORDERS_ENDPOINTS,
  INTEGRATIONS_ENDPOINTS,
  ANALYSIS_ENDPOINTS,
  CHAT_ENDPOINTS,
  ADMIN_ENDPOINTS,
  SETTINGS_ENDPOINTS,
  API_ENDPOINTS,
  HTTP_STATUS,
  HTTP_STATUS_CATEGORY,
  buildEndpoint,
  type AuthEndpoint,
  type DashboardEndpoint,
  type ProductsEndpoint,
  type OrdersEndpoint,
  type IntegrationsEndpoint,
  type AnalysisEndpoint,
  type ChatEndpoint,
  type AdminEndpoint,
  type SettingsEndpoint,
  type HttpStatus
} from './api';
