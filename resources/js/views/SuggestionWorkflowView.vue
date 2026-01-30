<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAnalysisStore } from '@/stores/analysisStore';
import SuggestionStepsPanel from '@/components/analysis/SuggestionStepsPanel.vue';
import SuggestionTasksPanel from '@/components/analysis/SuggestionTasksPanel.vue';
import SuggestionComments from '@/components/analysis/SuggestionComments.vue';
import BaseModal from '@/components/common/BaseModal.vue';
import {
    ArrowLeftIcon,
    CheckCircleIcon,
    ClockIcon,
    PlayIcon,
    XMarkIcon,
    ChevronDownIcon,
} from '@heroicons/vue/24/outline';
import type { SuggestionTask, SuggestionComment } from '@/types/analysis';

const route = useRoute();
const router = useRouter();
const analysisStore = useAnalysisStore();

const suggestionId = computed(() => route.params.id as string);

const suggestion = ref<any>(null);
const tasks = ref<SuggestionTask[]>([]);
const comments = ref<SuggestionComment[]>([]);

const isLoading = ref(false);
const tasksLoading = ref(false);
const commentsLoading = ref(false);
const showStatusDropdown = ref(false);
const showFeedbackModal = ref(false);
const pendingCompletedStatus = ref(false);

const categoryConfig: Record<string, { icon: string; label: string; color: string }> = {
    marketing: { icon: '📣', label: 'Marketing', color: 'from-pink-500 to-rose-500' },
    pricing: { icon: '💰', label: 'Precificação', color: 'from-amber-500 to-yellow-500' },
    inventory: { icon: '📦', label: 'Estoque', color: 'from-sky-500 to-cyan-500' },
    product: { icon: '🛍️', label: 'Produtos', color: 'from-violet-500 to-purple-500' },
    customer: { icon: '👥', label: 'Clientes', color: 'from-emerald-500 to-teal-500' },
    conversion: { icon: '🎯', label: 'Conversão', color: 'from-orange-500 to-red-500' },
    coupon: { icon: '🏷️', label: 'Cupons', color: 'from-indigo-500 to-blue-500' },
    operational: { icon: '⚙️', label: 'Operacional', color: 'from-slate-500 to-gray-500' },
};

const statusOptions = [
    { value: 'accepted', label: 'Aguardando', icon: ClockIcon, color: 'text-gray-600 dark:text-gray-400' },
    { value: 'in_progress', label: 'Em Andamento', icon: PlayIcon, color: 'text-primary-600 dark:text-primary-400' },
    { value: 'completed', label: 'Concluído', icon: CheckCircleIcon, color: 'text-success-600 dark:text-success-400' },
    { value: 'rejected', label: 'Rejeitar', icon: XMarkIcon, color: 'text-gray-600 dark:text-gray-400' },
];

const category = computed(() =>
    categoryConfig[suggestion.value?.category] || { icon: '💡', label: 'Geral', color: 'from-gray-500 to-gray-600' }
);

const currentStatus = computed(() =>
    statusOptions.find(o => o.value === suggestion.value?.status) || statusOptions[0]
);

onMounted(async () => {
    await loadSuggestion();
    await Promise.all([
        loadTasks(),
        loadComments(),
    ]);
});

async function loadSuggestion() {
    isLoading.value = true;
    const data = await analysisStore.getSuggestionDetail(suggestionId.value);
    if (data) {
        suggestion.value = data;
    } else {
        // Suggestion not found, redirect back
        router.push({ name: 'suggestions' });
    }
    isLoading.value = false;
}

async function loadTasks() {
    tasksLoading.value = true;
    const result = await analysisStore.fetchSuggestionTasks(suggestionId.value);
    if (result.success) {
        tasks.value = result.tasks || [];
    }
    tasksLoading.value = false;
}

async function loadComments() {
    commentsLoading.value = true;
    const result = await analysisStore.fetchSuggestionComments(suggestionId.value);
    if (result.success) {
        comments.value = result.comments || [];
    }
    commentsLoading.value = false;
}

// Tasks handlers
async function handleCreateTask(data: { title: string; description?: string; step_index?: number | null }) {
    const result = await analysisStore.createSuggestionTask(suggestionId.value, data);
    if (result.success && result.task) {
        tasks.value.push(result.task);
    }
}

async function handleCreateTaskForStep(stepIndex: number, stepText: string) {
    const result = await analysisStore.createSuggestionTask(suggestionId.value, {
        title: stepText,
        step_index: stepIndex,
    });
    if (result.success && result.task) {
        tasks.value.push(result.task);
    }
}

async function handleToggleTask(taskId: number) {
    const result = await analysisStore.toggleTaskStatus(suggestionId.value, taskId);
    if (result.success && result.task) {
        const index = tasks.value.findIndex(t => t.id === taskId);
        if (index !== -1) {
            tasks.value[index] = result.task;
        }
    }
}

async function handleDeleteTask(taskId: number) {
    const result = await analysisStore.deleteSuggestionTask(suggestionId.value, taskId);
    if (result.success) {
        tasks.value = tasks.value.filter(t => t.id !== taskId);
    }
}

// Comments handlers
async function handleCreateComment(content: string) {
    const result = await analysisStore.createSuggestionComment(suggestionId.value, { content });
    if (result.success && result.comment) {
        comments.value.push(result.comment);
    }
}

