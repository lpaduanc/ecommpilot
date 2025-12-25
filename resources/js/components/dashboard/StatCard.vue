<script setup>
import { computed } from 'vue';
import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], required: true },
    change: { type: Number, default: null },
    icon: { type: Object, required: true },
    color: { type: String, default: 'primary' },
});

const colorConfig = {
    primary: {
        bg: 'bg-primary-100',
        icon: 'text-primary-600',
        iconBg: 'bg-gradient-to-br from-primary-500 to-primary-600',
        gradient: 'from-primary-500/10 to-transparent',
        glow: 'shadow-primary-500/20',
    },
    secondary: {
        bg: 'bg-secondary-100',
        icon: 'text-secondary-600',
        iconBg: 'bg-gradient-to-br from-secondary-500 to-secondary-600',
        gradient: 'from-secondary-500/10 to-transparent',
        glow: 'shadow-secondary-500/20',
    },
    success: {
        bg: 'bg-success-100',
        icon: 'text-success-600',
        iconBg: 'bg-gradient-to-br from-success-500 to-success-600',
        gradient: 'from-success-500/10 to-transparent',
        glow: 'shadow-success-500/20',
    },
    danger: {
        bg: 'bg-danger-100',
        icon: 'text-danger-600',
        iconBg: 'bg-gradient-to-br from-danger-500 to-danger-600',
        gradient: 'from-danger-500/10 to-transparent',
        glow: 'shadow-danger-500/20',
    },
    accent: {
        bg: 'bg-accent-100',
        icon: 'text-accent-600',
        iconBg: 'bg-gradient-to-br from-accent-500 to-accent-600',
        gradient: 'from-accent-500/10 to-transparent',
        glow: 'shadow-accent-500/20',
    },
};

const config = computed(() => colorConfig[props.color] || colorConfig.primary);

const isPositive = computed(() => props.change > 0);
const isNegative = computed(() => props.change < 0);
const hasChange = computed(() => props.change !== null && props.change !== undefined);
</script>

<template>
    <div class="relative overflow-hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-6 transition-all duration-300 hover:shadow-xl hover:-translate-y-1 group">
        <!-- Background Gradient -->
        <div :class="['absolute inset-0 bg-gradient-to-br opacity-0 group-hover:opacity-100 transition-opacity duration-300', config.gradient]"></div>
        
        <!-- Decorative Element -->
        <div class="absolute -top-10 -right-10 w-32 h-32 rounded-full opacity-10 group-hover:opacity-20 transition-opacity" :class="config.iconBg"></div>
        
        <div class="relative">
            <div class="flex items-start justify-between mb-4">
                <div :class="['p-3 rounded-xl shadow-lg transition-transform duration-300 group-hover:scale-110', config.iconBg]">
                    <component :is="icon" class="w-6 h-6 text-white" />
                </div>
                
                <!-- Change Indicator -->
                <div
                    v-if="hasChange"
                    :class="[
                        'flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold',
                        isPositive 
                            ? 'bg-success-100 text-success-700' 
                            : isNegative 
                                ? 'bg-danger-100 text-danger-700' 
                                : 'bg-gray-100 text-gray-600'
                    ]"
                >
                    <ArrowUpIcon v-if="isPositive" class="w-3.5 h-3.5" />
                    <ArrowDownIcon v-else-if="isNegative" class="w-3.5 h-3.5" />
                    <span>{{ Math.abs(change).toFixed(1) }}%</span>
                </div>
            </div>
            
            <div>
                <p class="text-sm font-medium text-gray-500 mb-2">{{ title }}</p>
                <p class="text-3xl font-display font-bold text-gray-900">{{ value }}</p>
            </div>
        </div>
    </div>
</template>
