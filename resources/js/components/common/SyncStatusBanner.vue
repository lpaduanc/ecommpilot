<script setup>
import { computed, watch, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useIntegrationStore } from '../../stores/integrationStore';
import {
    ArrowPathIcon,
    ExclamationTriangleIcon,
    ExclamationCircleIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const integrationStore = useIntegrationStore();

// Computed from store
const isActiveStoreSyncing = computed(() => integrationStore.isActiveStoreSyncing);
const isActiveStoreFailed = computed(() => integrationStore.isActiveStoreFailed);
const isActiveStoreTokenExpired = computed(() => integrationStore.isActiveStoreTokenExpired);
const activeStore = computed(() => integrationStore.activeStore);

// Show banner if syncing, failed, or token expired
const showBanner = computed(() =>
    isActiveStoreSyncing.value ||
    isActiveStoreFailed.value ||
    isActiveStoreTokenExpired.value
);

// Banner variant based on status
const bannerVariant = computed(() => {
    if (isActiveStoreTokenExpired.value) return 'danger';
    if (isActiveStoreFailed.value) return 'warning';
    return 'info'; // syncing
});

// Banner message
const bannerMessage = computed(() => {
    if (isActiveStoreTokenExpired.value) {
        return 'O token da sua loja expirou. Por favor, reconecte para continuar sincronizando.';
    }
    if (isActiveStoreFailed.value) {
        return 'A sincronização da sua loja falhou. Tente novamente ou entre em contato com o suporte.';
    }
    const storeName = activeStore.value?.name || 'Sua loja';
    return `${storeName} está sincronizando dados. Algumas funcionalidades estão temporariamente indisponíveis.`;
});

// Banner icon component
const bannerIcon = computed(() => {
    if (isActiveStoreTokenExpired.value) return ExclamationCircleIcon;
    if (isActiveStoreFailed.value) return ExclamationTriangleIcon;
    return ArrowPathIcon;
});

// Action button label
const actionLabel = computed(() => {
    if (isActiveStoreTokenExpired.value) return 'Reconectar Agora';
    if (isActiveStoreFailed.value) return 'Tentar Novamente';
    return null;
});

// Handle action button click
async function handleAction() {
    if (isActiveStoreTokenExpired.value) {
        router.push({ name: 'integrations' });
    } else if (isActiveStoreFailed.value && activeStore.value?.id) {
        await integrationStore.syncStore(activeStore.value.id);
    }
}

// Start/stop polling based on sync status
watch(isActiveStoreSyncing, (isSyncing) => {
    if (isSyncing) {
        integrationStore.startSyncStatusPolling(5000);
    } else {
        integrationStore.stopSyncStatusPolling();
    }
}, { immediate: true });

// Initial sync status check on mount
onMounted(async () => {
    if (integrationStore.stores.length > 0) {
        await integrationStore.fetchSyncStatus();
    }
});

// Cleanup on unmount
onUnmounted(() => {
    integrationStore.stopSyncStatusPolling();
});
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-500"
        enter-from-class="opacity-0 -translate-y-full"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-300"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-full"
    >
        <div
            v-if="showBanner"
            role="alert"
            :class="[
                'relative overflow-hidden',
                bannerVariant === 'danger'
                    ? 'bg-gradient-to-r from-red-600 via-red-500 to-red-600'
                    : bannerVariant === 'warning'
                        ? 'bg-gradient-to-r from-amber-500 via-orange-500 to-amber-500'
                        : 'bg-gradient-to-r from-indigo-600 via-purple-600 to-indigo-600'
            ]"
        >
            <!-- Animated background effect -->
            <div
                v-if="isActiveStoreSyncing && !isActiveStoreFailed && !isActiveStoreTokenExpired"
                class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent animate-shimmer"
            ></div>

            <!-- Pulsing border effect -->
            <div
                v-if="isActiveStoreSyncing"
                class="absolute bottom-0 left-0 right-0 h-1 bg-white/30"
            >
                <div class="h-full bg-white animate-progress"></div>
            </div>

            <div class="relative flex items-center justify-center gap-4 px-6 py-4">
                <!-- Icon with animation -->
                <div
                    :class="[
                        'flex items-center justify-center w-12 h-12 rounded-full',
                        bannerVariant === 'danger'
                            ? 'bg-red-700/50'
                            : bannerVariant === 'warning'
                                ? 'bg-orange-600/50'
                                : 'bg-indigo-700/50'
                    ]"
                >
                    <component
                        :is="bannerIcon"
                        :class="[
                            'w-7 h-7 text-white',
                            isActiveStoreSyncing && !isActiveStoreFailed && !isActiveStoreTokenExpired ? 'animate-spin' : 'animate-pulse'
                        ]"
                    />
                </div>

                <!-- Message -->
                <div class="flex-1 text-center lg:text-left">
                    <p class="text-lg font-bold text-white">
                        <span v-if="isActiveStoreSyncing && !isActiveStoreFailed && !isActiveStoreTokenExpired">
                            Sincronizando...
                        </span>
                        <span v-else-if="isActiveStoreFailed">
                            Falha na Sincronização
                        </span>
                        <span v-else>
                            Token Expirado
                        </span>
                    </p>
                    <p class="text-sm text-white/90 mt-0.5">
                        {{ bannerMessage }}
                    </p>
                </div>

                <!-- Action button -->
                <button
                    v-if="actionLabel"
                    @click="handleAction"
                    :class="[
                        'px-6 py-3 rounded-xl text-base font-bold shadow-lg transition-all hover:scale-105 active:scale-95',
                        bannerVariant === 'danger'
                            ? 'bg-white text-red-600 hover:bg-red-50'
                            : bannerVariant === 'warning'
                                ? 'bg-white text-orange-600 hover:bg-orange-50'
                                : 'bg-white text-indigo-600 hover:bg-indigo-50'
                    ]"
                >
                    {{ actionLabel }}
                </button>

                <!-- Progress dots for syncing state -->
                <div
                    v-if="isActiveStoreSyncing && !isActiveStoreFailed && !isActiveStoreTokenExpired && !actionLabel"
                    class="flex items-center gap-2"
                >
                    <span class="w-3 h-3 rounded-full bg-white animate-bounce" style="animation-delay: 0ms"></span>
                    <span class="w-3 h-3 rounded-full bg-white animate-bounce" style="animation-delay: 150ms"></span>
                    <span class="w-3 h-3 rounded-full bg-white animate-bounce" style="animation-delay: 300ms"></span>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

@keyframes progress {
    0% {
        width: 0%;
    }
    50% {
        width: 100%;
    }
    100% {
        width: 0%;
    }
}

.animate-shimmer {
    animation: shimmer 2s infinite;
}

.animate-progress {
    animation: progress 3s ease-in-out infinite;
}
</style>
