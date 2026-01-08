/**
 * Central Types Export File
 *
 * This file exports all TypeScript types used throughout the ecommpilot application.
 * Import types from here for better organization and maintainability.
 *
 * @example
 * ```typescript
 * import type { User, LoginCredentials, ApiResponse } from '@/types';
 * ```
 */

// API Types
export type {
  ApiResponse,
  PaginatedResponse,
  ApiError,
  Result,
  ValidationError,
  HttpMethod,
  RequestConfig,
  UploadProgressCallback,
  UploadConfig,
} from './api';

// User Types
export type {
  User,
  UserRole,
  LoginCredentials,
  RegisterData,
  PasswordResetRequest,
  PasswordReset,
  PasswordChange,
  UserProfileUpdate,
} from './user';

// Store Types
export type {
  Store,
  Platform,
  SyncStatus,
  StoreStats,
  StoreConnectionConfig,
} from './store';

// Product Types
export type {
  SyncedProduct,
  ProductImage,
  ProductVariant,
  ProductFilter,
  LowStockProduct,
  ProductStats,
} from './product';

// Order Types
export type {
  SyncedOrder,
  OrderStatus,
  PaymentStatus,
  PaymentMethod,
  OrderItem,
  ShippingAddress,
  OrderFilter,
  OrderStats,
  OrdersByStatus,
} from './order';

// Customer Types
export type {
  SyncedCustomer,
  CustomerFilter,
  CustomerStats,
  TopCustomer,
} from './customer';

// Dashboard Types
export type {
  DashboardStats,
  RevenueDataPoint,
  PaymentMethodData,
  CategoryData,
  TopProduct,
  DateRange,
  DashboardFilters,
  StatCard,
} from './dashboard';

// Analysis Types
export type {
  Analysis,
  AnalysisStatus,
  Suggestion,
  SuggestionPriority,
  Alert,
  Opportunity,
  AnalysisRequest,
  AnalysisFilter,
  AnalysisSummary,
} from './analysis';

// Chat Types
export type {
  ChatMessage,
  ChatConversation,
  MessageRole,
  NewMessagePayload,
  ChatFilter,
  ChatStats,
  TypingState,
} from './chat';

// Notification Types
export type {
  Notification,
  NotificationType,
  ToastConfig,
} from './notification';

export { NOTIFICATION_DURATION } from './notification';
