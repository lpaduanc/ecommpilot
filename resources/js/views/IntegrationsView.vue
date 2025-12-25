<script setup>
import { ref, onMounted, computed } from 'vue';
import { useNotificationStore } from '../stores/notificationStore';
import api from '../services/api';
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

const notificationStore = useNotificationStore();

const stores = ref([]);
const isLoading = ref(false);
const isSyncing = ref(false);
const showDisconnectModal = ref(false);
const selectedStore = ref(null);

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

const hasConnectedStore = computed(() => stores.value.length > 0);

async function fetchStores() {
    isLoading.value = true;
    try {
        const response = await api.get('/integrations/stores');
        stores.value = response.data;
    } catch {
        stores.value = [];
    } finally {
        isLoading.value = false;
    }
}

function connectPlatform(platform) {
    if (!platform.available) return;
    
    // Redirect to OAuth flow
    window.location.href = `/api/integrations/${platform.id}/connect`;
}

async function syncStore(store) {
    isSyncing.value = true;
    try {
        await api.post(`/integrations/stores/${store.id}/sync`);
        notificationStore.success('Sincronização iniciada! Você será notificado quando concluir.');
        await fetchStores();
    } catch {
        notificationStore.error('Erro ao iniciar sincronização.');
    } finally {
        isSyncing.value = false;
    }
}

function confirmDisconnect(store) {
    selectedStore.value = store;
    showDisconnectModal.value = true;
}

async function disconnectStore() {
    if (!selectedStore.value) return;
    
    try {
        await api.delete(`/integrations/stores/${selectedStore.value.id}`);
        notificationStore.success('Loja desconectada com sucesso.');
        showDisconnectModal.value = false;
        selectedStore.value = null;
        await fetchStores();
    } catch {
        notificationStore.error('Erro ao desconectar loja.');
    }
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

onMounted(() => {
    fetchStores();
});
</script>

<template>
    <div class="min-h-screen -m-8 -mt-8">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 px-8 py-12">
            <!-- Animated Background Elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 rounded-full blur-3xl animate-pulse-soft"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 rounded-full blur-3xl animate-pulse-soft" style="animation-delay: 1s;"></div>
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
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 min-h-[calc(100vh-200px)]">
            <!-- Loading State -->
            <div v-if="isLoading" class="flex items-center justify-center py-32">
                <div class="relative">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500 animate-pulse"></div>
                    <LoadingSpinner size="xl" class="absolute inset-0 m-auto text-white" />
                </div>
            </div>

            <template v-else>
                <div class="max-w-7xl mx-auto space-y-8">
                    <!-- Connected Stores -->
                    <div v-if="hasConnectedStore" class="space-y-4 animate-fade-in">
                        <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-success-500 to-success-600 flex items-center justify-center">
                                <CheckCircleIcon class="w-4 h-4 text-white" />
                            </div>
                            Lojas Conectadas
                        </h2>
                        
                        <BaseCard
                            v-for="(store, index) in stores"
                            :key="store.id"
                            padding="normal"
                            :class="['animate-slide-up', `animate-delay-${index * 100}`]"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-primary-100 to-secondary-100 flex items-center justify-center text-2xl shadow-lg">
                                        🛒
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">{{ store.name }}</h3>
                                        <p class="text-sm text-gray-500">{{ store.domain }}</p>
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
                                    <div class="flex items-center gap-2">
                                        <BaseButton
                                            variant="secondary"
                                            size="sm"
                                            @click="syncStore(store)"
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
                    <div class="space-y-4 animate-fade-in animate-delay-200">
                        <h2 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
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
                                    'group relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 transition-all duration-300',
                                    platform.available 
                                        ? 'hover:shadow-xl hover:-translate-y-1 cursor-pointer' 
                                        : 'opacity-60',
                                    'animate-slide-up'
                                ]"
                                :style="{ animationDelay: `${index * 100}ms` }"
                                @click="connectPlatform(platform)"
                            >
                                <!-- Background Gradient -->
                                <div :class="['absolute inset-0 bg-gradient-to-br opacity-0 group-hover:opacity-100 transition-opacity duration-300', platform.gradient, 'to-transparent']"></div>
                                
                                <div class="relative p-6 text-center">
                                    <div :class="['w-16 h-16 rounded-2xl bg-gradient-to-br flex items-center justify-center text-3xl mx-auto mb-4 shadow-lg transition-transform duration-300 group-hover:scale-110', platform.gradient]">
                                        {{ platform.logo }}
                                    </div>
                                    <h3 class="font-semibold text-gray-900 mb-2">{{ platform.name }}</h3>
                                    <p class="text-sm text-gray-500 mb-4">{{ platform.description }}</p>
                                    
                                    <BaseButton
                                        v-if="platform.available"
                                        @click.stop="connectPlatform(platform)"
                                        full-width
                                        class="group-hover:scale-105 transition-transform"
                                    >
                                        <PlusIcon class="w-4 h-4" />
                                        Conectar Loja
                                    </BaseButton>
                                    <span
                                        v-else-if="platform.comingSoon"
                                        class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500"
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
                        @click="disconnectStore"
                        class="flex-1 px-6 py-3 rounded-xl bg-gradient-to-r from-danger-500 to-danger-600 text-white font-semibold shadow-lg shadow-danger-500/30 hover:shadow-xl transition-all"
                    >
                        Desconectar
                    </button>
                </div>
            </div>
        </BaseModal>
    </div>
</template>
