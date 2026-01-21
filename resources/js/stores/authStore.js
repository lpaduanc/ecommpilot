import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const token = ref(localStorage.getItem('token'));
    const isInitialized = ref(false);
    const isLoading = ref(false);
    
    const isAuthenticated = computed(() => !!user.value && !!token.value);
    const isAdmin = computed(() => user.value?.role === 'admin');
    const userName = computed(() => user.value?.name || '');
    const userEmail = computed(() => user.value?.email || '');
    const userPermissions = computed(() => user.value?.permissions || []);
    const planLimits = computed(() => user.value?.plan_limits || null);

    // Plan feature checks
    const canAccessAiAnalysis = computed(() => {
        if (isAdmin.value) return true;
        return planLimits.value?.has_ai_analysis ?? false;
    });

    const canAccessAiChat = computed(() => {
        if (isAdmin.value) return true;
        return planLimits.value?.has_ai_chat ?? false;
    });

    const canAccessCustomDashboards = computed(() => {
        if (isAdmin.value) return true;
        return planLimits.value?.has_custom_dashboards ?? false;
    });

    const canAccessExternalIntegrations = computed(() => {
        if (isAdmin.value) return true;
        return planLimits.value?.has_external_integrations ?? false;
    });
    
    function hasPermission(permission) {
        if (isAdmin.value) return true;
        return userPermissions.value.includes(permission);
    }
    
    function hasAnyPermission(permissions) {
        if (isAdmin.value) return true;
        return permissions.some(p => userPermissions.value.includes(p));
    }
    
    async function initialize() {
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
    
    async function login(credentials) {
        isLoading.value = true;
        
        try {
            const response = await api.post('/auth/login', credentials);
            
            token.value = response.data.token;
            user.value = response.data.user;
            
            localStorage.setItem('token', token.value);
            api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
            
            return { success: true };
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Credenciais inválidas',
                errors: error.response?.data?.errors || {},
            };
        } finally {
            isLoading.value = false;
        }
    }
    
    async function register(userData) {
        isLoading.value = true;
        
        try {
            const response = await api.post('/auth/register', userData);
            
            token.value = response.data.token;
            user.value = response.data.user;
            
            localStorage.setItem('token', token.value);
            api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
            
            return { success: true };
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Erro ao criar conta',
                errors: error.response?.data?.errors || {},
            };
        } finally {
            isLoading.value = false;
        }
    }
    
    async function fetchUser() {
        try {
            const response = await api.get('/auth/user');
            user.value = response.data.user;
        } catch (error) {
            throw error;
        }
    }
    
    async function forgotPassword(email) {
        isLoading.value = true;
        
        try {
            await api.post('/auth/forgot-password', { email });
            return { success: true };
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Erro ao enviar e-mail',
            };
        } finally {
            isLoading.value = false;
        }
    }
    
    async function resetPassword(data) {
        isLoading.value = true;
        
        try {
            await api.post('/auth/reset-password', data);
            return { success: true };
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Erro ao redefinir senha',
                errors: error.response?.data?.errors || {},
            };
        } finally {
            isLoading.value = false;
        }
    }
    
    async function updateProfile(profileData) {
        isLoading.value = true;
        
        try {
            const response = await api.put('/auth/profile', profileData);
            user.value = response.data.user;
            return { success: true };
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Erro ao atualizar perfil',
                errors: error.response?.data?.errors || {},
            };
        } finally {
            isLoading.value = false;
        }
    }
    
    async function updatePassword(passwordData) {
        isLoading.value = true;
        
        try {
            await api.put('/auth/password', passwordData);
            return { success: true };
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Erro ao atualizar senha',
                errors: error.response?.data?.errors || {},
            };
        } finally {
            isLoading.value = false;
        }
    }
    
    function logout() {
        user.value = null;
        token.value = null;
        localStorage.removeItem('token');
        delete api.defaults.headers.common['Authorization'];
    }
    
    async function logoutFromServer() {
        try {
            await api.post('/auth/logout');
        } catch {
            // Ignore errors
        }
        logout();
    }
    
    // Set token on axios if exists
    if (token.value) {
        api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`;
    }
    
    return {
        user,
        token,
        isInitialized,
        isLoading,
        isAuthenticated,
        isAdmin,
        userName,
        userEmail,
        userPermissions,
        planLimits,
        canAccessAiAnalysis,
        canAccessAiChat,
        canAccessCustomDashboards,
        canAccessExternalIntegrations,
        hasPermission,
        hasAnyPermission,
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

