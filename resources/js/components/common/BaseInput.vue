<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    type: {
        type: String,
        default: 'text',
    },
    label: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    hint: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    icon: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue']);

const inputClasses = computed(() => [
    'w-full px-4 py-2.5 rounded-lg border bg-white dark:bg-gray-800 dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 transition-all duration-200 focus:outline-none focus:ring-2',
    props.icon ? 'pl-11' : '',
    props.error
        ? 'border-danger-500 focus:border-danger-500 focus:ring-danger-500/20'
        : 'border-gray-200 dark:border-gray-700 dark:border-gray-600 focus:border-primary-500 dark:focus:border-primary-400 focus:ring-primary-500/20',
    props.disabled ? 'bg-gray-50 dark:bg-gray-900 dark:bg-gray-800 cursor-not-allowed opacity-70' : '',
]);

function handleInput(event) {
    emit('update:modelValue', event.target.value);
}
</script>

<template>
    <div class="space-y-1.5">
        <label v-if="label" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ label }}
            <span v-if="required" class="text-danger-500">*</span>
        </label>
        
        <div class="relative">
            <component
                v-if="icon"
                :is="icon"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
            />
            <input
                :type="type"
                :value="modelValue"
                :placeholder="placeholder"
                :disabled="disabled"
                :required="required"
                :class="inputClasses"
                @input="handleInput"
            />
        </div>
        
        <p v-if="error" class="text-sm text-danger-500">{{ error }}</p>
        <p v-else-if="hint" class="text-sm text-gray-500 dark:text-gray-400">{{ hint }}</p>
    </div>
</template>

