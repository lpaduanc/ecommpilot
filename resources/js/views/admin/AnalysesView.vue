<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useAdminAnalysesStore } from '../../stores/adminAnalysesStore';
import { useNotificationStore } from '../../stores/notificationStore';
import BaseCard from '../../components/common/BaseCard.vue';
import BaseButton from '../../components/common/BaseButton.vue';
import BaseInput from '../../components/common/BaseInput.vue';
import LoadingSpinner from '../../components/common/LoadingSpinner.vue';
import AnalysisDetailModal from '../../components/admin/AnalysisDetailModal.vue';
import {
    DocumentChartBarIcon,
    MagnifyingGlassIcon,
    XMarkIcon,
    CheckCircleIcon,
    ClockIcon,
    ExclamationCircleIcon,
    ChartBarIcon,
} from '@heroicons/vue/24/outline';

const adminAnalysesStore = useAdminAnalysesStore();
const notificationStore = useNotificationStore();

// Local state
const searchInput = ref('');
const selectedStatus = ref('');
const selectedStoreId = ref('');
const selectedUserId = ref('');
const dateFrom = ref('');
const dateTo = ref('');

// Modal state
const showDetailModal = ref(false);
const selectedAnalysisId = ref(null);

// Status options
const statusOptions = [
    { value: '', label: 'Todos os status' },
    { value: 'pending', label: 'Pendente' },
    { value: 'processing', label: 'Processando' },
    { value: 'completed', label: 'Completa' },
    { value: 'failed', label: 'Falhou' },
];

// Computed
const hasActiveFilters = computed(() => adminAnalysesStore.hasFilters);

// Methods
function applyFilters() {
    adminAnalysesStore.setFilter('search', searchInput.value);
    adminAnalysesStore.setFilter('status', selectedStatus.value || null);
    adminAnalysesStore.setFilter('store_id', selectedStoreId.value || null);
    adminAnalysesStore.setFilter('user_id', selectedUserId.value || null);
    adminAnalysesStore.setFilter('date_from', dateFrom.value || null);
    adminAnalysesStore.setFilter('date_to', dateTo.value || null);
    adminAnalysesStore.fetchAnalyses(1);
    adminAnalysesStore.fetchStats();
}

function clearFilters() {
    searchInput.value = '';
    selectedStatus.value = '';
    selectedStoreId.value = '';
    selectedUserId.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    adminAnalysesStore.clearFilters();
    adminAnalysesStore.fetchAnalyses(1);
    adminAnalysesStore.fetchStats();
}

async function openDetailModal(analysisId) {
    selectedAnalysisId.value = analysisId;
    showDetailModal.value = true;
    await adminAnalysesStore.fetchAnalysis(analysisId);
}

function closeDetailModal() {
    showDetailModal.value = false;
    selectedAnalysisId.value = null;
    adminAnalysesStore.resetCurrentAnalysis();
}

function changePage(page) {
    if (page < 1 || page > adminAnalysesStore.pagination.last_page) return;
    adminAnalysesStore.fetchAnalyses(page);
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getStatusBadgeClass(status) {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        case 'processing':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
        case 'failed':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
    }
}

function getHealthScoreColor(score) {
    if (!score) return 'text-gray-400';
    if (score >= 70) return 'text-green-600 dark:text-green-400';
    if (score >= 40) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
}

onMounted(async () => {
    await Promise.all([
        adminAnalysesStore.fetchAnalyses(1),
        adminAnalysesStore.fetchStats(),
    ]);
});

