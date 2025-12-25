<script setup>
import { computed } from 'vue';
import LoadingSpinner from './LoadingSpinner.vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (v) => ['primary', 'secondary', 'danger', 'success', 'ghost'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    loading: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: 'button',
    },
    fullWidth: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['click']);

const variantClasses = {
    primary: 'bg-gradient-to-r from-primary-600 to-primary-500 text-white hover:from-primary-700 hover:to-primary-600 focus:ring-primary-500 shadow-lg shadow-primary-500/25 hover:shadow-xl hover:shadow-primary-500/30',
    secondary: 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50 hover:border-gray-300 focus:ring-gray-500',
    danger: 'bg-gradient-to-r from-danger-600 to-danger-500 text-white hover:from-danger-700 hover:to-danger-600 focus:ring-danger-500 shadow-lg shadow-danger-500/25',
    success: 'bg-gradient-to-r from-success-600 to-success-500 text-white hover:from-success-700 hover:to-success-600 focus:ring-success-500 shadow-lg shadow-success-500/25',
    ghost: 'text-gray-600 hover:bg-gray-100 focus:ring-gray-500',
};

const sizeClasses = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2.5 text-sm',
    lg: 'px-6 py-3 text-base',
};

const buttonClasses = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2',
    variantClasses[props.variant],
    sizeClasses[props.size],
    props.fullWidth ? 'w-full' : '',
    (props.disabled || props.loading) ? 'opacity-50 cursor-not-allowed' : '',
]);

function handleClick(event) {
    if (props.disabled || props.loading) {
        event.preventDefault();
        return;
    }
    emit('click', event);
}
</script>

<template>
    <button
        :type="type"
        :class="buttonClasses"
        :disabled="disabled || loading"
        @click="handleClick"
    >
        <LoadingSpinner v-if="loading" size="sm" />
        <slot v-else />
    </button>
</template>

