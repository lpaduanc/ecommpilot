<script setup>
import { computed, watch, onMounted, onUnmounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useAnalysisStore } from '../../stores/analysisStore';
import {
    SparklesIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const route = useRoute();
const analysisStore = useAnalysisStore();

// Track when analysis completes to show success banner briefly
const recentlyCompleted = computed(() => {
    if (!analysisStore.currentAnalysis?.completed_at) return false;
    const completedAt = new Date(analysisStore.currentAnalysis.completed_at);
    const fiveMinutesAgo = new Date(Date.now() - 5 * 60 * 1000);
    return completedAt > fiveMinutesAgo;
});

// Check if pending analysis has error (recently failed)
const hasPendingError = computed(() => {
    return analysisStore.pendingAnalysis?.status === 'failed';
});

// Check if analysis has error
const hasError = computed(() => {
    return hasPendingError.value || analysisStore.currentAnalysis?.status === 'failed';
});

// Show banner conditions
// Não mostra o banner se estiver na página de análise
const showBanner = computed(() => {
    if (route.name === 'analysis') {
        return false;
    }
    return analysisStore.hasAnalysisInProgress ||
        hasPendingError.value ||
        (recentlyCompleted.value && !dismissedCompletion.value);
});

// Dismissable state for completion banner
import { ref } from 'vue';
const dismissedCompletion = ref(false);

// Banner variant based on status
const bannerVariant = computed(() => {
    if (hasError.value) return 'danger';
    if (recentlyCompleted.value) return 'success';
    return 'info'; // in progress
});

// Banner message
const bannerMessage = computed(() => {
    if (hasError.value) {
        // Prefer pending analysis error message if it exists
        return analysisStore.pendingAnalysis?.error_message
            || analysisStore.currentAnalysis?.error_message
            || 'Ocorreu um erro durante a análise. Tente novamente.';
    }
    if (recentlyCompleted.value) {
        return 'Sua análise foi concluída! Clique para ver os insights e recomendações.';
    }
    return 'Nossa IA está processando os dados';
});

// Banner icon component
const bannerIcon = computed(() => {
    if (hasError.value) return ExclamationTriangleIcon;
    if (recentlyCompleted.value) return CheckCircleIcon;
    return SparklesIcon;
});

// Action button label
const actionLabel = computed(() => {
    if (hasError.value) return 'Ver Detalhes';
    if (recentlyCompleted.value) return 'Ver Análise';
    return 'Ver Progresso';
});

// Handle action button click
async function handleAction() {
    // Navigate to analysis page for both errors and success
    // The analysis page has better context and retry button
    router.push({ name: 'analysis' });
    if (recentlyCompleted.value) {
        dismissedCompletion.value = true;
    }
}

// Fetch analysis status on mount
onMounted(async () => {
    await analysisStore.fetchCurrentAnalysis();
});

// Reset dismissed state when new analysis starts
watch(() => analysisStore.pendingAnalysis, (newVal, oldVal) => {
    if (newVal && !oldVal) {
        dismissedCompletion.value = false;
    }
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
                    : bannerVariant === 'success'
                        ? 'bg-gradient-to-r from-emerald-600 via-teal-500 to-emerald-600'
                        : 'bg-gradient-to-r from-violet-600 via-purple-600 to-violet-600'
            ]"
        >
            <!-- Animated background effect for in-progress -->
            <div
                v-if="analysisStore.hasAnalysisInProgress && !hasError"
                class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent animate-shimmer"
            ></div>

            <!-- Pulsing border effect for in-progress -->
            <div
                v-if="analysisStore.hasAnalysisInProgress && !hasError"
                class="absolute bottom-0 left-0 right-0 h-1 bg-white/30"
            >
                <div class="h-full bg-white animate-progress"></div>
            </div>

            <div class="relative flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-4 px-4 sm:px-6 py-3 sm:py-4">
                <!-- Icon with animation -->
                <div
                    :class="[
                        'flex items-center justify-center w-10 h-10 sm:w-12 sm:h-12 rounded-full flex-shrink-0',
                        bannerVariant === 'danger'
                            ? 'bg-red-700/50'
                            : bannerVariant === 'success'
                                ? 'bg-emerald-700/50'
                                : 'bg-violet-700/50'
                    ]"
                >
                    <component
                        :is="bannerIcon"
                        :class="[
                            'w-5 h-5 sm:w-7 sm:h-7 text-white',
                            analysisStore.hasAnalysisInProgress && !hasError ? 'animate-pulse' : ''
                        ]"
                    />
                </div>

                <!-- Message -->
                <div class="flex-1 text-center sm:text-left min-w-0">
                    <p class="text-base sm:text-lg font-bold text-white">
                        <span v-if="hasError">
                            Falha na Análise
                        </span>
                        <span v-else-if="recentlyCompleted">
                            Análise Concluída!
                        </span>
                        <span v-else>
                            Analisando sua loja...
                        </span>
                    </p>
                    <p class="text-sm text-white/90 mt-0.5">
                        {{ bannerMessage }}
                    </p>
                </div>

                <!-- Action button -->
                <button
                    @click="handleAction"
                    :class="[
                        'px-4 sm:px-6 py-2.5 sm:py-3 rounded-xl text-sm sm:text-base font-bold shadow-lg transition-all hover:scale-105 active:scale-95 flex-shrink-0',
                        bannerVariant === 'danger'
                            ? 'bg-white text-red-600 hover:bg-red-50'
                            : bannerVariant === 'success'
                                ? 'bg-white text-emerald-600 hover:bg-emerald-50'
                                : 'bg-white text-violet-600 hover:bg-violet-50'
                    ]"
                >
                    {{ actionLabel }}
                </button>

                <!-- Progress dots for in-progress state -->
                <div
                    v-if="analysisStore.hasAnalysisInProgress && !hasError"
                    class="hidden lg:flex items-center gap-2"
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
