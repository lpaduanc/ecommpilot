<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { useAnalysisStore } from '../stores/analysisStore';
import { useAuthStore } from '../stores/authStore';
import { useNotificationStore } from '../stores/notificationStore';
import { useIntegrationStore } from '../stores/integrationStore';
import BaseButton from '../components/common/BaseButton.vue';
import BaseModal from '../components/common/BaseModal.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import UpgradeBanner from '../components/common/UpgradeBanner.vue';
import SuggestionCard from '../components/analysis/SuggestionCard.vue';
import SuggestionDetailModal from '../components/analysis/SuggestionDetailModal.vue';
import OpportunityDetailModal from '../components/analysis/OpportunityDetailModal.vue';
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
    CalendarIcon,
    ArrowLeftIcon,
    EnvelopeIcon,
} from '@heroicons/vue/24/outline';

const analysisStore = useAnalysisStore();
const authStore = useAuthStore();
const notificationStore = useNotificationStore();
const integrationStore = useIntegrationStore();

const isStoreSyncing = computed(() => integrationStore.isActiveStoreSyncing);

// Verifica acesso pelo plano
const canAccessAnalysis = computed(() => authStore.canAccessAiAnalysis);

const showCreditWarning = ref(false);
const showRateLimitWarning = ref(false);
const selectedSuggestion = ref(null);
const showSuggestionDetail = ref(false);
const selectedOpportunity = ref(null);
const showOpportunityDetail = ref(false);
const countdownInterval = ref(null);
const showChat = ref(false);
const isResendingEmail = ref(false);

// Análises anteriores
const selectedHistoricalId = ref(null);
const isViewingHistorical = ref(false);
const originalAnalysis = ref(null);

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

// Últimas 3 análises anteriores (excluindo a atual)
const recentAnalyses = computed(() => {
    const history = analysisStore.analysisHistory || [];
    const currentId = currentAnalysis.value?.id;
    return history
        .filter(a => a.id !== currentId && a.status === 'completed')
        .slice(0, 3);
});

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

function viewOpportunityDetail(opportunity) {
    selectedOpportunity.value = opportunity;
    showOpportunityDetail.value = true;
}

function handleOpportunityAskAI(opportunity) {
    showOpportunityDetail.value = false;
    showChat.value = true;
    // TODO: Pre-fill chat with opportunity context
}

async function handleStatusChange({ suggestion, status }) {
    // For persistent suggestions (new system)
    if (suggestion.id && typeof suggestion.id === 'number') {
        const result = await analysisStore.updateSuggestionStatus(suggestion.id, status);
        if (result.success) {
            // Update the selected suggestion with new data
            selectedSuggestion.value = result.suggestion;
            notificationStore.success('Status da sugestão atualizado.');
        } else {
            notificationStore.error(result.message || 'Erro ao atualizar status.');
        }
    } else {
        // Legacy system - mark as done via old API
        if (status === 'completed' && currentAnalysis.value) {
            await analysisStore.markSuggestionAsDone(currentAnalysis.value.id, suggestion.id);
            notificationStore.success('Sugestão marcada como implementada.');
        }
    }
}

async function handleAcceptSuggestion(suggestion) {
    if (!suggestion?.id) return;

    const result = await analysisStore.acceptSuggestion(suggestion.id);
    if (result.success) {
        notificationStore.success('Sugestão aceita! Acesse "Acompanhamento de Sugestões" para acompanhar.');
        // Update selected suggestion if modal is open
        if (selectedSuggestion.value?.id === suggestion.id) {
            selectedSuggestion.value = result.suggestion;
        }
    } else {
        notificationStore.error(result.message || 'Erro ao aceitar sugestão.');
    }
}

async function handleRejectSuggestion(suggestion) {
    if (!suggestion?.id) return;

    const result = await analysisStore.rejectSuggestion(suggestion.id);
    if (result.success) {
        notificationStore.success('Sugestão rejeitada.');
        // Update selected suggestion if modal is open
        if (selectedSuggestion.value?.id === suggestion.id) {
            selectedSuggestion.value = result.suggestion;
        }
    } else {
        notificationStore.error(result.message || 'Erro ao rejeitar sugestão.');
    }
}

