<script setup>
import { ref, onMounted } from 'vue';
import api from '../../services/api';
import BaseCard from '../../components/common/BaseCard.vue';
import LoadingSpinner from '../../components/common/LoadingSpinner.vue';
import {
    BuildingOfficeIcon,
    UsersIcon,
    CreditCardIcon,
    SparklesIcon,
    ChartBarIcon,
} from '@heroicons/vue/24/outline';

const stats = ref(null);
const isLoading = ref(true);

const statCards = [
    { key: 'total_clients', label: 'Total de Clientes', icon: UsersIcon, color: 'primary' },
    { key: 'active_clients', label: 'Clientes Ativos', icon: UsersIcon, color: 'success' },
    { key: 'new_this_month', label: 'Novos Este Mês', icon: ChartBarIcon, color: 'accent' },
    { key: 'total_revenue', label: 'Receita Total', icon: CreditCardIcon, color: 'success', isCurrency: true },
];

async function fetchStats() {
    isLoading.value = true;
    try {
        const response = await api.get('/admin/stats');
        stats.value = response.data;
    } catch {
        // Mock data for now
        stats.value = {
            total_clients: 156,
            active_clients: 142,
            new_this_month: 23,
            total_revenue: 45890.50,
        };
    } finally {
        isLoading.value = false;
    }
}

function formatValue(value, isCurrency = false) {
    if (isCurrency) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        }).format(value || 0);
    }
    return new Intl.NumberFormat('pt-BR').format(value || 0);
}

onMounted(() => {
    fetchStats();
});
</script>

<template>
    <div class="space-y-8">
        <!-- Page Header -->
        <div>
            <h1 class="text-2xl font-display font-bold text-gray-900 flex items-center gap-3">
                <BuildingOfficeIcon class="w-8 h-8 text-primary-500" />
                Painel Administrativo
            </h1>
            <p class="text-gray-500 mt-1">Visão geral da plataforma</p>
        </div>

        <!-- Loading -->
        <div v-if="isLoading" class="flex items-center justify-center py-20">
            <LoadingSpinner size="xl" class="text-primary-500" />
        </div>

        <!-- Stats Grid -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
            <BaseCard
                v-for="card in statCards"
                :key="card.key"
                padding="normal"
                class="relative overflow-hidden"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-1">{{ card.label }}</p>
                        <p class="text-xl sm:text-2xl font-display font-bold text-gray-900">
                            {{ formatValue(stats?.[card.key], card.isCurrency) }}
                        </p>
                    </div>
                    <div :class="[
                        'p-3 rounded-xl',
                        `bg-${card.color}-100`
                    ]">
                        <component :is="card.icon" :class="['w-6 h-6', `text-${card.color}-600`]" />
                    </div>
                </div>
            </BaseCard>
        </div>

        <!-- Recent Activity Placeholder -->
        <BaseCard padding="normal">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Atividade Recente</h3>
            <div class="text-center py-8 text-gray-400">
                <ChartBarIcon class="w-12 h-12 mx-auto mb-2 opacity-50" />
                <p>Dados de atividade serão exibidos aqui</p>
            </div>
        </BaseCard>
    </div>
</template>

