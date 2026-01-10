<script setup>
import { ref, computed } from 'vue';
import { useIntegration } from '../composables/useIntegration';
import { useAuthStore } from '../stores/authStore';
import BaseCard from '../components/common/BaseCard.vue';
import BaseButton from '../components/common/BaseButton.vue';
import BaseModal from '../components/common/BaseModal.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import {
    LinkIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
    ArrowPathIcon,
    TrashIcon,
    PlusIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();

// Use integration composable - will auto-process OAuth callback
const {
    stores,
    isLoading,
    isSyncing,
    isProcessingOAuth,
    hasConnectedStore,
    connectPlatform,
    syncStore,
    disconnectStore: disconnectStoreAction,
    refreshStores,
} = useIntegration({
    autoFetch: true,
    autoProcessOAuth: true,
    redirectAfterOAuth: true,
});

const showDisconnectModal = ref(false);
const showConnectModal = ref(false);
const selectedStore = ref(null);
const storeUrl = ref('');
const storeUrlError = ref('');

const platforms = [
    {
        id: 'nuvemshop',
        name: 'Nuvemshop',
        description: 'Conecte sua loja Nuvemshop para sincronizar produtos, pedidos e clientes.',
        logo: '🛒',
        gradient: 'from-blue-500 to-indigo-600',
        available: true,
    },
    {
        id: 'shopify',
        name: 'Shopify',
        description: 'Integração com Shopify para sincronização completa de dados.',
        logo: '🛍️',
        gradient: 'from-emerald-500 to-teal-600',
        available: false,
        comingSoon: true,
    },
    {
        id: 'woocommerce',
        name: 'WooCommerce',
        description: 'Conecte sua loja WooCommerce/WordPress.',
        logo: '🔌',
        gradient: 'from-purple-500 to-pink-600',
        available: false,
        comingSoon: true,
    },
];

function handleConnectPlatform(platform) {
    if (!platform.available) return;

    // Open modal for store URL input
    showConnectModal.value = true;
    storeUrl.value = '';
    storeUrlError.value = '';
}

function closeConnectModal() {
    showConnectModal.value = false;
    storeUrl.value = '';
    storeUrlError.value = '';
}

function validateStoreUrl() {
    storeUrlError.value = '';

    if (!storeUrl.value || !storeUrl.value.trim()) {
        storeUrlError.value = 'A URL da loja é obrigatória';
        return false;
    }

    return true;
}

async function handleConnect() {
    if (!validateStoreUrl()) return;

    // Call connectPlatform with store URL
    const result = await connectPlatform('nuvemshop', storeUrl.value);

    // Only show error in modal if there was a failure (success redirects away)
    if (result && !result.success) {
        storeUrlError.value = result.message;
    }
}

async function handleSyncStore(store) {
    await syncStore(store.id);
    await refreshStores();
}

function confirmDisconnect(store) {
    selectedStore.value = store;
    showDisconnectModal.value = true;
}

async function handleDisconnectStore() {
    if (!selectedStore.value) return;

    await disconnectStoreAction(selectedStore.value.id);
    showDisconnectModal.value = false;
    selectedStore.value = null;
}

function getSyncStatusLabel(status) {
    const labels = {
        pending: 'Pendente',
        syncing: 'Sincronizando...',
        completed: 'Sincronizado',
        failed: 'Falhou',
    };
    return labels[status] || status;
}

function getSyncStatusColor(status) {
    const colors = {
        pending: 'text-gray-500 bg-gray-100',
        syncing: 'text-primary-500 bg-primary-100',
        completed: 'text-success-500 bg-success-100',
        failed: 'text-danger-500 bg-danger-100',
    };
    return colors[status] || 'text-gray-500 bg-gray-100';
}

function formatDate(date) {
    if (!date) return 'Nunca';
    return new Date(date).toLocaleString('pt-BR');
}
</script>