async function handleResendEmail() {
    isResendingEmail.value = true;
    try {
        await analysisStore.resendAnalysisEmail(currentAnalysis.value.id);
        notificationStore.success('E-mail reenviado com sucesso!');
    } catch (error) {
        notificationStore.error(error.response?.data?.message || 'Erro ao reenviar e-mail');
    } finally {
        isResendingEmail.value = false;
    }
}

// Funções para análises anteriores
function formatAnalysisDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const now = new Date();

    // Normalizar para início do dia (meia-noite) para comparar apenas datas do calendário
    const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const nowOnly = new Date(now.getFullYear(), now.getMonth(), now.getDate());

    const diffDays = Math.round((nowOnly - dateOnly) / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return 'Hoje';
    if (diffDays === 1) return 'Ontem';
    if (diffDays < 7) return `Há ${diffDays} dias`;
    return date.toLocaleDateString('pt-BR');
}

function getScoreColorClasses(score) {
    if (!score) return 'bg-gray-100 dark:bg-gray-700 text-gray-500';
    if (score >= 80) return 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400';
    if (score >= 60) return 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-400';
    if (score >= 40) return 'bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-400';
    return 'bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-400';
}

function getScoreLabel(score) {
    if (!score) return 'N/A';
    if (score >= 80) return 'Excelente';
    if (score >= 60) return 'Saudável';
    if (score >= 40) return 'Atenção';
    return 'Crítico';
}

async function viewHistoricalAnalysis(analysisId) {
    if (!isViewingHistorical.value && currentAnalysis.value) {
        originalAnalysis.value = currentAnalysis.value;
    }
    selectedHistoricalId.value = analysisId;
    isViewingHistorical.value = true;
    const historical = await analysisStore.getAnalysisById(analysisId);
    if (historical) {
        analysisStore.setCurrentAnalysis(historical);
    }
}

