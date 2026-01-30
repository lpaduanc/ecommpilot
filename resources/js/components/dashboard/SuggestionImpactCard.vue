<script setup lang="ts">
import { computed } from 'vue';
import { useDashboardStore } from '@/stores/dashboardStore';
import { useFormatters } from '@/composables/useFormatters';
import {
    ChartBarIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    ArrowRightIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';

const dashboardStore = useDashboardStore();
const { formatCurrency, formatPercentage } = useFormatters();

const hasAccess = computed(() => dashboardStore.hasImpactDashboardAccess);
const isLoading = computed(() => dashboardStore.impactDashboardLoading);
const data = computed(() => dashboardStore.impactDashboard);

const summary = computed(() => data.value?.summary);
const trend = computed(() => data.value?.trend_analysis);

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

const trendMessage = computed(() => {
    if (!trend.value?.has_data) return null;
    const interpretation = trend.value.interpretation;
    const messages = {
        significant_improvement: 'Suas métricas aceleraram significativamente!',
        slight_improvement: 'Suas métricas melhoraram desde que você começou a agir.',
        stable: 'Suas métricas estão estáveis.',
        decline: 'Suas métricas precisam de atenção.',
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

function formatNumber(value: number, decimals: number = 0): string {
    return new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(value);
}

function formatPercent(value: number): string {
    return formatPercentage(value);
}
</script>

<template>
    <!-- Card de Upgrade (plano sem acesso) -->
    <div
        v-if="hasAccess === false"
        class="bg-gradient-to-br from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 rounded-2xl p-6 border border-purple-200 dark:border-purple-800"
    >
        <div class="flex items-start gap-4">
            <div class="p-3 bg-purple-100 dark:bg-purple-900/50 rounded-xl">
                <ChartBarIcon class="w-6 h-6 text-purple-600 dark:text-purple-400" />
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    Dashboard de Impacto nas Vendas
                </h3>
                <p class="text-gray-600 dark:text-gray-400 text-sm mb-4">
                    Veja como suas ações nas sugestões estão impactando suas vendas.
                    Disponível no plano Enterprise.
                </p>
                <router-link
                    to="/settings/subscription"
                    class="inline-flex items-center gap-2 text-purple-600 dark:text-purple-400 font-medium hover:underline"
                >
                    Fazer upgrade
                    <ArrowRightIcon class="w-4 h-4" />
                </router-link>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div
        v-else-if="isLoading"
        class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6"
    >
        <div class="animate-pulse">
            <div class="h-6 bg-gray-200 dark:bg-gray-700 rounded w-1/3 mb-4"></div>
            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-6"></div>
            <div class="grid grid-cols-3 gap-4">
                <div class="h-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                <div class="h-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                <div class="h-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
            </div>
        </div>
    </div>

    <!-- Card com Dados -->
    <div
        v-else-if="hasAccess && data"
        class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
    >
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary-50 to-indigo-50 dark:from-primary-900/20 dark:to-indigo-900/20">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-primary-100 dark:bg-primary-900/50 rounded-lg">
                        <ChartBarIcon class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Impacto das Suas Sugestões
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Comparando período antes e depois das suas ações
                        </p>
                    </div>
                </div>
                <div v-if="summary?.has_data" class="flex items-center gap-2 text-sm">
                    <span class="px-2 py-1 bg-primary-100 dark:bg-primary-900/50 text-primary-700 dark:text-primary-300 rounded-full">
                        {{ summary.suggestions_in_progress }} em andamento
                    </span>
                    <span class="px-2 py-1 bg-success-100 dark:bg-success-900/50 text-success-700 dark:text-success-300 rounded-full">
                        {{ summary.suggestions_completed }} concluídas
                    </span>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div v-if="summary?.has_data" class="p-6">
            <!-- Metrics Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <!-- Receita -->
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Receita Diária</p>
                    <div class="flex items-end gap-2">
                        <span class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ formatCurrency(summary.after?.daily_revenue || 0) }}
                        </span>
                        <span
                            v-if="revenueVariation !== null"
                            class="text-sm font-medium mb-1"
                            :class="revenueVariation >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400'"
                        >
                            {{ revenueVariation >= 0 ? '+' : '' }}{{ formatPercent(revenueVariation) }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        Antes: {{ formatCurrency(summary.before?.daily_revenue || 0) }}/dia
                    </p>
                </div>

                <!-- Pedidos -->
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Pedidos Diários</p>
                    <div class="flex items-end gap-2">
                        <span class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ formatNumber(summary.after?.daily_orders || 0, 1) }}
                        </span>
                        <span
                            v-if="ordersVariation !== null"
                            class="text-sm font-medium mb-1"
                            :class="ordersVariation >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400'"
                        >
                            {{ ordersVariation >= 0 ? '+' : '' }}{{ formatPercent(ordersVariation) }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        Antes: {{ formatNumber(summary.before?.daily_orders || 0, 1) }}/dia
                    </p>
                </div>

                <!-- Ticket Médio -->
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50">
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Ticket Médio</p>
                    <div class="flex items-end gap-2">
                        <span class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ formatCurrency(summary.after?.avg_ticket || 0) }}
                        </span>
                        <span
                            v-if="ticketVariation !== null"
                            class="text-sm font-medium mb-1"
                            :class="ticketVariation >= 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400'"
                        >
                            {{ ticketVariation >= 0 ? '+' : '' }}{{ formatPercent(ticketVariation) }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        Antes: {{ formatCurrency(summary.before?.avg_ticket || 0) }}
                    </p>
                </div>
            </div>

            <!-- Trend Message -->
            <div
                v-if="trendMessage"
                class="flex items-center gap-3 p-4 rounded-xl bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-900/30 dark:to-gray-800/30 border border-gray-200 dark:border-gray-700"
            >
                <component :is="trendIcon" class="w-6 h-6" :class="trendColor" />
                <div class="flex-1">
                    <p class="font-medium text-gray-900 dark:text-white">
                        {{ trendMessage }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Tendência anterior: {{ trend?.pre_trend >= 0 ? '+' : '' }}{{ formatPercent(trend?.pre_trend || 0) }} →
                        Atual: {{ trend?.post_trend >= 0 ? '+' : '' }}{{ formatPercent(trend?.post_trend || 0) }}
                    </p>
                </div>
            </div>

            <!-- View Details Button -->
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <router-link
                    to="/impact"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white font-medium transition-colors"
                >
                    Ver Análise Completa
                    <ArrowRightIcon class="w-4 h-4" />
                </router-link>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="p-8 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                <SparklesIcon class="w-8 h-8 text-gray-400" />
            </div>
            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                Comece a agir nas sugestões
            </h4>
            <p class="text-gray-500 dark:text-gray-400 text-sm max-w-md mx-auto mb-4">
                Quando você colocar sugestões em andamento ou concluí-las,
                veremos o impacto nas suas métricas aqui.
            </p>
            <div class="flex items-center justify-center gap-3">
                <router-link
                    to="/suggestions"
                    class="inline-flex items-center gap-2 text-primary-600 dark:text-primary-400 font-medium hover:underline"
                >
                    Ver sugestões
                    <ArrowRightIcon class="w-4 h-4" />
                </router-link>
                <span class="text-gray-300 dark:text-gray-600">•</span>
                <router-link
                    to="/impact"
                    class="inline-flex items-center gap-2 text-gray-600 dark:text-gray-400 font-medium hover:underline"
                >
                    Ver análise completa
                    <ArrowRightIcon class="w-4 h-4" />
                </router-link>
            </div>
        </div>
    </div>
</template>
