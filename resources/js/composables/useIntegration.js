import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useIntegrationStore } from '../stores/integrationStore';
import { useNotificationStore } from '../stores/notificationStore';
import { useAuthStore } from '../stores/authStore';
import { useDashboardStore } from '../stores/dashboardStore';
import { logger } from '../utils/logger';

/**
 * Composable for managing integrations and OAuth flows
 *
 * @example
 * const {
 *   stores,
 *   isLoading,
 *   connectPlatform,
 *   syncStore,
 *   disconnectStore
 * } = useIntegration();
 */
export function useIntegration(options = {}) {
    const {
        autoFetch = true,
        autoProcessOAuth = true,
        redirectAfterOAuth = true,
    } = options;

    const route = useRoute();
    const router = useRouter();
    const integrationStore = useIntegrationStore();
    const notificationStore = useNotificationStore();
    const authStore = useAuthStore();
    const dashboardStore = useDashboardStore();

    // Local state
    const isInitialized = ref(false);

    // Computed properties from store
    const stores = computed(() => integrationStore.stores);
    const isLoading = computed(() => integrationStore.isLoading);
    const isSyncing = computed(() => integrationStore.isSyncing);
    const isProcessingOAuth = computed(() => integrationStore.isProcessingOAuth);
    const hasConnectedStore = computed(() => integrationStore.hasConnectedStore);
    const oauthError = computed(() => integrationStore.oauthError);

    /**
     * Initialize - fetch stores and process OAuth if needed
     */
    async function initialize() {
        if (isInitialized.value) return;

        // Fetch stores if autoFetch is enabled
        if (autoFetch) {
            await fetchStores();
        }

        // Process OAuth code if present in URL
        if (autoProcessOAuth && route.query.code) {
            await handleOAuthCallback();
        }

        isInitialized.value = true;
    }

    /**
     * Fetch all stores
     */
    async function fetchStores() {
        const result = await integrationStore.fetchStores();
        return result;
    }

    /**
     * Connect to a platform
     */
    async function connectPlatform(platformId, storeDomain = null) {
        const result = await integrationStore.connectPlatform(platformId, storeDomain);

        if (result && !result.success) {
            notificationStore.error(result.message);
        }

        return result;
    }

    /**
     * Handle OAuth callback
     */
    async function handleOAuthCallback() {
        const code = route.query.code;
        const platform = route.query.platform || 'nuvemshop';

        if (!code) {
            return;
        }

        // Process the code
        const result = await integrationStore.processOAuthCode(code, platform);

        if (result.success) {
            notificationStore.success(result.message);

            // Update application state after successful connection
            await updateApplicationState(result.data);

            // Redirect to dashboard after successful connection
            if (redirectAfterOAuth) {
                router.push({ name: 'dashboard' });
            }
        } else {
            notificationStore.error(result.message);
        }

        return result;
    }

    /**
     * Update application state after successful store connection
     */
    async function updateApplicationState(connectionData) {
        try {
            // If there's a new store, set it as the active store
            if (connectionData.store && connectionData.store.id) {
                const newStore = connectionData.store;

                // Fetch updated user data which should include the new active_store_id
                await authStore.fetchUser();

                // Refresh stores list with the new active store
                await integrationStore.fetchStores();

                // Refresh dashboard data with the new store
                await dashboardStore.fetchAllData();
            }
        } catch (error) {
            logger.error('Error updating application state:', error);
            // Don't show error to user as the connection was successful
        }
    }

    /**
     * Sync store
     */
    async function syncStore(storeId) {
        const result = await integrationStore.syncStore(storeId);

        if (result.success) {
            notificationStore.success(result.message);
        } else {
            notificationStore.error(result.message);
        }

        return result;
    }

    /**
     * Disconnect store
     */
    async function disconnectStore(storeId) {
        const result = await integrationStore.disconnectStore(storeId);

        if (result.success) {
            notificationStore.success(result.message);
        } else {
            notificationStore.error(result.message);
        }

        return result;
    }

    /**
     * Refresh stores data
     */
    async function refreshStores() {
        return await fetchStores();
    }

    /**
     * Get store by ID
     */
    function getStoreById(storeId) {
        return stores.value.find(store => store.id === storeId);
    }

    /**
     * Get stores by platform
     */
    function getStoresByPlatform(platform) {
        return stores.value.filter(store =>
            store.platform?.toLowerCase() === platform.toLowerCase()
        );
    }

    /**
     * Check if platform is connected
     */
    function isPlatformConnected(platform) {
        return stores.value.some(store =>
            store.platform?.toLowerCase() === platform.toLowerCase()
        );
    }

    /**
     * Clear OAuth error
     */
    function clearOAuthError() {
        integrationStore.clearOAuthError();
    }

    // Auto-initialize on mount
    onMounted(() => {
        initialize();
    });

    // Watch for route changes to process OAuth
    if (autoProcessOAuth) {
        watch(
            () => route.query.code,
            (newCode, oldCode) => {
                if (newCode && newCode !== oldCode) {
                    handleOAuthCallback();
                }
            }
        );
    }

    return {
        // State
        stores,
        isLoading,
        isSyncing,
        isProcessingOAuth,
        hasConnectedStore,
        oauthError,
        isInitialized,

        // Methods
        initialize,
        fetchStores,
        connectPlatform,
        handleOAuthCallback,
        syncStore,
        disconnectStore,
        refreshStores,
        getStoreById,
        getStoresByPlatform,
        isPlatformConnected,
        clearOAuthError,
    };
}

/**
 * Composable specifically for Nuvemshop configuration
 */
export function useNuvemshopConfig() {
    const integrationStore = useIntegrationStore();
    const notificationStore = useNotificationStore();

    const config = computed(() => integrationStore.nuvemshopConfig);
    const isLoading = computed(() => integrationStore.isLoading);

    /**
     * Fetch Nuvemshop configuration
     */
    async function fetchConfig() {
        const result = await integrationStore.fetchNuvemshopConfig();

        if (!result.success) {
            notificationStore.error(result.message);
        }

        return result;
    }

    /**
     * Update Nuvemshop configuration
     */
    async function updateConfig(newConfig) {
        const result = await integrationStore.updateNuvemshopConfig(newConfig);

        if (result.success) {
            notificationStore.success(result.message);
        } else {
            notificationStore.error(result.message);
        }

        return result;
    }

    /**
     * Check if Nuvemshop is connected
     */
    const isConnected = computed(() => config.value.isConnected);

    /**
     * Get connection status display
     */
    const connectionStatus = computed(() => {
        return isConnected.value ? 'Conectado' : 'Desconectado';
    });

    /**
     * Get connection status color
     */
    const connectionStatusColor = computed(() => {
        return isConnected.value
            ? 'text-success-600 bg-success-50'
            : 'text-gray-600 bg-gray-50';
    });

    onMounted(() => {
        fetchConfig();
    });

    return {
        // State
        config,
        isLoading,
        isConnected,
        connectionStatus,
        connectionStatusColor,

        // Methods
        fetchConfig,
        updateConfig,
    };
}
