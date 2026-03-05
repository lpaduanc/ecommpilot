<script setup lang="ts">
import InfoTooltip from '../common/InfoTooltip.vue';
import { BookOpenIcon } from '@heroicons/vue/20/solid';

defineProps<{
    tooltipText: string;
    tooltipPosition?: string;
    iconClass?: string;
    lightVariant?: boolean;
}>();

defineEmits<{
    (e: 'open-guide'): void;
}>();
</script>

<template>
    <div class="flex items-center gap-2 mt-1">
        <InfoTooltip
            :text="tooltipText"
            :position="tooltipPosition || 'bottom'"
            :icon-class="iconClass"
        />
        <button
            type="button"
            :class="[
                'flex items-center gap-1 text-xs hover:underline transition-colors cursor-pointer',
                lightVariant
                    ? 'text-white/70 hover:text-white'
                    : 'text-primary-500 dark:text-primary-400 hover:text-primary-600 dark:hover:text-primary-300'
            ]"
            @click="$emit('open-guide')"
        >
            <BookOpenIcon class="w-3.5 h-3.5" />
            saiba mais sobre esse módulo da análise
        </button>
    </div>
</template>
