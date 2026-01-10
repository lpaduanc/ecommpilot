<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { useAnalysisStore } from '../stores/analysisStore';
import { useAuthStore } from '../stores/authStore';
import { useNotificationStore } from '../stores/notificationStore';
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

const showCreditWarning = ref(false);
const showRateLimitWarning = ref(false);
const selectedSuggestion = ref(null);
const showSuggestionDetail = ref(false);
const countdownInterval = ref(null);
const showChat = ref(false);

const isLoading = computed(() => analysisStore.isLoading);
const isRequesting = computed(() => analysisStore.isRequesting);
const currentAnalysis = computed(() => analysisStore.currentAnalysis);
const suggestions = computed(() => analysisStore.suggestions);
const summary = computed(() => analysisStore.summary);
const alerts = computed(() => analysisStore.alerts);
const opportunities = computed(() => analysisStore.opportunities);
const canRequestAnalysis = computed(() => analysisStore.canRequestAnalysis);
const timeUntilNext = computed(() => analysisStore.timeUntilNextAnalysis);
const credits = computed(() => analysisStore.credits);

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
        notificationStore.success('Análise concluída! Confira as sugestões.');
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
});
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
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/30">
                                <SparklesIcon class="w-7 h-7 text-white" />
                            </div>
                            <div>
                                <h1 class="text-3xl lg:text-4xl font-display font-bold text-white">
                                    Análises IA
                                </h1>
                                <p class="text-primary-200/80 text-sm lg:text-base">
                                    Insights inteligentes para potencializar suas vendas
                                </p>
                            </div>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="flex flex-wrap items-center gap-4 lg:gap-6 text-sm">
                            <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 backdrop-blur-sm border border-white/10">
                                <BoltIcon class="w-4 h-4 text-accent-400" />
                                <span class="text-white/90">{{ totalSuggestions }} sugestões</span>
                            </div>
                            <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 backdrop-blur-sm border border-white/10">
                                <ChartBarIcon class="w-4 h-4 text-success-400" />
                                <span class="text-white/90">{{ completedSuggestions }} implementadas</span>
                            </div>
                            <div class="flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 backdrop-blur-sm border border-white/10">
                                <RocketLaunchIcon class="w-4 h-4 text-secondary-400" />
                                <span class="text-white/90">{{ credits }} créditos</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                        <button
                            @click="showChat = !showChat"
                            class="lg:hidden px-4 py-2.5 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white font-medium hover:bg-white/20 transition-all"
                        >
                            {{ showChat ? 'Ver Análises' : 'Chat IA' }}
                        </button>
                        <button
                            v-if="authStore.hasPermission('analysis.request')"
                            @click="handleRequestAnalysis"
                            :disabled="isRequesting"
                            class="group relative px-6 py-3 rounded-xl bg-gradient-to-r from-primary-500 to-secondary-500 text-white font-semibold shadow-lg shadow-primary-500/30 hover:shadow-xl hover:shadow-primary-500/40 hover:scale-[1.02] active:scale-[0.98] transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                        >
                            <span class="flex items-center gap-2">
                                <SparklesIcon v-if="!isRequesting" class="w-5 h-5" />
                                <LoadingSpinner v-else size="sm" class="text-white" />
                                Nova Análise
                            </span>
                            <div class="absolute inset-0 rounded-xl bg-gradient-to-r from-primary-400 to-secondary-400 opacity-0 group-hover:opacity-100 transition-opacity -z-10 blur-xl"></div>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">
            <!-- Loading State -->
            <div v-if="isLoading && !currentAnalysis" class="flex flex-col items-center justify-center py-32">
                <div class="relative">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500"></div>
                    <LoadingSpinner size="xl" class="absolute inset-0 m-auto text-white" />
                </div>
                <p class="text-gray-500 mt-6 font-medium">Carregando análises...</p>
            </div>

            <!-- Content -->
            <template v-else>
                <div class="max-w-7xl mx-auto">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <!-- Left Column - Analysis (hidden on mobile when chat is open) -->
                        <div :class="['lg:col-span-2 space-y-6', showChat ? 'hidden lg:block' : '']">
                            <!-- Health Score -->
                            <HealthScore v-if="summary" :summary="summary" />

                            <!-- Alerts -->
                            <AnalysisAlerts v-if="alerts.length > 0" :alerts="alerts" />

                            <!-- Suggestions by Priority -->
                            <div v-if="suggestions.length > 0" class="space-y-8">
                                <!-- High Priority -->
                                <div v-if="groupedSuggestions.high.length > 0">
                                    <div class="flex items-center gap-3 mb-5">
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-danger-500 to-danger-600 flex items-center justify-center shadow-lg shadow-danger-500/30">
                                            <BoltIcon class="w-5 h-5 text-white" />
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Alta Prioridade</h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Ações com maior impacto imediato</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <SuggestionCard
                                            v-for="(suggestion, index) in groupedSuggestions.high"
                                            :key="suggestion.id"
                                            :suggestion="suggestion"
                                            @view-detail="viewSuggestionDetail"
                                        />
                                    </div>
                                </div>

                                <!-- Medium Priority -->
                                <div v-if="groupedSuggestions.medium.length > 0">
                                    <div class="flex items-center gap-3 mb-5">
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-accent-500 to-accent-600 flex items-center justify-center shadow-lg shadow-accent-500/30">
                                            <ChartBarIcon class="w-5 h-5 text-white" />
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Média Prioridade</h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Melhorias estratégicas recomendadas</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <SuggestionCard
                                            v-for="(suggestion, index) in groupedSuggestions.medium"
                                            :key="suggestion.id"
                                            :suggestion="suggestion"
                                            @view-detail="viewSuggestionDetail"
                                        />
                                    </div>
                                </div>

                                <!-- Low Priority -->
                                <div v-if="groupedSuggestions.low.length > 0">
                                    <div class="flex items-center gap-3 mb-5">
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-success-500 to-success-600 flex items-center justify-center shadow-lg shadow-success-500/30">
                                            <RocketLaunchIcon class="w-5 h-5 text-white" />
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Baixa Prioridade</h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Otimizações complementares</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <SuggestionCard
                                            v-for="(suggestion, index) in groupedSuggestions.low"
                                            :key="suggestion.id"
                                            :suggestion="suggestion"
                                            @view-detail="viewSuggestionDetail"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div v-else class="text-center py-20">
                                <div class="relative inline-block mb-6">
                                    <div class="w-32 h-32 rounded-3xl bg-gradient-to-br from-primary-100 to-secondary-100 flex items-center justify-center">
                                        <SparklesIcon class="w-16 h-16 text-primary-400" />
                                    </div>
                                    <div class="absolute -bottom-2 -right-2 w-8 h-8 rounded-full bg-accent-400 flex items-center justify-center">
                                        <BoltIcon class="w-4 h-4 text-white" />
                                    </div>
                                </div>
                                <h3 class="text-2xl font-display font-bold text-gray-900 mb-3">
                                    Nenhuma análise disponível
                                </h3>
                                <p class="text-gray-500 mb-8 max-w-md mx-auto">
                                    Solicite sua primeira análise e receba sugestões personalizadas baseadas em inteligência artificial para impulsionar suas vendas.
                                </p>
                                <button
                                    v-if="authStore.hasPermission('analysis.request')"
                                    @click="handleRequestAnalysis"
                                    class="inline-flex items-center gap-2 px-8 py-4 rounded-2xl bg-gradient-to-r from-primary-500 to-secondary-500 text-white font-semibold shadow-lg shadow-primary-500/30 hover:shadow-xl hover:shadow-primary-500/40 hover:scale-[1.02] transition-all duration-200"
                                >
                                    <SparklesIcon class="w-5 h-5" />
                                    Solicitar Primeira Análise
                                </button>
                                <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                                    Você não possui permissão para solicitar análises.
                                </p>
                            </div>
                        </div>

                        <!-- Right Column - Chat & Opportunities -->
                        <div :class="['space-y-6', !showChat ? 'hidden lg:block' : '']">
                            <!-- Opportunities -->
                            <OpportunitiesPanel v-if="opportunities.length > 0" :opportunities="opportunities" />

                            <!-- Chat -->
                            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden h-[500px] lg:h-[600px]">
                                <ChatContainer compact />
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

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
