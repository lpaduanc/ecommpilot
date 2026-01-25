<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useNotificationStore } from '../stores/notificationStore';
import { useAuthStore } from '../stores/authStore';
import api from '../services/api';
import BaseButton from '../components/common/BaseButton.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import SuggestionDetailModal from '../components/analysis/SuggestionDetailModal.vue';
import SuggestionChatPanel from '../components/analysis/SuggestionChatPanel.vue';
import {
    ClipboardDocumentListIcon,
    CalendarIcon,
    CheckCircleIcon,
    ClockIcon,
    PlayIcon,
    XMarkIcon,
    FunnelIcon,
    ArrowPathIcon,
    SparklesIcon,
    ChevronDownIcon,
} from '@heroicons/vue/24/outline';

const notificationStore = useNotificationStore();
const authStore = useAuthStore();

const isLoading = ref(false);
const analyses = ref([]);
const stats = ref(null);
const selectedSuggestion = ref(null);
const showSuggestionDetail = ref(false);
const expandedAnalyses = ref(new Set());
const showChatPanel = ref(false);
const chatPanelContext = ref(null);

// Filters
const statusFilter = ref(null);
const categoryFilter = ref(null);
const impactFilter = ref(null);

const statusOptions = [
    { value: null, label: 'Todos os Status' },
    { value: 'accepted', label: 'Aguardando', icon: ClockIcon, color: 'text-gray-600' },
    { value: 'in_progress', label: 'Em Andamento', icon: PlayIcon, color: 'text-primary-600' },
    { value: 'completed', label: 'Concluído', icon: CheckCircleIcon, color: 'text-success-600' },
];

const categoryOptions = [
    { value: null, label: 'Todas as Categorias' },
    { value: 'marketing', label: 'Marketing' },
    { value: 'pricing', label: 'Precificação' },
    { value: 'inventory', label: 'Estoque' },
    { value: 'product', label: 'Produtos' },
    { value: 'customer', label: 'Clientes' },
    { value: 'conversion', label: 'Conversão' },
    { value: 'coupon', label: 'Cupons' },
    { value: 'operational', label: 'Operacional' },
];

const impactOptions = [
    { value: null, label: 'Todos os Impactos' },
    { value: 'high', label: 'Alto Impacto' },
    { value: 'medium', label: 'Médio Impacto' },
    { value: 'low', label: 'Baixo Impacto' },
];

const categoryConfig = {
    marketing: { icon: '📣', label: 'Marketing', bg: 'bg-pink-50 dark:bg-pink-900/30', text: 'text-pink-700 dark:text-pink-400' },
    pricing: { icon: '💰', label: 'Precificação', bg: 'bg-amber-50 dark:bg-amber-900/30', text: 'text-amber-700 dark:text-amber-400' },
    inventory: { icon: '📦', label: 'Estoque', bg: 'bg-sky-50 dark:bg-sky-900/30', text: 'text-sky-700 dark:text-sky-400' },
    product: { icon: '🛍️', label: 'Produtos', bg: 'bg-violet-50 dark:bg-violet-900/30', text: 'text-violet-700 dark:text-violet-400' },
    customer: { icon: '👥', label: 'Clientes', bg: 'bg-emerald-50 dark:bg-emerald-900/30', text: 'text-emerald-700 dark:text-emerald-400' },
    conversion: { icon: '🎯', label: 'Conversão', bg: 'bg-orange-50 dark:bg-orange-900/30', text: 'text-orange-700 dark:text-orange-400' },
    coupon: { icon: '🏷️', label: 'Cupons', bg: 'bg-indigo-50 dark:bg-indigo-900/30', text: 'text-indigo-700 dark:text-indigo-400' },
    operational: { icon: '⚙️', label: 'Operacional', bg: 'bg-slate-50 dark:bg-slate-900/30', text: 'text-slate-700 dark:text-slate-400' },
};

const priorityConfig = {
    high: { label: 'Alta', color: 'bg-danger-500', ring: 'ring-danger-500/30', rgb: [239, 68, 68] },
    medium: { label: 'Média', color: 'bg-accent-500', ring: 'ring-accent-500/30', rgb: [245, 158, 11] },
    low: { label: 'Baixa', color: 'bg-success-500', ring: 'ring-success-500/30', rgb: [34, 197, 94] },
};

