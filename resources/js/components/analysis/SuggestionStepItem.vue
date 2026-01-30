<script setup lang="ts">
import { ref, computed } from 'vue';
import { useFormatters } from '@/composables/useFormatters';
import {
    CheckCircleIcon,
    TrashIcon,
    ChatBubbleLeftIcon,
} from '@heroicons/vue/24/outline';
import { CheckCircleIcon as CheckCircleSolidIcon } from '@heroicons/vue/24/solid';
import type { SuggestionStep } from '@/types/analysis';

const props = defineProps<{
    step: SuggestionStep;
    isToggling?: boolean;
}>();

const emit = defineEmits<{
    toggle: [stepId: number];
    delete: [stepId: number];
    comment: [stepId: number];
}>();

const { formatDate } = useFormatters();

const isCompleted = computed(() => props.step.status === 'completed');

function handleToggle() {
    if (!props.isToggling) {
        emit('toggle', props.step.id);
    }
}

function handleDelete() {
    if (confirm('Deseja realmente excluir este passo?')) {
        emit('delete', props.step.id);
    }
}
</script>

<template>
    <div
        :class="[
            'group flex items-start gap-3 p-4 rounded-xl transition-all duration-200',
            isCompleted
                ? 'bg-success-50 dark:bg-success-900/20 border border-success-200 dark:border-success-800'
                : 'bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-primary-300 dark:hover:border-primary-700'
        ]"
    >
        <!-- Checkbox -->
        <button
            @click="handleToggle"
            :disabled="isToggling"
            :class="[
                'flex-shrink-0 w-6 h-6 rounded-md border-2 transition-all duration-200 flex items-center justify-center',
                isToggling && 'opacity-50 cursor-not-allowed',
                isCompleted
                    ? 'bg-success-500 border-success-500'
                    : 'border-gray-300 dark:border-gray-600 hover:border-primary-500 dark:hover:border-primary-400'
            ]"
        >
            <CheckCircleSolidIcon
                v-if="isCompleted"
                class="w-5 h-5 text-white"
            />
        </button>

        <!-- Content -->
        <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
                <div class="flex-1">
                    <p
                        :class="[
                            'text-sm font-medium transition-colors',
                            isCompleted
                                ? 'text-gray-500 dark:text-gray-400 line-through'
                                : 'text-gray-900 dark:text-gray-100'
                        ]"
                    >
                        {{ step.title }}
                    </p>
                    <p
                        v-if="step.description"
                        :class="[
                            'text-xs mt-1',
                            isCompleted
                                ? 'text-gray-400 dark:text-gray-500'
                                : 'text-gray-600 dark:text-gray-400'
                        ]"
                    >
                        {{ step.description }}
                    </p>
                </div>

                <!-- Badges and Actions -->
                <div class="flex items-center gap-2">
                    <!-- Custom Badge -->
                    <span
                        v-if="step.is_custom"
                        class="px-2 py-0.5 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 text-xs font-medium"
                    >
                        Customizado
                    </span>

                    <!-- Delete Button (only for custom steps) -->
                    <button
                        v-if="step.is_custom"
                        @click="handleDelete"
                        class="opacity-0 group-hover:opacity-100 p-1 rounded hover:bg-danger-100 dark:hover:bg-danger-900/30 text-danger-600 dark:text-danger-400 transition-all"
                        title="Excluir passo"
                    >
                        <TrashIcon class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Completion Info -->
            <div
                v-if="isCompleted && step.completed_at"
                class="flex items-center gap-1 mt-2 text-xs text-success-700 dark:text-success-400"
            >
                <CheckCircleIcon class="w-3.5 h-3.5" />
                <span>
                    Concluído em {{ formatDate(step.completed_at) }}
                    <span v-if="step.completedBy">
                        por {{ step.completedBy.name }}
                    </span>
                </span>
            </div>
        </div>
    </div>
</template>
