/**
 * Auth Store
 *
 * Manages authentication state with standardized error handling.
 *
 * Example of using handleApiCall for consistent error handling patterns.
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';
import { handleApiCall, type Result, type ApiError } from '../utils/apiHelpers';
import { clearAllRequestCache } from '../utils/requestCache';

/**
 * Types
 */
interface User {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'client';
  permissions: string[];
  active_store_id: number | null;
  must_change_password?: boolean;
}

interface LoginCredentials {
  email: string;
  password: string;
  remember?: boolean;
}

interface RegisterData {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

interface ProfileData {
  name: string;
  email: string;
}

interface PasswordData {
  current_password: string;
  password: string;
  password_confirmation: string;
}

interface AuthResponse {
  token: string;
  user: User;
}

export const useAuthStore = defineStore('auth', () => {
  /**
   * State
   */
  const user = ref<User | null>(null);
  const token = ref<string | null>(localStorage.getItem('token'));
  const isInitialized = ref(false);
  const isLoading = ref(false);

  /**
   * Computed
   */
  const isAuthenticated = computed(() => !!user.value && !!token.value);
  const isAdmin = computed(() => user.value?.role === 'admin');
  const userName = computed(() => user.value?.name || '');
  const userEmail = computed(() => user.value?.email || '');
  const userPermissions = computed(() => user.value?.permissions || []);
  const mustChangePassword = computed(() => user.value?.must_change_password || false);

  /**
   * Helpers
   */
  function hasPermission(permission: string): boolean {
    if (isAdmin.value) return true;
    return userPermissions.value.includes(permission);
  }

  function hasAnyPermission(permissions: string[]): boolean {
    if (isAdmin.value) return true;
    return permissions.some((p) => userPermissions.value.includes(p));
  }

  /**
   * Initialize auth state
   */
  async function initialize(): Promise<void> {
    if (isInitialized.value) return;

    if (token.value) {
      try {
        await fetchUser();
      } catch {
        logout();
      }
    }

    isInitialized.value = true;
  }

  /**
   * Login user
   *
   * EXAMPLE: Using handleApiCall with proper error handling
   */
  async function login(credentials: LoginCredentials): Promise<Result<User>> {
    isLoading.value = true;

    const result = await handleApiCall<AuthResponse>(
      () => api.post('/auth/login', credentials)
    );

    isLoading.value = false;

    if (result.success) {
      // Success: set token and user
      token.value = result.data.token;
      user.value = result.data.user;

      // Store token in localStorage
      localStorage.setItem('token', token.value);

      // Set authorization header for future requests
      api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;

      return {
        success: true,
        data: result.data.user,
      };
    }

    // Error: return the error
    return result as Result<User>;
  }

  /**
   * Register new user
   */
  async function register(userData: RegisterData): Promise<Result<User>> {
    isLoading.value = true;

    const result = await handleApiCall<AuthResponse>(
      () => api.post('/auth/register', userData)
    );

    isLoading.value = false;

    if (result.success) {
      token.value = result.data.token;
      user.value = result.data.user;

      localStorage.setItem('token', token.value);
      api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;

      return {
        success: true,
        data: result.data.user,
      };
    }

    return result as Result<User>;
  }

  /**
   * Fetch current user data
   */
  async function fetchUser(): Promise<Result<User>> {
    const result = await handleApiCall<{ user: User }>(
      () => api.get('/auth/user')
    );

    if (result.success) {
      user.value = result.data.user;
      return {
        success: true,
        data: result.data.user,
      };
    }

    // If failed to fetch user, logout
    logout();
    throw new Error(result.error.message);
  }

  /**
   * Request password reset
   */
  async function forgotPassword(email: string): Promise<Result<void>> {
    isLoading.value = true;

    const result = await handleApiCall<void>(
      () => api.post('/auth/forgot-password', { email })
    );

    isLoading.value = false;
    return result;
  }

  /**
   * Reset password with token
   */
  async function resetPassword(data: {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
  }): Promise<Result<void>> {
    isLoading.value = true;

    const result = await handleApiCall<void>(
      () => api.post('/auth/reset-password', data)
    );

    isLoading.value = false;
    return result;
  }

  /**
   * Update user profile
   */
  async function updateProfile(profileData: ProfileData): Promise<Result<User>> {
    isLoading.value = true;

    const result = await handleApiCall<{ user: User }>(
      () => api.put('/auth/profile', profileData)
    );

    isLoading.value = false;

    if (result.success) {
      user.value = result.data.user;
      return {
        success: true,
        data: result.data.user,
      };
    }

    return result as Result<User>;
  }

  /**
   * Update user password
   */
  async function updatePassword(passwordData: PasswordData): Promise<Result<void>> {
    isLoading.value = true;

    const result = await handleApiCall<void>(
      () => api.put('/auth/password', passwordData)
    );

    isLoading.value = false;

    if (result.success && user.value) {
      // Clear must_change_password flag if it exists
      user.value.must_change_password = false;
    }

    return result;
  }

  /**
   * Logout user (client-side only)
   */
  function logout(): void {
    user.value = null;
    token.value = null;
    localStorage.removeItem('token');
    delete api.defaults.headers.common['Authorization'];

    // Clear all request caches
    clearAllRequestCache();
  }

  /**
   * Logout user (with server-side invalidation)
   */
  async function logoutFromServer(): Promise<void> {
    try {
      await api.post('/auth/logout');
    } catch (error) {
      // Ignore errors - logout locally anyway
      console.error('Error during server logout:', error);
    } finally {
      logout();
    }
  }

  /**
   * Set authorization token on axios if exists
   */
  if (token.value) {
    api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
  }

  return {
    // State
    user,
    token,
    isInitialized,
    isLoading,

    // Computed
    isAuthenticated,
    isAdmin,
    userName,
    userEmail,
    userPermissions,
    mustChangePassword,

    // Helpers
    hasPermission,
    hasAnyPermission,

    // Actions
    initialize,
    login,
    register,
    fetchUser,
    forgotPassword,
    resetPassword,
    updateProfile,
    updatePassword,
    logout,
    logoutFromServer,
  };
});
