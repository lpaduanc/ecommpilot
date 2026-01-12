<script setup>
import { computed, ref, onMounted } from 'vue';
import {
    HeartIcon,
    ArrowTrendingUpIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    summary: { type: Object, required: true },
});

const score = computed(() => props.summary?.health_score || 0);
const insight = computed(() => props.summary?.main_insight || '');

const statusLabels = {
    critical: 'Crítico',
    attention: 'Precisa Atenção',
    healthy: 'Saudável',
    excellent: 'Excelente',
};
const status = computed(() => {
    const rawStatus = props.summary?.health_status || 'N/A';
    return statusLabels[rawStatus] || rawStatus;
});
const animatedScore = ref(0);

const scoreColor = computed(() => {
    if (score.value >= 80) return 'success';
    if (score.value >= 60) return 'primary';
    if (score.value >= 40) return 'warning';
    return 'danger';
});

const gradientConfig = {
    success: {
        from: 'from-emerald-400',
        to: 'to-teal-500',
        bg: 'from-emerald-500/10 to-teal-500/10',
        text: 'text-emerald-600',
        stroke: '#10b981',
        glow: 'shadow-emerald-500/20',
    },
    primary: {
        from: 'from-blue-400',
        to: 'to-indigo-500',
        bg: 'from-blue-500/10 to-indigo-500/10',
        text: 'text-blue-600',
        stroke: '#0c87f7',
        glow: 'shadow-blue-500/20',
    },
    warning: {
        from: 'from-amber-400',
        to: 'to-orange-500',
        bg: 'from-amber-500/10 to-orange-500/10',
        text: 'text-amber-600',
        stroke: '#f59e0b',
        glow: 'shadow-amber-500/20',
    },
    danger: {
        from: 'from-rose-400',
        to: 'to-red-500',
        bg: 'from-rose-500/10 to-red-500/10',
        text: 'text-rose-600',
        stroke: '#ef4444',
        glow: 'shadow-rose-500/20',
    },
};

const config = computed(() => gradientConfig[scoreColor.value]);

const circumference = 2 * Math.PI * 54;
const strokeDashoffset = computed(() => {
    return circumference - (animatedScore.value / 100) * circumference;
});

onMounted(() => {
    // Animate the score
    const duration = 1500;
    const start = 0;
    const end = score.value;
    const startTime = performance.now();

    const animate = (currentTime) => {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function (ease-out cubic)
        const eased = 1 - Math.pow(1 - progress, 3);
        animatedScore.value = Math.round(start + (end - start) * eased);

        if (progress < 1) {
            requestAnimationFrame(animate);
        }
    };

    requestAnimationFrame(animate);
});
</script>

<template>
    <div class="relative overflow-hidden bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 lg:p-8">
        <!-- Background Gradient -->
        <div :class="['absolute inset-0 bg-gradient-to-br opacity-30', config.bg]"></div>
        
        <!-- Decorative Elements -->
        <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br opacity-20" :class="[config.from, config.to]"></div>
        
        <div class="relative flex flex-col lg:flex-row items-center gap-6 lg:gap-10">
            <!-- Score Ring -->
            <div class="relative flex-shrink-0">
                <!-- Glow Effect -->
                <div :class="['absolute inset-0 rounded-full blur-2xl opacity-40', config.glow]" :style="{ boxShadow: `0 0 60px ${config.stroke}40` }"></div>
                
                <svg width="140" height="140" class="transform -rotate-90 relative z-10">
                    <!-- Background Ring -->
                    <circle
                        cx="70"
                        cy="70"
                        r="54"
                        fill="none"
                        stroke="#f3f4f6"
                        stroke-width="12"
                    />
                    <!-- Progress Ring -->
                    <circle
                        cx="70"
                        cy="70"
                        r="54"
                        fill="none"
                        :stroke="config.stroke"
                        stroke-width="12"
                        stroke-linecap="round"
                        :stroke-dasharray="circumference"
                        :stroke-dashoffset="strokeDashoffset"
                        class="transition-all duration-1000 ease-out"
                        style="filter: drop-shadow(0 0 8px currentColor);"
                    />
                </svg>
                <div class="absolute inset-0 flex items-center justify-center z-20">
                    <div class="text-center">
                        <span :class="['text-4xl font-display font-bold', config.text]">{{ animatedScore }}</span>
                        <span class="text-sm text-gray-400 block mt-1">de 100</span>
                    </div>
                </div>
            </div>

            <!-- Info -->
            <div class="flex-1 text-center lg:text-left">
                <div class="flex flex-col lg:flex-row items-center gap-3 mb-4">
                    <div class="flex items-center gap-2">
                        <HeartIcon class="w-6 h-6 text-gray-400" />
                        <h3 class="text-xl font-display font-bold text-gray-900">Saúde da Loja</h3>
                    </div>
                    <span
                        :class="[
                            'px-4 py-1.5 rounded-full text-sm font-semibold bg-gradient-to-r text-white shadow-lg',
                            config.from, config.to, config.glow
                        ]"
                    >
                        {{ status }}
                    </span>
                </div>
                
                <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-4">{{ insight }}</p>
                
                <!-- Quick Actions -->
                <div class="flex flex-wrap items-center justify-center lg:justify-start gap-3">
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-900 text-sm text-gray-600">
                        <SparklesIcon class="w-4 h-4 text-primary-500" />
                        <span>Análise atualizada</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-900 text-sm text-gray-600">
                        <ArrowTrendingUpIcon class="w-4 h-4 text-success-500" />
                        <span>Baseado em dados reais</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
