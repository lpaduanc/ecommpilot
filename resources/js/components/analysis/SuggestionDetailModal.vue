<script setup>
import { computed } from 'vue';
import BaseModal from '../common/BaseModal.vue';
import { 
    CheckCircleIcon, 
    ChatBubbleLeftRightIcon,
    SparklesIcon,
    ClockIcon,
    BoltIcon,
    ChartBarIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    show: { type: Boolean, default: false },
    suggestion: { type: Object, default: null },
});

const emit = defineEmits(['close', 'ask-ai', 'mark-done']);

const categoryConfig = {
    marketing: { icon: '📣', label: 'Marketing', color: 'from-pink-500 to-rose-500', bg: 'bg-pink-100' },
    pricing: { icon: '💰', label: 'Precificação', color: 'from-amber-500 to-yellow-500', bg: 'bg-amber-100' },
    inventory: { icon: '📦', label: 'Estoque', color: 'from-sky-500 to-cyan-500', bg: 'bg-sky-100' },
    product: { icon: '🛍️', label: 'Produtos', color: 'from-violet-500 to-purple-500', bg: 'bg-violet-100' },
    customer: { icon: '👥', label: 'Clientes', color: 'from-emerald-500 to-teal-500', bg: 'bg-emerald-100' },
    conversion: { icon: '🎯', label: 'Conversão', color: 'from-orange-500 to-red-500', bg: 'bg-orange-100' },
};

const priorityConfig = {
    high: { label: 'Alta Prioridade', color: 'from-rose-500 to-red-500', bg: 'bg-rose-100', text: 'text-rose-700' },
    medium: { label: 'Média Prioridade', color: 'from-amber-500 to-orange-500', bg: 'bg-amber-100', text: 'text-amber-700' },
    low: { label: 'Baixa Prioridade', color: 'from-emerald-500 to-teal-500', bg: 'bg-emerald-100', text: 'text-emerald-700' },
};

const effortLabels = {
    low: 'Baixo',
    medium: 'Médio',
    high: 'Alto',
};

const category = computed(() => 
    categoryConfig[props.suggestion?.category] || { icon: '💡', label: 'Geral', color: 'from-gray-500 to-gray-600', bg: 'bg-gray-100' }
);

const priority = computed(() => 
    priorityConfig[props.suggestion?.priority] || priorityConfig.medium
);

// Get action_steps from either action_steps or implementation_steps
const actionSteps = computed(() => {
    if (!props.suggestion) return [];
    return props.suggestion.action_steps || props.suggestion.implementation_steps || [];
});
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
                <div class="relative w-full max-w-2xl max-h-[90vh] overflow-hidden bg-white rounded-3xl shadow-2xl animate-scale-in">
                    <!-- Header with Gradient -->
                    <div class="relative px-8 py-6 bg-gradient-to-r overflow-hidden" :class="category.color">
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
                            <h3 class="flex items-center gap-2 font-semibold text-gray-900 mb-3">
                                <SparklesIcon class="w-5 h-5 text-primary-500" />
                                Descrição
                            </h3>
                            <p class="text-gray-600 leading-relaxed">{{ suggestion.description }}</p>
                        </div>

                        <!-- Expected Impact -->
                        <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-2xl p-5 border border-emerald-100">
                            <h3 class="flex items-center gap-2 font-semibold text-emerald-900 mb-2">
                                <ChartBarIcon class="w-5 h-5 text-emerald-600" />
                                Impacto Esperado
                            </h3>
                            <p class="text-emerald-700 leading-relaxed">{{ suggestion.expected_impact }}</p>
                        </div>

                        <!-- Implementation Steps -->
                        <div v-if="actionSteps.length > 0">
                            <h3 class="flex items-center gap-2 font-semibold text-gray-900 mb-4">
                                <BoltIcon class="w-5 h-5 text-primary-500" />
                                Passos para Implementação
                            </h3>
                            <div class="space-y-3">
                                <div
                                    v-for="(step, index) in actionSteps"
                                    :key="index"
                                    class="flex items-start gap-4 p-4 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors"
                                >
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-secondary-500 text-white flex items-center justify-center text-sm font-bold shadow-lg shadow-primary-500/30">
                                        {{ index + 1 }}
                                    </div>
                                    <span class="text-gray-700 pt-1">{{ step }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Metrics to Track -->
                        <div v-if="suggestion.metrics_to_track?.length > 0">
                            <h3 class="font-semibold text-gray-900 mb-3">📊 Métricas para Acompanhar</h3>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="metric in suggestion.metrics_to_track"
                                    :key="metric"
                                    class="px-4 py-2 bg-gradient-to-r from-gray-100 to-gray-50 rounded-xl text-sm text-gray-700 border border-gray-200"
                                >
                                    {{ metric }}
                                </span>
                            </div>
                        </div>

                        <!-- Effort & Time -->
                        <div class="flex flex-wrap gap-4">
                            <div v-if="suggestion.estimated_effort" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-gray-50">
                                <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center">
                                    <BoltIcon class="w-5 h-5 text-amber-600" />
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 block">Esforço</span>
                                    <span class="font-semibold text-gray-900">{{ effortLabels[suggestion.estimated_effort] }}</span>
                                </div>
                            </div>
                            <div v-if="suggestion.estimated_time" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-gray-50">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <ClockIcon class="w-5 h-5 text-blue-600" />
                                </div>
                                <div>
                                    <span class="text-xs text-gray-500 block">Tempo Estimado</span>
                                    <span class="font-semibold text-gray-900">{{ suggestion.estimated_time }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-8 py-5 bg-gray-50 border-t border-gray-100">
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                            <button
                                v-if="!suggestion.is_done"
                                @click="emit('mark-done', suggestion)"
                                class="flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border-2 border-dashed border-gray-300 text-gray-600 font-medium hover:border-success-500 hover:text-success-600 hover:bg-success-50 transition-all"
                            >
                                <CheckCircleIcon class="w-5 h-5" />
                                Marcar como Implementado
                            </button>
                            <div v-else class="flex items-center gap-2 text-success-600 font-semibold">
                                <CheckCircleIcon class="w-5 h-5" />
                                Implementado
                            </div>

                            <div class="flex items-center gap-3">
                                <button
                                    @click="emit('close')"
                                    class="px-5 py-2.5 rounded-xl border border-gray-200 text-gray-700 font-medium hover:bg-gray-100 transition-colors"
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
    transition: all 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .animate-scale-in,
.modal-leave-to .animate-scale-in {
    transform: scale(0.95) translateY(20px);
}
</style>
