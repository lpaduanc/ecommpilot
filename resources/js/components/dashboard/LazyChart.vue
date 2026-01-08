<script setup lang="ts">
import { Suspense, ref, computed, watch } from 'vue';
import { useAsyncChartComponent } from '@/composables/useAsyncComponent';

/**
 * Props for LazyChart component
 */
interface Props {
    /**
     * Chart type
     */
    type?: 'line' | 'area' | 'bar' | 'pie' | 'donut' | 'radialBar' | 'scatter' | 'bubble' | 'heatmap' | 'candlestick';

    /**
     * Chart height in pixels
     */
    height?: number | string;

    /**
     * Chart width (auto by default)
     */
    width?: string | number;

    /**
     * Chart options (ApexCharts options object)
     */
    options?: any;

    /**
     * Chart series data
     */
    series?: any[];

    /**
     * Show loading state
     */
    loading?: boolean;

    /**
     * Custom loading message
     */
    loadingMessage?: string;

    /**
     * Empty state message when no data
     */
    emptyMessage?: string;
}

const props = withDefaults(defineProps<Props>(), {
    type: 'line',
    height: 300,
    width: '100%',
    options: () => ({}),
    series: () => [],
    loading: false,
    loadingMessage: 'Carregando gráfico...',
    emptyMessage: 'Nenhum dado disponível',
});

/**
 * Lazy load vue3-apexcharts only when component is rendered
 * This reduces initial bundle size significantly (~500kb)
 */
const VueApexCharts = useAsyncChartComponent(() => import('vue3-apexcharts'));

/**
 * Check if chart has data
 */
const hasData = computed(() => {
    if (!props.series || props.series.length === 0) {
        return false;
    }

    // Check if any series has data
    return props.series.some(s => {
        if (Array.isArray(s.data)) {
            return s.data.length > 0;
        }
        return s > 0; // For simple number values (pie/donut charts)
    });
});

/**
 * Loading state (external loading prop or internal async loading)
 */
const isLoading = computed(() => props.loading);

/**
 * Emit events
 */
const emit = defineEmits<{
    dataPointSelection: [event: any, chartContext: any, config: any];
    legendClick: [chartContext: any, seriesIndex: number, config: any];
}>();

/**
 * Handle chart events
 */
const handleDataPointSelection = (event: any, chartContext: any, config: any) => {
    emit('dataPointSelection', event, chartContext, config);
};

const handleLegendClick = (chartContext: any, seriesIndex: number, config: any) => {
    emit('legendClick', chartContext, seriesIndex, config);
};
</script>

<template>
    <div class="lazy-chart-container">
        <!-- Loading State -->
        <div
            v-if="isLoading"
            class="flex items-center justify-center"
            :style="{ height: typeof height === 'number' ? `${height}px` : height }"
        >
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mb-3"></div>
                <p class="text-gray-600 text-sm">{{ loadingMessage }}</p>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="!hasData"
            class="flex flex-col items-center justify-center text-center p-8"
            :style="{ height: typeof height === 'number' ? `${height}px` : height }"
        >
            <svg
                class="w-16 h-16 text-gray-400 mb-3"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                />
            </svg>
            <p class="text-gray-600 font-medium">{{ emptyMessage }}</p>
            <p class="text-gray-500 text-sm mt-1">
                Os dados do gráfico aparecerão aqui quando disponíveis.
            </p>
        </div>

        <!-- Chart (loaded asynchronously with Suspense) -->
        <Suspense v-else>
            <!-- Default: Show chart when loaded -->
            <template #default>
                <VueApexCharts
                    :type="type"
                    :height="height"
                    :width="width"
                    :options="options"
                    :series="series"
                    @dataPointSelection="handleDataPointSelection"
                    @legendClick="handleLegendClick"
                />
            </template>

            <!-- Fallback: Show while async component is loading -->
            <template #fallback>
                <div
                    class="flex items-center justify-center"
                    :style="{ height: typeof height === 'number' ? `${height}px` : height }"
                >
                    <div class="text-center">
                        <div class="inline-block animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600 mb-2"></div>
                        <p class="text-gray-500 text-xs">Inicializando gráfico...</p>
                    </div>
                </div>
            </template>
        </Suspense>
    </div>
</template>

<style scoped>
.lazy-chart-container {
    width: 100%;
    min-height: 100px;
}

/**
 * Ensure chart renders smoothly
 */
.lazy-chart-container :deep(.apexcharts-canvas) {
    margin: 0 auto;
}

/**
 * Smooth loading transitions
 */
.lazy-chart-container > * {
    transition: opacity 0.3s ease-in-out;
}
</style>
