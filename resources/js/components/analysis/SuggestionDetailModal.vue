<script setup>
import { computed, ref } from 'vue';
import BaseModal from '../common/BaseModal.vue';
import {
    CheckCircleIcon,
    ChatBubbleLeftRightIcon,
    SparklesIcon,
    ClockIcon,
    BoltIcon,
    ChartBarIcon,
    XMarkIcon,
    PlayIcon,
    XCircleIcon,
    ChevronDownIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    show: { type: Boolean, default: false },
    suggestion: { type: Object, default: null },
});

const emit = defineEmits(['close', 'ask-ai', 'mark-done', 'status-change']);

const showStatusDropdown = ref(false);

const categoryConfig = {
    marketing: { icon: '📣', label: 'Marketing', color: 'from-pink-500 to-rose-500', bg: 'bg-pink-100' },
    pricing: { icon: '💰', label: 'Precificação', color: 'from-amber-500 to-yellow-500', bg: 'bg-amber-100' },
    inventory: { icon: '📦', label: 'Estoque', color: 'from-sky-500 to-cyan-500', bg: 'bg-sky-100' },
    product: { icon: '🛍️', label: 'Produtos', color: 'from-violet-500 to-purple-500', bg: 'bg-violet-100' },
    customer: { icon: '👥', label: 'Clientes', color: 'from-emerald-500 to-teal-500', bg: 'bg-emerald-100' },
    conversion: { icon: '🎯', label: 'Conversão', color: 'from-orange-500 to-red-500', bg: 'bg-orange-100' },
    coupon: { icon: '🏷️', label: 'Cupons', color: 'from-indigo-500 to-blue-500', bg: 'bg-indigo-100' },
    operational: { icon: '⚙️', label: 'Operacional', color: 'from-slate-500 to-gray-500', bg: 'bg-slate-100' },
};

const priorityConfig = {
    high: { label: 'Alta Prioridade', color: 'from-orange-500 to-red-600', bg: 'bg-rose-100', text: 'text-rose-700' },
    medium: { label: 'Média Prioridade', color: 'from-blue-500 to-purple-600', bg: 'bg-blue-100', text: 'text-blue-700' },
    low: { label: 'Baixa Prioridade', color: 'from-emerald-500 to-green-600', bg: 'bg-emerald-100', text: 'text-emerald-700' },
};

const effortLabels = {
    low: 'Baixo',
    medium: 'Médio',
    high: 'Alto',
};

const statusOptions = [
    { value: 'pending', label: 'Pendente', icon: ClockIcon, color: 'text-gray-600 dark:text-gray-400' },
    { value: 'in_progress', label: 'Em Andamento', icon: PlayIcon, color: 'text-primary-600 dark:text-primary-400' },
    { value: 'completed', label: 'Implementado', icon: CheckCircleIcon, color: 'text-success-600 dark:text-success-400' },
    { value: 'ignored', label: 'Ignorar', icon: XCircleIcon, color: 'text-gray-500 dark:text-gray-400' },
];

const category = computed(() =>
    categoryConfig[props.suggestion?.category] || { icon: '💡', label: 'Geral', color: 'from-gray-500 to-gray-600', bg: 'bg-gray-100' }
);

const priority = computed(() =>
    priorityConfig[props.suggestion?.priority || props.suggestion?.expected_impact] || priorityConfig.medium
);

