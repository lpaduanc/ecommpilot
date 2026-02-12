<script setup>
import { computed } from 'vue';
import {
    EyeIcon,
    ArrowRightIcon,
    CheckCircleIcon,
    ClockIcon,
    PlayIcon,
    XCircleIcon,
    CheckIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    suggestion: { type: Object, required: true },
    showActions: { type: Boolean, default: true }, // Show accept/reject buttons
});

const emit = defineEmits(['view-detail', 'ask-ai', 'status-change', 'accept', 'reject']);

const categoryConfig = {
    strategy: { icon: '🎯', label: 'Estratégia', color: 'from-rose-500 to-red-500', bg: 'bg-rose-50 dark:bg-rose-900/30', text: 'text-rose-700 dark:text-rose-400' },
    investment: { icon: '💎', label: 'Investimento', color: 'from-cyan-500 to-blue-500', bg: 'bg-cyan-50 dark:bg-cyan-900/30', text: 'text-cyan-700 dark:text-cyan-400' },
    market: { icon: '🌍', label: 'Mercado', color: 'from-teal-500 to-emerald-500', bg: 'bg-teal-50 dark:bg-teal-900/30', text: 'text-teal-700 dark:text-teal-400' },
    growth: { icon: '📈', label: 'Crescimento', color: 'from-lime-500 to-green-500', bg: 'bg-lime-50 dark:bg-lime-900/30', text: 'text-lime-700 dark:text-lime-400' },
    financial: { icon: '💵', label: 'Financeiro', color: 'from-yellow-500 to-amber-500', bg: 'bg-yellow-50 dark:bg-yellow-900/30', text: 'text-yellow-700 dark:text-yellow-400' },
    positioning: { icon: '🏆', label: 'Posicionamento', color: 'from-fuchsia-500 to-purple-500', bg: 'bg-fuchsia-50 dark:bg-fuchsia-900/30', text: 'text-fuchsia-700 dark:text-fuchsia-400' },
    marketing: { icon: '📣', label: 'Marketing', color: 'from-pink-500 to-rose-500', bg: 'bg-pink-50 dark:bg-pink-900/30', text: 'text-pink-700 dark:text-pink-400' },
    pricing: { icon: '💰', label: 'Precificação', color: 'from-amber-500 to-yellow-500', bg: 'bg-amber-50 dark:bg-amber-900/30', text: 'text-amber-700 dark:text-amber-400' },
    inventory: { icon: '📦', label: 'Estoque', color: 'from-sky-500 to-cyan-500', bg: 'bg-sky-50 dark:bg-sky-900/30', text: 'text-sky-700 dark:text-sky-400' },
    product: { icon: '🛍️', label: 'Produtos', color: 'from-violet-500 to-purple-500', bg: 'bg-violet-50 dark:bg-violet-900/30', text: 'text-violet-700 dark:text-violet-400' },
    customer: { icon: '👥', label: 'Clientes', color: 'from-emerald-500 to-teal-500', bg: 'bg-emerald-50 dark:bg-emerald-900/30', text: 'text-emerald-700 dark:text-emerald-400' },
    conversion: { icon: '🔄', label: 'Conversão', color: 'from-orange-500 to-red-500', bg: 'bg-orange-50 dark:bg-orange-900/30', text: 'text-orange-700 dark:text-orange-400' },
    coupon: { icon: '🏷️', label: 'Cupons', color: 'from-indigo-500 to-blue-500', bg: 'bg-indigo-50 dark:bg-indigo-900/30', text: 'text-indigo-700 dark:text-indigo-400' },
    operational: { icon: '⚙️', label: 'Operacional', color: 'from-slate-500 to-gray-500', bg: 'bg-slate-50 dark:bg-slate-900/30', text: 'text-slate-700 dark:text-slate-400' },
};

const priorityConfig = {
    high: { label: 'Alta', color: 'bg-danger-500', ring: 'ring-danger-500/30', glow: 'shadow-danger-500/50' },
    medium: { label: 'Média', color: 'bg-accent-500', ring: 'ring-accent-500/30', glow: 'shadow-accent-500/50' },
    low: { label: 'Baixa', color: 'bg-success-500', ring: 'ring-success-500/30', glow: 'shadow-success-500/50' },
};

const category = computed(() => categoryConfig[props.suggestion.category] || { icon: '💡', label: 'Geral', color: 'from-gray-500 to-gray-600', bg: 'bg-gray-50', text: 'text-gray-700' });
const priority = computed(() => priorityConfig[props.suggestion.priority || props.suggestion.expected_impact] || priorityConfig.medium);

// New status system
const suggestionStatus = computed(() => props.suggestion.status || 'new');

// Steps progress
const stepsProgress = computed(() => {
    const total = props.suggestion.steps_count || 0;
    const completed = props.suggestion.completed_steps_count || 0;
    if (total === 0) return 0;
    return Math.round((completed / total) * 100);
});