async function handleDeleteComment(commentId: number) {
    const result = await analysisStore.deleteSuggestionComment(suggestionId.value, commentId);
    if (result.success) {
        comments.value = comments.value.filter(c => c.id !== commentId);
    }
}

// Status handlers
async function handleStatusChange(newStatus: string) {
    showStatusDropdown.value = false;

    if (newStatus === suggestion.value?.status) return;

    // Se mudando para completed, mostrar modal de feedback primeiro
    if (newStatus === 'completed') {
        pendingCompletedStatus.value = true;
        showFeedbackModal.value = true;
        return;
    }

    await updateStatus(newStatus);
}

async function updateStatus(newStatus: string) {
    const result = await analysisStore.updateSuggestionStatus(suggestionId.value, newStatus);
    if (result.success && result.suggestion) {
        suggestion.value = { ...suggestion.value, ...result.suggestion };
    }
}

async function submitFeedback(wasSuccessful: boolean | null) {
    showFeedbackModal.value = false;

    // Primeiro atualiza o feedback
    if (wasSuccessful !== null) {
        await analysisStore.updateSuggestionFeedback(suggestionId.value, wasSuccessful);
    }

    // Depois atualiza o status para completed
    if (pendingCompletedStatus.value) {
        await updateStatus('completed');
        pendingCompletedStatus.value = false;
    }
}

function goBack() {
    router.push({ name: 'suggestions' });
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <!-- Loading State -->
        <div v-if="isLoading" class="flex items-center justify-center h-screen">
            <div class="text-center">
                <div class="inline-block w-12 h-12 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
                <p class="mt-4 text-gray-600 dark:text-gray-400">Carregando sugestão...</p>
            </div>
        </div>

        <!-- Content -->
        <div v-else-if="suggestion" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="mb-6">
                <button
                    @click="goBack"
                    class="flex items-center gap-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 transition-colors mb-4"
                >
                    <ArrowLeftIcon class="w-5 h-5" />
                    Voltar para Sugestões
                </button>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <!-- Gradient Header -->
                    <div class="relative px-6 py-5 bg-gradient-to-r" :class="category.color">
                        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(rgba(255,255,255,0.3) 1px, transparent 1px); background-size: 20px 20px;"></div>

                        <div class="relative flex items-start justify-between gap-4">
                            <div class="flex items-start gap-4 flex-1">
                                <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center text-3xl">
                                    {{ category.icon }}
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="text-white/80 text-sm font-medium uppercase tracking-wider">
                                            {{ category.label }}
                                        </span>
                                    </div>
                                    <h1 class="text-2xl lg:text-3xl font-display font-bold text-white">
                                        {{ suggestion.title }}
                                    </h1>
                                </div>
                            </div>

                            <!-- Status Dropdown -->
                            <div class="relative">
                                <button
                                    @click="showStatusDropdown = !showStatusDropdown"
                                    class="flex items-center gap-2 px-4 py-2 rounded-xl bg-white/20 backdrop-blur-sm text-white font-medium hover:bg-white/30 transition-all"
                                >
                                    <component :is="currentStatus.icon" class="w-5 h-5" />
                                    {{ currentStatus.label }}
                                    <ChevronDownIcon class="w-4 h-4" />
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
                                        class="absolute right-0 top-full mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 py-2 z-20"
                                    >
                                        <button
                                            v-for="option in statusOptions"
                                            :key="option.value"
                                            @click="handleStatusChange(option.value)"
                                            class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                            :class="[option.color, option.value === suggestion.status ? 'bg-gray-50 dark:bg-gray-700' : '']"
                                        >
                                            <component :is="option.icon" class="w-5 h-5" />
                                            {{ option.label }}
                                        </button>
                                    </div>
                                </Transition>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                            {{ suggestion.description }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Steps Panel -->
                <SuggestionStepsPanel
                    :recommended-action="suggestion.recommended_action"
                    @create-task-for-step="handleCreateTaskForStep"
                />

                <!-- Tasks Panel -->
                <SuggestionTasksPanel
                    :tasks="tasks"
                    :is-loading="tasksLoading"
                    @create="handleCreateTask"
                    @toggle="handleToggleTask"
                    @delete="handleDeleteTask"
                />
            </div>

            <!-- Comments Section -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <SuggestionComments
                    :comments="comments"
                    :is-loading="commentsLoading"
                    @create="handleCreateComment"
                    @delete="handleDeleteComment"
                />
            </div>
        </div>

        <!-- Feedback Modal -->
        <BaseModal :show="showFeedbackModal" title="Sugestão Concluída!" size="sm" @close="showFeedbackModal = false">
            <div class="text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-success-100 dark:bg-success-900/30 flex items-center justify-center">
                    <CheckCircleIcon class="w-8 h-8 text-success-600 dark:text-success-400" />
                </div>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    Essa sugestão trouxe resultado para sua loja?
                </p>
                <div class="flex gap-3 justify-center">
                    <button
                        @click="submitFeedback(true)"
                        class="px-4 py-2 bg-success-500 hover:bg-success-600 text-white rounded-lg transition-colors"
                    >
                        👍 Sim
                    </button>
                    <button
                        @click="submitFeedback(false)"
                        class="px-4 py-2 bg-danger-500 hover:bg-danger-600 text-white rounded-lg transition-colors"
                    >
                        👎 Não
                    </button>
                    <button
                        @click="submitFeedback(null)"
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors"
                    >
                        🤷 Ainda não sei
                    </button>
                </div>
            </div>
        </BaseModal>
    </div>
</template>