const totalSuggestions = computed(() => stats.value?.accepted + stats.value?.in_progress + stats.value?.completed || 0);
const hasFilters = computed(() => statusFilter.value || categoryFilter.value || impactFilter.value);

function getAnalysisBorderGradient(analysis) {
    const acceptedSuggestions = analysis.suggestions.filter(s => s.status === 'accepted');

    if (acceptedSuggestions.length === 0) {
        return null;
    }

    const priorityOrder = ['high', 'medium', 'low'];
    const presentColors = [];

    priorityOrder.forEach(priority => {
        const hasThisPriority = acceptedSuggestions.some(s => (s.expected_impact || 'medium') === priority);
        if (hasThisPriority) {
            const rgb = priorityConfig[priority].rgb;
            presentColors.push(`rgb(${rgb[0]}, ${rgb[1]}, ${rgb[2]})`);
        }
    });

    if (presentColors.length === 0) return null;

    if (presentColors.length === 1) {
        return presentColors[0];
    }

    return `linear-gradient(90deg, ${presentColors.join(', ')})`;
}

async function fetchTrackingSuggestions() {
    isLoading.value = true;

    try {
        const params = new URLSearchParams();
        if (statusFilter.value) params.append('status', statusFilter.value);
        if (categoryFilter.value) params.append('category', categoryFilter.value);
        if (impactFilter.value) params.append('impact', impactFilter.value);

        const response = await api.get(`/suggestions/tracking?${params.toString()}`);
        analyses.value = response.data.analyses || [];
        stats.value = response.data.stats || { accepted: 0, in_progress: 0, completed: 0 };

        // Inicializar a primeira análise como expandida
        if (analyses.value.length > 0 && expandedAnalyses.value.size === 0) {
            expandedAnalyses.value.add(analyses.value[0].analysis_id);
        }
    } catch (err) {
        notificationStore.error('Erro ao carregar sugestões.');
    } finally {
        isLoading.value = false;
    }
}

function formatAnalysisDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function getCategory(category) {
    return categoryConfig[category] || { icon: '💡', label: 'Geral', bg: 'bg-gray-50', text: 'text-gray-700' };
}

function getPriority(impact) {
    return priorityConfig[impact] || priorityConfig.medium;
}

function getStatusInfo(status) {
    const option = statusOptions.find(o => o.value === status);
    return option || statusOptions[1];
}

function viewSuggestionDetail(suggestion) {
    selectedSuggestion.value = suggestion;
    showSuggestionDetail.value = true;
}

async function handleStatusChange({ suggestion, status }) {
    try {
        const response = await api.patch(`/suggestions/${suggestion.id}`, { status });

        if (response.data.suggestion) {
            // Update local state
            const updatedSuggestion = response.data.suggestion;

            analyses.value = analyses.value.map(analysis => ({
                ...analysis,
                suggestions: analysis.suggestions.map(s =>
                    s.id === updatedSuggestion.id ? { ...s, ...updatedSuggestion } : s
                ),
            }));

            // Update selected suggestion if modal is open
            if (selectedSuggestion.value?.id === updatedSuggestion.id) {
                selectedSuggestion.value = { ...selectedSuggestion.value, ...updatedSuggestion };
            }

            notificationStore.success('Status atualizado com sucesso.');

            // Refresh stats
            fetchTrackingSuggestions();
        }
    } catch (err) {
        notificationStore.error(err.response?.data?.message || 'Erro ao atualizar status.');
    }
}

async function handleRejectSuggestion(suggestion) {
    try {
        await api.post(`/suggestions/${suggestion.id}/reject`);

        // Remove from local state
        analyses.value = analyses.value.map(analysis => ({
            ...analysis,
            suggestions: analysis.suggestions.filter(s => s.id !== suggestion.id),
        })).filter(analysis => analysis.suggestions.length > 0);

        showSuggestionDetail.value = false;
        notificationStore.success('Sugestão rejeitada e movida de volta para a análise.');

        // Refresh data
        fetchTrackingSuggestions();
    } catch (err) {
        notificationStore.error(err.response?.data?.message || 'Erro ao rejeitar sugestão.');
    }
}

