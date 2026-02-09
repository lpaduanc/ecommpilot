import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';
import { logger } from '../utils/logger';

/**
 * Integration Store
 * Manages integration state, OAuth flows, and store connections
 */
export const useIntegrationStore = defineStore('integration', () => {
    // State
    const stores = ref([]);
    const currentStore = ref(null);
    const isLoading = ref(false);
    const isSyncing = ref(false);
    const isProcessingOAuth = ref(false);
    const oauthError = ref(null);
    const syncStatusPollingInterval = ref(null);
    const pollingStartTime = ref(null);

    // Nuvemshop configuration
    const nuvemshopConfig = ref({
        clientId: '',
        clientSecret: '',
        grantType: 'authorization_code',
        accessToken: '',
        tokenType: '',
        userId: '',
        scope: '',
        isConnected: false,
    });

    // Computed
    const hasConnectedStore = computed(() => stores.value.length > 0);

    const connectedStores = computed(() =>
        stores.value.filter(store => store.sync_status === 'completed')
    );

    const pendingStores = computed(() =>
        stores.value.filter(store => ['pending', 'syncing'].includes(store.sync_status))
    );

    const failedStores = computed(() =>
        stores.value.filter(store => store.sync_status === 'failed')
    );

    // Active store ID
    const activeStoreId = ref(null);

    // Active store computed (activeStoreId is UUID)
    const activeStore = computed(() => {
        if (!activeStoreId.value || stores.value.length === 0) return null;
        // Both activeStoreId and s.id are UUIDs
        return stores.value.find(s => s.id === activeStoreId.value);
    });

    const isActiveStoreSyncing = computed(() =>
        ['syncing', 'pending'].includes(activeStore.value?.sync_status)
    );

    const isActiveStoreFailed = computed(() =>
        activeStore.value?.sync_status === 'failed'
    );

    // Actions

    /**
     * Fetch all connected stores
     */
    async function fetchStores() {
        isLoading.value = true;
        try {
            // Use my-stores endpoint which includes active_store_id
            const response = await api.get('/integrations/my-stores');
            stores.value = response.data.stores || [];
            activeStoreId.value = response.data.active_store_id;
            return { success: true, data: response.data };
        } catch (error) {
            stores.value = [];
            return {
                success: false,
                message: error.response?.data?.message || 'Erro ao buscar lojas'
            };
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Connect to a platform (redirects to OAuth)
     * For Nuvemshop, we make an AJAX call first to get the redirect URL, then redirect
     */
    async function connectPlatform(platformId, storeDomain = null) {
        if (platformId === 'nuvemshop') {
            if (!storeDomain) {
                logger.error('Store domain is required for Nuvemshop connection');
                return { success: false, message: 'URL da loja é obrigatória' };
            }

            try {
                // Make AJAX call to get the OAuth redirect URL
                // This ensures authentication cookies are sent
                const response = await api.get('/integrations/nuvemshop/connect', {
                    params: { store_url: storeDomain }
                });

                // Redirect to the OAuth URL returned by the backend
                if (response.data.redirect_url) {
                    window.location.href = response.data.redirect_url;
                    return { success: true };
                }

                return { success: false, message: 'URL de redirecionamento não recebida' };
            } catch (error) {
                const errorMessage = error.response?.data?.message || 'Erro ao conectar com a loja';
                logger.error('Error connecting to Nuvemshop:', errorMessage);
                return { success: false, message: errorMessage };
            }
        } else {
            // For other platforms, use the backend endpoint
            window.location.href = `/api/integrations/${platformId}/connect`;
            return { success: true };
        }
    }

    /**
     * Process OAuth authorization code
     */
    async function processOAuthCode(code, platform = 'nuvemshop') {
        isProcessingOAuth.value = true;
        oauthError.value = null;

        try {
            const response = await api.post(`/integrations/${platform}/authorize`, {
                code: code,
            });

            // Update stores list
            await fetchStores();

            // Update nuvemshop config if available
            if (response.data.config) {
                nuvemshopConfig.value = {
                    ...nuvemshopConfig.value,
                    ...response.data.config,
                    isConnected: true,
                };
            }

            return {
                success: true,
                message: 'Integração conectada com sucesso!',
                data: response.data,
            };
        } catch (error) {
            const errorMessage = error.response?.data?.message || 'Erro ao processar autorização';
            oauthError.value = errorMessage;

            return {
                success: false,
                message: errorMessage,
                errors: error.response?.data?.errors,
            };
        } finally {
            isProcessingOAuth.value = false;
        }
    }

    /**
     * Sync store data (expects UUID)
     */
    async function syncStore(storeId) {
        isSyncing.value = true;
        try {
            const response = await api.post(`/integrations/stores/${storeId}/sync`);

            // Update the store in the list immediately (compare by UUID)
            const storeIndex = stores.value.findIndex(s => s.id === storeId);
            if (storeIndex !== -1) {
                stores.value[storeIndex] = {
                    ...stores.value[storeIndex],
                    sync_status: 'syncing',
                };
            }

            // Start polling to track sync progress
            startSyncStatusPolling(5000);

            return {
                success: true,
                message: 'Sincronização iniciada com sucesso',
                data: response.data,
            };
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Erro ao iniciar sincronização',
            };
        } finally {
            isSyncing.value = false;
        }
    }

    /**
     * Disconnect store (expects UUID)
     */
    async function disconnectStore(storeId) {
        try {
            await api.delete(`/integrations/stores/${storeId}`);

            // Remove from stores list (compare by UUID)
            stores.value = stores.value.filter(s => s.id !== storeId);

            // Clear current store if it was the one disconnected
            if (currentStore.value?.id === storeId) {
                currentStore.value = null;
            }

            return {
                success: true,
                message: 'Loja desconectada com sucesso',
            };
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Erro ao desconectar loja',
            };
        }
    }

    /**
     * Fetch Nuvemshop configuration
     */
    async function fetchNuvemshopConfig() {
        isLoading.value = true;
        try {
            const response = await api.get('/settings/store');
            nuvemshopConfig.value = {
                ...nuvemshopConfig.value,
                ...response.data,
            };
            return { success: true, data: response.data };
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Erro ao buscar configurações',
            };
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Update Nuvemshop configuration
     */
    async function updateNuvemshopConfig(config) {
        isLoading.value = true;
        try {
            const response = await api.put('/settings/store', config);
            nuvemshopConfig.value = {
                ...nuvemshopConfig.value,
                ...response.data,
            };
            return {
                success: true,
                message: 'Configurações salvas com sucesso',
                data: response.data,
            };
        } catch (error) {
            return {
                success: false,
                message: error.response?.data?.message || 'Erro ao salvar configurações',
                errors: error.response?.data?.errors,
            };
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Set current active store
     */
    function setCurrentStore(store) {
        currentStore.value = store;
    }

    /**
     * Clear OAuth error
     */
    function clearOAuthError() {
        oauthError.value = null;
    }

    /**
     * Fetch sync status for active store (lightweight endpoint for polling)
     * Note: store_id returned is UUID
     */
    async function fetchSyncStatus() {
        try {
            const response = await api.get('/integrations/sync-status');
            if (response.data.has_store && response.data.store_id) {
                // Compare by UUID (store_id is UUID from backend)
                const idx = stores.value.findIndex(s => s.id === response.data.store_id);
                if (idx !== -1) {
                    stores.value[idx] = {
                        ...stores.value[idx],
                        sync_status: response.data.sync_status,
                        last_sync_at: response.data.last_sync_at,
                    };
                }
            }
            return { success: true, data: response.data };
        } catch (error) {
            return { success: false, message: error.response?.data?.message || 'Erro ao verificar status' };
        }
    }

    /**
     * Start polling for sync status updates
     */
    function startSyncStatusPolling(intervalMs = 5000) {
        const MAX_POLLING_DURATION = 300000; // 5 minutos

        stopSyncStatusPolling(); // Clear any existing interval
        pollingStartTime.value = Date.now();

        syncStatusPollingInterval.value = setInterval(async () => {
            // Timeout safety - para após 5 minutos
            if (Date.now() - pollingStartTime.value > MAX_POLLING_DURATION) {
                console.warn('[Sync Polling] Timeout após 5 minutos');
                stopSyncStatusPolling();
                return;
            }

            const result = await fetchSyncStatus();

            // Para polling se houver erro de rede
            if (!result.success) {
                console.warn('[Sync Polling] Erro ao buscar status, parando polling');
                stopSyncStatusPolling();
                return;
            }

            // Para polling se status não é mais "em progresso"
            const status = result.data?.sync_status;
            if (status && !['syncing', 'pending'].includes(status)) {
                console.log('[Sync Polling] Status final:', status);
                stopSyncStatusPolling();
            }
        }, intervalMs);
    }

    /**
     * Stop polling for sync status
     */
    function stopSyncStatusPolling() {
        if (syncStatusPollingInterval.value) {
            clearInterval(syncStatusPollingInterval.value);
            syncStatusPollingInterval.value = null;
            pollingStartTime.value = null;
        }
    }

    /**
     * Helper to check if sync is in progress
     */
    function isSyncInProgress(status) {
        return ['syncing', 'pending'].includes(status);
    }

    /**
     * Reset store state
     */
    function $reset() {
        stores.value = [];
        currentStore.value = null;
        isLoading.value = false;
        isSyncing.value = false;
        isProcessingOAuth.value = false;
        oauthError.value = null;
        pollingStartTime.value = null;
        nuvemshopConfig.value = {
            clientId: '',
            clientSecret: '',
            grantType: 'authorization_code',
            accessToken: '',
            tokenType: '',
            userId: '',
            scope: '',
            isConnected: false,
        };
    }

    return {
        // State
        stores,
        currentStore,
        activeStoreId,
        isLoading,
        isSyncing,
        isProcessingOAuth,
        oauthError,
        nuvemshopConfig,

        // Computed
        hasConnectedStore,
        connectedStores,
        pendingStores,
        failedStores,
        activeStore,
        isActiveStoreSyncing,
        isActiveStoreFailed,

        // Actions
        fetchStores,
        connectPlatform,
        processOAuthCode,
        syncStore,
        disconnectStore,
        fetchNuvemshopConfig,
        updateNuvemshopConfig,
        setCurrentStore,
        clearOAuthError,
        fetchSyncStatus,
        startSyncStatusPolling,
        stopSyncStatusPolling,
        isSyncInProgress,
        $reset,
    };
});
