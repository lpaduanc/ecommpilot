<script setup>
import { computed, ref, watch, onUnmounted } from 'vue';
import { useAuthStore } from '../../stores/authStore';
import { useAnalysisStore } from '../../stores/analysisStore';
import {
    CheckCircleIcon,
    ChatBubbleLeftRightIcon,
    SparklesIcon,
    ClockIcon,
    BoltIcon,
    ChartBarIcon,
    XMarkIcon,
    PlayIcon,
    ChevronDownIcon,
    CheckIcon,
    LockClosedIcon,
    ListBulletIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const analysisStore = useAnalysisStore();

const props = defineProps({
    show: { type: Boolean, default: false },
    suggestion: { type: Object, default: null },
    mode: { type: String, default: 'analysis' }, // 'analysis' or 'tracking'
    shiftLeft: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'ask-ai', 'mark-done', 'status-change', 'accept', 'reject', 'manage-workflow']);

const showStatusDropdown = ref(false);

// Reset dropdown state and handle body scroll lock
watch(() => props.show, (newVal) => {
    if (newVal) {
        // Lock body scroll when modal opens
        document.body.style.overflow = 'hidden';
    } else {
        // Restore body scroll and reset state when modal closes
        document.body.style.overflow = '';
        showStatusDropdown.value = false;
    }
}, { immediate: true });

// Cleanup on unmount
onUnmounted(() => {
    document.body.style.overflow = '';
});

const categoryConfig = {
    strategy: { icon: '🎯', label: 'Estratégia', color: 'from-rose-500 to-red-500', bg: 'bg-rose-100' },
    investment: { icon: '💎', label: 'Investimento', color: 'from-cyan-500 to-blue-500', bg: 'bg-cyan-100' },
    market: { icon: '🌍', label: 'Mercado', color: 'from-teal-500 to-emerald-500', bg: 'bg-teal-100' },
    growth: { icon: '📈', label: 'Crescimento', color: 'from-lime-500 to-green-500', bg: 'bg-lime-100' },
    financial: { icon: '💵', label: 'Financeiro', color: 'from-yellow-500 to-amber-500', bg: 'bg-yellow-100' },
    positioning: { icon: '🏆', label: 'Posicionamento', color: 'from-fuchsia-500 to-purple-500', bg: 'bg-fuchsia-100' },
    marketing: { icon: '📣', label: 'Marketing', color: 'from-pink-500 to-rose-500', bg: 'bg-pink-100' },
    pricing: { icon: '💰', label: 'Precificação', color: 'from-amber-500 to-yellow-500', bg: 'bg-amber-100' },
    inventory: { icon: '📦', label: 'Estoque', color: 'from-sky-500 to-cyan-500', bg: 'bg-sky-100' },
    product: { icon: '🛍️', label: 'Produtos', color: 'from-violet-500 to-purple-500', bg: 'bg-violet-100' },
    customer: { icon: '👥', label: 'Clientes', color: 'from-emerald-500 to-teal-500', bg: 'bg-emerald-100' },
    conversion: { icon: '🔄', label: 'Conversão', color: 'from-orange-500 to-red-500', bg: 'bg-orange-100' },
    coupon: { icon: '🏷️', label: 'Cupons', color: 'from-indigo-500 to-blue-500', bg: 'bg-indigo-100' },
    operational: { icon: '⚙️', label: 'Operacional', color: 'from-slate-500 to-gray-500', bg: 'bg-slate-100' },
};

const priorityConfig = {
    high: { label: 'Alta Prioridade', color: 'from-orange-500 to-red-600', bg: 'bg-rose-100', text: 'text-rose-700' },
    medium: { label: 'Média Prioridade', color: 'from-blue-500 to-purple-600', bg: 'bg-blue-100', text: 'text-blue-700' },
    low: { label: 'Baixa Prioridade', color: 'from-emerald-500 to-green-600', bg: 'bg-emerald-100', text: 'text-emerald-700' },
};

// Status options for tracking page
const trackingStatusOptions = computed(() => {
    const baseOptions = [
        { value: 'accepted', label: 'Aguardando', icon: ClockIcon, color: 'text-gray-600 dark:text-gray-400' },
        { value: 'in_progress', label: 'Em Andamento', icon: PlayIcon, color: 'text-primary-600 dark:text-primary-400' },
        { value: 'completed', label: 'Concluído', icon: CheckCircleIcon, color: 'text-success-600 dark:text-success-400' },
    ];

    // Add Accept/Reject option based on current status
    if (isRejected.value) {
        return [
            { value: 'accept', label: 'Aceitar', icon: CheckIcon, color: 'text-success-600 dark:text-success-400' },
            ...baseOptions,
        ];
    } else if (isOnTrackingPage.value) {
        return [
            ...baseOptions,
            { value: 'rejected', label: 'Rejeitar', icon: XMarkIcon, color: 'text-gray-600 dark:text-gray-400' },
        ];
    }

    return baseOptions;
});

const category = computed(() =>
    categoryConfig[props.suggestion?.category] || { icon: '💡', label: 'Geral', color: 'from-gray-500 to-gray-600', bg: 'bg-gray-100' }
);

const priority = computed(() =>
    priorityConfig[props.suggestion?.priority || props.suggestion?.expected_impact] || priorityConfig.medium
);

// Status handling
const currentStatus = computed(() => props.suggestion?.status || 'new');
const isOnAnalysisPage = computed(() => props.suggestion?.is_on_analysis_page ?? ['new', 'pending', 'rejected', 'ignored'].includes(currentStatus.value));
const isOnTrackingPage = computed(() => props.suggestion?.is_on_tracking_page ?? ['accepted', 'in_progress', 'completed'].includes(currentStatus.value));
const isRejected = computed(() => ['rejected', 'ignored'].includes(currentStatus.value));

const currentStatusOption = computed(() =>
    trackingStatusOptions.value.find(o => o.value === currentStatus.value) || trackingStatusOptions.value[0]
);

// Parse action steps from recommended_action
const actionSteps = computed(() => {
    if (!props.suggestion) return [];

    const action = props.suggestion.recommended_action;
    if (!action) return [];

    // If it's already an array, use it directly
    if (Array.isArray(action)) {
        return action.filter(s => s && typeof s === 'string' && s.trim());
    }

    // If it's a string, try to parse it
    if (typeof action === 'string') {
        // Try JSON parse first
        try {
            const parsed = JSON.parse(action);
            if (Array.isArray(parsed)) {
                return parsed.filter(s => s && typeof s === 'string' && s.trim());
            }
        } catch (e) {
            // Not JSON, continue with string parsing
        }

        // Try splitting by newlines
        const lines = action.split(/\\n|\n/).filter(l => l.trim());
        if (lines.length > 1) {
            return lines.map(l => l.replace(/^\d+\.\s*/, '').trim()).filter(Boolean);
        }

        // Try splitting by numbered pattern "1. ... 2. ... 3. ..."
        const numbered = action.split(/(?=\d+\.\s)/).filter(s => s.trim());
        if (numbered.length > 1) {
            return numbered.map(s => s.replace(/^\d+\.\s*/, '').trim()).filter(Boolean);
        }

        // Return as single step
        return [action.trim()];
    }

    return [];
});

function selectStatus(status) {
    showStatusDropdown.value = false;

    // Handle special cases
    if (status === 'accept') {
        emit('accept', props.suggestion);
        return;
    }

    if (status === 'rejected') {
        emit('reject', props.suggestion);
        return;
    }

    // Regular status change
    if (status !== currentStatus.value) {
        emit('status-change', { suggestion: props.suggestion, status });
    }
}

function handleAccept() {
    emit('accept', props.suggestion);
    emit('close');
}

function handleReject() {
    emit('reject', props.suggestion);
    emit('close');
}

function handleManageWorkflow() {
    emit('manage-workflow', props.suggestion);
}
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="show && suggestion"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 transition-all duration-300"
                :style="shiftLeft ? 'width: 50vw;' : ''"
            >
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-all duration-300"
                    :style="shiftLeft ? 'width: 50vw;' : ''"
                    @click="emit('close')"
                ></div>

                <!-- Modal -->
                <div class="relative w-full max-w-2xl max-h-[90vh] overflow-hidden bg-white dark:bg-gray-800 rounded-3xl shadow-2xl z-10">
                    <!-- Header with Gradient based on Priority -->
                    <div class="relative px-8 py-6 bg-gradient-to-r overflow-hidden" :class="priority.color">
                        <!-- Background Pattern -->
                        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(rgba(255,255,255,0.3) 1px, transparent 1px); background-size: 20px 20px;"></div>
                        
                        <!-- Close Button -->
                        <button
                            @click="emit('close')"
                            class="absolute top-4 right-4 z-10 w-10 h-10 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition-colors cursor-pointer"
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

                        <!-- Action Steps (from AI) -->
                        <div v-if="actionSteps.length > 0">
                            <h3 class="flex items-center gap-2 font-semibold text-gray-900 dark:text-gray-100 mb-3">
                                <ListBulletIcon class="w-5 h-5 text-primary-500" />
                                Passos Recomendados
                            </h3>
                            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-xl p-4 border border-gray-100 dark:border-gray-700">
                                <div class="space-y-3">
                                    <div
                                        v-for="(step, index) in actionSteps"
                                        :key="index"
                                        class="flex items-start gap-3 bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-100 dark:border-gray-700 hover:border-primary-200 dark:hover:border-primary-700 transition-colors"
                                    >
                                        <div class="flex-shrink-0 w-7 h-7 rounded-full bg-gradient-to-br from-primary-500 to-secondary-500 text-white flex items-center justify-center text-xs font-bold shadow-sm">
                                            {{ index + 1 }}
                                        </div>
                                        <p class="flex-1 text-gray-700 dark:text-gray-300 text-sm leading-relaxed pt-0.5">{{ step }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div v-if="isOnTrackingPage" class="bg-gradient-to-r from-primary-50 to-secondary-50 dark:from-primary-900/20 dark:to-secondary-900/20 rounded-2xl p-5 border border-primary-200 dark:border-primary-800">
                            <h3 class="flex items-center gap-2 font-semibold text-primary-900 dark:text-primary-200 mb-3">
                                <BoltIcon class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                                Gerenciar Workflow
                            </h3>
                            <p class="text-primary-700 dark:text-primary-300 text-sm mb-4">
                                Acesse a página dedicada para gerenciar tarefas, passos e impactos nas vendas.
                            </p>
                            <button
                                @click="handleManageWorkflow"
                                class="w-full flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-gradient-to-r from-primary-500 to-secondary-500 text-white font-semibold shadow-lg shadow-primary-500/30 hover:shadow-xl transition-all"
                            >
                                <BoltIcon class="w-5 h-5" />
                                Abrir Página de Workflow
                            </button>
                        </div>

                    </div>

                    <!-- Footer -->
                    <div class="px-8 py-5 bg-gray-50 dark:bg-gray-900 border-t border-gray-100 dark:border-gray-700">
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                            <!-- Analysis Page: Accept/Reject buttons -->
                            <template v-if="isOnAnalysisPage">
                                <div class="flex items-center gap-2">
                                    <button
                                        @click="handleAccept"
                                        class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-success-500 to-emerald-500 text-white font-semibold shadow-md hover:shadow-lg transition-all"
                                    >
                                        <CheckIcon class="w-5 h-5" />
                                        Aceitar
                                    </button>
                                    <button
                                        @click="handleReject"
                                        :class="[
                                            'flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium transition-all',
                                            isRejected
                                                ? 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-default'
                                                : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                                        ]"
                                        :disabled="isRejected"
                                    >
                                        <XMarkIcon class="w-5 h-5" />
                                        {{ isRejected ? 'Rejeitada' : 'Rejeitar' }}
                                    </button>
                                </div>
                            </template>

                            <!-- Tracking Page: Status Dropdown -->
                            <template v-else-if="isOnTrackingPage">
                                <div class="flex items-center gap-2">
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
                                                    v-for="option in trackingStatusOptions"
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
                                </div>
                            </template>

                            <div class="flex items-center gap-3">
                                <button
                                    @click="emit('close')"
                                    class="px-5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 font-medium hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                >
                                    Fechar
                                </button>
                                <button
                                    v-if="authStore.canDiscussSuggestion"
                                    @click="$emit('ask-ai', suggestion)"
                                    class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-primary-500 to-secondary-500 text-white font-semibold shadow-lg shadow-primary-500/30 hover:shadow-xl transition-all"
                                >
                                    <ChatBubbleLeftRightIcon class="w-5 h-5" />
                                    Discutir com IA
                                </button>
                                <button
                                    v-else
                                    disabled
                                    class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 font-medium cursor-not-allowed"
                                    title="Seu plano não inclui esta funcionalidade"
                                >
                                    <LockClosedIcon class="w-5 h-5" />
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
