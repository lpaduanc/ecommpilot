<script setup>
import { ref, computed, onBeforeUnmount } from 'vue';
import { useIntegration } from '../composables/useIntegration';
import { useAuthStore } from '../stores/authStore';
import { useNotificationStore } from '../stores/notificationStore';
import { usePreviewMode } from '../composables/usePreviewMode';
import BaseCard from '../components/common/BaseCard.vue';
import BaseButton from '../components/common/BaseButton.vue';
import BaseModal from '../components/common/BaseModal.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import UpgradeBanner from '../components/common/UpgradeBanner.vue';
import PreviewModeBanner from '../components/common/PreviewModeBanner.vue';
import {
    LinkIcon,
    CheckCircleIcon,
    ExclamationCircleIcon,
    ArrowPathIcon,
    TrashIcon,
    PlusIcon,
    Cog6ToothIcon,
    BuildingStorefrontIcon,
} from '@heroicons/vue/24/outline';
import { mockIntegrationStore } from '../mocks/previewMocks';

const authStore = useAuthStore();
const notificationStore = useNotificationStore();
const { isInPreviewMode, enablePreviewMode, disablePreviewMode } = usePreviewMode();

// Verifica acesso pelo plano
const canAccessIntegrations = computed(() => authStore.canAccessExternalIntegrations);

// Determina se deve mostrar o conteúdo (tem acesso OU está em preview mode)
const showContent = computed(() => canAccessIntegrations.value || isInPreviewMode.value);

// Usa dados mockados quando em preview mode E não tem acesso
const shouldUseMocks = computed(() => isInPreviewMode.value && !canAccessIntegrations.value);

// Use integration composable - will auto-process OAuth callback
const {
    stores,
    isLoading: realIsLoading,
    isSyncing,
    isProcessingOAuth,
    hasConnectedStore: realHasConnectedStore,
    connectPlatform,
    syncStore,
    disconnectStore: disconnectStoreAction,
    refreshStores,
    getStoresByPlatform,
} = useIntegration({
    autoFetch: true,
    autoProcessOAuth: true,
    redirectAfterOAuth: true,
});

// Computed para usar mocks quando apropriado
const isLoading = computed(() => shouldUseMocks.value ? false : realIsLoading.value);
const hasConnectedStore = computed(() => shouldUseMocks.value ? true : realHasConnectedStore.value);

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

// Agrupar lojas por plataforma
const storesByPlatform = computed(() => {
    if (shouldUseMocks.value) {
        // Retorna dados mockados quando em preview mode
        return {
            nuvemshop: {
                platform: platforms[0],
                stores: [mockIntegrationStore],
            },
        };
    }

    const grouped = {};

    platforms.forEach(platform => {
        const platformStores = getStoresByPlatform(platform.id);
        if (platformStores.length > 0) {
            grouped[platform.id] = {
                platform,
                stores: platformStores,
            };
        }
    });

    return grouped;
});

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
    // Não chamar refreshStores() aqui pois:
    // 1. syncStore já atualiza o estado local para 'syncing'
    // 2. syncStore já inicia o polling que vai atualizar quando terminar
    // 3. Chamar refreshStores imediatamente busca dados antigos do servidor
    await syncStore(store.id);
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
        pending: 'text-gray-500 bg-gray-100 dark:text-gray-400 dark:bg-gray-700',
        syncing: 'text-primary-600 bg-primary-100 dark:text-primary-400 dark:bg-primary-900/50',
        completed: 'text-success-600 bg-success-100 dark:text-success-400 dark:bg-success-900/50',
        failed: 'text-danger-600 bg-danger-100 dark:text-danger-400 dark:bg-danger-900/50',
    };
    return colors[status] || 'text-gray-500 bg-gray-100 dark:text-gray-400 dark:bg-gray-700';
}

function formatDate(date) {
    if (!date) return 'Nunca';
    return new Date(date).toLocaleString('pt-BR');
}

onBeforeUnmount(() => {
    // Desabilita preview mode ao sair
    if (isInPreviewMode.value) {
        disablePreviewMode();
    }
});
</script>

