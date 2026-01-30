<script setup lang="ts">
import { computed } from 'vue';
import { useFormatters } from '@/composables/useFormatters';
import { CheckCircleIcon, ClockIcon, PlayIcon, TrashIcon } from '@heroicons/vue/24/outline';
import type { SuggestionTask } from '@/types/analysis';

const props = defineProps<{
    task: SuggestionTask;
    isToggling?: boolean;
}>();

const emit = defineEmits<{
    toggle: [taskId: number];
    delete: [taskId: number];
}>();

const { formatDate } = useFormatters();

const statusConfig = {
    pending: {
        icon: ClockIcon,
        label: 'Pendente',
        color: 'text-gray-600 dark:text-gray-400',
        bg: 'bg-gray-100 dark:bg-gray-700',
    },
    in_progress: {
        icon: PlayIcon,
        label: 'Em Andamento',
        color: 'text-primary-600 dark:text-primary-400',
        bg: 'bg-primary-100 dark:bg-primary-900/30',
    },
    completed: {
        icon: CheckCircleIcon,
        label: 'Concluída',
        color: 'text-success-600 dark:text-success-400',
        bg: 'bg-success-100 dark:bg-success-900/30',
    },
};

const currentStatus = computed(() => statusConfig[props.task.status] || statusConfig.pending);

const isCompleted = computed(() => props.task.status === 'completed');

function handleToggle() {
    emit('toggle', props.task.id);
}

function handleDelete() {
    if (confirm('Deseja realmente excluir esta tarefa?')) {
        emit('delete', props.task.id);
    }
}
</script>

<template>
    <div
        :class="[
            'group relative p-4 rounded-xl border transition-all',
            isCompleted
                ? 'bg-success-50 dark:bg-success-900/10 border-success-200 dark:border-success-800'
                : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:border-primary-300 dark:hover:border-primary-700'
        ]"
    >
        <div class="flex items-start gap-3">
            <!-- Checkbox -->
            <button
                @click="handleToggle"
                :disabled="isToggling"
                :class="[
                    'flex-shrink-0 w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all',
                    isCompleted
                        ? 'bg-success-500 border-success-500'
                        : 'border-gray-300 dark:border-gray-600 hover:border-primary-500 dark:hover:border-primary-400',
                    isToggling && 'opacity-50 cursor-wait'
                ]"
            >
                <CheckCircleIcon
                    v-if="isCompleted"
                    class="w-5 h-5 text-white"
                />
            </button>

            <!-- Content -->
            <div class="flex-1 min-w-0">
                <!-- Title -->
                <h4
                    :class="[
                        'font-medium',
                        isCompleted
                            ? 'text-gray-500 dark:text-gray-400 line-through'
                            : 'text-gray-900 dark:text-gray-100'
                    ]"
                >
                    {{ task.title }}
                </h4>

                <!-- Description -->
                <p
                    v-if="task.description"
                    class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                >
                    {{ task.description }}
                </p>

                <!-- Meta Info -->
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <!-- Status Badge -->
                    <span
                        :class="[
                            'inline-flex items-center gap-1.5 px-2 py-1 rounded-lg text-xs font-medium',
                            currentStatus.bg,
                            currentStatus.color
                        ]"
                    >
                        <component :is="currentStatus.icon" class="w-3.5 h-3.5" />
                        {{ currentStatus.label }}
                    </span>

                    <!-- Step Reference -->
                    <span
                        v-if="task.step_index !== null && task.step_index !== undefined"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-xs font-medium"
                    >
                        📍 Passo {{ task.step_index + 1 }}
                    </span>

                    <!-- Due Date -->
                    <span
                        v-if="task.due_date"
                        class="text-xs text-gray-500 dark:text-gray-400"
                    >
                        Vencimento: {{ formatDate(task.due_date) }}
                    </span>

                    <!-- Completed Info -->
                    <span
                        v-if="task.completed_at"
                        class="text-xs text-success-600 dark:text-success-400"
                    >
                        Concluída em {{ formatDate(task.completed_at) }}
                    </span>
                </div>
            </div>

            <!-- Delete Button -->
            <button
                @click="handleDelete"
                class="flex-shrink-0 p-2 rounded-lg text-danger-600 dark:text-danger-400 hover:bg-danger-50 dark:hover:bg-danger-900/20 transition-colors opacity-0 group-hover:opacity-100"
                title="Excluir tarefa"
            >
                <TrashIcon class="w-4 h-4" />
            </button>
        </div>
    </div>
</template>
