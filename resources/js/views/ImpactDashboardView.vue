<script setup lang="ts">
import { onMounted, computed, ref } from 'vue';
import { useDashboardStore } from '@/stores/dashboardStore';
import { useFormatters } from '@/composables/useFormatters';
import VueApexCharts from 'vue3-apexcharts';
import {
    ChartBarIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    SparklesIcon,
    ArrowRightIcon,
    CheckCircleIcon,
    PlayIcon,
    LockClosedIcon,
} from '@heroicons/vue/24/outline';
import BaseCard from '@/components/common/BaseCard.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';

const dashboardStore = useDashboardStore();
const { formatCurrency, formatPercentage, formatDate } = useFormatters();

const hasAccess = computed(() => dashboardStore.hasImpactDashboardAccess);
const isLoading = computed(() => dashboardStore.impactDashboardLoading);
const data = computed(() => dashboardStore.impactDashboard);

const summary = computed(() => data.value?.summary);
const trend = computed(() => data.value?.trend_analysis);
const byCategory = computed(() => data.value?.by_category || []);
const timeline = computed(() => data.value?.timeline || { suggestions: [], daily_metrics: [] });

// Category configuration (reusing from SuggestionCard)
const categoryConfig = {
    strategy: { icon: '🎯', label: 'Estratégia', color: 'from-rose-500 to-red-500', bg: 'bg-rose-50 dark:bg-rose-900/30', text: 'text-rose-700 dark:text-rose-400' },
    investment: { icon: '💎', label: 'Investimento', color: 'from-cyan-500 to-blue-500', bg: 'bg-cyan-50 dark:bg-cyan-900/30', text: 'text-cyan-700 dark:text-cyan-400' },
    market: { icon: '🌍', label: 'Mercado', color: 'from-teal-500 to-emerald-500', bg: 'bg-teal-50 dark:bg-teal-900/30', text: 'text-teal-700 dark:text-teal-400' },
    growth: { icon: '📈', label: 'Crescimento', color: 'from-lime-500 to-green-500', bg: 'bg-lime-50 dark:bg-lime-900/30', text: 'text-lime-700 dark:text-lime-400' },
    financial: { icon: '💵', label: 'Financeiro', color: 'from-yellow-500 to-amber-500', bg: 'bg-yellow-50 dark:bg-yellow-900/30', text: 'text-yellow-700 dark:text-yellow-400' },
    positioning: { icon: '🏆', label: 'Posicionamento', color: 'from-fuchsia-500 to-purple-500', bg: 'bg-fuchsia-50 dark:bg-fuchsia-900/30', text: 'text-fuchsia-700 dark:text-fuchsia-400' },
    marketing: { icon: '📣', label: 'Marketing', color: 'from-pink-500 to-rose-500', bg: 'bg-pink-50 dark:bg-pink-900/30', text: 'text-pink-700 dark:text-pink-400' },
    pricing: { icon: '💰', label: 'Precificação', color: 'from-amber-500 to-yellow-500', bg: 'bg-amber-50 dark:bg-amber-900/30', text: 'text-amber-700 dark:text-amber-400' },
    inventory: { icon: '📦', label: 'Estoque', color: 'from-sky-500 to-cyan-500', bg: 'bg-sky-50 dark:bg-sky-900/30', text: 'text-sky-700 dark:text-sky-400' },
    product: { icon: '🛍️', label: 'Produtos', color: 'from-violet-500 to-purple-500', bg: 'bg-violet-50 dark:bg-violet-900/30', text: 'text-violet-700 dark:text-violet-400' },
    customer: { icon: '👥', label: 'Clientes', color: 'from-emerald-500 to-teal-500', bg: 'bg-emerald-50 dark:bg-emerald-900/30', text: 'text-emerald-700 dark:text-emerald-400' },
    conversion: { icon: '🔄', label: 'Conversão', color: 'from-orange-500 to-red-500', bg: 'bg-orange-50 dark:bg-orange-900/30', text: 'text-orange-700 dark:text-orange-400' },
    coupon: { icon: '🏷️', label: 'Cupons', color: 'from-indigo-500 to-blue-500', bg: 'bg-indigo-50 dark:bg-indigo-900/30', text: 'text-indigo-700 dark:text-indigo-400' },
    operational: { icon: '⚙️', label: 'Operacional', color: 'from-slate-500 to-gray-500', bg: 'bg-slate-50 dark:bg-slate-900/30', text: 'text-slate-700 dark:text-slate-400' },
};

