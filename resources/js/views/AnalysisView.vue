<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { useAnalysisStore } from '../stores/analysisStore';
import { useAuthStore } from '../stores/authStore';
import { useNotificationStore } from '../stores/notificationStore';
import { useIntegrationStore } from '../stores/integrationStore';
import BaseButton from '../components/common/BaseButton.vue';
import BaseModal from '../components/common/BaseModal.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import SuggestionCard from '../components/analysis/SuggestionCard.vue';
import SuggestionDetailModal from '../components/analysis/SuggestionDetailModal.vue';
import HealthScore from '../components/analysis/HealthScore.vue';
import AnalysisAlerts from '../components/analysis/AnalysisAlerts.vue';
import OpportunitiesPanel from '../components/analysis/OpportunitiesPanel.vue';
import ChatContainer from '../components/chat/ChatContainer.vue';
import {
    SparklesIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    BoltIcon,
    RocketLaunchIcon,
    ChartBarIcon,
} from '@heroicons/vue/24/outline';

const analysisStore = useAnalysisStore();
const authStore = useAuthStore();
const notificationStore = useNotificationStore();
const integrationStore = useIntegrationStore();

const isStoreSyncing = computed(() => integrationStore.isActiveStoreSyncing);

const showCreditWarning = ref(false);
const showRateLimitWarning = ref(false);
const selectedSuggestion = ref(null);
const showSuggestionDetail = ref(false);
const countdownInterval = ref(null);
const showChat = ref(false);

const isLoading = computed(() => analysisStore.isLoading);
const isRequesting = computed(() => analysisStore.isRequesting);
const currentAnalysis = computed(() => analysisStore.currentAnalysis);
const hasAnalysisInProgress = computed(() => analysisStore.hasAnalysisInProgress);
const pendingAnalysis = computed(() => analysisStore.pendingAnalysis);
const suggestions = computed(() => analysisStore.suggestions);
const summary = computed(() => analysisStore.summary);
const alerts = computed(() => analysisStore.alerts);
const opportunities = computed(() => analysisStore.opportunities);
const canRequestAnalysis = computed(() => analysisStore.canRequestAnalysis);
const timeUntilNext = computed(() => analysisStore.timeUntilNextAnalysis);
const credits = computed(() => analysisStore.credits);

const pendingAnalysisElapsed = computed(() => {
    if (!pendingAnalysis.value?.created_at) return null;
    const start = new Date(pendingAnalysis.value.created_at);
    const now = new Date();
    const diff = Math.floor((now - start) / 1000);
    const minutes = Math.floor(diff / 60);
    const seconds = diff % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
});

const groupedSuggestions = computed(() => {
    return {
        high: analysisStore.highPrioritySuggestions,
        medium: analysisStore.mediumPrioritySuggestions,
        low: analysisStore.lowPrioritySuggestions,
    };
});

const totalSuggestions = computed(() => suggestions.value.length);
const completedSuggestions = computed(() => 
    suggestions.value.filter(s => s.is_done).length
);

function handleRequestAnalysis() {
    if (isStoreSyncing.value) {
        notificationStore.warning('Aguarde a sincronização da loja ser concluída antes de solicitar uma nova análise.');
        return;
    }
    if (hasAnalysisInProgress.value) {
        notificationStore.warning('Já existe uma análise em andamento para esta loja.');
        return;
    }
    if (!canRequestAnalysis.value) {
        showRateLimitWarning.value = true;
        return;
    }
    showCreditWarning.value = true;
}

async function confirmAnalysis() {
    showCreditWarning.value = false;

    const result = await analysisStore.requestNewAnalysis();

    if (result.success) {
        notificationStore.success('Análise iniciada! Você será notificado quando ela for concluída.');
    } else {
        notificationStore.error(result.message);
    }
}

function viewSuggestionDetail(suggestion) {
    selectedSuggestion.value = suggestion;
    showSuggestionDetail.value = true;
}

