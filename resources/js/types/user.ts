/**
 * User Type Definitions
 *
 * Defines all user-related TypeScript interfaces and types for the ecommpilot application.
 */

/**
 * User role enum
 */
export type UserRole = 'admin' | 'client';

/**
 * Complete User interface representing authenticated user data
 */
export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  permissions: string[];
  active_store_id: number | null;
  ai_credits: number;
  must_change_password?: boolean;
  email_verified_at?: string | null;
  created_at?: string;
  updated_at?: string;
}

/**
 * Login credentials interface
 */
export interface LoginCredentials {
  email: string;
  password: string;
  remember?: boolean;
}

/**
 * Registration data interface
 */
export interface RegisterData {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

/**
 * Password reset request interface
 */
export interface PasswordResetRequest {
  email: string;
}

/**
 * Password reset interface
 */
export interface PasswordReset {
  email: string;
  password: string;
  password_confirmation: string;
  token: string;
}

/**
 * Password change interface (for authenticated users)
 */
export interface PasswordChange {
  current_password: string;
  password: string;
  password_confirmation: string;
}

/**
 * User profile update interface
 */
export interface UserProfileUpdate {
  name?: string;
  email?: string;
}