// Variations
const revenueVariation = computed(() => {
    if (!summary.value?.has_data || !summary.value.before || !summary.value.after) return null;
    const before = summary.value.before.daily_revenue;
    const after = summary.value.after.daily_revenue;
    if (before === 0) return null;
    return ((after - before) / before) * 100;
});

const ordersVariation = computed(() => {
    if (!summary.value?.has_data || !summary.value.before || !summary.value.after) return null;
    const before = summary.value.before.daily_orders;
    const after = summary.value.after.daily_orders;
    if (before === 0) return null;
    return ((after - before) / before) * 100;
});

const ticketVariation = computed(() => {
    if (!summary.value?.has_data || !summary.value.before || !summary.value.after) return null;
    const before = summary.value.before.avg_ticket;
    const after = summary.value.after.avg_ticket;
    if (before === 0) return null;
    return ((after - before) / before) * 100;
});

// Trend interpretation
const trendMessage = computed(() => {
    if (!trend.value?.has_data) return null;
    const interpretation = trend.value.interpretation;
    const messages = {
        significant_improvement: 'Excelente! Suas métricas aceleraram significativamente após implementar as sugestões.',
        slight_improvement: 'Parabéns! Suas métricas melhoraram desde que você começou a agir nas sugestões.',
        stable: 'Suas métricas estão estáveis. Continue implementando as sugestões para ver melhores resultados.',
        decline: 'Atenção! Suas métricas precisam de atenção. Revise as sugestões e priorize as de alto impacto.',
    };
    return messages[interpretation] || null;
});

const trendIcon = computed(() => {
    if (!trend.value?.has_data) return null;
    return trend.value.acceleration >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon;
});

const trendColor = computed(() => {
    if (!trend.value?.has_data) return '';
    if (trend.value.interpretation === 'significant_improvement') return 'text-success-600 dark:text-success-400';
    if (trend.value.interpretation === 'slight_improvement') return 'text-success-500 dark:text-success-500';
    if (trend.value.interpretation === 'stable') return 'text-gray-500';
    return 'text-warning-600 dark:text-warning-400';
});

const trendBgColor = computed(() => {
    if (!trend.value?.has_data) return '';
    if (trend.value.interpretation === 'significant_improvement') return 'bg-success-50 dark:bg-success-900/20 border-success-200 dark:border-success-800';
    if (trend.value.interpretation === 'slight_improvement') return 'bg-success-50 dark:bg-success-900/20 border-success-200 dark:border-success-800';
    if (trend.value.interpretation === 'stable') return 'bg-gray-50 dark:bg-gray-900/20 border-gray-200 dark:border-gray-800';
    return 'bg-warning-50 dark:bg-warning-900/20 border-warning-200 dark:border-warning-800';
});

// Chart data
const timelineChart = computed(() => {
    const dailyMetrics = timeline.value.daily_metrics || [];
    const suggestions = timeline.value.suggestions || [];

    // Ensure we have data
    if (!dailyMetrics || dailyMetrics.length === 0) {
        return {
            series: [],
            categories: [],
            annotations: [],
        };
    }

    const categories = dailyMetrics.map((item: any) => formatDate(item.date));
    const revenueData = dailyMetrics.map((item: any) => item.revenue || 0);
    const ordersData = dailyMetrics.map((item: any) => item.orders || 0);

    // Create annotations for suggestions
    const annotations: any[] = [];
    suggestions.forEach((suggestion: any) => {
        if (suggestion.in_progress_at) {
            const dateIndex = dailyMetrics.findIndex((m: any) => m.date === suggestion.in_progress_at.split('T')[0]);
            if (dateIndex !== -1) {
                annotations.push({
                    x: categories[dateIndex],
                    borderColor: '#3b82f6',
                    label: {
                        text: '▶ Iniciada',
                        style: { color: '#fff', background: '#3b82f6' },
                    },
                });
            }
        }
        if (suggestion.completed_at) {
            const dateIndex = dailyMetrics.findIndex((m: any) => m.date === suggestion.completed_at.split('T')[0]);
            if (dateIndex !== -1) {
                annotations.push({
                    x: categories[dateIndex],
                    borderColor: '#10b981',
                    label: {
                        text: '✓ Concluída',
                        style: { color: '#fff', background: '#10b981' },
                    },
                });
            }
        }
    });

    return {
        series: [
            { name: 'Receita', data: revenueData, type: 'line' },
            { name: 'Pedidos', data: ordersData, type: 'line' },
        ],
        categories,
        annotations,
    };
});