function startCountdown() {
    if (countdownInterval.value) {
        clearInterval(countdownInterval.value);
    }
    
    countdownInterval.value = setInterval(() => {
        analysisStore.nextAvailableAt = analysisStore.nextAvailableAt;
    }, 1000);
}

onMounted(() => {
    analysisStore.fetchCurrentAnalysis();
    startCountdown();
});

onUnmounted(() => {
    if (countdownInterval.value) {
        clearInterval(countdownInterval.value);
    }
    analysisStore.stopPolling();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Compact Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/20">
                    <SparklesIcon class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Análises IA</h1>
                    <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                        <span class="flex items-center gap-1">
                            <BoltIcon class="w-3.5 h-3.5" />
                            {{ totalSuggestions }} sugestões
                        </span>
                        <span class="flex items-center gap-1">
                            <ChartBarIcon class="w-3.5 h-3.5" />
                            {{ completedSuggestions }} implementadas
                        </span>
                        <span class="flex items-center gap-1">
                            <RocketLaunchIcon class="w-3.5 h-3.5" />
                            {{ credits }} créditos
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="showChat = !showChat"
                    class="px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                    :class="showChat
                        ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700'"
                >
                    {{ showChat ? 'Ocultar Chat' : 'Chat IA' }}
                </button>
                <button
                    v-if="authStore.hasPermission('analysis.request')"
                    @click="handleRequestAnalysis"
                    :disabled="isRequesting || isStoreSyncing || hasAnalysisInProgress"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-primary-500 to-secondary-500 text-white text-sm font-semibold shadow-md hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <SparklesIcon v-if="!isRequesting" class="w-4 h-4" />
                    <LoadingSpinner v-else size="sm" class="text-white" />
                    Nova Análise
                </button>
            </div>
        </div>

        <!-- Analysis In Progress Banner -->
        <div v-if="hasAnalysisInProgress" class="relative overflow-hidden rounded-xl bg-gradient-to-r from-primary-500/10 via-secondary-500/10 to-accent-500/10 dark:from-primary-500/20 dark:via-secondary-500/20 dark:to-accent-500/20 border border-primary-200 dark:border-primary-800">
            <div class="px-4 py-3">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-primary-500 to-secondary-500 flex items-center justify-center">
                                <SparklesIcon class="w-5 h-5 text-white animate-pulse" />
                            </div>
                            <div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-accent-400 border-2 border-white dark:border-gray-800 animate-ping"></div>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Analisando sua loja...</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Nossa IA está processando os dados</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="px-3 py-1.5 rounded-lg bg-white/50 dark:bg-gray-800/50 text-xs font-medium text-gray-700 dark:text-gray-300">
                            {{ pendingAnalysis?.status === 'processing' ? 'Processando' : 'Na fila' }}
                        </div>
                        <div class="flex items-center gap-1 text-xs text-gray-500">
                            <ClockIcon class="w-3.5 h-3.5" />
                            <span class="tabular-nums">{{ pendingAnalysisElapsed }}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 h-1 bg-gray-200/50 dark:bg-gray-700/50 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-primary-500 via-secondary-500 to-accent-500 rounded-full animate-progress-indeterminate"></div>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="isLoading && !currentAnalysis" class="flex flex-col items-center justify-center py-20">
            <LoadingSpinner size="lg" class="text-primary-500" />
            <p class="text-gray-500 mt-4 text-sm">Carregando análises...</p>
        </div>

        <!-- Main Content -->
        <template v-else>
            <div class="flex gap-6">
                <!-- Main Column -->
                <div class="flex-1 min-w-0 space-y-6">
                    <!-- Health Score + Alerts Row -->
                    <div v-if="summary || alerts.length > 0" class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        <HealthScore v-if="summary" :summary="summary" />
                        <AnalysisAlerts v-if="alerts.length > 0" :alerts="alerts" />
                    </div>

                    <!-- Opportunities Row -->
                    <OpportunitiesPanel v-if="opportunities.length > 0 && !showChat" :opportunities="opportunities" />

                    <!-- Suggestions -->
                    <div v-if="suggestions.length > 0" class="space-y-6">
                        <!-- High Priority -->
                        <div v-if="groupedSuggestions.high.length > 0">
                            <div class="flex items-center gap-2 mb-4">
                                <div class="w-8 h-8 rounded-lg bg-danger-100 dark:bg-danger-900/30 flex items-center justify-center">
                                    <BoltIcon class="w-4 h-4 text-danger-600 dark:text-danger-400" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Alta Prioridade</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Ações com maior impacto imediato</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                                <SuggestionCard
                                    v-for="suggestion in groupedSuggestions.high"
                                    :key="suggestion.id"
                                    :suggestion="suggestion"
                                    @view-detail="viewSuggestionDetail"
                                />
                            </div>
                        </div>

                        <!-- Medium Priority -->
                        <div v-if="groupedSuggestions.medium.length > 0">
                            <div class="flex items-center gap-2 mb-4">
                                <div class="w-8 h-8 rounded-lg bg-accent-100 dark:bg-accent-900/30 flex items-center justify-center">
                                    <ChartBarIcon class="w-4 h-4 text-accent-600 dark:text-accent-400" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Média Prioridade</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Melhorias estratégicas recomendadas</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                                <SuggestionCard
                                    v-for="suggestion in groupedSuggestions.medium"
                                    :key="suggestion.id"
                                    :suggestion="suggestion"
                                    @view-detail="viewSuggestionDetail"
                                />
                            </div>
                        </div>

                        <!-- Low Priority -->
                        <div v-if="groupedSuggestions.low.length > 0">
                            <div class="flex items-center gap-2 mb-4">
                                <div class="w-8 h-8 rounded-lg bg-success-100 dark:bg-success-900/30 flex items-center justify-center">
                                    <RocketLaunchIcon class="w-4 h-4 text-success-600 dark:text-success-400" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Baixa Prioridade</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Otimizações complementares</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                                <SuggestionCard
                                    v-for="suggestion in groupedSuggestions.low"
                                    :key="suggestion.id"
                                    :suggestion="suggestion"
                                    @view-detail="viewSuggestionDetail"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="text-center py-16">
                        <div class="relative inline-block mb-4">
                            <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary-100 to-secondary-100 dark:from-primary-900/30 dark:to-secondary-900/30 flex items-center justify-center">
                                <SparklesIcon class="w-10 h-10 text-primary-500" />
                            </div>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">Nenhuma análise disponível</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 max-w-sm mx-auto">
                            Solicite sua primeira análise e receba sugestões personalizadas baseadas em IA.
                        </p>
                        <button
                            v-if="authStore.hasPermission('analysis.request')"
                            @click="handleRequestAnalysis"
                            :disabled="isStoreSyncing || hasAnalysisInProgress"
                            class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-primary-500 to-secondary-500 text-white font-semibold shadow-lg hover:shadow-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <SparklesIcon class="w-5 h-5" />
                            Solicitar Primeira Análise
                        </button>
                    </div>
                </div>

                <!-- Chat Sidebar -->
                <div
                    v-if="showChat"
                    class="w-80 xl:w-96 flex-shrink-0 hidden lg:block"
                >
                    <div class="sticky top-6">
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden h-[calc(100vh-180px)]">
                            <ChatContainer compact />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile Chat (fullscreen overlay) -->
            <div
                v-if="showChat"
                class="lg:hidden fixed inset-0 z-50 bg-white dark:bg-gray-900"
            >
                <div class="flex flex-col h-full">
                    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="font-semibold text-gray-900 dark:text-gray-100">Chat IA</h2>
                        <button
                            @click="showChat = false"
                            class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <ChatContainer />
                    </div>
                </div>
            </div>
        </template>

        <!-- Credit Warning Modal -->
        <BaseModal
            :show="showCreditWarning"
            @close="showCreditWarning = false"
        >
            <div class="text-center py-4">
                <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-accent-400 to-accent-500 flex items-center justify-center mx-auto mb-6 shadow-lg shadow-accent-500/30">
                    <ExclamationTriangleIcon class="w-10 h-10 text-white" />
                </div>
                <h3 class="text-xl font-display font-bold text-gray-900 mb-2">Consumo de Créditos</h3>
                <p class="text-gray-500 mb-6">
                    Esta análise irá consumir recursos de IA.
                </p>
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-2xl p-6 mb-6 space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 dark:text-gray-400">Você possui:</span>
                        <span class="font-bold text-lg text-gray-900 dark:text-gray-100">{{ credits }} créditos</span>
                    </div>
                    <div class="h-px bg-gray-200"></div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 dark:text-gray-400">Custo desta análise:</span>
                        <span class="font-bold text-lg text-primary-600">1 crédito</span>
                    </div>
                </div>
                <p class="text-sm text-gray-400 mb-6">
                    Ao esgotar seus créditos, será necessário adquirir mais para continuar utilizando as análises de IA.
                </p>
                <div class="flex gap-3">
                    <button
                        @click="showCreditWarning = false"
                        class="flex-1 px-6 py-3 rounded-xl border border-gray-200 text-gray-700 font-medium hover:bg-gray-50 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="confirmAnalysis"
                        :disabled="isRequesting"
                        class="flex-1 px-6 py-3 rounded-xl bg-gradient-to-r from-primary-500 to-secondary-500 text-white font-semibold shadow-lg shadow-primary-500/30 hover:shadow-xl transition-all disabled:opacity-50"
                    >
                        <span v-if="!isRequesting">Confirmar Análise</span>
                        <LoadingSpinner v-else size="sm" class="mx-auto" />
                    </button>
                </div>
            </div>
        </BaseModal>

        <!-- Rate Limit Warning Modal -->
        <BaseModal
            :show="showRateLimitWarning"
            @close="showRateLimitWarning = false"
        >
            <div class="text-center py-4">
                <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-500 flex items-center justify-center mx-auto mb-6 shadow-lg shadow-primary-500/30">
                    <ClockIcon class="w-10 h-10 text-white" />
                </div>
                <h3 class="text-xl font-display font-bold text-gray-900 mb-2">Análise em Andamento</h3>
                <p class="text-gray-500 mb-6">
                    Você já solicitou uma análise recentemente.
                </p>
                <div class="bg-gradient-to-r from-primary-50 to-secondary-50 rounded-2xl p-6 mb-6">
                    <p class="text-sm text-gray-600 mb-2">Próxima análise disponível em:</p>
                    <p class="text-4xl font-display font-bold bg-gradient-to-r from-primary-600 to-secondary-600 bg-clip-text text-transparent">
                        {{ timeUntilNext || '00:00' }}
                    </p>
                </div>
                <p class="text-sm text-gray-400 mb-6">
                    Enquanto isso, você pode revisar as sugestões anteriores ou conversar com o assistente.
                </p>
                <button
                    @click="showRateLimitWarning = false"
                    class="w-full px-6 py-3 rounded-xl bg-gradient-to-r from-primary-500 to-secondary-500 text-white font-semibold shadow-lg shadow-primary-500/30 hover:shadow-xl transition-all"
                >
                    Entendi
                </button>
            </div>
        </BaseModal>

        <!-- Suggestion Detail Modal -->
        <SuggestionDetailModal
            :show="showSuggestionDetail"
            :suggestion="selectedSuggestion"
            @close="showSuggestionDetail = false"
        />
    </div>
</template>

<style scoped>
@keyframes progress-indeterminate {
    0% {
        transform: translateX(-100%);
        width: 30%;
    }
    50% {
        width: 50%;
    }
    100% {
        transform: translateX(400%);
        width: 30%;
    }
}

.animate-progress-indeterminate {
    animation: progress-indeterminate 1.5s ease-in-out infinite;
}
</style>
