<script setup lang="ts">
import { ref, computed } from 'vue';
import SuggestionTaskItem from './SuggestionTaskItem.vue';
import { CheckCircleIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import type { SuggestionTask } from '@/types/analysis';

const props = defineProps<{
    tasks: SuggestionTask[];
    isLoading?: boolean;
}>();

const emit = defineEmits<{
    create: [data: {
        title: string;
        description?: string;
        step_index?: number | null;
    }];
    toggle: [taskId: number];
    delete: [taskId: number];
}>();

const showAddForm = ref(false);
const newTaskTitle = ref('');
const newTaskDescription = ref('');
const linkedStepIndex = ref<number | null>(null);

const tasksProgress = computed(() => {
    if (props.tasks.length === 0) return 0;
    const completed = props.tasks.filter(t => t.status === 'completed').length;
    return Math.round((completed / props.tasks.length) * 100);
});

const completedTasks = computed(() => props.tasks.filter(t => t.status === 'completed').length);

function handleCreate() {
    const title = newTaskTitle.value.trim();
    if (!title) return;

    emit('create', {
        title,
        description: newTaskDescription.value.trim() || undefined,
        step_index: linkedStepIndex.value,
    });

    // Reset form
    newTaskTitle.value = '';
    newTaskDescription.value = '';
    linkedStepIndex.value = null;
    showAddForm.value = false;
}

function handleToggle(taskId: number) {
    emit('toggle', taskId);
}

function handleDelete(taskId: number) {
    emit('delete', taskId);
}

function cancelAdd() {
    newTaskTitle.value = '';
    newTaskDescription.value = '';
    linkedStepIndex.value = null;
    showAddForm.value = false;
}
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-success-500 to-emerald-600 flex items-center justify-center">
                    <CheckCircleIcon class="w-6 h-6 text-white" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                        Tarefas
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ completedTasks }} de {{ tasks.length }} concluídas
                    </p>
                </div>
            </div>

            <!-- Progress -->
            <div v-if="tasks.length > 0" class="flex items-center gap-2">
                <div class="w-24 h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div
                        :style="{ width: `${tasksProgress}%` }"
                        class="h-full bg-gradient-to-r from-success-500 to-emerald-500 transition-all duration-300"
                    ></div>
                </div>
                <span class="text-sm font-semibold text-success-600 dark:text-success-400">
                    {{ tasksProgress }}%
                </span>
            </div>
        </div>

        <!-- Add Task Button -->
        <button
            v-if="!showAddForm"
            @click="showAddForm = true"
            class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-success-500 hover:text-success-600 dark:hover:text-success-400 transition-all mb-4"
        >
            <PlusIcon class="w-5 h-5" />
            Nova Tarefa
        </button>

        <!-- Add Task Form -->
        <div
            v-else
            class="mb-4 p-4 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700"
        >
            <input
                v-model="newTaskTitle"
                type="text"
                placeholder="Título da tarefa"
                class="w-full px-3 py-2 mb-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-success-500 focus:border-transparent"
                @keydown.enter="handleCreate"
            />
            <textarea
                v-model="newTaskDescription"
                placeholder="Descrição (opcional)"
                rows="2"
                class="w-full px-3 py-2 mb-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-success-500 focus:border-transparent resize-none"
            ></textarea>
            <div class="flex gap-2">
                <button
                    @click="handleCreate"
                    :disabled="!newTaskTitle.trim()"
                    class="px-4 py-2 rounded-lg bg-success-500 text-white font-medium hover:bg-success-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                    Adicionar
                </button>
                <button
                    @click="cancelAdd"
                    class="px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                >
                    Cancelar
                </button>
            </div>
        </div>

        <!-- Tasks List -->
        <div v-if="isLoading" class="text-center py-8">
            <div class="inline-block w-8 h-8 border-4 border-success-500 border-t-transparent rounded-full animate-spin"></div>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Carregando tarefas...</p>
        </div>

        <div v-else-if="tasks.length === 0" class="text-center py-8 bg-gray-50 dark:bg-gray-900 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
            <CheckCircleIcon class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-2" />
            <p class="text-sm text-gray-500 dark:text-gray-400">Nenhuma tarefa criada</p>
        </div>

        <div v-else class="space-y-2">
            <SuggestionTaskItem
                v-for="task in tasks"
                :key="task.id"
                :task="task"
                @toggle="handleToggle"
                @delete="handleDelete"
            />
        </div>
    </div>
</template>