const chartOptions = computed(() => ({
    chart: {
        type: 'line',
        height: 400,
        toolbar: { show: true },
        zoom: { enabled: true },
        fontFamily: 'DM Sans, sans-serif',
    },
    stroke: {
        curve: 'smooth',
        width: [3, 2],
    },
    colors: ['#0c87f7', '#10b981'],
    xaxis: {
        categories: timelineChart.value.categories,
        labels: {
            style: { colors: '#9ca3af', fontSize: '11px' },
            rotate: -45,
            rotateAlways: false,
        },
    },
    yaxis: [
        {
            title: { text: 'Receita (R$)', style: { color: '#0c87f7' } },
            labels: {
                style: { colors: '#9ca3af' },
                formatter: (value: number) => {
                    return new Intl.NumberFormat('pt-BR', {
                        style: 'currency',
                        currency: 'BRL',
                        notation: 'compact',
                    }).format(value);
                },
            },
        },
        {
            opposite: true,
            title: { text: 'Pedidos', style: { color: '#10b981' } },
            labels: {
                style: { colors: '#9ca3af' },
                formatter: (value: number) => Math.round(value),
            },
        },
    ],
    tooltip: {
        shared: true,
        intersect: false,
        y: [
            {
                formatter: (value: number) => {
                    return new Intl.NumberFormat('pt-BR', {
                        style: 'currency',
                        currency: 'BRL',
                    }).format(value);
                },
            },
            {
                formatter: (value: number) => `${Math.round(value)} pedidos`,
            },
        ],
    },
    annotations: {
        xaxis: timelineChart.value.annotations,
    },
    grid: {
        borderColor: '#f3f4f6',
        strokeDashArray: 4,
    },
    legend: {
        position: 'top',
        horizontalAlign: 'left',
    },
}));

function formatNumber(value: number, decimals: number = 0): string {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(value);
}

function getCategoryLabel(category: string): string {
    return categoryConfig[category as keyof typeof categoryConfig]?.label || category;
}

function getCategoryIcon(category: string): string {
    return categoryConfig[category as keyof typeof categoryConfig]?.icon || '💡';
}

function getCategoryBg(category: string): string {
    return categoryConfig[category as keyof typeof categoryConfig]?.bg || 'bg-gray-50';
}

function getCategoryText(category: string): string {
    return categoryConfig[category as keyof typeof categoryConfig]?.text || 'text-gray-700';
}