// Get action_steps from either action_steps or implementation_steps or recommended_action
const actionSteps = computed(() => {
    if (!props.suggestion) return [];
    const steps = props.suggestion.action_steps || props.suggestion.implementation_steps;
    if (steps && steps.length > 0) return steps;

    // Handle recommended_action (can be array or string)
    const action = props.suggestion.recommended_action;
    if (!action) return [];

    // If it's already an array, use it directly
    if (Array.isArray(action)) {
        return action.filter(step => step && typeof step === 'string' && step.trim());
    }

    // If it's a string, try to parse as JSON first (in case it's a JSON array string)
    if (typeof action === 'string') {
        try {
            const parsed = JSON.parse(action);
            if (Array.isArray(parsed)) {
                return parsed.filter(step => step && typeof step === 'string' && step.trim());
            }
        } catch (e) {
            // Not JSON, handle as plain string
        }

        // Try splitting by newlines
        const lines = action.split(/\\n|\n/).filter(line => line.trim());
        if (lines.length > 1) return lines;

        // Try splitting by numbered pattern "1. ... 2. ... 3. ..."
        const numberedSteps = action.split(/(?=\d+\.\s)/).filter(s => s.trim());
        if (numberedSteps.length > 1) {
            return numberedSteps.map(s => s.replace(/^\d+\.\s*/, '').trim()).filter(s => s);
        }

        return [action];
    }

    return [];
});

// Support both legacy is_done and new status field
const currentStatus = computed(() => {
    if (props.suggestion?.status) return props.suggestion.status;
    return props.suggestion?.is_done ? 'completed' : 'pending';
});

const currentStatusOption = computed(() =>
    statusOptions.find(o => o.value === currentStatus.value) || statusOptions[0]
);

