<script setup>
import { onMounted, computed } from 'vue';
import { useAnalysisStore } from '../../stores/analysisStore';

const props = defineProps({
    modelValue: { type: String, default: 'general' },
});

const emit = defineEmits(['update:modelValue']);

const analysisStore = useAnalysisStore();

const types = computed(() => analysisStore.analysisTypes);
const isLoading = computed(() => types.value.length === 0);

const typeIcons = {
    general: '📊',
    financial: '💰',
    conversion: '🎯',
    competitors: '🏆',
    campaigns: '📣',
    tracking: '🚚',
};

function selectType(type) {
    if (!type.available) return;
    emit('update:modelValue', type.key);
}

onMounted(() => {
    if (types.value.length === 0) {
        analysisStore.fetchAnalysisTypes();
    }
});
</script>

<template>
    <div>
        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">
            Tipo de Análise
        </label>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="type in types"
                :key="type.key"
                @click="selectType(type)"
                :disabled="!type.available"
                :class="[
                    'relative flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all border',
                    type.available
                        ? (modelValue === type.key
                            ? 'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400 dark:border-primary-500 shadow-sm'
                            : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 cursor-pointer')
                        : 'border-gray-200 bg-white text-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-500 opacity-60 cursor-not-allowed'
                ]"
            >
                <span class="text-base">{{ typeIcons[type.key] || '📊' }}</span>
                <span>{{ type.label }}</span>
                <span
                    v-if="!type.available"
                    class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 ml-1"
                >
                    Em breve
                </span>
            </button>
        </div>
        <p
            v-if="modelValue !== 'general'"
            class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
        >
            {{ types.find(t => t.key === modelValue)?.description }}
        </p>
    </div>
</template>
