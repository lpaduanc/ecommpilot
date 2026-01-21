<script setup>
import {
    ExclamationTriangleIcon,
    ExclamationCircleIcon,
    InformationCircleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
    alerts: { type: Array, default: () => [] },
});

const dismissedAlerts = ref([]);

// Mapeamento para traduzir tipos de alerta que possam vir com underscore
const alertTypeLabels = {
    // Tipos V3 (AnalystAgentPrompt)
    inventory_management: 'Gestão de Estoque',
    sales_performance: 'Desempenho de Vendas',
    pricing_strategy: 'Estratégia de Preços',
    customer_behavior: 'Comportamento do Cliente',
    order_management: 'Gestão de Pedidos',
    // Tipos legados
    concentracao_vendas: 'Concentração de Vendas',
    estoque_critico: 'Estoque Crítico',
    queda_vendas_recente: 'Queda de Vendas Recente',
    cupons_excessivos: 'Cupons Excessivos',
    sazonalidade_inicio_ano: 'Sazonalidade de Início de Ano',
    dependencia_cupons: 'Dependência de Cupons',
    produtos_estrela: 'Produtos Estrela',
    gestao_estoque: 'Gestão de Estoque',
    cancellation_rate: 'Taxa de Cancelamento',
    refund_rate: 'Taxa de Reembolso',
    inventory_critical: 'Estoque Crítico',
    low_conversion: 'Baixa Conversão',
    high_abandonment: 'Alto Abandono',
    revenue_decline: 'Queda de Receita',
    order_decline: 'Queda de Pedidos',
    ticket_decline: 'Queda de Ticket Médio',
    customer_churn: 'Perda de Clientes',
    stock_out: 'Ruptura de Estoque',
};

function formatAlertTitle(title) {
    if (!title) return 'Alerta';
    // Se o título está no mapeamento, usa o label traduzido
    if (alertTypeLabels[title]) return alertTypeLabels[title];
    // Se contém underscore, transforma em título capitalizado
    if (title.includes('_')) {
        return title.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    }
    return title;
}

const alertConfig = {
    warning: {
        icon: ExclamationTriangleIcon,
        gradient: 'from-amber-500 to-orange-500',
        bg: 'bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/30 dark:to-orange-900/30',
        border: 'border-amber-200/50 dark:border-amber-700/50',
        iconBg: 'bg-gradient-to-br from-amber-400 to-orange-500',
        text: 'text-amber-900 dark:text-amber-200',
        subtext: 'text-amber-700 dark:text-amber-300',
    },
    danger: {
        icon: ExclamationCircleIcon,
        gradient: 'from-rose-500 to-red-500',
        bg: 'bg-gradient-to-r from-rose-50 to-red-50 dark:from-rose-900/30 dark:to-red-900/30',
        border: 'border-rose-200/50 dark:border-rose-700/50',
        iconBg: 'bg-gradient-to-br from-rose-400 to-red-500',
        text: 'text-rose-900 dark:text-rose-200',
        subtext: 'text-rose-700 dark:text-rose-300',
    },
    info: {
        icon: InformationCircleIcon,
        gradient: 'from-blue-500 to-indigo-500',
        bg: 'bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30',
        border: 'border-blue-200/50 dark:border-blue-700/50',
        iconBg: 'bg-gradient-to-br from-blue-400 to-indigo-500',
        text: 'text-blue-900 dark:text-blue-200',
        subtext: 'text-blue-700 dark:text-blue-300',
    },
};

function getConfig(type) {
    // Map info to warning for warm colors only
    if (type === 'info') {
        return alertConfig.warning;
    }
    return alertConfig[type] || alertConfig.warning;
}

function dismissAlert(index) {
    dismissedAlerts.value.push(index);
}

function isVisible(index) {
    return !dismissedAlerts.value.includes(index);
}
</script>

<template>
    <div class="space-y-3">
        <transition-group name="alert">
            <div
                v-for="(alert, index) in alerts"
                :key="index"
                v-show="isVisible(index)"
                :class="[
                    'group relative flex items-start gap-4 p-4 rounded-2xl border backdrop-blur-sm transition-all duration-300 hover:shadow-lg',
                    getConfig(alert.type).bg,
                    getConfig(alert.type).border
                ]"
            >
                <!-- Icon -->
                <div 
                    :class="[
                        'flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center shadow-lg',
                        getConfig(alert.type).iconBg
                    ]"
                >
                    <component
                        :is="getConfig(alert.type).icon"
                        class="w-5 h-5 text-white"
                    />
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <p v-if="alert.title" :class="['font-semibold', getConfig(alert.type).text]">
                        {{ formatAlertTitle(alert.title) }}
                    </p>
                    <p :class="['leading-relaxed', alert.title ? getConfig(alert.type).subtext : 'font-medium ' + getConfig(alert.type).text]">
                        {{ alert.message }}
                    </p>
                </div>

                <!-- Dismiss Button -->
                <button
                    @click.stop="dismissAlert(index)"
                    :class="[
                        'flex-shrink-0 p-1.5 rounded-lg opacity-0 group-hover:opacity-100 transition-all duration-200 hover:bg-white/50 dark:hover:bg-gray-700/50',
                        getConfig(alert.type).subtext
                    ]"
                >
                    <XMarkIcon class="w-4 h-4" />
                </button>

                <!-- Accent Line -->
                <div 
                    :class="[
                        'absolute left-0 top-4 bottom-4 w-1 rounded-r-full bg-gradient-to-b',
                        getConfig(alert.type).gradient
                    ]"
                ></div>
            </div>
        </transition-group>
    </div>
</template>

<style scoped>
.alert-enter-active,
.alert-leave-active {
    transition: all 0.3s ease;
}

.alert-enter-from {
    opacity: 0;
    transform: translateX(-20px);
}

.alert-leave-to {
    opacity: 0;
    transform: translateX(20px);
}
</style>
