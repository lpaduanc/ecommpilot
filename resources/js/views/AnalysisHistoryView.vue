<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAnalysisStore } from '../stores/analysisStore';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import {
    ArrowLeftIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ClockIcon,
    BeakerIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const router = useRouter();
const analysisStore = useAnalysisStore();

onMounted(() => {
    analysisStore.fetchPaginatedHistory(1);
});

const analyses = computed(() => analysisStore.paginatedHistory);
const pagination = computed(() => analysisStore.historyPagination);
const isLoading = computed(() => analysisStore.isLoadingHistory);

function goToPage(page) {
    if (page >= 1 && page <= pagination.value.last_page) {
        analysisStore.fetchPaginatedHistory(page);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

function viewAnalysis(analysisId) {
    router.push({ name: 'analysis', query: { view: analysisId } });
}

function formatAnalysisDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getScoreColorClasses(score) {
    if (!score) return 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400';
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

function getTypeLabel(type) {
    const labels = {
        general: 'Geral',
        financial: 'Financeira',
        conversion: 'Conversão',
        competitors: 'Concorrentes',
        campaigns: 'Campanhas',
        tracking: 'Tracking',
    };
    return labels[type] || type || 'Geral';
}

function getTypeColorClasses(type) {
    const colors = {
        general: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300',
        financial: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
        conversion: 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300',
        competitors: 'bg-orange-100 dark:bg-orange-900/30 text-orange-700 dark:text-orange-300',
        campaigns: 'bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-300',
        tracking: 'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-300',
    };
    return colors[type] || 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300';
}

const visiblePages = computed(() => {
    const current = pagination.value.current_page;
    const last = pagination.value.last_page;
    if (last <= 1) return [];
    const start = Math.max(1, current - 2);
    const end = Math.min(last, current + 2);
    const pages = [];
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    return pages;
});

const showPagination = computed(() => pagination.value.last_page > 1);

const rangeStart = computed(() => {
    if (pagination.value.total === 0) return 0;
    return (pagination.value.current_page - 1) * pagination.value.per_page + 1;
});

const rangeEnd = computed(() => {
    return Math.min(
        pagination.value.current_page * pagination.value.per_page,
        pagination.value.total
    );
});
</script>

<template>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <RouterLink
                    :to="{ name: 'analysis' }"
                    class="flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    aria-label="Voltar para análise"
                >
                    <ArrowLeftIcon class="w-5 h-5" />
                </RouterLink>
                <div>
                    <div class="flex items-center gap-2">
                        <ClockIcon class="w-5 h-5 text-gray-400 dark:text-gray-500" />
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                            Histórico de Análises
                        </h1>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5 ml-7">
                        Todas as análises geradas para a sua loja
                    </p>
                </div>
            </div>

            <!-- Total count badge -->
            <div
                v-if="!isLoading && pagination.total > 0"
                class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-gray-100 dark:bg-gray-800 text-sm text-gray-600 dark:text-gray-400"
            >
                <BeakerIcon class="w-4 h-4" />
                <span>{{ pagination.total }} análise{{ pagination.total !== 1 ? 's' : '' }}</span>
            </div>
        </div>

        <!-- Loading State -->
        <div
            v-if="isLoading"
            class="flex items-center justify-center py-24"
        >
            <div class="text-center space-y-3">
                <LoadingSpinner size="lg" />
                <p class="text-sm text-gray-500 dark:text-gray-400">Carregando histórico...</p>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="!isLoading && analyses.length === 0"
            class="flex flex-col items-center justify-center py-24 text-center"
        >
            <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-4">
                <ClockIcon class="w-8 h-8 text-gray-400 dark:text-gray-500" />
            </div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                Nenhuma análise encontrada
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 max-w-xs mb-6">
                Ainda não há análises concluídas para esta loja. Solicite sua primeira análise na página principal.
            </p>
            <RouterLink
                :to="{ name: 'analysis' }"
                class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors"
            >
                <ArrowLeftIcon class="w-4 h-4" />
                Ir para Análises
            </RouterLink>
        </div>

        <!-- Analyses Grid -->
        <template v-else>
            <!-- Result count -->
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Mostrando <span class="font-medium text-gray-700 dark:text-gray-300">{{ rangeStart }}–{{ rangeEnd }}</span>
                de <span class="font-medium text-gray-700 dark:text-gray-300">{{ pagination.total }}</span>
                análise{{ pagination.total !== 1 ? 's' : '' }}
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <button
                    v-for="analysis in analyses"
                    :key="analysis.id"
                    @click="viewAnalysis(analysis.id)"
                    class="group relative text-left p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-primary-400 dark:hover:border-primary-600 hover:shadow-md transition-all duration-200"
                >
                    <!-- Score badge -->
                    <div class="flex items-start justify-between mb-3">
                        <div
                            :class="[
                                'w-12 h-12 rounded-xl flex flex-col items-center justify-center font-bold',
                                getScoreColorClasses(analysis.summary?.health_score)
                            ]"
                        >
                            <span class="text-lg leading-none">
                                {{ analysis.summary?.health_score || '–' }}
                            </span>
                            <span class="text-[10px] leading-tight font-normal mt-0.5">
                                {{ getScoreLabel(analysis.summary?.health_score) }}
                            </span>
                        </div>

                        <!-- Email error indicator -->
                        <ExclamationTriangleIcon
                            v-if="analysis.email_error && !analysis.email_sent_at"
                            class="w-4 h-4 text-rose-500 dark:text-rose-400 flex-shrink-0 mt-1"
                            title="E-mail não enviado"
                        />
                    </div>

                    <!-- Date -->
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                        {{ formatAnalysisDate(analysis.created_at) }}
                    </div>

                    <!-- Type badge + ID -->
                    <div class="flex items-center justify-between gap-2">
                        <span
                            :class="[
                                'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                getTypeColorClasses(analysis.analysis_type)
                            ]"
                        >
                            {{ getTypeLabel(analysis.analysis_type) }}
                        </span>
                        <span class="text-xs text-gray-400 dark:text-gray-500 font-mono truncate">
                            #{{ String(analysis.id).slice(0, 6) }}
                        </span>
                    </div>

                    <!-- Suggestions count if available -->
                    <div
                        v-if="analysis.suggestions && analysis.suggestions.length > 0"
                        class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400"
                    >
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ analysis.suggestions.length }}</span>
                        sugestão{{ analysis.suggestions.length !== 1 ? 'ões' : '' }}
                    </div>

                    <!-- Hover arrow -->
                    <ChevronRightIcon class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-300 dark:text-gray-600 group-hover:text-primary-500 dark:group-hover:text-primary-400 transition-colors opacity-0 group-hover:opacity-100" />
                </button>
            </div>

            <!-- Pagination -->
            <div
                v-if="showPagination"
                class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-4 rounded-xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50"
            >
                <!-- Range info -->
                <p class="text-sm text-gray-500 dark:text-gray-400 order-2 sm:order-1">
                    Página <span class="font-medium text-gray-700 dark:text-gray-300">{{ pagination.current_page }}</span>
                    de <span class="font-medium text-gray-700 dark:text-gray-300">{{ pagination.last_page }}</span>
                </p>

                <!-- Page controls -->
                <div class="flex items-center gap-1 order-1 sm:order-2">
                    <!-- Previous -->
                    <button
                        @click="goToPage(pagination.current_page - 1)"
                        :disabled="pagination.current_page <= 1"
                        class="flex items-center justify-center w-8 h-8 rounded-lg text-sm font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 disabled:hover:bg-transparent"
                        aria-label="Página anterior"
                    >
                        <ChevronLeftIcon class="w-4 h-4" />
                    </button>

                    <!-- Page numbers -->
                    <template v-for="page in visiblePages" :key="page">
                        <button
                            @click="goToPage(page)"
                            :class="[
                                'flex items-center justify-center w-8 h-8 rounded-lg text-sm font-medium transition-colors',
                                page === pagination.current_page
                                    ? 'bg-primary-600 text-white shadow-sm'
                                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'
                            ]"
                            :aria-label="`Ir para página ${page}`"
                            :aria-current="page === pagination.current_page ? 'page' : undefined"
                        >
                            {{ page }}
                        </button>
                    </template>

                    <!-- Next -->
                    <button
                        @click="goToPage(pagination.current_page + 1)"
                        :disabled="pagination.current_page >= pagination.last_page"
                        class="flex items-center justify-center w-8 h-8 rounded-lg text-sm font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 disabled:hover:bg-transparent"
                        aria-label="Próxima página"
                    >
                        <ChevronRightIcon class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
