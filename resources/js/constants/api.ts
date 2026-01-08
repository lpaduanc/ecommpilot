/**
 * API Constants
 *
 * Centralized API endpoint paths and HTTP status codes
 * to ensure consistency across the application.
 */

/**
 * Authentication endpoints
 */
export const AUTH_ENDPOINTS = {
  LOGIN: '/auth/login',
  REGISTER: '/auth/register',
  LOGOUT: '/auth/logout',
  FORGOT_PASSWORD: '/auth/forgot-password',
  RESET_PASSWORD: '/auth/reset-password',
  GET_USER: '/auth/user',
  UPDATE_PROFILE: '/auth/profile',
  UPDATE_PASSWORD: '/auth/password'
} as const;

/**
 * Dashboard endpoints
 */
export const DASHBOARD_ENDPOINTS = {
  STATS: '/dashboard/stats',
  REVENUE_CHART: '/dashboard/charts/revenue',
  ORDERS_STATUS_CHART: '/dashboard/charts/orders-status',
  TOP_PRODUCTS: '/dashboard/charts/top-products',
  PAYMENT_METHODS_CHART: '/dashboard/charts/payment-methods',
  CATEGORIES_CHART: '/dashboard/charts/categories',
  LOW_STOCK: '/dashboard/low-stock'
} as const;

/**
 * Products endpoints
 */
export const PRODUCTS_ENDPOINTS = {
  LIST: '/products',
  DETAIL: '/products/:id',
  PERFORMANCE: '/products/:id/performance'
} as const;

/**
 * Orders endpoints
 */
export const ORDERS_ENDPOINTS = {
  LIST: '/orders',
  DETAIL: '/orders/:id'
} as const;

/**
 * Integrations endpoints
 */
export const INTEGRATIONS_ENDPOINTS = {
  STORES: '/integrations/stores',
  MY_STORES: '/integrations/my-stores',
  SELECT_STORE: '/integrations/select-store/:storeId',
  NUVEMSHOP_CONNECT: '/integrations/nuvemshop/connect',
  NUVEMSHOP_CALLBACK: '/integrations/nuvemshop/callback',
  SYNC_STORE: '/integrations/stores/:storeId/sync',
  DISCONNECT_STORE: '/integrations/stores/:storeId'
} as const;

/**
 * Analysis (AI) endpoints
 */
export const ANALYSIS_ENDPOINTS = {
  CURRENT: '/analysis/current',
  REQUEST: '/analysis/request',
  HISTORY: '/analysis/history',
  DETAIL: '/analysis/:id',
  MARK_SUGGESTION_DONE: '/analysis/:analysisId/suggestions/:suggestionId/done'
} as const;

/**
 * Chat (AI) endpoints
 */
export const CHAT_ENDPOINTS = {
  CONVERSATION: '/chat/conversation',
  SEND_MESSAGE: '/chat/message',
  CLEAR_CONVERSATION: '/chat/conversation'
} as const;

/**
 * Admin endpoints
 */
export const ADMIN_ENDPOINTS = {
  STATS: '/admin/stats',
  CLIENTS_LIST: '/admin/clients',
  CREATE_CLIENT: '/admin/clients',
  CLIENT_DETAIL: '/admin/clients/:id',
  UPDATE_CLIENT: '/admin/clients/:id',
  DELETE_CLIENT: '/admin/clients/:id',
  TOGGLE_CLIENT_STATUS: '/admin/clients/:id/toggle-status',
  ADD_CREDITS: '/admin/clients/:id/add-credits',
  REMOVE_CREDITS: '/admin/clients/:id/remove-credits',
  RESET_PASSWORD: '/admin/clients/:id/reset-password',
  IMPERSONATE: '/admin/clients/:id/impersonate',

  // Admin Settings
  GET_AI_SETTINGS: '/admin/settings/ai',
  UPDATE_AI_SETTINGS: '/admin/settings/ai',
  TEST_AI_PROVIDER: '/admin/settings/ai/test'
} as const;

/**
 * Settings endpoints
 */
export const SETTINGS_ENDPOINTS = {
  GET_NOTIFICATIONS: '/settings/notifications',
  UPDATE_NOTIFICATIONS: '/settings/notifications'
} as const;

/**
 * All API endpoints combined
 */
