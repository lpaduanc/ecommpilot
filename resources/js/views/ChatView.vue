<script setup>
import { onMounted, onBeforeUnmount, computed, ref } from 'vue';
import { useChatStore } from '../stores/chatStore';
import { useAuthStore } from '../stores/authStore';
import { usePreviewMode } from '../composables/usePreviewMode';
import ChatContainer from '../components/chat/ChatContainer.vue';
import UpgradeBanner from '../components/common/UpgradeBanner.vue';
import PreviewModeBanner from '../components/common/PreviewModeBanner.vue';
import { ExclamationTriangleIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import { mockChatMessages } from '../mocks/previewMocks';

const chatStore = useChatStore();
const authStore = useAuthStore();
const { isInPreviewMode, enablePreviewMode, disablePreviewMode } = usePreviewMode();

// Verifica acesso pelo plano (authStore) ou se backend retornou 403 (chatStore)
const upgradeRequired = computed(() => !authStore.canAccessAiChat || chatStore.upgradeRequired);

// Determina se deve mostrar o conteúdo (tem acesso OU está em preview mode)
const showContent = computed(() => !upgradeRequired.value || isInPreviewMode.value);

// Usa dados mockados quando em preview mode E não tem acesso
const shouldUseMocks = computed(() => isInPreviewMode.value && upgradeRequired.value);

onMounted(() => {
    if (shouldUseMocks.value) {
        // Em preview mode, carrega mensagens mockadas
        chatStore.messages = [...mockChatMessages];
    } else if (authStore.canAccessAiChat) {
        // Carrega conversa existente do backend (persiste entre navegações)
        chatStore.fetchConversation();
    }
});

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
            v-if="isInPreviewMode && upgradeRequired"
            feature-name="Assistente IA"
            @close="disablePreviewMode"
        />

        <!-- Banner de Upgrade - Plano não inclui Assistente IA -->
        <UpgradeBanner
            v-if="upgradeRequired && !isInPreviewMode"
            title="Recurso não disponível no seu plano"
            description="Seu plano atual não inclui acesso ao Assistente IA. Faça upgrade para desbloquear conversas ilimitadas com nossa IA especializada em e-commerce."
            feature-name="Assistente IA"
            @enable-preview="enablePreviewMode('Assistente IA')"
        />

        <!-- Conteúdo - mostra se tiver acesso OU estiver em preview mode -->
        <div
            v-if="showContent"
            class="flex flex-col h-[calc(100vh-4rem)] lg:h-[calc(100vh-5rem)] overflow-hidden -m-4 sm:-m-6 lg:-m-8 -mt-4 sm:-mt-6 lg:-mt-8"
            :class="shouldUseMocks ? 'preview-mode-disabled' : ''"
        >
            <!-- Hero Header with Gradient -->
            <div class="shrink-0 relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-4 sm:px-6 lg:px-8 py-6 sm:py-8 lg:py-12">
                <!-- Background Elements -->
                <div class="absolute inset-0 overflow-hidden">
                    <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 dark:bg-primary-500/10 rounded-full blur-3xl"></div>
                    <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 dark:bg-secondary-500/10 rounded-full blur-3xl"></div>
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-accent-500/10 dark:bg-accent-500/5 rounded-full blur-3xl"></div>
                    <!-- Grid Pattern -->
                    <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
                </div>

                <div class="relative z-10 max-w-7xl mx-auto">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                        <div class="space-y-3 sm:space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 sm:w-14 h-10 sm:h-14 rounded-xl sm:rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/30">
                                    <SparklesIcon class="w-5 sm:w-7 h-5 sm:h-7 text-white" />
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h1 class="text-xl sm:text-2xl lg:text-4xl font-display font-bold text-white dark:text-gray-100">
                                            Assistente IA
                                        </h1>
                                        <span class="px-2 sm:px-3 py-0.5 sm:py-1 text-xs sm:text-sm font-semibold bg-primary-400/30 backdrop-blur-sm border border-primary-300/30 text-white rounded-lg">BETA</span>
                                    </div>
                                    <p class="text-primary-200/80 dark:text-gray-400 text-xs sm:text-sm lg:text-base mt-1">
                                        Seu consultor de e-commerce inteligente com IA
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Beta Warning Banner -->
            <div class="shrink-0 bg-gradient-to-r from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-900/10 border-b border-amber-200 dark:border-amber-800 px-4 sm:px-6 lg:px-8 py-4">
                <div class="max-w-7xl mx-auto">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0 mt-0.5">
                            <ExclamationTriangleIcon class="w-5 h-5 text-amber-600 dark:text-amber-500" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-amber-900 dark:text-amber-300 mb-1">Recurso Beta</h3>
                            <p class="text-sm text-amber-800 dark:text-amber-400">
                                Este Assistente de IA está atualmente em <span class="font-semibold">Beta</span>. Embora forneça insights valiosos, recomendamos verificar informações importantes no seu painel. Estamos continuamente melhorando sua precisão e confiabilidade.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chat Container -->
            <div class="flex-1 min-h-0 flex flex-col px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950">
                <div class="w-full flex-1 min-h-0 flex flex-col">
                    <ChatContainer />
                </div>
            </div>
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