<template>
    <div class="space-y-6">
        <!-- Banner de Preview Mode - Aparece quando está visualizando sem acesso -->
        <PreviewModeBanner
            v-if="isInPreviewMode && !canAccessIntegrations"
            feature-name="Integrações Externas"
            @close="disablePreviewMode"
        />

        <!-- Banner de Upgrade - Plano não inclui Integrações Externas -->
        <UpgradeBanner
            v-if="!canAccessIntegrations && !isInPreviewMode"
            title="Recurso não disponível no seu plano"
            description="Seu plano atual não inclui acesso às Integrações Externas. Faça upgrade para conectar suas lojas e sincronizar dados automaticamente."
            feature-name="Integrações Externas"
            @enable-preview="enablePreviewMode('Integrações Externas')"
        />

        <!-- Conteúdo - mostra se tiver acesso OU estiver em preview mode -->
        <div
            v-if="showContent"
            class="min-h-screen -m-8 -mt-8"
            :class="shouldUseMocks ? 'preview-mode-disabled' : ''"
        >
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
                <div class="max-w-7xl mx-auto space-y-10">
                    <!-- SEÇÃO: SUAS LOJAS -->
                    <BaseCard>
                        <div class="space-y-5">
                            <!-- Título da Seção -->
                            <div class="flex items-center gap-3 pb-4 border-b border-gray-200 dark:border-gray-700">
                                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-md">
                                    <BuildingStorefrontIcon class="w-5 h-5 text-white" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Suas Lojas</h2>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Gerencie suas lojas conectadas</p>
                                </div>
                            </div>

                            <!-- Lojas Conectadas -->
                            <div v-if="hasConnectedStore" class="space-y-6">
                                <!-- Lojas agrupadas por plataforma -->
                                <div
                                    v-for="(group, platformId) in storesByPlatform"
                                    :key="platformId"
                                    class="space-y-3"
                                >
                                    <!-- Cabeçalho da Plataforma -->
                                    <div class="flex items-center gap-3 px-3 py-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                        <div :class="['w-8 h-8 rounded-lg bg-gradient-to-br flex items-center justify-center text-lg shadow-sm', group.platform.gradient]">
                                            {{ group.platform.logo }}
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-semibold text-sm text-gray-900 dark:text-gray-100">{{ group.platform.name }}</h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ group.stores.length }} {{ group.stores.length === 1 ? 'loja conectada' : 'lojas conectadas' }}</p>
                                        </div>
                                    </div>

                                    <!-- Lista de lojas da plataforma -->
                                    <div
                                        v-for="store in group.stores"
                                        :key="store.id"
                                        class="p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/50"
                                    >
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                                <div :class="['w-12 h-12 rounded-lg bg-gradient-to-br flex items-center justify-center text-xl shadow-md flex-shrink-0', group.platform.gradient]">
                                                    {{ group.platform.logo }}
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ store.name }}</h3>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ store.domain }}</p>
                                                    <div class="flex items-center gap-2 mt-1">
                                                        <span
                                                            :class="[
                                                                'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium',
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
                                                        <span class="text-xs text-gray-400">{{ formatDate(store.last_sync_at) }}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Actions -->
                                            <div v-if="authStore.hasPermission('integrations.manage')" class="flex items-center gap-2 flex-shrink-0">
                                                <router-link
                                                    :to="{ name: 'store-config', params: { id: store.id } }"
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors"
                                                >
                                                    <Cog6ToothIcon class="w-4 h-4" />
                                                    Configurar
                                                </router-link>
                                                <BaseButton
                                                    variant="secondary"
                                                    size="sm"
                                                    @click.stop.prevent="handleSyncStore(store)"
                                                    :disabled="isSyncing || ['syncing', 'pending'].includes(store.sync_status)"
                                                >
                                                    <ArrowPathIcon :class="['w-4 h-4', ['syncing', 'pending'].includes(store.sync_status) ? 'animate-spin' : '']" />
                                                    {{ ['syncing', 'pending'].includes(store.sync_status) ? 'Sincronizando...' : 'Sincronizar' }}
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
                                </div>

                                <!-- Separador -->
                                <div class="pt-2 border-t border-gray-200 dark:border-gray-700"></div>
                            </div>

                            <!-- Cards de Plataformas (sempre visíveis) -->
                            <div class="space-y-4">
                                <div v-if="!hasConnectedStore">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Conecte sua primeira loja para começar a usar a plataforma
                                    </p>
                                </div>
                                <div v-else>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">
                                        Conectar outra plataforma
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <div
                                        v-for="platform in platforms"
                                        :key="platform.id"
                                        :class="[
                                            'group relative overflow-hidden bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 transition-all duration-300',
                                            platform.available
                                                ? 'hover:shadow-xl hover:-translate-y-1 cursor-pointer'
                                                : 'opacity-60'
                                        ]"
                                        @click="handleConnectPlatform(platform)"
                                    >
                                        <!-- Background Gradient on Hover -->
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
                                                class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400"
                                            >
                                                Sem Permissão
                                            </span>
                                            <span
                                                v-else-if="platform.comingSoon"
                                                class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400"
                                            >
                                                Em Breve
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </BaseCard>
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
    </div>
</template>

<style scoped>
/* Preview Mode - Disabled State */
.preview-mode-disabled {
    pointer-events: none;
    user-select: none;
}

.preview-mode-disabled * {
    opacity: 0.9;
    cursor: not-allowed !important;
}

.preview-mode-disabled button,
.preview-mode-disabled a,
.preview-mode-disabled input,
.preview-mode-disabled textarea,
.preview-mode-disabled select {
    pointer-events: none !important;
    filter: grayscale(0.2);
}
</style>
