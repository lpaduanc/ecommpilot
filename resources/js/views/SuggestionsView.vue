<script setup>
import { ref, onMounted, computed } from 'vue';
import { useNotificationStore } from '../stores/notificationStore';
import { useAuthStore } from '../stores/authStore';
import api from '../services/api';
import BaseButton from '../components/common/BaseButton.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import SuggestionDetailModal from '../components/analysis/SuggestionDetailModal.vue';
import {
    ClipboardDocumentListIcon,
    FunnelIcon,
    CalendarIcon,
    CheckCircleIcon,
    ClockIcon,
    PlayIcon,
    XMarkIcon,
    ChevronDownIcon,
    ArrowPathIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';

const notificationStore = useNotificationStore();
const authStore = useAuthStore();

const isLoading = ref(false);
const analyses = ref([]);
const stats = ref(null);
const selectedSuggestion = ref(null);
const showSuggestionDetail = ref(false);

// Filters
const statusFilter = ref(null);
const categoryFilter = ref(null);
const impactFilter = ref(null);
const showFilters = ref(false);

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
    high: { label: 'Alta', color: 'bg-danger-500', ring: 'ring-danger-500/30' },
    medium: { label: 'Média', color: 'bg-accent-500', ring: 'ring-accent-500/30' },
    low: { label: 'Baixa', color: 'bg-success-500', ring: 'ring-success-500/30' },
};

const totalSuggestions = computed(() => stats.value?.accepted + stats.value?.in_progress + stats.value?.completed || 0);
const hasFilters = computed(() => statusFilter.value || categoryFilter.value || impactFilter.value);

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

function clearFilters() {
    statusFilter.value = null;
    categoryFilter.value = null;
    impactFilter.value = null;
    fetchTrackingSuggestions();
}

onMounted(() => {
    fetchTrackingSuggestions();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/20">
                    <ClipboardDocumentListIcon class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Acompanhamento de Sugestões</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Acompanhe e gerencie suas sugestões aceitas
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button
                    @click="showFilters = !showFilters"
                    :class="[
                        'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                        hasFilters
                            ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700'
                    ]"
                >
                    <FunnelIcon class="w-4 h-4" />
                    Filtros
                    <span v-if="hasFilters" class="w-2 h-2 rounded-full bg-primary-500"></span>
                </button>
                <button
                    @click="fetchTrackingSuggestions"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 text-sm font-medium transition-colors"
                >
                    <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
                    Atualizar
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div v-if="stats" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4">
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
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4">
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
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4">
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
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div v-if="showFilters" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
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
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Categoria</label>
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
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Impacto</label>
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
                    <div class="flex items-end">
                        <button
                            v-if="hasFilters"
                            @click="clearFilters"
                            class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition-colors"
                        >
                            Limpar filtros
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Loading State -->
        <div v-if="isLoading && analyses.length === 0" class="flex flex-col items-center justify-center py-20">
            <LoadingSpinner size="lg" class="text-primary-500" />
            <p class="text-gray-500 mt-4 text-sm">Carregando sugestões...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="!isLoading && analyses.length === 0" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 text-center py-16 px-6">
            <div class="relative inline-block mb-6">
                <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-primary-100 to-secondary-100 dark:from-primary-900/30 dark:to-secondary-900/30 flex items-center justify-center shadow-lg">
                    <ClipboardDocumentListIcon class="w-10 h-10 text-primary-500" />
                </div>
                <div class="absolute -top-1 -right-1 w-6 h-6 rounded-full bg-gradient-to-br from-primary-500 to-secondary-500 flex items-center justify-center">
                    <SparklesIcon class="w-3.5 h-3.5 text-white" />
                </div>
            </div>
            <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Nenhuma sugestão aceita</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-6">
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
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-100 dark:border-gray-700">
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
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                                {{ analysis.stats.total }} sugestões
                            </span>
                            <span v-if="analysis.stats.completed > 0" class="px-2.5 py-1 rounded-full text-xs font-medium bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-400">
                                {{ analysis.stats.completed }} concluídas
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Suggestions List -->
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    <div
                        v-for="suggestion in analysis.suggestions"
                        :key="suggestion.id"
                        class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer"
                        @click="viewSuggestionDetail(suggestion)"
                    >
                        <div class="flex items-start gap-4">
                            <!-- Category Icon -->
                            <div :class="['w-12 h-12 rounded-xl flex items-center justify-center text-xl flex-shrink-0', getCategory(suggestion.category).bg]">
                                {{ getCategory(suggestion.category).icon }}
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span :class="['text-xs font-semibold uppercase tracking-wider', getCategory(suggestion.category).text]">
                                                {{ getCategory(suggestion.category).label }}
                                            </span>
                                            <div
                                                :class="[
                                                    'w-1.5 h-1.5 rounded-full',
                                                    getPriority(suggestion.expected_impact).color
                                                ]"
                                            ></div>
                                        </div>
                                        <h4 class="font-semibold text-gray-900 dark:text-gray-100 line-clamp-1">
                                            {{ suggestion.title }}
                                        </h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-1 mt-1">
                                            {{ suggestion.description }}
                                        </p>
                                    </div>

                                    <!-- Status Badge -->
                                    <div
                                        :class="[
                                            'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium flex-shrink-0',
                                            suggestion.status === 'completed'
                                                ? 'bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-400'
                                                : suggestion.status === 'in_progress'
                                                    ? 'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400'
                                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'
                                        ]"
                                    >
                                        <component
                                            :is="getStatusInfo(suggestion.status).icon"
                                            class="w-3.5 h-3.5"
                                        />
                                        {{ getStatusInfo(suggestion.status).label }}
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
            mode="tracking"
            @close="showSuggestionDetail = false"
            @status-change="handleStatusChange"
            @reject="handleRejectSuggestion"
        />
    </div>
</template>
