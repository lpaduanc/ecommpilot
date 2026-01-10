<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useNotificationStore } from '../../stores/notificationStore';
import { useIntegrationStore } from '../../stores/integrationStore';
import api from '../../services/api';
import { ChevronDownIcon, BuildingStorefrontIcon, CheckIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';

const emit = defineEmits(['store-changed']);

const notificationStore = useNotificationStore();
const integrationStore = useIntegrationStore();

const localStores = ref([]);
const localActiveStoreId = ref(null);
const isOpen = ref(false);
const isLoading = ref(false);
const isSyncing = ref(false);

const activeStore = ref(null);

// Use stores from integrationStore if available, otherwise use local
const stores = computed(() => {
    // If integrationStore has stores, use those (more up-to-date after connection)
    if (integrationStore.stores.length > 0) {
        return integrationStore.stores;
    }
    return localStores.value;
});

// Use activeStoreId from integrationStore if available
const activeStoreId = computed(() => {
    if (integrationStore.activeStoreId) {
        return integrationStore.activeStoreId;
    }
    return localActiveStoreId.value;
});

// Watch for changes in integrationStore to update activeStore
watch([() => integrationStore.stores, () => integrationStore.activeStoreId], ([newStores, newActiveId]) => {
    if (newStores && newStores.length > 0) {
        // Update activeStore based on activeStoreId
        const current = newStores.find(s => s.id === (newActiveId || localActiveStoreId.value));
        if (current) {
            activeStore.value = current;
        } else {
            // If active store not found, select first one
            activeStore.value = newStores[0];
            localActiveStoreId.value = newStores[0].id;
        }
    }
}, { deep: true, immediate: true });

async function fetchStores() {
    isLoading.value = true;
    try {
        const response = await api.get('/integrations/my-stores');
        localStores.value = response.data.stores;
        localActiveStoreId.value = response.data.active_store_id;
        activeStore.value = stores.value.find(s => s.id === activeStoreId.value) || stores.value[0];

        // Also update integrationStore to keep them in sync
        if (response.data.stores.length > 0) {
            integrationStore.stores = response.data.stores;
            integrationStore.activeStoreId = response.data.active_store_id;
        }
    } catch {
        localStores.value = [];
    } finally {
        isLoading.value = false;
    }
}

async function selectStore(store) {
    if (store.id === activeStoreId.value) {
        isOpen.value = false;
        return;
    }

    try {
        await api.post(`/integrations/select-store/${store.id}`);
        localActiveStoreId.value = store.id;
        integrationStore.activeStoreId = store.id;
        activeStore.value = store;
        isOpen.value = false;
        emit('store-changed', store);
        notificationStore.success(`Loja "${store.name}" selecionada`);
        // Reload the page to refresh all data
        window.location.reload();
    } catch {
        notificationStore.error('Erro ao selecionar loja');
    }
}

async function syncStore() {
    if (!activeStore.value || isSyncing.value) return;

    isSyncing.value = true;
    try {
        await api.post(`/integrations/stores/${activeStore.value.id}/sync`);
        notificationStore.success('Sincronização iniciada!');
        // Update sync status
        activeStore.value.sync_status = 'syncing';
    } catch {
        notificationStore.error('Erro ao iniciar sincronização');
    } finally {
        isSyncing.value = false;
    }
}

function getPlatformLabel(platform) {
    const labels = {
        nuvemshop: 'Nuvemshop',
        shopify: 'Shopify',
        woocommerce: 'WooCommerce',
    };
    return labels[platform] || platform;
}

function getSyncStatusColor(status) {
    const colors = {
        pending: 'text-gray-500',
        syncing: 'text-primary-500',
        completed: 'text-success-500',
        failed: 'text-danger-500',
    };
    return colors[status] || 'text-gray-500';
}

function closeDropdown(e) {
    if (!e.target.closest('.store-selector')) {
        isOpen.value = false;
    }
}

onMounted(() => {
    fetchStores();
    document.addEventListener('click', closeDropdown);
});
</script>

<template>
    <div v-if="stores.length > 0" class="store-selector relative">
        <button
            @click="isOpen = !isOpen"
            class="flex items-center gap-3 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900"
        >
            <div class="w-8 h-8 rounded-lg bg-primary-100 flex items-center justify-center">
                <BuildingStorefrontIcon class="w-4 h-4 text-primary-600" />
            </div>
            <div class="text-left hidden sm:block">
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate max-w-[120px]">
                    {{ activeStore?.name || 'Selecionar loja' }}
                </p>
                <p class="text-xs text-gray-500">{{ getPlatformLabel(activeStore?.platform) }}</p>
            </div>
            <ChevronDownIcon class="w-4 h-4 text-gray-400" />
        </button>

        <!-- Dropdown -->
        <div
            v-if="isOpen"
            class="absolute top-full right-0 mt-2 w-72 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 z-50 overflow-hidden"
        >
            <div class="p-2 border-b border-gray-100">
                <p class="px-3 py-1 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    Suas Lojas
                </p>
            </div>
            
            <div class="max-h-60 overflow-y-auto">
                <button
                    v-for="store in stores"
                    :key="store.id"
                    @click="selectStore(store)"
                    class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 dark:bg-gray-900 text-left"
                >
                    <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-lg">
                        🛒
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ store.name }}</p>
                        <p class="text-xs text-gray-500">{{ getPlatformLabel(store.platform) }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            v-if="store.sync_status === 'syncing'"
                            :class="['w-2 h-2 rounded-full animate-pulse', getSyncStatusColor(store.sync_status)]"
                            style="background-color: currentColor;"
                        ></span>
                        <CheckIcon
                            v-if="store.id === activeStoreId"
                            class="w-5 h-5 text-primary-500"
                        />
                    </div>
                </button>
            </div>

            <!-- Sync Button -->
            <div class="p-2 border-t border-gray-100">
                <button
                    @click="syncStore"
                    :disabled="isSyncing || activeStore?.sync_status === 'syncing'"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-primary-600 hover:bg-primary-50 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <ArrowPathIcon :class="['w-4 h-4', { 'animate-spin': isSyncing || activeStore?.sync_status === 'syncing' }]" />
                    {{ isSyncing || activeStore?.sync_status === 'syncing' ? 'Sincronizando...' : 'Sincronizar Agora' }}
                </button>
            </div>
        </div>
    </div>
</template>