export const API_ENDPOINTS = {
  AUTH: AUTH_ENDPOINTS,
  DASHBOARD: DASHBOARD_ENDPOINTS,
  PRODUCTS: PRODUCTS_ENDPOINTS,
  ORDERS: ORDERS_ENDPOINTS,
  INTEGRATIONS: INTEGRATIONS_ENDPOINTS,
  ANALYSIS: ANALYSIS_ENDPOINTS,
  CHAT: CHAT_ENDPOINTS,
  ADMIN: ADMIN_ENDPOINTS,
  SETTINGS: SETTINGS_ENDPOINTS
} as const;

/**
 * HTTP Status Codes
 * Standard HTTP status codes used throughout the application
 */
export const HTTP_STATUS = {
  // Success
  OK: 200,
  CREATED: 201,
  ACCEPTED: 202,
  NO_CONTENT: 204,

  // Redirection
  MOVED_PERMANENTLY: 301,
  FOUND: 302,
  NOT_MODIFIED: 304,

  // Client Errors
  BAD_REQUEST: 400,
  UNAUTHORIZED: 401,
  PAYMENT_REQUIRED: 402,
  FORBIDDEN: 403,
  NOT_FOUND: 404,
  METHOD_NOT_ALLOWED: 405,
  NOT_ACCEPTABLE: 406,
  REQUEST_TIMEOUT: 408,
  CONFLICT: 409,
  GONE: 410,
  UNPROCESSABLE_ENTITY: 422,
  TOO_MANY_REQUESTS: 429,

  // Server Errors
  INTERNAL_SERVER_ERROR: 500,
  NOT_IMPLEMENTED: 501,
  BAD_GATEWAY: 502,
  SERVICE_UNAVAILABLE: 503,
  GATEWAY_TIMEOUT: 504
} as const;

/**
 * HTTP Status Code Categories
 * Helper constants to check status code ranges
 */
export const HTTP_STATUS_CATEGORY = {
  isSuccess: (status: number): boolean => status >= 200 && status < 300,
  isRedirect: (status: number): boolean => status >= 300 && status < 400,
  isClientError: (status: number): boolean => status >= 400 && status < 500,
  isServerError: (status: number): boolean => status >= 500 && status < 600,
  isError: (status: number): boolean => status >= 400
} as const;

/**
 * Helper function to replace URL parameters
 * @param endpoint - Endpoint template with :param placeholders
 * @param params - Object with parameter values
 * @returns Formatted endpoint URL
 *
 * @example
 * buildEndpoint(PRODUCTS_ENDPOINTS.DETAIL, { id: 123 })
 * // Returns: '/products/123'
 */
export function buildEndpoint(endpoint: string, params: Record<string, string | number>): string {
  let url = endpoint;

  Object.entries(params).forEach(([key, value]) => {
    url = url.replace(`:${key}`, String(value));
  });

  return url;
}

/**
 * Type helpers for API endpoints
 */
export type AuthEndpoint = typeof AUTH_ENDPOINTS[keyof typeof AUTH_ENDPOINTS];
export type DashboardEndpoint = typeof DASHBOARD_ENDPOINTS[keyof typeof DASHBOARD_ENDPOINTS];
export type ProductsEndpoint = typeof PRODUCTS_ENDPOINTS[keyof typeof PRODUCTS_ENDPOINTS];
export type OrdersEndpoint = typeof ORDERS_ENDPOINTS[keyof typeof ORDERS_ENDPOINTS];
export type IntegrationsEndpoint = typeof INTEGRATIONS_ENDPOINTS[keyof typeof INTEGRATIONS_ENDPOINTS];
export type AnalysisEndpoint = typeof ANALYSIS_ENDPOINTS[keyof typeof ANALYSIS_ENDPOINTS];
export type ChatEndpoint = typeof CHAT_ENDPOINTS[keyof typeof CHAT_ENDPOINTS];
export type AdminEndpoint = typeof ADMIN_ENDPOINTS[keyof typeof ADMIN_ENDPOINTS];
export type SettingsEndpoint = typeof SETTINGS_ENDPOINTS[keyof typeof SETTINGS_ENDPOINTS];
export type HttpStatus = typeof HTTP_STATUS[keyof typeof HTTP_STATUS];
