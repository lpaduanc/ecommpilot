<script setup>
import { computed } from 'vue';
import {
    EyeIcon,
    ArrowRightIcon,
    CheckCircleIcon,
    ClockIcon,
    PlayIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    suggestion: { type: Object, required: true },
});

const emit = defineEmits(['view-detail', 'ask-ai', 'status-change']);

const categoryConfig = {
    marketing: { icon: '📣', label: 'Marketing', color: 'from-pink-500 to-rose-500', bg: 'bg-pink-50 dark:bg-pink-900/30', text: 'text-pink-700 dark:text-pink-400' },
    pricing: { icon: '💰', label: 'Precificação', color: 'from-amber-500 to-yellow-500', bg: 'bg-amber-50 dark:bg-amber-900/30', text: 'text-amber-700 dark:text-amber-400' },
    inventory: { icon: '📦', label: 'Estoque', color: 'from-sky-500 to-cyan-500', bg: 'bg-sky-50 dark:bg-sky-900/30', text: 'text-sky-700 dark:text-sky-400' },
    product: { icon: '🛍️', label: 'Produtos', color: 'from-violet-500 to-purple-500', bg: 'bg-violet-50 dark:bg-violet-900/30', text: 'text-violet-700 dark:text-violet-400' },
    customer: { icon: '👥', label: 'Clientes', color: 'from-emerald-500 to-teal-500', bg: 'bg-emerald-50 dark:bg-emerald-900/30', text: 'text-emerald-700 dark:text-emerald-400' },
    conversion: { icon: '🎯', label: 'Conversão', color: 'from-orange-500 to-red-500', bg: 'bg-orange-50 dark:bg-orange-900/30', text: 'text-orange-700 dark:text-orange-400' },
};

const priorityConfig = {
    high: { label: 'Alta', color: 'bg-danger-500', ring: 'ring-danger-500/30', glow: 'shadow-danger-500/50' },
    medium: { label: 'Média', color: 'bg-accent-500', ring: 'ring-accent-500/30', glow: 'shadow-accent-500/50' },
    low: { label: 'Baixa', color: 'bg-success-500', ring: 'ring-success-500/30', glow: 'shadow-success-500/50' },
};

const statusConfig = {
    pending: { label: 'Pendente', icon: ClockIcon, bg: 'bg-gray-100 dark:bg-gray-700', text: 'text-gray-600 dark:text-gray-300' },
    in_progress: { label: 'Em Andamento', icon: PlayIcon, bg: 'bg-primary-100 dark:bg-primary-900/30', text: 'text-primary-700 dark:text-primary-400' },
    completed: { label: 'Implementado', icon: CheckCircleIcon, bg: 'bg-success-100 dark:bg-success-900/30', text: 'text-success-700 dark:text-success-400' },
    ignored: { label: 'Ignorada', icon: XCircleIcon, bg: 'bg-gray-100 dark:bg-gray-700', text: 'text-gray-500 dark:text-gray-400' },
};

const category = computed(() => categoryConfig[props.suggestion.category] || { icon: '💡', label: 'Geral', color: 'from-gray-500 to-gray-600', bg: 'bg-gray-50', text: 'text-gray-700' });
const priority = computed(() => priorityConfig[props.suggestion.priority || props.suggestion.expected_impact] || priorityConfig.medium);

// Support both legacy is_done and new status field
const suggestionStatus = computed(() => {
    if (props.suggestion.status) return props.suggestion.status;
    return props.suggestion.is_done ? 'completed' : 'pending';
});
const statusInfo = computed(() => statusConfig[suggestionStatus.value] || statusConfig.pending);
const isDone = computed(() => suggestionStatus.value === 'completed');
const isInProgress = computed(() => suggestionStatus.value === 'in_progress');
const isIgnored = computed(() => suggestionStatus.value === 'ignored');
</script>

<template>
    <div
        :class="[
            'group relative bg-white dark:bg-gray-800 rounded-2xl border transition-all duration-300 cursor-pointer overflow-hidden',
            isDone
                ? 'border-success-200 dark:border-success-800 bg-success-50/30 dark:bg-success-900/10'
                : isInProgress
                    ? 'border-primary-200 dark:border-primary-800 bg-primary-50/30 dark:bg-primary-900/10'
                    : isIgnored
                        ? 'border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30 opacity-60'
                        : 'border-gray-100 dark:border-gray-700 hover:border-primary-200 hover:shadow-xl hover:shadow-primary-500/10 hover:-translate-y-1'
        ]"
        @click="emit('view-detail', suggestion)"
    >
        <!-- Status Badge -->
        <div v-if="suggestionStatus !== 'pending'" class="absolute top-3 right-3 z-10">
            <div :class="['flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium', statusInfo.bg, statusInfo.text]">
                <component :is="statusInfo.icon" class="w-3.5 h-3.5" />
                {{ statusInfo.label }}
            </div>
        </div>

        <!-- Priority Indicator -->
        <div
            :class="[
                'absolute top-0 left-6 w-1 h-8 rounded-b-full transition-all duration-300',
                priority.color,
                !isDone && !isIgnored && 'group-hover:h-12 group-hover:shadow-lg',
                priority.glow
            ]"
        ></div>

        <div class="p-5">
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
                        !isDone && 'group-hover:text-primary-600'
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
                <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">{{ suggestion.expected_impact }}</p>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-3 text-xs text-gray-400 dark:text-gray-500">
                    <span v-if="suggestion.estimated_effort" class="flex items-center gap-1">
                        {{ suggestion.estimated_effort === 'low' ? 'Fácil' : suggestion.estimated_effort === 'medium' ? 'Médio' : 'Complexo' }}
                    </span>
                    <span v-if="suggestion.estimated_time">
                        {{ suggestion.estimated_time }}
                    </span>
                    <span v-if="suggestion.expected_impact && !suggestion.estimated_effort" class="capitalize">
                        Impacto {{ suggestion.expected_impact === 'high' ? 'Alto' : suggestion.expected_impact === 'medium' ? 'Médio' : 'Baixo' }}
                    </span>
                </div>
                <div
                    :class="[
                        'flex items-center gap-1.5 text-sm font-medium transition-all duration-200',
                        isDone || isIgnored ? '' : 'text-primary-600 dark:text-primary-400 opacity-0 group-hover:opacity-100 translate-x-2 group-hover:translate-x-0'
                    ]"
                >
                    <template v-if="!isDone && !isIgnored">
                        Ver detalhes
                        <ArrowRightIcon class="w-4 h-4" />
                    </template>
                </div>
            </div>
        </div>

        <!-- Hover Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-t from-primary-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
    </div>
</template>
