<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useSystemNotificationStore } from '../../stores/systemNotificationStore';
import NotificationItem from './NotificationItem.vue';
import LoadingSpinner from '../common/LoadingSpinner.vue';
import { BellSlashIcon } from '@heroicons/vue/24/outline';
import { logger } from '@/utils/logger';

const router = useRouter();
const notificationStore = useSystemNotificationStore();

const displayNotifications = computed(() => {
    return notificationStore.unreadNotifications.slice(0, 5);
});

const hasNotifications = computed(() => displayNotifications.value.length > 0);

async function handleMarkAsRead(notificationId) {
    try {
        await notificationStore.markAsRead(notificationId);
    } catch (error) {
        logger.error('Erro ao marcar notificação como lida:', error);
    }
}

async function handleMarkAllAsRead() {
    try {
        await notificationStore.markAllAsRead();
    } catch (error) {
        logger.error('Erro ao marcar todas como lidas:', error);
    }
}

function viewAll() {
    router.push({ name: 'notifications' });
}
</script>

<template>
    <div class="w-80 max-w-[calc(100vw-2rem)] rounded-2xl bg-white dark:bg-gray-800 shadow-xl ring-1 ring-black/5 dark:ring-gray-700 overflow-hidden">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="font-semibold text-gray-900 dark:text-white">Notificações</span>
                <span
                    v-if="notificationStore.unreadCount > 0"
                    class="px-2 py-0.5 text-xs font-medium rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400"
                >
                    {{ notificationStore.unreadCount }}
                </span>
            </div>
            <button
                v-if="hasNotifications"
                @click="handleMarkAllAsRead"
                class="text-sm text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 font-medium"
            >
                Marcar todas como lidas
            </button>
        </div>

        <!-- Content -->
        <div class="max-h-80 overflow-y-auto scrollbar-thin">
            <!-- Loading State -->
            <div v-if="notificationStore.isLoading" class="flex items-center justify-center py-12">
                <LoadingSpinner size="lg" />
            </div>

            <!-- Empty State -->
            <div v-else-if="!hasNotifications" class="flex flex-col items-center justify-center py-12 px-4">
                <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mb-4">
                    <BellSlashIcon class="w-8 h-8 text-gray-400 dark:text-gray-500" />
                </div>
                <p class="text-gray-600 dark:text-gray-400 text-sm text-center">
                    Nenhuma notificação nova
                </p>
            </div>

            <!-- Notifications List -->
            <div v-else>
                <NotificationItem
                    v-for="notification in displayNotifications"
                    :key="notification.id"
                    :notification="notification"
                    :compact="true"
                    @mark-as-read="handleMarkAsRead"
                />
            </div>
        </div>

        <!-- Footer -->
        <div
            v-if="hasNotifications"
            class="border-t border-gray-100 dark:border-gray-700 p-2"
        >
            <button
                @click="viewAll"
                class="w-full px-4 py-2.5 rounded-xl text-sm font-medium text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors"
            >
                Ver todas as notificações
            </button>
        </div>
    </div>
</template>
