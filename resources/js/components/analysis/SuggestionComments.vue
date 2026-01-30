<script setup lang="ts">
import { ref, computed } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import { useFormatters } from '@/composables/useFormatters';
import { useSanitize } from '@/composables/useSanitize';
import {
    TrashIcon,
    PaperAirplaneIcon,
    ChatBubbleLeftRightIcon,
} from '@heroicons/vue/24/outline';
import type { SuggestionComment } from '@/types/analysis';

const props = defineProps<{
    comments: SuggestionComment[];
    isLoading?: boolean;
}>();

const emit = defineEmits<{
    create: [content: string];
    delete: [commentId: number];
}>();

const authStore = useAuthStore();
const { formatDate } = useFormatters();
const { sanitizeHtml } = useSanitize();

const newComment = ref('');
const isSubmitting = ref(false);

const currentUserId = computed(() => authStore.user?.id);

async function handleSubmit() {
    const content = newComment.value.trim();
    if (!content || isSubmitting.value) return;

    isSubmitting.value = true;

    try {
        emit('create', content);

        // Reset after a delay to allow parent to handle
        setTimeout(() => {
            newComment.value = '';
            isSubmitting.value = false;
        }, 500);
    } catch (error) {
        isSubmitting.value = false;
    }
}

function handleDelete(commentId: number) {
    if (confirm('Deseja realmente excluir este comentário?')) {
        emit('delete', commentId);
    }
}

function canDeleteComment(comment: SuggestionComment): boolean {
    // User can delete their own comments or if they are admin
    return (
        currentUserId.value === comment.user_id ||
        authStore.hasPermission('suggestions.manage')
    );
}
</script>

<template>
    <div class="space-y-4">
        <!-- Header -->
        <div class="flex items-center gap-2">
            <ChatBubbleLeftRightIcon class="w-5 h-5 text-primary-500" />
            <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                Comentários
                <span
                    v-if="comments.length > 0"
                    class="ml-2 px-2 py-0.5 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 text-xs font-medium"
                >
                    {{ comments.length }}
                </span>
            </h3>
        </div>

        <!-- Comments List -->
        <div v-if="isLoading" class="text-center py-8">
            <div class="inline-block w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Carregando comentários...</p>
        </div>

        <div v-else-if="comments.length === 0" class="text-center py-8">
            <ChatBubbleLeftRightIcon class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-2" />
            <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum comentário ainda</p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="comment in comments"
                :key="comment.id"
                class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700"
            >
                <!-- Header -->
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2">
                        <!-- Avatar -->
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-secondary-500 flex items-center justify-center text-white text-xs font-bold">
                            {{ comment.user?.name?.charAt(0).toUpperCase() || '?' }}
                        </div>

                        <!-- User Info -->
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ comment.user?.name || 'Usuário' }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ formatDate(comment.created_at) }}
                            </p>
                        </div>
                    </div>

                    <!-- Delete Button -->
                    <button
                        v-if="canDeleteComment(comment)"
                        @click="handleDelete(comment.id)"
                        class="p-1 rounded hover:bg-danger-100 dark:hover:bg-danger-900/30 text-danger-600 dark:text-danger-400 transition-colors"
                        title="Excluir comentário"
                    >
                        <TrashIcon class="w-4 h-4" />
                    </button>
                </div>

                <!-- Content -->
                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap" v-html="sanitizeHtml(comment.content)"></p>

                <!-- Step Reference -->
                <div
                    v-if="comment.step_id"
                    class="mt-2 inline-flex items-center gap-1 px-2 py-1 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-xs"
                >
                    <span>📍 Em um passo específico</span>
                </div>
            </div>
        </div>

        <!-- New Comment Form -->
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
            <div class="flex gap-2">
                <textarea
                    v-model="newComment"
                    @keydown.enter.ctrl.prevent="handleSubmit"
                    @keydown.enter.meta.prevent="handleSubmit"
                    placeholder="Adicione um comentário... (Ctrl+Enter para enviar)"
                    rows="3"
                    class="flex-1 px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
                ></textarea>
                <button
                    @click="handleSubmit"
                    :disabled="!newComment.trim() || isSubmitting"
                    :class="[
                        'px-4 h-fit rounded-xl font-medium transition-all flex items-center gap-2',
                        newComment.trim() && !isSubmitting
                            ? 'bg-gradient-to-r from-primary-500 to-secondary-500 text-white shadow-lg shadow-primary-500/30 hover:shadow-xl'
                            : 'bg-gray-200 dark:bg-gray-700 text-gray-400 dark:text-gray-500 cursor-not-allowed'
                    ]"
                >
                    <PaperAirplaneIcon class="w-5 h-5" />
                    <span class="hidden sm:inline">Enviar</span>
                </button>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Pressione Ctrl+Enter para enviar rapidamente
            </p>
        </div>
    </div>
</template>
