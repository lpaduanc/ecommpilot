<script setup>
import { ref, computed, watch } from 'vue';
import BaseModal from '../common/BaseModal.vue';
import LoadingSpinner from '../common/LoadingSpinner.vue';
import {
    CheckCircleIcon,
    ClockIcon,
    XCircleIcon,
    ExclamationTriangleIcon,
    ChevronDownIcon,
    ChevronRightIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    show: { type: Boolean, default: false },
    analysis: { type: Object, default: null },
    isLoading: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

// Accordion state
const openSections = ref({
    timeline: true,
    alerts: false,
    opportunities: false,
    suggestions: false,
    rawResponse: false,
});

function toggleSection(section) {
    openSections.value[section] = !openSections.value[section];
}

// Computed
const healthScoreColor = computed(() => {
    if (!props.analysis?.health_score) return 'text-gray-400';
    const score = props.analysis.health_score;
    if (score >= 70) return 'text-green-600 dark:text-green-400';
    if (score >= 40) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
});

const healthScoreBgColor = computed(() => {
    if (!props.analysis?.health_score) return 'bg-gray-100 dark:bg-gray-700';
    const score = props.analysis.health_score;
    if (score >= 70) return 'bg-green-100 dark:bg-green-900/30';
    if (score >= 40) return 'bg-yellow-100 dark:bg-yellow-900/30';
    return 'bg-red-100 dark:bg-red-900/30';
});

const executionLogs = computed(() => props.analysis?.execution_logs || []);

const groupedSuggestions = computed(() => {
    const suggestions = props.analysis?.suggestions || [];
    return {
        high: suggestions.filter(s => s.expected_impact === 'high'),
        medium: suggestions.filter(s => s.expected_impact === 'medium'),
        low: suggestions.filter(s => s.expected_impact === 'low'),
    };
});

const alerts = computed(() => props.analysis?.alerts || []);
const opportunities = computed(() => props.analysis?.opportunities || []);

function getStepStatusIcon(status) {
    switch (status) {
        case 'completed':
            return CheckCircleIcon;
        case 'running':
            return ClockIcon;
        case 'failed':
            return XCircleIcon;
        default:
            return ClockIcon;
    }
}

function getStepStatusColor(status) {
    switch (status) {
        case 'completed':
            return 'text-green-600 dark:text-green-400';
        case 'running':
            return 'text-yellow-600 dark:text-yellow-400';
        case 'failed':
            return 'text-red-600 dark:text-red-400';
        default:
            return 'text-gray-400';
    }
}

function formatDuration(seconds) {
    if (!seconds) return '-';
    if (seconds < 60) return `${seconds}s`;
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}m ${remainingSeconds}s`;
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleString('pt-BR');
}

function getPriorityBadgeClass(priority) {
    switch (priority) {
        case 'high':
            return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
        case 'medium':
            return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
        case 'low':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
        default:
            return 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-400';
    }
}

watch(() => props.show, (newVal) => {
    if (!newVal) {
        // Reset accordion state when modal closes
        openSections.value = {
            timeline: true,
            alerts: false,
            opportunities: false,
            suggestions: false,
            rawResponse: false,
        };
    }
});
</script>

<template>
    <BaseModal :show="show" @close="emit('close')" size="ultra" title="Detalhes da Análise">
        <div v-if="isLoading" class="flex justify-center py-12">
            <LoadingSpinner size="lg" class="text-primary-500" />
        </div>

        <div v-else-if="analysis" class="space-y-6">
            <!-- Header -->
            <div class="flex items-start justify-between pb-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Análise #{{ analysis.id }}
                        </h3>
                        <span :class="[
                            'px-2 py-1 text-xs font-medium rounded-full',
                            analysis.status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' :
                            analysis.status === 'processing' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' :
                            analysis.status === 'failed' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' :
                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400'
                        ]">
                            {{ analysis.status }}
                        </span>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        <p><strong>Loja:</strong> {{ analysis.store?.name || 'N/A' }}</p>
                        <p><strong>Usuário:</strong> {{ analysis.user?.name || 'N/A' }}</p>
                        <p><strong>Data:</strong> {{ formatDate(analysis.created_at) }}</p>
                    </div>
                </div>

                <!-- Health Score -->
                <div v-if="analysis.health_score" :class="['px-6 py-4 rounded-xl text-center', healthScoreBgColor]">
                    <div class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Health Score</div>
                    <div :class="['text-3xl font-bold', healthScoreColor]">{{ analysis.health_score }}</div>
                </div>
            </div>

            <!-- Summary -->
            <div v-if="analysis.insight" class="p-4 bg-primary-50 dark:bg-primary-900/20 rounded-xl border border-primary-200 dark:border-primary-800">
                <h4 class="text-sm font-semibold text-primary-900 dark:text-primary-300 mb-2">Insight Principal</h4>
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ analysis.insight }}</p>
            </div>

            <!-- Timeline de Execução -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <button
                    @click="toggleSection('timeline')"
                    class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Timeline de Execução</h4>
                    <ChevronDownIcon
                        :class="['w-4 h-4 text-gray-500 transition-transform', openSections.timeline ? 'rotate-180' : '']"
                    />
                </button>

                <div v-if="openSections.timeline" class="p-4 space-y-3">
                    <div
                        v-for="(log, index) in executionLogs"
                        :key="index"
                        class="flex items-start gap-4 p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700"
                    >
                        <div class="flex-shrink-0 mt-1">
                            <component
                                :is="getStepStatusIcon(log.status)"
                                :class="['w-5 h-5', getStepStatusColor(log.status)]"
                            />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-1">
                                <h5 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ index + 1 }}. {{ log.step_name || 'Etapa ' + (index + 1) }}
                                </h5>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ formatDuration(log.duration) }}
                                </span>
                            </div>
                            <div v-if="log.metrics" class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                <div v-for="(value, key) in log.metrics" :key="key">
                                    <strong>{{ key }}:</strong> {{ value }}
                                </div>
                            </div>
                            <div v-if="log.error_message" class="mt-2 text-xs text-red-600 dark:text-red-400">
                                <ExclamationTriangleIcon class="w-4 h-4 inline mr-1" />
                                {{ log.error_message }}
                            </div>
                        </div>
                    </div>

                    <div v-if="executionLogs.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                        Nenhum log de execução disponível
                    </div>
                </div>
            </div>

            <!-- Alertas -->
            <div v-if="alerts.length > 0" class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <button
                    @click="toggleSection('alerts')"
                    class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        Alertas
                        <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                            {{ alerts.length }}
                        </span>
                    </h4>
                    <ChevronDownIcon
                        :class="['w-4 h-4 text-gray-500 transition-transform', openSections.alerts ? 'rotate-180' : '']"
                    />
                </button>

                <div v-if="openSections.alerts" class="p-4 space-y-2">
                    <div
                        v-for="(alert, index) in alerts"
                        :key="index"
                        class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800"
                    >
                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ alert }}</p>
                    </div>
                </div>
            </div>

            <!-- Oportunidades -->
            <div v-if="opportunities.length > 0" class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <button
                    @click="toggleSection('opportunities')"
                    class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        Oportunidades
                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                            {{ opportunities.length }}
                        </span>
                    </h4>
                    <ChevronDownIcon
                        :class="['w-4 h-4 text-gray-500 transition-transform', openSections.opportunities ? 'rotate-180' : '']"
                    />
                </button>

                <div v-if="openSections.opportunities" class="p-4 space-y-2">
                    <div
                        v-for="(opportunity, index) in opportunities"
                        :key="index"
                        class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800"
                    >
                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ opportunity }}</p>
                    </div>
                </div>
            </div>

            <!-- Sugestões -->
            <div v-if="analysis.suggestions?.length > 0" class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <button
                    @click="toggleSection('suggestions')"
                    class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        Sugestões
                        <span class="text-xs px-2 py-0.5 rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400">
                            {{ analysis.suggestions.length }}
                        </span>
                    </h4>
                    <ChevronDownIcon
                        :class="['w-4 h-4 text-gray-500 transition-transform', openSections.suggestions ? 'rotate-180' : '']"
                    />
                </button>

                <div v-if="openSections.suggestions" class="p-4 space-y-4">
                    <!-- High Priority -->
                    <div v-if="groupedSuggestions.high.length > 0">
                        <h5 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                            Prioridade Alta ({{ groupedSuggestions.high.length }})
                        </h5>
                        <div class="space-y-2">
                            <div
                                v-for="suggestion in groupedSuggestions.high"
                                :key="suggestion.id"
                                class="p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700"
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <h6 class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ suggestion.title }}</h6>
                                    <span :class="['text-xs px-2 py-0.5 rounded-full', getPriorityBadgeClass(suggestion.expected_impact)]">
                                        {{ suggestion.expected_impact }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">{{ suggestion.description }}</p>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <strong>Ação:</strong> {{ suggestion.recommended_action }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Medium Priority -->
                    <div v-if="groupedSuggestions.medium.length > 0">
                        <h5 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                            Prioridade Média ({{ groupedSuggestions.medium.length }})
                        </h5>
                        <div class="space-y-2">
                            <div
                                v-for="suggestion in groupedSuggestions.medium"
                                :key="suggestion.id"
                                class="p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700"
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <h6 class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ suggestion.title }}</h6>
                                    <span :class="['text-xs px-2 py-0.5 rounded-full', getPriorityBadgeClass(suggestion.expected_impact)]">
                                        {{ suggestion.expected_impact }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">{{ suggestion.description }}</p>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <strong>Ação:</strong> {{ suggestion.recommended_action }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Low Priority -->
                    <div v-if="groupedSuggestions.low.length > 0">
                        <h5 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                            Prioridade Baixa ({{ groupedSuggestions.low.length }})
                        </h5>
                        <div class="space-y-2">
                            <div
                                v-for="suggestion in groupedSuggestions.low"
                                :key="suggestion.id"
                                class="p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700"
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <h6 class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ suggestion.title }}</h6>
                                    <span :class="['text-xs px-2 py-0.5 rounded-full', getPriorityBadgeClass(suggestion.expected_impact)]">
                                        {{ suggestion.expected_impact }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">{{ suggestion.description }}</p>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <strong>Ação:</strong> {{ suggestion.recommended_action }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Raw Response -->
            <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <button
                    @click="toggleSection('rawResponse')"
                    class="w-full flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                >
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">JSON Bruto</h4>
                    <ChevronDownIcon
                        :class="['w-4 h-4 text-gray-500 transition-transform', openSections.rawResponse ? 'rotate-180' : '']"
                    />
                </button>

                <div v-if="openSections.rawResponse" class="p-4">
                    <pre class="text-xs text-gray-700 dark:text-gray-300 bg-gray-900 dark:bg-gray-950 p-6 rounded-lg overflow-x-auto overflow-y-auto max-h-[600px] font-mono border border-gray-700 dark:border-gray-800">{{ JSON.stringify(analysis, null, 2) }}</pre>
                </div>
            </div>
        </div>

        <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
            Nenhuma análise selecionada
        </div>

        <template #footer>
            <div class="flex justify-end">
                <button
                    @click="emit('close')"
                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                    Fechar
                </button>
            </div>
        </template>
    </BaseModal>
</template>
