<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useSystemNotificationStore } from '../stores/systemNotificationStore';
import { useConfirmDialog } from '../composables/useConfirmDialog';
import NotificationItem from '../components/notifications/NotificationItem.vue';
import BaseCard from '../components/common/BaseCard.vue';
import BaseButton from '../components/common/BaseButton.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import {
    FunnelIcon,
    CheckCircleIcon,
    BellSlashIcon,
    BellIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';

const notificationStore = useSystemNotificationStore();
const { confirm } = useConfirmDialog();

const currentPage = ref(1);
const totalPages = ref(1);
const perPage = ref(20);

const typeFilter = ref('all');
const statusFilter = ref('all');
const periodFilter = ref('all');

const typeOptions = [
    { value: 'all', label: 'Todos os tipos' },
    { value: 'sync', label: 'Sincronização' },
    { value: 'analysis', label: 'Análise IA' },
    { value: 'email', label: 'E-mail' },
];

const statusOptions = [
    { value: 'all', label: 'Todas' },
    { value: 'unread', label: 'Não lidas' },
    { value: 'read', label: 'Lidas' },
];

const periodOptions = [
    { value: 'all', label: 'Todos os períodos' },
    { value: 'yesterday', label: 'Ontem' },
    { value: 'today', label: 'Hoje' },
    { value: 'week', label: 'Última semana' },
    { value: 'month', label: 'Último mês' },
];

const notifications = computed(() => notificationStore.filteredNotifications);
const hasNotifications = computed(() => notifications.value.length > 0);

async function fetchData() {
    try {
        const response = await notificationStore.fetchNotifications(currentPage.value, perPage.value);
        totalPages.value = response.last_page || 1;
    } catch (error) {
        console.error('Erro ao carregar notificações:', error);
    }
}

async function handleMarkAsRead(notificationId) {
    try {
        await notificationStore.markAsRead(notificationId);
    } catch (error) {
        console.error('Erro ao marcar notificação como lida:', error);
    }
}

async function handleDelete(notificationId) {
    const confirmed = await confirm({
        title: 'Excluir Notificação',
        message: 'Deseja realmente deletar esta notificação?',
        confirmText: 'Excluir',
        variant: 'danger',
    });

    if (!confirmed) return;

    try {
        await notificationStore.deleteNotification(notificationId);
    } catch (error) {
        console.error('Erro ao deletar notificação:', error);
    }
}

async function handleMarkAllAsRead() {
    const confirmed = await confirm({
        title: 'Marcar Todas como Lidas',
        message: 'Deseja marcar todas as notificações como lidas?',
        confirmText: 'Confirmar',
        variant: 'primary',
    });

    if (!confirmed) return;

    try {
        await notificationStore.markAllAsRead();
    } catch (error) {
        console.error('Erro ao marcar todas como lidas:', error);
    }
}

function handlePageChange(page) {
    currentPage.value = page;
    fetchData();
}

// Watch filters and update store + refetch
watch([typeFilter, statusFilter, periodFilter], () => {
    notificationStore.setFilters({
        type: typeFilter.value,
        status: statusFilter.value,
        period: periodFilter.value,
    });
    currentPage.value = 1;
    fetchData();
});

onMounted(() => {
    fetchData();
});
</script>

