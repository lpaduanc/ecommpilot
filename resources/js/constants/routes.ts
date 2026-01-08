/**
 * Route Names Constants
 *
 * Centralized route name constants to avoid magic strings
 * and enable type-safe routing across the application.
 */

/**
 * Authentication route names
 */
export const AUTH_ROUTES = {
  LOGIN: 'login',
  REGISTER: 'register',
  FORGOT_PASSWORD: 'forgot-password',
  RESET_PASSWORD: 'reset-password'
} as const;

/**
 * Main application route names
 */
export const APP_ROUTES = {
  DASHBOARD: 'dashboard',
  PRODUCTS: 'products',
  ORDERS: 'orders',
  ANALYSIS: 'analysis',
  CHAT: 'chat',
  INTEGRATIONS: 'integrations',
  SETTINGS: 'settings'
} as const;

/**
 * Admin panel route names
 */
export const ADMIN_ROUTES = {
  DASHBOARD: 'admin-dashboard',
  USERS: 'admin-users',
  USER_DETAIL: 'admin-user-detail',
  CLIENTS: 'admin-clients',
  CLIENT_DETAIL: 'admin-client-detail',
  SETTINGS: 'admin-settings'
} as const;

/**
 * Special route names
 */
export const SPECIAL_ROUTES = {
  NOT_FOUND: 'not-found'
} as const;

/**
 * All route names combined
 * Useful for type checking and validation
 */
export const ROUTE_NAMES = {
  ...AUTH_ROUTES,
  ...APP_ROUTES,
  ...ADMIN_ROUTES,
  ...SPECIAL_ROUTES
} as const;

/**
 * Route paths
 * Useful for programmatic navigation with dynamic parameters
 */
export const ROUTE_PATHS = {
  // Auth
  LOGIN: '/login',
  REGISTER: '/register',
  FORGOT_PASSWORD: '/forgot-password',
  RESET_PASSWORD: '/reset-password/:token',

  // App
  DASHBOARD: '/',
  PRODUCTS: '/products',
  ORDERS: '/orders',
  ANALYSIS: '/analysis',
  CHAT: '/chat',
  INTEGRATIONS: '/integrations',
  SETTINGS: '/settings',

  // Admin
  ADMIN_DASHBOARD: '/admin',
  ADMIN_USERS: '/admin/users',
  ADMIN_USER_DETAIL: '/admin/users/:id',
  ADMIN_CLIENTS: '/admin/clients',
  ADMIN_CLIENT_DETAIL: '/admin/clients/:id',
  ADMIN_SETTINGS: '/admin/settings'
} as const;

/**
 * Permissions required for protected routes
 */
export const ROUTE_PERMISSIONS = {
  ANALYSIS: 'analytics.view',
  CHAT: 'chat.use',
  INTEGRATIONS: 'integrations.manage',
  ADMIN_DASHBOARD: 'admin.access',
  ADMIN_USERS: 'users.view',
  ADMIN_CLIENTS: 'admin.access',
  ADMIN_SETTINGS: 'admin.access'
} as const;

/**
 * Type helpers for route names
 */
export type AuthRouteName = typeof AUTH_ROUTES[keyof typeof AUTH_ROUTES];
export type AppRouteName = typeof APP_ROUTES[keyof typeof APP_ROUTES];
export type AdminRouteName = typeof ADMIN_ROUTES[keyof typeof ADMIN_ROUTES];
export type RouteName = typeof ROUTE_NAMES[keyof typeof ROUTE_NAMES];