function handleSuggestionAskAI(suggestion) {
    // Check permission first
    if (!authStore.canDiscussSuggestion) {
        notificationStore.warning('Seu plano não inclui discussão de sugestões com IA. Faça upgrade para desbloquear.');
        return;
    }

    // Don't close the detail modal - keep it open

    // Set context for chat panel
    chatPanelContext.value = {
        type: 'suggestion',
        suggestion: {
            id: suggestion.id,
            title: suggestion.title,
            category: suggestion.category,
            description: suggestion.description,
            recommended_action: suggestion.recommended_action || suggestion.action_steps,
            expected_impact: suggestion.expected_impact || suggestion.priority,
            priority: suggestion.priority,
        }
    };

    // Open chat panel (side by side with modal)
    showChatPanel.value = true;
}

function clearFilters() {
    statusFilter.value = null;
    categoryFilter.value = null;
    impactFilter.value = null;
    fetchTrackingSuggestions();
}

function toggleAnalysis(analysisId) {
    if (expandedAnalyses.value.has(analysisId)) {
        expandedAnalyses.value.delete(analysisId);
    } else {
        expandedAnalyses.value.add(analysisId);
    }
}

function isAnalysisExpanded(analysisId) {
    return expandedAnalyses.value.has(analysisId);
}

// Close chat panel when suggestion modal is closed
watch(showSuggestionDetail, (isOpen) => {
    if (!isOpen) {
        showChatPanel.value = false;
    }
});

// Clear chat panel context when panel is closed
watch(showChatPanel, (isOpen) => {
    if (!isOpen) {
        chatPanelContext.value = null;
    }
});

onMounted(() => {
    fetchTrackingSuggestions();
});
</script>