// Watch para aplicar filtros ao pressionar Enter
watch([searchInput, selectedStatus, dateFrom, dateTo], () => {
    // Auto-apply when filters change (debounced by user action)
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-display font-bold text-gray-900 dark:text-gray-100 flex items-center gap-3">
                    <DocumentChartBarIcon class="w-8 h-8 text-primary-500" />
                    Análises Geradas
                </h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">
                    Visualize e monitore todas as análises de IA do sistema
                </p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div v-if="adminAnalysesStore.stats" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <BaseCard padding="normal">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ adminAnalysesStore.stats.total || 0 }}
                        </p>
                    </div>
                    <div class="p-3 bg-primary-100 dark:bg-primary-900/30 rounded-xl">
                        <DocumentChartBarIcon class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                    </div>
                </div>
            </BaseCard>

            <BaseCard padding="normal">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Completadas</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ adminAnalysesStore.stats.completed || 0 }}
                        </p>
                    </div>
                    <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-xl">
                        <CheckCircleIcon class="w-6 h-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
            </BaseCard>

            <BaseCard padding="normal">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Falhas</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                            {{ adminAnalysesStore.stats.failed || 0 }}
                        </p>
                    </div>
                    <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-xl">
                        <ExclamationCircleIcon class="w-6 h-6 text-red-600 dark:text-red-400" />
                    </div>
                </div>
            </BaseCard>

            <BaseCard padding="normal">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Tempo Médio</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ adminAnalysesStore.stats.average_duration || '0s' }}
                        </p>
                    </div>
                    <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-xl">
                        <ClockIcon class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </BaseCard>
        </div>

        <!-- Filters -->
        <BaseCard padding="normal">
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Buscar
                        </label>
                        <div class="relative">
                            <input
                                v-model="searchInput"
                                type="text"
                                placeholder="Buscar por palavra-chave..."
                                @keyup.enter="applyFilters"
                                class="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                            />
                            <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                        </div>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Status
                        </label>
                        <select
                            v-model="selectedStatus"
                            class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                        >
                            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <!-- Date From -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Data Inicial
                        </label>
                        <input
                            v-model="dateFrom"
                            type="date"
                            class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                        />
                    </div>

                    <!-- Date To -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Data Final
                        </label>
                        <input
                            v-model="dateTo"
                            type="date"
                            class="w-full px-4 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent transition-colors"
                        />
                    </div>
                </div>

                <!-- Filter Actions -->
                <div class="flex items-center gap-3">
                    <BaseButton @click="applyFilters" size="sm">
                        <MagnifyingGlassIcon class="w-4 h-4" />
                        Aplicar Filtros
                    </BaseButton>
                    <BaseButton v-if="hasActiveFilters" @click="clearFilters" variant="secondary" size="sm">
                        <XMarkIcon class="w-4 h-4" />
                        Limpar Filtros
                    </BaseButton>
                </div>
            </div>
        </BaseCard>

        <!-- Loading -->
        <div v-if="adminAnalysesStore.isLoading && adminAnalysesStore.analyses.length === 0" class="flex items-center justify-center py-20">
            <LoadingSpinner size="lg" class="text-primary-500" />
        </div>

        <!-- Error -->
        <BaseCard v-else-if="adminAnalysesStore.error" padding="normal">
            <div class="text-center py-8">
                <ExclamationCircleIcon class="w-12 h-12 text-red-500 mx-auto mb-4" />
                <p class="text-gray-600 dark:text-gray-400">{{ adminAnalysesStore.error }}</p>
            </div>
        </BaseCard>

        <!-- Empty State -->
        <BaseCard v-else-if="adminAnalysesStore.analyses.length === 0" padding="normal">
            <div class="text-center py-12">
                <DocumentChartBarIcon class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                    Nenhuma análise encontrada
                </h3>
                <p class="text-gray-500 dark:text-gray-400">
                    {{ hasActiveFilters ? 'Tente ajustar os filtros' : 'Ainda não há análises geradas no sistema' }}
                </p>
            </div>
        </BaseCard>

        <!-- Table -->
        <BaseCard v-else padding="none">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                ID
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Loja
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Usuário
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Health Score
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Sugestões
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Data
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr
                            v-for="analysis in adminAnalysesStore.analyses"
                            :key="analysis.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer"
                            @click="openDetailModal(analysis.id)"
                        >
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                #{{ analysis.id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                {{ analysis.store?.name || 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                {{ analysis.user?.name || 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="['px-2 py-1 text-xs font-medium rounded-full', getStatusBadgeClass(analysis.status)]">
                                    {{ analysis.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span v-if="analysis.health_score" :class="['text-sm font-semibold', getHealthScoreColor(analysis.health_score)]">
                                    {{ analysis.health_score }}
                                </span>
                                <span v-else class="text-sm text-gray-400">-</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                {{ analysis.suggestions_count || 0 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                {{ formatDate(analysis.created_at) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <button
                                    @click.stop="openDetailModal(analysis.id)"
                                    class="text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 font-medium"
                                >
                                    Ver Detalhes
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="adminAnalysesStore.pagination.last_page > 1" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Mostrando página {{ adminAnalysesStore.pagination.current_page }} de {{ adminAnalysesStore.pagination.last_page }}
                        ({{ adminAnalysesStore.pagination.total }} total)
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="changePage(adminAnalysesStore.pagination.current_page - 1)"
                            :disabled="adminAnalysesStore.pagination.current_page === 1"
                            class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            Anterior
                        </button>
                        <button
                            @click="changePage(adminAnalysesStore.pagination.current_page + 1)"
                            :disabled="adminAnalysesStore.pagination.current_page === adminAnalysesStore.pagination.last_page"
                            class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            Próxima
                        </button>
                    </div>
                </div>
            </div>
        </BaseCard>

        <!-- Detail Modal -->
        <AnalysisDetailModal
            :show="showDetailModal"
            :analysis="adminAnalysesStore.currentAnalysis"
            :isLoading="adminAnalysesStore.isLoadingDetail"
            @close="closeDetailModal"
        />
    </div>
</template>