const hasSteps = computed(() => (props.suggestion.steps_count || 0) > 0);
const isRejected = computed(() => ['rejected', 'ignored'].includes(suggestionStatus.value));
const isAccepted = computed(() => suggestionStatus.value === 'accepted');
const isInProgress = computed(() => suggestionStatus.value === 'in_progress');
const isCompleted = computed(() => suggestionStatus.value === 'completed');
const isOnAnalysisPage = computed(() => props.suggestion.is_on_analysis_page ?? ['new', 'pending', 'rejected', 'ignored'].includes(suggestionStatus.value));

function handleAccept(e) {
    e.stopPropagation();
    emit('accept', props.suggestion);
}

function handleReject(e) {
    e.stopPropagation();
    emit('reject', props.suggestion);
}
</script>

<template>
    <div
        :class="[
            'group relative bg-white dark:bg-gray-800 rounded-2xl border transition-all duration-300 overflow-hidden',
            isRejected
                ? 'border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30 opacity-70'
                : 'border-gray-100 dark:border-gray-700 hover:border-primary-200 hover:shadow-xl hover:shadow-primary-500/10 hover:-translate-y-1'
        ]"
    >
        <!-- Status Badges -->
        <div v-if="isRejected" class="absolute top-3 right-3 z-10">
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                <XCircleIcon class="w-3.5 h-3.5" />
                Rejeitada
            </div>
        </div>
        <div v-else-if="isAccepted" class="absolute top-3 right-3 z-10">
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-400">
                <CheckCircleIcon class="w-3.5 h-3.5" />
                Aceita
            </div>
        </div>
        <div v-else-if="isInProgress" class="absolute top-3 right-3 z-10">
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400">
                <PlayIcon class="w-3.5 h-3.5" />
                Em Andamento
            </div>
        </div>
        <div v-else-if="isCompleted" class="absolute top-3 right-3 z-10">
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-400">
                <CheckCircleIcon class="w-3.5 h-3.5" />
                Concluída
            </div>
        </div>

        <!-- Priority Indicator -->
        <div
            :class="[
                'absolute top-0 left-6 w-1 h-8 rounded-b-full transition-all duration-300',
                priority.color,
                !isRejected && 'group-hover:h-12 group-hover:shadow-lg',
                priority.glow
            ]"
        ></div>

        <!-- Clickable area for details -->
        <div class="p-5 cursor-pointer" @click="emit('view-detail', suggestion)">
            <!-- Header -->
            <div class="flex items-start gap-3 mb-4">
                <div :class="['w-12 h-12 rounded-xl flex items-center justify-center text-xl transition-transform duration-300 group-hover:scale-110', category.bg]">
                    {{ category.icon }}
                </div>
                <div class="flex-1 min-w-0">
                    <span :class="['text-xs font-semibold uppercase tracking-wider', category.text]">
                        {{ category.label }}
                    </span>
                    <h4 :class="[
                        'font-semibold text-gray-900 dark:text-gray-100 mt-1 line-clamp-2 transition-colors duration-200',
                        !isRejected && 'group-hover:text-primary-600'
                    ]">
                        {{ suggestion.title }}
                    </h4>
                </div>
            </div>

            <!-- Description -->
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 line-clamp-2 leading-relaxed">
                {{ suggestion.description }}
            </p>

            <!-- Expected Impact -->
            <div v-if="suggestion.expected_impact" class="flex items-start gap-2 mb-4 p-3 rounded-xl bg-gradient-to-r from-gray-50 to-transparent dark:from-gray-700/50 dark:to-transparent">
                <span class="text-lg">💡</span>
                <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                    Impacto {{ suggestion.expected_impact === 'high' ? 'Alto' : suggestion.expected_impact === 'medium' ? 'Médio' : suggestion.expected_impact === 'low' ? 'Baixo' : suggestion.expected_impact }}
                </p>
            </div>

            <!-- Steps Progress (Mini Progress Bar) -->
            <div v-if="hasSteps" class="flex items-center gap-2 mb-4">
                <div class="flex gap-1">
                    <div
                        v-for="i in 5"
                        :key="i"
                        :class="[
                            'w-2 h-2 rounded-full transition-colors',
                            i <= Math.ceil((stepsProgress / 100) * 5)
                                ? 'bg-success-500'
                                : 'bg-gray-300 dark:bg-gray-600'
                        ]"
                    ></div>
                </div>
                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">
                    {{ stepsProgress }}% completo
                </span>
            </div>
        </div>

        <!-- Action Buttons (Accept/Reject) -->
        <div v-if="showActions && isOnAnalysisPage" class="px-5 pb-4 pt-0">
            <div class="flex items-center justify-start gap-2 pt-3 border-t border-gray-100 dark:border-gray-700">
                <button
                    @click="handleAccept"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-success-500 hover:bg-success-600 text-white text-xs font-medium transition-colors"
                >
                    <CheckIcon class="w-3.5 h-3.5" />
                    Aceitar
                </button>
                <button
                    @click="handleReject"
                    :class="[
                        'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                        isRejected
                            ? 'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-default'
                            : 'bg-danger-500 hover:bg-danger-600 text-white'
                    ]"
                    :disabled="isRejected"
                >
                    <XMarkIcon class="w-3.5 h-3.5" />
                    {{ isRejected ? 'Rejeitada' : 'Rejeitar' }}
                </button>
            </div>
        </div>

        <!-- Hover Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-t from-primary-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
    </div>
</template>
