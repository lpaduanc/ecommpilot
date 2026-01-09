<script setup>
import { computed } from 'vue';
import { 
    EyeIcon,
    ArrowRightIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    suggestion: { type: Object, required: true },
});

const emit = defineEmits(['view-detail', 'ask-ai']);

const categoryConfig = {
    marketing: { icon: '📣', label: 'Marketing', color: 'from-pink-500 to-rose-500', bg: 'bg-pink-50', text: 'text-pink-700' },
    pricing: { icon: '💰', label: 'Precificação', color: 'from-amber-500 to-yellow-500', bg: 'bg-amber-50', text: 'text-amber-700' },
    inventory: { icon: '📦', label: 'Estoque', color: 'from-sky-500 to-cyan-500', bg: 'bg-sky-50', text: 'text-sky-700' },
    product: { icon: '🛍️', label: 'Produtos', color: 'from-violet-500 to-purple-500', bg: 'bg-violet-50', text: 'text-violet-700' },
    customer: { icon: '👥', label: 'Clientes', color: 'from-emerald-500 to-teal-500', bg: 'bg-emerald-50', text: 'text-emerald-700' },
    conversion: { icon: '🎯', label: 'Conversão', color: 'from-orange-500 to-red-500', bg: 'bg-orange-50', text: 'text-orange-700' },
};

const priorityConfig = {
    high: { label: 'Alta', color: 'bg-danger-500', ring: 'ring-danger-500/30', glow: 'shadow-danger-500/50' },
    medium: { label: 'Média', color: 'bg-accent-500', ring: 'ring-accent-500/30', glow: 'shadow-accent-500/50' },
    low: { label: 'Baixa', color: 'bg-success-500', ring: 'ring-success-500/30', glow: 'shadow-success-500/50' },
};

const category = computed(() => categoryConfig[props.suggestion.category] || { icon: '💡', label: 'Geral', color: 'from-gray-500 to-gray-600', bg: 'bg-gray-50', text: 'text-gray-700' });
const priority = computed(() => priorityConfig[props.suggestion.priority] || priorityConfig.medium);
const isDone = computed(() => props.suggestion.is_done);
</script>

<template>
    <div 
        :class="[
            'group relative bg-white dark:bg-gray-800 rounded-2xl border transition-all duration-300 cursor-pointer overflow-hidden',
            isDone 
                ? 'border-success-200 bg-success-50/30' 
                : 'border-gray-100 dark:border-gray-700 hover:border-primary-200 hover:shadow-xl hover:shadow-primary-500/10 hover:-translate-y-1'
        ]"
        @click="emit('view-detail', suggestion)"
    >
        <!-- Completed Badge -->
        <div v-if="isDone" class="absolute top-3 right-3 z-10">
            <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-success-100 text-success-700 text-xs font-medium">
                <CheckCircleIcon class="w-3.5 h-3.5" />
                Implementado
            </div>
        </div>

        <!-- Priority Indicator -->
        <div 
            :class="[
                'absolute top-0 left-6 w-1 h-8 rounded-b-full transition-all duration-300',
                priority.color,
                !isDone && 'group-hover:h-12 group-hover:shadow-lg',
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
            <div v-if="suggestion.expected_impact" class="flex items-start gap-2 mb-4 p-3 rounded-xl bg-gradient-to-r from-gray-50 to-transparent">
                <span class="text-lg">💡</span>
                <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">{{ suggestion.expected_impact }}</p>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                <div class="flex items-center gap-3 text-xs text-gray-400">
                    <span v-if="suggestion.estimated_effort" class="flex items-center gap-1">
                        ⚡ {{ suggestion.estimated_effort === 'low' ? 'Fácil' : suggestion.estimated_effort === 'medium' ? 'Médio' : 'Complexo' }}
                    </span>
                    <span v-if="suggestion.estimated_time">
                        📅 {{ suggestion.estimated_time }}
                    </span>
                </div>
                <div 
                    :class="[
                        'flex items-center gap-1.5 text-sm font-medium transition-all duration-200',
                        isDone ? 'text-success-600' : 'text-primary-600 opacity-0 group-hover:opacity-100 translate-x-2 group-hover:translate-x-0'
                    ]"
                >
                    <template v-if="!isDone">
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
