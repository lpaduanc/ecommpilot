import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '../services/api';
import { logger } from '../utils/logger';

export const useSystemNotificationStore = defineStore('systemNotification', () => {
    const notifications = ref([]);
    const unreadNotifications = ref([]);
    const isLoading = ref(false);
    const error = ref(null);

    // Filters
    const filters = ref({
        type: 'all', // all|sync|analysis|email
        status: 'all', // all|read|unread
        period: 'all', // all|today|week|month
    });

    // Computed
    const unreadCount = computed(() => unreadNotifications.value.length);

    const hasUnread = computed(() => unreadCount.value > 0);

    const filteredNotifications = computed(() => {
        let filtered = [...notifications.value];

        // Filter by type
        if (filters.value.type !== 'all') {
            filtered = filtered.filter(n => n.type === filters.value.type);
        }

        // Filter by status
        if (filters.value.status === 'read') {
            filtered = filtered.filter(n => n.read_at !== null);
        } else if (filters.value.status === 'unread') {
            filtered = filtered.filter(n => n.read_at === null);
        }

        // Filter by period
        if (filters.value.period !== 'all') {
            if (filters.value.period === 'yesterday') {
                const now = new Date();
                const yesterdayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1, 0, 0, 0);
                const yesterdayEnd = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1, 23, 59, 59);
                filtered = filtered.filter(n => {
                    const d = new Date(n.created_at);
                    return d >= yesterdayStart && d <= yesterdayEnd;
                });
            } else {
                const now = new Date();
                const periods = {
                    today: 1,
                    week: 7,
                    month: 30,
                };
                const days = periods[filters.value.period];
                const cutoff = new Date(now.setDate(now.getDate() - days));

                filtered = filtered.filter(n => {
                    const notificationDate = new Date(n.created_at);
                    return notificationDate >= cutoff;
                });
            }
        }

        return filtered;
    });

    // Actions
    async function fetchNotifications(page = 1, perPage = 20) {
        isLoading.value = true;
        error.value = null;

        try {
            const response = await api.get('/notifications', {
                params: {
                    page,
                    per_page: perPage,
                    type: filters.value.type !== 'all' ? filters.value.type : undefined,
                    status: filters.value.status !== 'all' ? filters.value.status : undefined,
                    period: filters.value.period !== 'all' ? filters.value.period : undefined,
                },
            });

            notifications.value = response.data.data || [];
            return response.data;
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao carregar notificações';
            throw err;
        } finally {
            isLoading.value = false;
        }
    }

    async function fetchUnread() {
        try {
            const response = await api.get('/notifications/unread');
            unreadNotifications.value = response.data.data || [];
        } catch (err) {
            logger.error('Erro ao carregar notificações não lidas:', err);
        }
    }

    async function markAsRead(notificationId) {
        try {
            await api.post(`/notifications/${notificationId}/read`);

            // Update local state
            const notification = notifications.value.find(n => n.id === notificationId);
            if (notification) {
                notification.read_at = new Date().toISOString();
            }

            // Remove from unread
            unreadNotifications.value = unreadNotifications.value.filter(n => n.id !== notificationId);
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao marcar notificação como lida';
            throw err;
        }
    }

    async function markAllAsRead() {
        try {
            await api.post('/notifications/read-all');

            // Update local state
            notifications.value.forEach(n => {
                n.read_at = new Date().toISOString();
            });

            unreadNotifications.value = [];
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao marcar todas como lidas';
            throw err;
        }
    }

    async function deleteNotification(notificationId) {
        try {
            await api.delete(`/notifications/${notificationId}`);

            // Remove from local state
            notifications.value = notifications.value.filter(n => n.id !== notificationId);
            unreadNotifications.value = unreadNotifications.value.filter(n => n.id !== notificationId);
        } catch (err) {
            error.value = err.response?.data?.message || 'Erro ao deletar notificação';
            throw err;
        }
    }

    function setFilters(newFilters) {
        filters.value = { ...filters.value, ...newFilters };
    }

    function resetFilters() {
        filters.value = {
            type: 'all',
            status: 'all',
            period: 'all',
        };
    }

    // Poll for new notifications periodically
    let pollInterval = null;

    function startPolling(intervalMs = 60000) {
        if (pollInterval) return;

        pollInterval = setInterval(() => {
            fetchUnread();
        }, intervalMs);
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    return {
        notifications,
        unreadNotifications,
        isLoading,
        error,
        filters,
        unreadCount,
        hasUnread,
        filteredNotifications,
        fetchNotifications,
        fetchUnread,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        setFilters,
        resetFilters,
        startPolling,
        stopPolling,
    };
});