function getStatusBadge(status: string) {
    const badges: Record<string, { label: string; color: string; icon: any }> = {
        new: { label: 'Nova', color: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300', icon: SparklesIcon },
        accepted: { label: 'Aceita', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400', icon: CheckCircleIcon },
        in_progress: { label: 'Em Andamento', color: 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400', icon: PlayIcon },
        completed: { label: 'Concluída', color: 'bg-success-100 text-success-700 dark:bg-success-900/30 dark:text-success-400', icon: CheckCircleIcon },
    };
    return badges[status] || badges.new;
}

onMounted(() => {
    dashboardStore.fetchImpactDashboard();
});
</script>

<template>
    <div class="min-h-screen -m-4 sm:-m-6 lg:-m-8 -mt-4 sm:-mt-6 lg:-mt-8">
        <!-- Upgrade Required State -->
        <div v-if="hasAccess === false" class="min-h-screen bg-gradient-to-b from-gray-50 to-white dark:from-gray-900 dark:to-gray-950 flex items-center justify-center px-4">
            <div class="max-w-2xl mx-auto text-center">
                <!-- Icon -->
                <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-gradient-to-br from-purple-100 to-indigo-100 dark:from-purple-900/30 dark:to-indigo-900/30 flex items-center justify-center">
                    <LockClosedIcon class="w-12 h-12 text-purple-600 dark:text-purple-400" />
                </div>

                <!-- Title -->
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">
                    Dashboard de Impacto nas Vendas
                </h1>

                <!-- Description -->
                <p class="text-lg text-gray-600 dark:text-gray-400 mb-8">
                    Veja em tempo real como suas ações nas sugestões estão impactando suas vendas.
                </p>

                <!-- Features List -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8 text-left">
                    <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30 flex items-center justify-center flex-shrink-0">
                                <ChartBarIcon class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Métricas Detalhadas</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Compare receita, pedidos e ticket médio antes e depois das suas ações.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-success-100 to-success-200 dark:from-success-900/30 dark:to-success-800/30 flex items-center justify-center flex-shrink-0">
                                <ArrowTrendingUpIcon class="w-5 h-5 text-success-600 dark:text-success-400" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Análise de Tendência</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Descubra se suas métricas estão acelerando ou desacelerando.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-100 to-purple-200 dark:from-purple-900/30 dark:to-purple-800/30 flex items-center justify-center flex-shrink-0">
                                <SparklesIcon class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Gráficos Interativos</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Visualize a evolução temporal com marcadores de sugestões implementadas.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-amber-100 to-amber-200 dark:from-amber-900/30 dark:to-amber-800/30 flex items-center justify-center flex-shrink-0">
                                <ChartBarIcon class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Impacto por Categoria</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Veja quais categorias de sugestões você mais implementou.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CTA -->
                <div class="bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-2xl p-6 border border-purple-200 dark:border-purple-800">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Este recurso está disponível no plano <strong class="text-purple-600 dark:text-purple-400">Enterprise</strong>
                    </p>
                    <router-link
                        to="/settings/subscription"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-medium transition-all shadow-lg shadow-purple-500/30"
                    >
                        Fazer Upgrade Agora
                        <ArrowRightIcon class="w-4 h-4" />
                    </router-link>
                </div>
            </div>
        </div>

        <!-- Loading State -->
        <div v-else-if="isLoading" class="min-h-screen bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 flex items-center justify-center">
            <div class="text-center">
                <LoadingSpinner size="xl" />
                <p class="text-gray-500 dark:text-gray-400 mt-4">Carregando dados de impacto...</p>
            </div>
        </div>

        <!-- Main Content -->
        <div v-else-if="hasAccess && data">
            <!-- Hero Header -->
            <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
                <!-- Background Elements -->
                <div class="absolute inset-0 overflow-hidden">
                    <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 dark:bg-primary-500/10 rounded-full blur-3xl"></div>
                    <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 dark:bg-secondary-500/10 rounded-full blur-3xl"></div>
                    <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
                </div>

                <div class="relative z-10 max-w-7xl mx-auto">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/30">
                            <ChartBarIcon class="w-7 h-7 text-white" />
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-display font-bold text-white">
                                Impacto das Suas Sugestões
                            </h1>
                            <p v-if="summary?.period" class="text-primary-200/80 dark:text-gray-400 text-sm lg:text-base mt-1">
                                Analisando {{ summary.period.before.start }} a {{ summary.period.after.end }}
                            </p>
                        </div>
                    </div>

                    <!-- Badges -->
                    <div v-if="summary?.has_data" class="flex items-center gap-3 mt-4">
                        <div class="px-4 py-2 bg-primary-500/20 backdrop-blur-sm rounded-full text-white text-sm font-medium">
                            {{ summary.suggestions_in_progress }} sugestões em andamento
                        </div>
                        <div class="px-4 py-2 bg-success-500/20 backdrop-blur-sm rounded-full text-white text-sm font-medium">
                            {{ summary.suggestions_completed }} concluídas
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950">
                <div class="max-w-7xl mx-auto space-y-6">
                    <!-- Empty State -->
                    <div v-if="!summary?.has_data" class="text-center py-16">
                        <BaseCard>
                            <div class="py-12">
                                <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-purple-100 to-indigo-100 dark:from-purple-900/30 dark:to-indigo-900/30 flex items-center justify-center">
                                    <SparklesIcon class="w-10 h-10 text-purple-600 dark:text-purple-400" />
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">
                                    Comece a agir nas sugestões
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400 max-w-md mx-auto mb-6">
                                    Quando você colocar sugestões em andamento ou concluí-las,
                                    mostraremos aqui o impacto real nas suas métricas de vendas.
                                </p>
                                <router-link
                                    to="/suggestions"
                                    class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-primary-600 hover:bg-primary-700 text-white font-medium transition-colors"
                                >
                                    Ver Sugestões
                                    <ArrowRightIcon class="w-4 h-4" />
                                </router-link>
                            </div>
                        </BaseCard>
                    </div>

                    <!-- Main Content -->
                    <template v-else>
                        <!-- Section 1: Metric Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-6">
                            <!-- Revenue Card -->
                            <BaseCard>
                                <div class="p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Receita Diária</h3>
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30 flex items-center justify-center">
                                            <span class="text-xl">💰</span>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <div class="text-3xl font-bold text-gray-900 dark:text-white">
                                            {{ formatCurrency(summary.after?.daily_revenue || 0) }}
                                        </div>
                                        <div v-if="revenueVariation !== null" class="flex items-center gap-2 mt-2">
                                            <component
                                                :is="revenueVariation >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon"
                                                class="w-5 h-5"
                                                :class="revenueVariation >= 0 ? 'text-success-600' : 'text-danger-600'"
                                            />
                                            <span
                                                class="text-lg font-semibold"
                                                :class="revenueVariation >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400'"
                                            >
                                                {{ revenueVariation >= 0 ? '+' : '' }}{{ formatPercentage(revenueVariation) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Antes: {{ formatCurrency(summary.before?.daily_revenue || 0) }}/dia
                                        </p>
                                    </div>
                                </div>
                            </BaseCard>

                            <!-- Orders Card -->
                            <BaseCard>
                                <div class="p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Pedidos Diários</h3>
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-success-100 to-success-200 dark:from-success-900/30 dark:to-success-800/30 flex items-center justify-center">
                                            <span class="text-xl">📦</span>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <div class="text-3xl font-bold text-gray-900 dark:text-white">
                                            {{ formatNumber(summary.after?.daily_orders || 0, 1) }}
                                        </div>
                                        <div v-if="ordersVariation !== null" class="flex items-center gap-2 mt-2">
                                            <component
                                                :is="ordersVariation >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon"
                                                class="w-5 h-5"
                                                :class="ordersVariation >= 0 ? 'text-success-600' : 'text-danger-600'"
                                            />
                                            <span
                                                class="text-lg font-semibold"
                                                :class="ordersVariation >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400'"
                                            >
                                                {{ ordersVariation >= 0 ? '+' : '' }}{{ formatPercentage(ordersVariation) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Antes: {{ formatNumber(summary.before?.daily_orders || 0, 1) }}/dia
                                        </p>
                                    </div>
                                </div>
                            </BaseCard>

                            <!-- Ticket Card -->
                            <BaseCard>
                                <div class="p-6">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Ticket Médio</h3>
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-100 to-purple-200 dark:from-purple-900/30 dark:to-purple-800/30 flex items-center justify-center">
                                            <span class="text-xl">🎫</span>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <div class="text-3xl font-bold text-gray-900 dark:text-white">
                                            {{ formatCurrency(summary.after?.avg_ticket || 0) }}
                                        </div>
                                        <div v-if="ticketVariation !== null" class="flex items-center gap-2 mt-2">
                                            <component
                                                :is="ticketVariation >= 0 ? ArrowTrendingUpIcon : ArrowTrendingDownIcon"
                                                class="w-5 h-5"
                                                :class="ticketVariation >= 0 ? 'text-success-600' : 'text-danger-600'"
                                            />
                                            <span
                                                class="text-lg font-semibold"
                                                :class="ticketVariation >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400'"
                                            >
                                                {{ ticketVariation >= 0 ? '+' : '' }}{{ formatPercentage(ticketVariation) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="pt-3 border-t border-gray-200 dark:border-gray-700">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Antes: {{ formatCurrency(summary.before?.avg_ticket || 0) }}
                                        </p>
                                    </div>
                                </div>
                            </BaseCard>
                        </div>

                        <!-- Section 2: Timeline Chart -->
                        <BaseCard v-if="timelineChart.series.length > 0">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center">
                                        <ChartBarIcon class="w-4 h-4 text-white" />
                                    </div>
                                    Evolução Temporal das Métricas
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                                    Os marcadores indicam quando você iniciou ou concluiu sugestões
                                </p>
                                <VueApexCharts
                                    type="line"
                                    height="400"
                                    :options="chartOptions"
                                    :series="timelineChart.series"
                                />
                            </div>
                        </BaseCard>

                        <!-- Section 3: Trend Analysis -->
                        <BaseCard v-if="trend?.has_data">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-success-500 to-success-600 flex items-center justify-center">
                                        <ArrowTrendingUpIcon class="w-4 h-4 text-white" />
                                    </div>
                                    Análise de Tendência
                                </h3>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Tendência Antes</p>
                                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                            {{ trend.pre_trend >= 0 ? '+' : '' }}{{ formatPercentage(trend.pre_trend) }}
                                        </div>
                                    </div>
                                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Tendência Após Ações</p>
                                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                            {{ trend.post_trend >= 0 ? '+' : '' }}{{ formatPercentage(trend.post_trend) }}
                                        </div>
                                    </div>
                                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50">
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Aceleração</p>
                                        <div
                                            class="text-2xl font-bold"
                                            :class="trend.acceleration >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400'"
                                        >
                                            {{ trend.acceleration >= 0 ? '+' : '' }}{{ formatPercentage(trend.acceleration) }}
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="flex items-start gap-4 p-4 rounded-xl border"
                                    :class="trendBgColor"
                                >
                                    <component :is="trendIcon" class="w-8 h-8 flex-shrink-0 mt-1" :class="trendColor" />
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white text-lg mb-2">
                                            {{ trendMessage }}
                                        </p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            A aceleração mede a diferença entre a tendência antes e depois das suas ações.
                                            Valores positivos indicam que suas métricas estão melhorando mais rápido do que antes.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </BaseCard>

                        <!-- Section 4: Impact by Category -->
                        <BaseCard v-if="byCategory.length > 0">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center">
                                        <SparklesIcon class="w-4 h-4 text-white" />
                                    </div>
                                    Impacto por Categoria
                                </h3>

                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                    <div
                                        v-for="cat in byCategory"
                                        :key="cat.category"
                                        class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:shadow-lg transition-all"
                                    >
                                        <div class="flex items-center gap-3 mb-3">
                                            <div :class="['w-10 h-10 rounded-lg flex items-center justify-center text-lg', getCategoryBg(cat.category)]">
                                                {{ getCategoryIcon(cat.category) }}
                                            </div>
                                            <h4 :class="['font-semibold text-sm', getCategoryText(cat.category)]">
                                                {{ getCategoryLabel(cat.category) }}
                                            </h4>
                                        </div>
                                        <div class="space-y-2">
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-500 dark:text-gray-400">Total:</span>
                                                <span class="font-medium text-gray-900 dark:text-white">{{ cat.count }}</span>
                                            </div>
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-500 dark:text-gray-400">Em andamento:</span>
                                                <span class="font-medium text-primary-600 dark:text-primary-400">{{ cat.in_progress }}</span>
                                            </div>
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-500 dark:text-gray-400">Concluídas:</span>
                                                <span class="font-medium text-success-600 dark:text-success-400">{{ cat.completed }}</span>
                                            </div>
                                            <div v-if="cat.successful > 0" class="flex justify-between text-sm pt-2 border-t border-gray-200 dark:border-gray-700">
                                                <span class="text-gray-500 dark:text-gray-400">Taxa de sucesso:</span>
                                                <span class="font-medium text-success-600 dark:text-success-400">
                                                    {{ Math.round((cat.successful / cat.completed) * 100) }}%
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </BaseCard>

                        <!-- Section 5: Timeline of Suggestions -->
                        <BaseCard v-if="timeline.suggestions && timeline.suggestions.length > 0">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-6 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center">
                                        <CheckCircleIcon class="w-4 h-4 text-white" />
                                    </div>
                                    Linha do Tempo das Sugestões
                                </h3>

                                <div class="space-y-3">
                                    <div
                                        v-for="suggestion in timeline.suggestions"
                                        :key="suggestion.id"
                                        class="flex items-start gap-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 hover:bg-gray-100 dark:hover:bg-gray-900/70 transition-colors"
                                    >
                                        <!-- Icon -->
                                        <div :class="['w-10 h-10 rounded-lg flex items-center justify-center text-lg flex-shrink-0', getCategoryBg(suggestion.category)]">
                                            {{ getCategoryIcon(suggestion.category) }}
                                        </div>

                                        <!-- Content -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <h4 class="font-semibold text-gray-900 dark:text-white mb-1">
                                                        {{ suggestion.title }}
                                                    </h4>
                                                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                        <span :class="getCategoryText(suggestion.category)">
                                                            {{ getCategoryLabel(suggestion.category) }}
                                                        </span>
                                                        <span>•</span>
                                                        <span v-if="suggestion.in_progress_at">
                                                            Iniciada em {{ formatDate(suggestion.in_progress_at) }}
                                                        </span>
                                                        <span v-if="suggestion.completed_at">
                                                            • Concluída em {{ formatDate(suggestion.completed_at) }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div
                                                    :class="['px-2.5 py-1 rounded-full text-xs font-medium flex-shrink-0', getStatusBadge(suggestion.status).color]"
                                                >
                                                    {{ getStatusBadge(suggestion.status).label }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </BaseCard>

                        <!-- Footer Disclaimer -->
                        <div class="text-center py-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                💡 <strong>Nota importante:</strong> Estas métricas refletem a evolução geral da sua loja no período analisado.
                                O impacto pode ser influenciado por múltiplos fatores além das sugestões implementadas.
                            </p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