function returnToCurrentAnalysis() {
    if (originalAnalysis.value) {
        analysisStore.setCurrentAnalysis(originalAnalysis.value);
    }
    selectedHistoricalId.value = null;
    isViewingHistorical.value = false;
    originalAnalysis.value = null;
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
    analysisStore.fetchAnalysisHistory();
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
        <!-- Banner de Upgrade - Plano não inclui Análises IA -->
        <UpgradeBanner
            v-if="!canAccessAnalysis"
            title="Recurso não disponível no seu plano"
            description="Seu plano atual não inclui acesso às Análises IA. Faça upgrade para desbloquear análises inteligentes da sua loja com sugestões personalizadas."
        />

        <!-- Conteúdo normal - só mostra se tiver acesso -->
        <template v-else>

        <!-- Banner de Análise com Erro (status = failed) -->
        <div
            v-if="currentAnalysis?.status === 'failed' && currentAnalysis?.error_message"
            class="bg-rose-50 dark:bg-rose-900/30 border-2 border-rose-300 dark:border-rose-700 rounded-xl p-5"
        >
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-12 h-12 rounded-full bg-rose-100 dark:bg-rose-800 flex items-center justify-center">
                    <ExclamationTriangleIcon class="w-6 h-6 text-rose-600 dark:text-rose-400" />
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-rose-800 dark:text-rose-200">
                        Erro na Análise
                    </h3>
                    <p class="text-sm text-rose-600 dark:text-rose-300 mt-1">
                        {{ currentAnalysis.error_message }}
                    </p>
                    <p class="text-xs text-rose-500 dark:text-rose-400 mt-2">
                        Seus créditos foram reembolsados automaticamente. Você pode tentar novamente.
                    </p>
                    <BaseButton
                        v-if="authStore.hasPermission('analysis.request') && canRequestAnalysis"
                        variant="danger"
                        size="md"
                        @click="handleRequestAnalysis"
                        class="mt-4"
                    >
                        <SparklesIcon class="w-5 h-5" />
                        Tentar Novamente
                    </BaseButton>
                </div>
            </div>
        </div>

        <!-- Banner de Análise em Processamento com Progresso -->
        <div
            v-if="pendingAnalysis && (pendingAnalysis.status === 'processing' || pendingAnalysis.status === 'pending')"
            class="bg-gradient-to-r from-primary-50 to-secondary-50 dark:from-primary-900/30 dark:to-secondary-900/30 border-2 border-primary-200 dark:border-primary-800 rounded-xl p-5"
        >
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-12 h-12 rounded-full bg-primary-100 dark:bg-primary-800 flex items-center justify-center">
                    <LoadingSpinner size="md" class="text-primary-600 dark:text-primary-400" />
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-primary-800 dark:text-primary-200">
                        Análise em Andamento
                    </h3>
                    <p class="text-sm text-primary-600 dark:text-primary-300 mt-1">
                        {{ pendingAnalysis.current_stage_name || 'Processando análise...' }}
                    </p>

                    <!-- Progress Bar -->
                    <div class="mt-3 bg-primary-100 dark:bg-primary-900/50 rounded-full h-2 overflow-hidden">
                        <div
                            class="h-full bg-gradient-to-r from-primary-500 to-secondary-500 rounded-full transition-all duration-500"
                            :style="{ width: `${pendingAnalysis.progress_percentage || 10}%` }"
                        ></div>
                    </div>

                    <div class="flex items-center justify-between mt-2 text-xs text-primary-500 dark:text-primary-400">
                        <span>Estágio {{ pendingAnalysis.current_stage || 0 }} de {{ pendingAnalysis.total_stages || 9 }}</span>
                        <span>{{ pendingAnalysis.progress_percentage || 0 }}% concluído</span>
                    </div>

                    <p class="text-xs text-primary-400 dark:text-primary-500 mt-2">
                        Tempo decorrido: {{ pendingAnalysisElapsed }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Banner de Análise com Erro (pendingAnalysis com status failed) -->
        <div
            v-if="pendingAnalysis && pendingAnalysis.status === 'failed'"
            class="bg-rose-50 dark:bg-rose-900/30 border-2 border-rose-300 dark:border-rose-700 rounded-xl p-5"
        >
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-12 h-12 rounded-full bg-rose-100 dark:bg-rose-800 flex items-center justify-center">
                    <ExclamationTriangleIcon class="w-6 h-6 text-rose-600 dark:text-rose-400" />
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-rose-800 dark:text-rose-200">
                        Erro na Análise
                    </h3>
                    <p class="text-sm text-rose-600 dark:text-rose-300 mt-1">
                        {{ pendingAnalysis.error_message || 'Ocorreu um erro ao processar sua análise. Por favor, tente novamente.' }}
                    </p>
                    <p class="text-xs text-rose-500 dark:text-rose-400 mt-2">
                        Seus créditos foram reembolsados automaticamente. Você pode tentar novamente.
                    </p>
                    <BaseButton
                        v-if="authStore.hasPermission('analysis.request')"
                        variant="danger"
                        size="md"
                        @click="handleRequestAnalysis"
                        class="mt-4"
                    >
                        <SparklesIcon class="w-5 h-5" />
                        Tentar Novamente
                    </BaseButton>
                </div>
            </div>
        </div>

        <!-- Alerta de E-mail não enviado - TOPO DA PÁGINA -->
        <div
            v-if="currentAnalysis?.status === 'completed' && currentAnalysis?.email_error && !currentAnalysis?.email_sent_at"
            class="bg-rose-50 dark:bg-rose-900/30 border-2 border-rose-300 dark:border-rose-700 rounded-xl p-5"
        >
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-12 h-12 rounded-full bg-rose-100 dark:bg-rose-800 flex items-center justify-center">
                    <ExclamationTriangleIcon class="w-6 h-6 text-rose-600 dark:text-rose-400" />
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-semibold text-rose-800 dark:text-rose-200">
                        E-mail não enviado
                    </h3>
                    <p class="text-sm text-rose-600 dark:text-rose-300 mt-1">
                        Não foi possível enviar o e-mail com os resultados desta análise.
                        Clique no botão abaixo para tentar novamente.
                    </p>
                    <p class="text-xs text-rose-500 dark:text-rose-400 mt-2">
                        Erro: {{ currentAnalysis.email_error }}
                    </p>
                    <BaseButton
                        variant="danger"
                        size="md"
                        @click="handleResendEmail"
                        :loading="isResendingEmail"
                        class="mt-4"
                    >
                        <EnvelopeIcon class="w-5 h-5" />
                        Reenviar E-mail da Análise
                    </BaseButton>
                </div>
            </div>
        </div>

        <!-- Compact Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/20">
                    <SparklesIcon class="w-5 h-5 text-white" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Análises IA</h1>
                        <span
                            v-if="currentAnalysis?.id"
                            class="text-sm font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded"
                        >
                            #{{ currentAnalysis.id }}
                        </span>
                    </div>
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

        <!-- Previous Analyses Section -->
        <div v-if="recentAnalyses.length > 0 && !isLoading" class="relative">
            <!-- Section Header -->
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <ClockIcon class="w-4 h-4 text-gray-400" />
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-400">
                        Análises Anteriores
                    </h2>
                </div>
                <button
                    v-if="isViewingHistorical"
                    @click="returnToCurrentAnalysis"
                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors"
                >
                    <ArrowLeftIcon class="w-3.5 h-3.5" />
                    Voltar para atual
                </button>
            </div>

            <!-- Horizontal Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <button
                    v-for="analysis in recentAnalyses"
                    :key="analysis.id"
                    @click="viewHistoricalAnalysis(analysis.id)"
                    :class="[
                        'group relative text-left p-4 rounded-xl border transition-all duration-200',
                        selectedHistoricalId === analysis.id
                            ? 'bg-primary-50 dark:bg-primary-900/30 border-primary-300 dark:border-primary-700 ring-2 ring-primary-500/20'
                            : 'bg-white dark:bg-gray-800 border-gray-100 dark:border-gray-700 hover:border-primary-200 dark:hover:border-primary-800 hover:shadow-md'
                    ]"
                >
                    <!-- Active Indicator -->
                    <div
                        v-if="selectedHistoricalId === analysis.id"
                        class="absolute top-2 right-2 w-2 h-2 rounded-full bg-primary-500 animate-pulse"
                    ></div>

                    <!-- Date and ID -->
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                            <CalendarIcon class="w-3.5 h-3.5" />
                            {{ formatAnalysisDate(analysis.created_at) }}
                        </div>
                        <span class="text-xs font-medium text-gray-400 dark:text-gray-500">
                            #{{ analysis.id }}
                        </span>
                    </div>

                    <!-- Health Score Mini -->
                    <div class="flex items-center gap-3 mb-2">
                        <div
                            :class="[
                                'w-10 h-10 rounded-lg flex items-center justify-center text-sm font-bold',
                                getScoreColorClasses(analysis.summary?.health_score)
                            ]"
                        >
                            {{ analysis.summary?.health_score || '-' }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-medium text-gray-900 dark:text-gray-100 truncate">
                                Saúde da Loja
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ getScoreLabel(analysis.summary?.health_score) }}
                            </div>
                        </div>
                    </div>

                    <!-- Suggestions Count -->
                    <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <BoltIcon class="w-3.5 h-3.5" />
                        {{ analysis.suggestions?.length || 0 }} sugestões
                    </div>

                    <!-- Hover Effect -->
                    <div class="absolute inset-0 rounded-xl bg-gradient-to-t from-primary-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
                </button>
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
                    <OpportunitiesPanel
                        v-if="opportunities.length > 0 && !showChat"
                        :opportunities="opportunities"
                        @view-detail="viewOpportunityDetail"
                    />

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
                                    @accept="handleAcceptSuggestion"
                                    @reject="handleRejectSuggestion"
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
                                    @accept="handleAcceptSuggestion"
                                    @reject="handleRejectSuggestion"
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
                                    @accept="handleAcceptSuggestion"
                                    @reject="handleRejectSuggestion"
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
            mode="analysis"
            @close="showSuggestionDetail = false"
            @status-change="handleStatusChange"
            @accept="handleAcceptSuggestion"
            @reject="handleRejectSuggestion"
        />

        <!-- Opportunity Detail Modal -->
        <OpportunityDetailModal
            :show="showOpportunityDetail"
            :opportunity="selectedOpportunity"
            @close="showOpportunityDetail = false"
            @ask-ai="handleOpportunityAskAI"
        />

        </template>
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
