<script setup lang="ts">
import { computed } from 'vue';
import { BookOpenIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    recommendedAction?: string | string[];
}>();

const emit = defineEmits<{
    createTaskForStep: [stepIndex: number, stepText: string];
}>();

// Parse recommended_action into steps array
const steps = computed(() => {
    if (!props.recommendedAction) return [];

    // If it's already an array, use it directly
    if (Array.isArray(props.recommendedAction)) {
        return props.recommendedAction.filter(step => step && typeof step === 'string' && step.trim());
    }

    // If it's a string, try to parse as JSON first
    if (typeof props.recommendedAction === 'string') {
        try {
            const parsed = JSON.parse(props.recommendedAction);
            if (Array.isArray(parsed)) {
                return parsed.filter(step => step && typeof step === 'string' && step.trim());
            }
        } catch (e) {
            // Not JSON, continue with string parsing
        }

        // Try splitting by newlines
        const lines = props.recommendedAction.split(/\\n|\n/).filter(line => line.trim());
        if (lines.length > 1) return lines;

        // Try splitting by numbered pattern "1. ... 2. ... 3. ..."
        const numberedSteps = props.recommendedAction.split(/(?=\d+\.\s)/).filter(s => s.trim());
        if (numberedSteps.length > 1) {
            return numberedSteps.map(s => s.replace(/^\d+\.\s*/, '').trim()).filter(s => s);
        }

        return [props.recommendedAction];
    }

    return [];
});

function handleCreateTaskForStep(index: number, stepText: string) {
    emit('createTaskForStep', index, stepText);
}
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <!-- Header -->
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                <BookOpenIcon class="w-6 h-6 text-white" />
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                    Passos da Sugestão
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Referência da IA (somente leitura)
                </p>
            </div>
        </div>

        <!-- Steps List -->
        <div v-if="steps.length === 0" class="text-center py-8 bg-gray-50 dark:bg-gray-900 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
            <BookOpenIcon class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-2" />
            <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum passo disponível</p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="(step, index) in steps"
                :key="index"
                class="group relative p-4 rounded-xl bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-900/20 dark:to-purple-900/20 border border-blue-200 dark:border-blue-800"
            >
                <!-- Step Number Badge -->
                <div class="absolute -left-3 -top-3 w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-bold shadow-lg">
                    {{ index + 1 }}
                </div>

                <!-- Step Content -->
                <div class="pl-6">
                    <p class="text-gray-700 dark:text-gray-300 leading-relaxed">
                        {{ step }}
                    </p>

                    <!-- Create Task Button -->
                    <button
                        @click="handleCreateTaskForStep(index, step)"
                        class="mt-3 flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/60 dark:bg-gray-800/60 border border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-400 text-sm font-medium hover:bg-white dark:hover:bg-gray-800 transition-all opacity-0 group-hover:opacity-100"
                    >
                        <PlusIcon class="w-4 h-4" />
                        Criar tarefa para este passo
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
