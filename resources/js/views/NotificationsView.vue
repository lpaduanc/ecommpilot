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
    <div class="max-w-5xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 dark:text-white">
                    Notificações
                </h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">
                    Acompanhe todas as atualizações e eventos do sistema
                </p>
            </div>

            <BaseButton
                v-if="hasNotifications && notificationStore.unreadCount > 0"
                variant="primary"
                @click="handleMarkAllAsRead"
            >
                <CheckCircleIcon class="w-5 h-5" />
                Marcar todas como lidas
            </BaseButton>
        </div>

        <!-- Filters -->
        <BaseCard>
            <div class="flex items-center gap-2 mb-4">
                <FunnelIcon class="w-5 h-5 text-gray-500 dark:text-gray-400" />
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Filtros</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Type Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Tipo
                    </label>
                    <select
                        v-model="typeFilter"
                        class="w-full px-4 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
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
                        class="w-full px-4 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
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
                        class="w-full px-4 py-2.5 rounded-lg bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none"
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
        </BaseCard>

        <!-- Notifications List -->
        <BaseCard padding="none">
            <!-- Loading State -->
            <div v-if="notificationStore.isLoading" class="flex items-center justify-center py-16">
                <LoadingSpinner size="lg" />
            </div>

            <!-- Empty State -->
            <div v-else-if="!hasNotifications" class="flex flex-col items-center justify-center py-16 px-4">
                <div class="w-20 h-20 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-4">
                    <BellSlashIcon class="w-10 h-10 text-gray-400 dark:text-gray-500" />
                </div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                    Nenhuma notificação
                </h3>
                <p class="text-gray-600 dark:text-gray-400 text-center">
                    Você não possui notificações no momento
                </p>
            </div>

            <!-- Notifications -->
            <div v-else class="divide-y divide-gray-100 dark:divide-gray-700">
                <NotificationItem
                    v-for="notification in notifications"
                    :key="notification.id"
                    :notification="notification"
                    @mark-as-read="handleMarkAsRead"
                    @delete="handleDelete"
                />
            </div>
        </BaseCard>

        <!-- Pagination -->
        <div v-if="hasNotifications && totalPages > 1" class="flex justify-center">
            <nav class="flex items-center gap-2">
                <BaseButton
                    variant="secondary"
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
                    variant="secondary"
                    size="sm"
                    :disabled="currentPage === totalPages"
                    @click="handlePageChange(currentPage + 1)"
                >
                    Próxima
                </BaseButton>
            </nav>
        </div>
    </div>
</template>