function selectStatus(status) {
    showStatusDropdown.value = false;
    if (status !== currentStatus.value) {
        emit('status-change', { suggestion: props.suggestion, status });
    }
}
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div 
                v-if="show && suggestion" 
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <!-- Backdrop -->
                <div 
                    class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"
                    @click="emit('close')"
                ></div>

                <!-- Modal -->
                <div class="relative w-full max-w-2xl max-h-[90vh] overflow-hidden bg-white dark:bg-gray-800 rounded-3xl shadow-2xl">
                    <!-- Header with Gradient based on Priority -->
                    <div class="relative px-8 py-6 bg-gradient-to-r overflow-hidden" :class="priority.color">
                        <!-- Background Pattern -->
                        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(rgba(255,255,255,0.3) 1px, transparent 1px); background-size: 20px 20px;"></div>
                        
                        <!-- Close Button -->
                        <button
                            @click="emit('close')"
                            class="absolute top-4 right-4 p-2 rounded-full bg-white/20 hover:bg-white/30 transition-colors"
                        >
                            <XMarkIcon class="w-5 h-5 text-white" />
                        </button>

                        <div class="relative flex items-start gap-4">
                            <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-3xl">
                                {{ category.icon }}
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-white/80 text-sm font-medium uppercase tracking-wider">
                                        {{ category.label }}
                                    </span>
                                    <span class="px-3 py-1 rounded-full bg-white/20 text-white text-xs font-semibold">
                                        {{ priority.label }}
                                    </span>
                                </div>
                                <h2 class="text-xl lg:text-2xl font-display font-bold text-white pr-8">
                                    {{ suggestion.title }}
                                </h2>
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="px-8 py-6 max-h-[60vh] overflow-y-auto scrollbar-thin space-y-6">
                        <!-- Description -->
                        <div>
                            <h3 class="flex items-center gap-2 font-semibold text-gray-900 dark:text-gray-100 mb-3">
                                <SparklesIcon class="w-5 h-5 text-primary-500" />
                                Descrição
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400 leading-relaxed">{{ suggestion.description }}</p>
                        </div>

                        <!-- Expected Impact -->
                        <div class="bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/30 dark:to-teal-900/30 rounded-2xl p-5 border border-emerald-100 dark:border-emerald-800">
                            <h3 class="flex items-center gap-2 font-semibold text-emerald-900 dark:text-emerald-200 mb-2">
                                <ChartBarIcon class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                                Impacto Esperado
                            </h3>
                            <p class="text-emerald-700 dark:text-emerald-300 leading-relaxed">
                                {{ suggestion.expected_impact === 'high' ? 'Alto - Resultados significativos esperados' : suggestion.expected_impact === 'medium' ? 'Médio - Melhoria moderada esperada' : suggestion.expected_impact === 'low' ? 'Baixo - Otimização incremental' : suggestion.expected_impact }}
                            </p>
                        </div>

                        <!-- Implementation Steps -->
                        <div v-if="actionSteps.length > 0">
                            <h3 class="flex items-center gap-2 font-semibold text-gray-900 dark:text-gray-100 mb-4">
                                <BoltIcon class="w-5 h-5 text-primary-500" />
                                Passos para Implementação
                            </h3>
                            <div class="space-y-3">
                                <div
                                    v-for="(step, index) in actionSteps"
                                    :key="index"
                                    class="flex items-start gap-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-900 hover:bg-gray-100 dark:hover:bg-gray-700 dark:bg-gray-800 transition-colors"
                                >
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-secondary-500 text-white flex items-center justify-center text-sm font-bold shadow-lg shadow-primary-500/30">
                                        {{ index + 1 }}
                                    </div>
                                    <span class="text-gray-700 dark:text-gray-300 pt-1">{{ step }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Metrics to Track -->
                        <div v-if="suggestion.metrics_to_track?.length > 0">
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">📊 Métricas para Acompanhar</h3>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="metric in suggestion.metrics_to_track"
                                    :key="metric"
                                    class="px-4 py-2 bg-gradient-to-r from-gray-100 to-gray-50 dark:from-gray-800 dark:to-gray-700 rounded-xl text-sm text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600"
                                >
                                    {{ metric }}
                                </span>
                            </div>
                        </div>

                        <!-- Effort & Time -->
                        <div class="flex flex-wrap gap-4">
                            <div v-if="suggestion.estimated_effort" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                <div class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                    <BoltIcon class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 block">Esforço</span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ effortLabels[suggestion.estimated_effort] }}</span>
                                </div>
                            </div>
                            <div v-if="suggestion.estimated_time" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-gray-50 dark:bg-gray-800">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                    <ClockIcon class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 block">Tempo Estimado</span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ suggestion.estimated_time }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-8 py-5 bg-gray-50 dark:bg-gray-900 border-t border-gray-100 dark:border-gray-700">
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                            <!-- Status Dropdown -->
                            <div class="relative">
                                <button
                                    @click="showStatusDropdown = !showStatusDropdown"
                                    class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 font-medium transition-all hover:border-primary-300 dark:hover:border-primary-700"
                                    :class="currentStatusOption.color"
                                >
                                    <component :is="currentStatusOption.icon" class="w-5 h-5" />
                                    {{ currentStatusOption.label }}
                                    <ChevronDownIcon class="w-4 h-4 ml-1" />
                                </button>
                                <Transition
                                    enter-active-class="transition ease-out duration-100"
                                    enter-from-class="transform opacity-0 scale-95"
                                    enter-to-class="transform opacity-100 scale-100"
                                    leave-active-class="transition ease-in duration-75"
                                    leave-from-class="transform opacity-100 scale-100"
                                    leave-to-class="transform opacity-0 scale-95"
                                >
                                    <div
                                        v-if="showStatusDropdown"
                                        class="absolute bottom-full mb-2 left-0 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 py-2 z-20"
                                    >
                                        <button
                                            v-for="option in statusOptions"
                                            :key="option.value"
                                            @click="selectStatus(option.value)"
                                            class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                            :class="[option.color, option.value === currentStatus ? 'bg-gray-50 dark:bg-gray-700' : '']"
                                        >
                                            <component :is="option.icon" class="w-5 h-5" />
                                            {{ option.label }}
                                        </button>
                                    </div>
                                </Transition>
                            </div>

                            <div class="flex items-center gap-3">
                                <button
                                    @click="emit('close')"
                                    class="px-5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                >
                                    Fechar
                                </button>
                                <button
                                    @click="emit('ask-ai', suggestion)"
                                    class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary-500 to-secondary-500 text-white font-semibold shadow-lg shadow-primary-500/30 hover:shadow-xl transition-all"
                                >
                                    <ChatBubbleLeftRightIcon class="w-5 h-5" />
                                    Discutir com IA
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