<template>
    <div class="min-h-screen">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-4 sm:px-6 lg:px-8 py-6 sm:py-8 lg:py-12 -mx-4 sm:-mx-6 lg:-mx-8 -mt-4 sm:-mt-6 lg:-mt-8">
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
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/30">
                                <ClipboardDocumentListIcon class="w-7 h-7 text-white" />
                            </div>
                            <div>
                                <h1 class="text-3xl lg:text-4xl font-display font-bold text-white dark:text-gray-100">
                                    Acompanhamento de Sugestões
                                </h1>
                                <p class="text-primary-200/80 dark:text-gray-400 text-sm lg:text-base">
                                    {{ totalSuggestions }} sugestões aceitas
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <button
                        @click="fetchTrackingSuggestions"
                        class="px-6 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white font-medium hover:bg-white/20 transition-all focus:outline-none focus:ring-2 focus:ring-white/50 flex items-center gap-2"
                    >
                        <ArrowPathIcon class="w-5 h-5" :class="{ 'animate-spin': isLoading }" />
                        <span class="hidden lg:inline">Atualizar</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="py-4 sm:py-6 lg:py-8 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">
            <div class="w-full">
                <!-- Stats Cards -->
                <div v-if="stats" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4 hover:shadow-lg transition-shadow">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                <ClockIcon class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.accepted }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Aguardando</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4 hover:shadow-lg transition-shadow">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                                <PlayIcon class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.in_progress }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Em Andamento</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4 hover:shadow-lg transition-shadow">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-success-100 dark:bg-success-900/30 flex items-center justify-center">
                                <CheckCircleIcon class="w-5 h-5 text-success-600 dark:text-success-400" />
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.completed }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Concluídas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Panel -->
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30 flex items-center justify-center">
                                <FunnelIcon class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Filtros</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Filtre as sugestões por status, categoria ou impacto</p>
                            </div>
                        </div>
                        <button
                            v-if="hasFilters"
                            @click="clearFilters"
                            class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition-colors flex items-center gap-2"
                        >
                            <XMarkIcon class="w-4 h-4" />
                            Limpar filtros
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Status</label>
                            <select
                                v-model="statusFilter"
                                @change="fetchTrackingSuggestions"
                                class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            >
                                <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Categoria</label>
                            <select
                                v-model="categoryFilter"
                                @change="fetchTrackingSuggestions"
                                class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            >
                                <option v-for="option in categoryOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Impacto</label>
                            <select
                                v-model="impactFilter"
                                @change="fetchTrackingSuggestions"
                                class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                            >
                                <option v-for="option in impactOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="isLoading && analyses.length === 0" class="flex flex-col items-center justify-center py-20">
                    <div class="relative">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500"></div>
                        <LoadingSpinner size="lg" class="absolute inset-0 m-auto text-white" />
                    </div>
                    <p class="text-gray-500 dark:text-gray-400 mt-4 text-sm">Carregando sugestões...</p>
                </div>

                <!-- Empty State -->
                <div v-else-if="!isLoading && analyses.length === 0" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 text-center py-20 px-6">
                    <div class="relative inline-block mb-6">
                        <div class="w-32 h-32 rounded-3xl bg-gradient-to-br from-primary-100 to-secondary-100 dark:from-primary-900/30 dark:to-secondary-900/30 flex items-center justify-center shadow-lg">
                            <ClipboardDocumentListIcon class="w-16 h-16 text-primary-400" />
                        </div>
                        <div class="absolute -bottom-2 -right-2 w-8 h-8 rounded-full bg-accent-400 flex items-center justify-center">
                            <SparklesIcon class="w-4 h-4 text-white" />
                        </div>
                    </div>
                    <h3 class="text-2xl font-display font-bold text-gray-900 dark:text-gray-100 mb-3">
                        Nenhuma sugestão aceita
                    </h3>
                    <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-6">
                        Aceite sugestões na tela de Análises IA para começar a acompanhar seu progresso aqui.
                    </p>
                    <button
                        @click="$router.push({ name: 'analysis' })"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary-500 to-secondary-500 text-white font-semibold shadow-lg shadow-primary-500/30 hover:shadow-xl transition-all"
                    >
                        <SparklesIcon class="w-5 h-5" />
                        Ir para Análises IA
                    </button>
                </div>

                <!-- Suggestions grouped by Analysis -->
                <div v-else class="space-y-6">
                    <div
                        v-for="analysis in analyses"
                        :key="analysis.analysis_id"
                        class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden"
                    >
                        <!-- Analysis Header -->
                        <div class="relative">
                            <button
                                @click="toggleAnalysis(analysis.analysis_id)"
                                class="w-full px-6 py-4 bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer text-left"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                                            <CalendarIcon class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                                                Análise #{{ analysis.analysis_id }}
                                            </h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ formatAnalysisDate(analysis.analysis_date) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center gap-2">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                                                {{ analysis.stats.total }} sugestões
                                            </span>
                                            <span v-if="analysis.stats.completed > 0" class="px-2.5 py-1 rounded-full text-xs font-medium bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-400">
                                                {{ analysis.stats.completed }} concluídas
                                            </span>
                                        </div>
                                        <ChevronDownIcon
                                            class="w-5 h-5 text-gray-500 dark:text-gray-400 transition-transform duration-200"
                                            :class="{ 'rotate-180': isAnalysisExpanded(analysis.analysis_id) }"
                                        />
                                    </div>
                                </div>
                            </button>
                            <!-- Borda inferior colorida com gradiente das prioridades -->
                            <div
                                v-if="getAnalysisBorderGradient(analysis)"
                                class="h-1"
                                :style="{ background: getAnalysisBorderGradient(analysis) }"
                            ></div>
                            <div
                                v-else
                                class="h-px bg-gray-100 dark:bg-gray-700"
                            ></div>
                        </div>

                        <!-- Suggestions List -->
                        <div
                            v-show="isAnalysisExpanded(analysis.analysis_id)"
                            class="divide-y divide-gray-100 dark:divide-gray-700">
                            <div
                                v-for="suggestion in analysis.suggestions"
                                :key="suggestion.id"
                                class="group relative p-5 hover:bg-gradient-to-r hover:from-gray-50 hover:to-transparent dark:hover:from-gray-700/30 dark:hover:to-transparent transition-all duration-200 cursor-pointer"
                                @click="viewSuggestionDetail(suggestion)"
                            >
                                <!-- Priority Indicator Border -->
                                <div
                                    :class="[
                                        'absolute left-0 top-0 bottom-0 w-1 rounded-r-full transition-all duration-200',
                                        getPriority(suggestion.expected_impact).color,
                                        'opacity-0 group-hover:opacity-100'
                                    ]"
                                ></div>

                                <div class="flex items-start gap-4">
                                    <!-- Category Icon with Priority Ring -->
                                    <div class="relative flex-shrink-0">
                                        <div :class="['w-14 h-14 rounded-xl flex items-center justify-center text-2xl shadow-sm group-hover:shadow-md transition-shadow', getCategory(suggestion.category).bg]">
                                            {{ getCategory(suggestion.category).icon }}
                                        </div>
                                        <!-- Priority Ring Indicator -->
                                        <div
                                            :class="[
                                                'absolute -bottom-1 -right-1 w-5 h-5 rounded-full ring-4 ring-white dark:ring-gray-800 flex items-center justify-center transition-transform group-hover:scale-110',
                                                getPriority(suggestion.expected_impact).color
                                            ]"
                                            :title="getPriority(suggestion.expected_impact).label + ' Prioridade'"
                                        >
                                            <span class="text-[8px] font-bold text-white">
                                                {{ suggestion.expected_impact === 'high' ? '!' : suggestion.expected_impact === 'medium' ? '•' : '·' }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Content -->
                                    <div class="flex-1 min-w-0 space-y-2.5">
                                        <!-- Header Row -->
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="flex-1 min-w-0 space-y-1.5">
                                                <!-- Category and Priority Labels -->
                                                <div class="flex items-center gap-2.5 flex-wrap">
                                                    <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-semibold uppercase tracking-wide', getCategory(suggestion.category).bg, getCategory(suggestion.category).text]">
                                                        {{ getCategory(suggestion.category).label }}
                                                    </span>
                                                    <span
                                                        :class="[
                                                            'inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-medium',
                                                            suggestion.expected_impact === 'high'
                                                                ? 'bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-400'
                                                                : suggestion.expected_impact === 'medium'
                                                                    ? 'bg-accent-50 dark:bg-accent-900/20 text-accent-700 dark:text-accent-400'
                                                                    : 'bg-success-50 dark:bg-success-900/20 text-success-700 dark:text-success-400'
                                                        ]"
                                                    >
                                                        <span
                                                            :class="[
                                                                'w-1.5 h-1.5 rounded-full',
                                                                getPriority(suggestion.expected_impact).color
                                                            ]"
                                                        ></span>
                                                        {{ getPriority(suggestion.expected_impact).label }}
                                                    </span>
                                                </div>

                                                <!-- Title -->
                                                <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 line-clamp-1 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                                    {{ suggestion.title }}
                                                </h4>

                                                <!-- Description -->
                                                <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 leading-relaxed">
                                                    {{ suggestion.description }}
                                                </p>
                                            </div>

                                            <!-- Status Badge -->
                                            <div
                                                :class="[
                                                    'flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold flex-shrink-0 shadow-sm border transition-all group-hover:shadow',
                                                    suggestion.status === 'completed'
                                                        ? 'bg-success-50 dark:bg-success-900/20 text-success-700 dark:text-success-400 border-success-200 dark:border-success-800'
                                                        : suggestion.status === 'in_progress'
                                                            ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-400 border-primary-200 dark:border-primary-800'
                                                            : 'bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700'
                                                ]"
                                            >
                                                <component
                                                    :is="getStatusInfo(suggestion.status).icon"
                                                    class="w-4 h-4"
                                                />
                                                <span class="hidden sm:inline">{{ getStatusInfo(suggestion.status).label }}</span>
                                            </div>
                                        </div>

                                        <!-- Action Hint (visible on hover) -->
                                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                            <div class="h-px flex-1 bg-gradient-to-r from-primary-200 to-transparent dark:from-primary-800"></div>
                                            <span class="text-xs font-medium text-primary-600 dark:text-primary-400">
                                                Clique para ver detalhes
                                            </span>
                                            <div class="h-px flex-1 bg-gradient-to-l from-primary-200 to-transparent dark:from-primary-800"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Suggestion Detail Modal -->
        <SuggestionDetailModal
            :show="showSuggestionDetail"
            :suggestion="selectedSuggestion"
            :shift-left="showChatPanel"
            mode="tracking"
            @close="showSuggestionDetail = false"
            @status-change="handleStatusChange"
            @reject="handleRejectSuggestion"
            @ask-ai="handleSuggestionAskAI"
        />

        <!-- Chat Panel (for suggestion discussions) - Opens side by side with modal -->
        <SuggestionChatPanel
            :show="showChatPanel"
            :initial-context="chatPanelContext"
            @close="showChatPanel = false"
        />
    </div>
</template>