<template>
    <div class="min-h-screen">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-4 sm:px-6 lg:px-8 py-6 sm:py-8 lg:py-12 -mx-4 sm:-mx-6 lg:-mx-8 -mt-4 sm:-mt-6 lg:-mt-8">
            <!-- Background Elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 dark:bg-primary-500/10 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 dark:bg-secondary-500/10 rounded-full blur-3xl"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-accent-500/10 dark:bg-accent-500/5 rounded-full blur-3xl"></div>
                <!-- Grid Pattern -->
                <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
            </div>

            <div class="relative z-10 max-w-7xl mx-auto">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="space-y-3 sm:space-y-4">
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="w-10 sm:w-12 lg:w-14 h-10 sm:h-12 lg:h-14 rounded-xl sm:rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/30 flex-shrink-0">
                                <BellIcon class="w-5 sm:w-6 lg:w-7 h-5 sm:h-6 lg:h-7 text-white" />
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-xl sm:text-2xl lg:text-4xl font-display font-bold text-white dark:text-gray-100 truncate">
                                    Notificações
                                </h1>
                                <p class="text-primary-200/80 dark:text-gray-400 text-xs sm:text-sm lg:text-base">
                                    {{ notifications.length }} notificações no total
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div v-if="hasNotifications && notificationStore.unreadCount > 0" class="flex items-center gap-3 w-full lg:w-auto">
                        <button
                            @click="handleMarkAllAsRead"
                            type="button"
                            class="flex-1 lg:flex-none px-6 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white font-medium hover:bg-white/20 transition-all focus:outline-none focus:ring-2 focus:ring-white/50 flex items-center justify-center gap-2"
                        >
                            <CheckCircleIcon class="w-5 h-5" />
                            <span class="hidden sm:inline">Marcar todas como lidas</span>
                            <span class="sm:hidden">Marcar lidas</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="py-4 sm:py-6 lg:py-8 px-4 sm:px-6 lg:px-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">
            <div class="w-full">
                <!-- Filters Section -->
                <BaseCard class="mb-4 sm:mb-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30 flex items-center justify-center flex-shrink-0">
                            <FunnelIcon class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                        </div>
                        <div class="flex-1">
                            <div class="mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Filtros</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Filtre as notificações por tipo, status ou período</p>
                            </div>

                            <!-- Filters Row -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                                <!-- Type Filter -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Tipo
                                    </label>
                                    <select
                                        v-model="typeFilter"
                                        class="w-full px-4 py-2.5 pr-10 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 appearance-none bg-no-repeat bg-right cursor-pointer"
                                        style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236B7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0.5rem center; background-size: 1.5em 1.5em;"
                                    >
                                        <option
                                            v-for="option in typeOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Status Filter -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Status
                                    </label>
                                    <select
                                        v-model="statusFilter"
                                        class="w-full px-4 py-2.5 pr-10 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 appearance-none bg-no-repeat bg-right cursor-pointer"
                                        style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236B7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0.5rem center; background-size: 1.5em 1.5em;"
                                    >
                                        <option
                                            v-for="option in statusOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Period Filter -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Período
                                    </label>
                                    <select
                                        v-model="periodFilter"
                                        class="w-full px-4 py-2.5 pr-10 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 appearance-none bg-no-repeat bg-right cursor-pointer"
                                        style="background-image: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 fill=%27none%27 viewBox=%270 0 20 20%27%3E%3Cpath stroke=%27%236B7280%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27 stroke-width=%271.5%27 d=%27m6 8 4 4 4-4%27/%3E%3C/svg%3E'); background-position: right 0.5rem center; background-size: 1.5em 1.5em;"
                                    >
                                        <option
                                            v-for="option in periodOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </BaseCard>

                <!-- Notifications List -->
                <BaseCard padding="none" class="overflow-hidden">
                    <!-- Loading State -->
                    <div v-if="notificationStore.isLoading" class="flex items-center justify-center py-20">
                        <div class="relative">
                            <div class="w-16 h-16 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500"></div>
                            <LoadingSpinner size="lg" class="absolute inset-0 m-auto text-white" />
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="!hasNotifications" class="text-center py-20">
                        <div class="relative inline-block mb-6">
                            <div class="w-32 h-32 rounded-3xl bg-gradient-to-br from-primary-100 to-secondary-100 dark:from-primary-900/30 dark:to-secondary-900/30 flex items-center justify-center">
                                <BellSlashIcon class="w-16 h-16 text-primary-400 dark:text-primary-500" />
                            </div>
                            <div class="absolute -bottom-2 -right-2 w-8 h-8 rounded-full bg-accent-400 flex items-center justify-center">
                                <SparklesIcon class="w-4 h-4 text-white" />
                            </div>
                        </div>
                        <h3 class="text-2xl font-display font-bold text-gray-900 dark:text-gray-100 mb-3">
                            Nenhuma notificação encontrada
                        </h3>
                        <p class="text-gray-500 dark:text-gray-400">
                            Você não possui notificações no momento
                        </p>
                    </div>

                    <!-- Notifications -->
                    <div v-else class="w-full divide-y divide-gray-100 dark:divide-gray-700">
                        <NotificationItem
                            v-for="notification in notifications"
                            :key="notification.id"
                            :notification="notification"
                            @mark-as-read="handleMarkAsRead"
                            @delete="handleDelete"
                        />
                    </div>

                    <!-- Pagination -->
                    <div v-if="hasNotifications && totalPages > 1" class="flex flex-col sm:flex-row items-center justify-center gap-3 px-4 sm:px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                        <nav class="flex items-center gap-2">
                            <BaseButton
                                variant="ghost"
                                size="sm"
                                :disabled="currentPage === 1"
                                @click="handlePageChange(currentPage - 1)"
                            >
                                Anterior
                            </BaseButton>

                            <div class="flex items-center gap-1">
                                <button
                                    v-for="page in totalPages"
                                    :key="page"
                                    @click="handlePageChange(page)"
                                    :class="[
                                        'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                                        page === currentPage
                                            ? 'bg-primary-600 text-white'
                                            : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700'
                                    ]"
                                >
                                    {{ page }}
                                </button>
                            </div>

                            <BaseButton
                                variant="ghost"
                                size="sm"
                                :disabled="currentPage === totalPages"
                                @click="handlePageChange(currentPage + 1)"
                            >
                                Próxima
                            </BaseButton>
                        </nav>
                    </div>
                </BaseCard>
            </div>
        </div>
    </div>
</template>