<template>
    <div class="min-h-screen -m-8 -mt-8">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-8 py-12">
            <!-- Background Elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 rounded-full blur-3xl"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-accent-500/10 rounded-full blur-3xl"></div>
                <!-- Grid Pattern -->
                <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
            </div>
            
            <div class="relative z-10 max-w-7xl mx-auto">
                <div class="flex items-center gap-3">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/30">
                        <LinkIcon class="w-7 h-7 text-white" />
                    </div>
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-display font-bold text-white">
                            Integrações
                        </h1>
                        <p class="text-primary-200/80 text-sm lg:text-base">
                            Conecte sua loja e sincronize seus dados
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">
            <!-- Processing OAuth -->
            <div v-if="isProcessingOAuth" class="flex items-center justify-center py-32">
                <div class="text-center">
                    <div class="relative mb-6">
                        <div class="w-20 h-20 mx-auto rounded-full bg-gradient-to-r from-primary-500 to-secondary-500"></div>
                        <LoadingSpinner size="xl" class="absolute inset-0 m-auto text-white" />
                    </div>
                    <p class="text-lg font-medium text-gray-900 dark:text-gray-100">Processando autorização...</p>
                    <p class="text-sm text-gray-500 mt-2">Aguarde enquanto conectamos sua loja</p>
                </div>
            </div>

            <!-- Loading State -->
            <div v-else-if="isLoading" class="flex items-center justify-center py-32">
                <div class="relative">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500"></div>
                    <LoadingSpinner size="xl" class="absolute inset-0 m-auto text-white" />
                </div>
            </div>

            <template v-else>
                <div class="max-w-7xl mx-auto space-y-8">
                    <!-- Connected Stores -->
                    <div v-if="hasConnectedStore" class="space-y-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-success-500 to-success-600 flex items-center justify-center">
                                <CheckCircleIcon class="w-4 h-4 text-white" />
                            </div>
                            Lojas Conectadas
                        </h2>
                        
                        <BaseCard
                            v-for="(store, index) in stores"
                            :key="store.id"
                            padding="normal"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-primary-100 to-secondary-100 flex items-center justify-center text-2xl shadow-lg">
                                        🛒
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ store.name }}</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ store.domain }}</p>
                                        <p v-if="store.user_id" class="text-xs text-gray-400 mt-0.5">ID: {{ store.user_id }}</p>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-4">
                                    <!-- Sync Status -->
                                    <div class="text-right">
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium',
                                                getSyncStatusColor(store.sync_status)
                                            ]"
                                        >
                                            <ArrowPathIcon
                                                v-if="store.sync_status === 'syncing'"
                                                class="w-3 h-3 animate-spin"
                                            />
                                            <CheckCircleIcon
                                                v-else-if="store.sync_status === 'completed'"
                                                class="w-3 h-3"
                                            />
                                            <ExclamationCircleIcon
                                                v-else-if="store.sync_status === 'failed'"
                                                class="w-3 h-3"
                                            />
                                            {{ getSyncStatusLabel(store.sync_status) }}
                                        </span>
                                        <p class="text-xs text-gray-400 mt-1">
                                            Última sync: {{ formatDate(store.last_sync_at) }}
                                        </p>
                                    </div>

                                    <!-- Actions -->
                                    <div v-if="authStore.hasPermission('integrations.manage')" class="flex items-center gap-2">
                                        <BaseButton
                                            variant="secondary"
                                            size="sm"
                                            @click="handleSyncStore(store)"
                                            :disabled="isSyncing || store.sync_status === 'syncing'"
                                        >
                                            <ArrowPathIcon class="w-4 h-4" />
                                            Sincronizar
                                        </BaseButton>
                                        <BaseButton
                                            variant="ghost"
                                            size="sm"
                                            @click="confirmDisconnect(store)"
                                        >
                                            <TrashIcon class="w-4 h-4 text-danger-500" />
                                        </BaseButton>
                                    </div>
                                </div>
                            </div>
                        </BaseCard>
                    </div>

                    <!-- Available Platforms -->
                    <div class="space-y-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center">
                                <PlusIcon class="w-4 h-4 text-white" />
                            </div>
                            Plataformas Disponíveis
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div
                                v-for="(platform, index) in platforms"
                                :key="platform.id"
                                :class="[
                                    'group relative overflow-hidden bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 transition-all duration-300',
                                    platform.available
                                        ? 'hover:shadow-xl hover:-translate-y-1 cursor-pointer'
                                        : 'opacity-60'
                                ]"
                                @click="handleConnectPlatform(platform)"
                            >
                                <!-- Background Gradient -->
                                <div :class="['absolute inset-0 bg-gradient-to-br opacity-0 group-hover:opacity-100 transition-opacity duration-300', platform.gradient, 'to-transparent']"></div>

                                <div class="relative p-6 text-center">
                                    <div :class="['w-16 h-16 rounded-2xl bg-gradient-to-br flex items-center justify-center text-3xl mx-auto mb-4 shadow-lg transition-transform duration-300 group-hover:scale-110', platform.gradient]">
                                        {{ platform.logo }}
                                    </div>
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ platform.name }}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ platform.description }}</p>

                                    <BaseButton
                                        v-if="platform.available && authStore.hasPermission('integrations.manage')"
                                        @click.stop="handleConnectPlatform(platform)"
                                        full-width
                                        class="group-hover:scale-105 transition-transform"
                                    >
                                        <PlusIcon class="w-4 h-4" />
                                        Conectar Loja
                                    </BaseButton>
                                    <span
                                        v-else-if="platform.available && !authStore.hasPermission('integrations.manage')"
                                        class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 dark:text-gray-400"
                                    >
                                        Sem Permissão
                                    </span>
                                    <span
                                        v-else-if="platform.comingSoon"
                                        class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 dark:text-gray-400"
                                    >
                                        Em Breve
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Connect Store Modal -->
        <BaseModal
            :show="showConnectModal"
            @close="closeConnectModal"
            title="Conectar Loja Nuvemshop"
            size="md"
        >
            <div class="py-4">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center mx-auto mb-6 shadow-lg shadow-primary-500/30 text-3xl">
                    🛒
                </div>

                <p class="text-gray-600 mb-6 text-center">
                    Digite a URL da sua loja Nuvemshop para conectá-la à plataforma
                </p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            URL da Loja
                            <span class="text-danger-500">*</span>
                        </label>
                        <input
                            v-model="storeUrl"
                            type="text"
                            placeholder="minhaloja.lojavirtualnuvem.com.br"
                            class="w-full px-4 py-2.5 rounded-lg border bg-white text-gray-900 placeholder-gray-400 transition-all duration-200 focus:outline-none focus:ring-2"
                            :class="storeUrlError
                                ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20'
                                : 'border-gray-200 focus:border-primary-500 focus:ring-primary-500/20'"
                            @keyup.enter="handleConnect"
                        />
                        <p v-if="storeUrlError" class="text-sm text-danger-500 mt-1.5">{{ storeUrlError }}</p>
                        <p v-else class="text-sm text-gray-500 mt-1.5">
                            Exemplo: minhaloja.lojavirtualnuvem.com.br
                        </p>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button
                        @click="closeConnectModal"
                        class="flex-1 px-6 py-3 rounded-xl border border-gray-200 text-gray-700 font-medium hover:bg-gray-50 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="handleConnect"
                        class="flex-1 px-6 py-3 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 text-white font-semibold shadow-lg shadow-primary-500/30 hover:shadow-xl transition-all"
                    >
                        Conectar
                    </button>
                </div>
            </div>
        </BaseModal>

        <!-- Disconnect Confirmation Modal -->
        <BaseModal
            :show="showDisconnectModal"
            @close="showDisconnectModal = false"
        >
            <div class="text-center py-4">
                <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-danger-400 to-danger-500 flex items-center justify-center mx-auto mb-6 shadow-lg shadow-danger-500/30">
                    <ExclamationCircleIcon class="w-10 h-10 text-white" />
                </div>
                <h3 class="text-xl font-display font-bold text-gray-900 mb-2">Desconectar Loja</h3>
                <p class="text-gray-500 mb-6">
                    Tem certeza que deseja desconectar a loja <strong>{{ selectedStore?.name }}</strong>?
                </p>
                <p class="text-sm text-gray-400 mb-6">
                    Todos os dados sincronizados serão removidos e você precisará conectar novamente para continuar usando a plataforma.
                </p>
                <div class="flex gap-3">
                    <button
                        @click="showDisconnectModal = false"
                        class="flex-1 px-6 py-3 rounded-xl border border-gray-200 text-gray-700 font-medium hover:bg-gray-50 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="handleDisconnectStore"
                        class="flex-1 px-6 py-3 rounded-xl bg-gradient-to-r from-danger-500 to-danger-600 text-white font-semibold shadow-lg shadow-danger-500/30 hover:shadow-xl transition-all"
                    >
                        Desconectar
                    </button>
                </div>
            </div>
        </BaseModal>
    </div>
</template>
