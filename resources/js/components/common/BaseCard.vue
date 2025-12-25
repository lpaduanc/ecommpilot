<script setup>
import { computed } from 'vue';

const props = defineProps({
    hoverable: {
        type: Boolean,
        default: false,
    },
    padding: {
        type: String,
        default: 'normal',
        validator: (v) => ['none', 'sm', 'normal', 'lg'].includes(v),
    },
});

const paddingClasses = {
    none: '',
    sm: 'p-4',
    normal: 'p-6',
    lg: 'p-8',
};

const cardClasses = computed(() => [
    'bg-white rounded-2xl border border-gray-100 overflow-hidden',
    paddingClasses[props.padding],
    props.hoverable 
        ? 'shadow-sm transition-all duration-300 hover:shadow-lg hover:shadow-gray-200/50 hover:-translate-y-0.5' 
        : 'shadow-sm',
]);
</script>

<template>
    <div :class="cardClasses">
        <slot />
    </div>
</template>

