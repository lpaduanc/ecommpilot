<script setup>
import { useNotificationStore } from '../../stores/notificationStore';
import {
    CheckCircleIcon,
    ExclamationCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const notificationStore = useNotificationStore();

const icons = {
    success: CheckCircleIcon,
    error: ExclamationCircleIcon,
    warning: ExclamationTriangleIcon,
    info: InformationCircleIcon,
};

const colors = {
    success: {
        bg: 'bg-success-50',
        border: 'border-success-200',
        icon: 'text-success-500',
        text: 'text-success-800',
    },
    error: {
        bg: 'bg-danger-50',
        border: 'border-danger-200',
        icon: 'text-danger-500',
        text: 'text-danger-800',
    },
    warning: {
        bg: 'bg-accent-50',
        border: 'border-accent-200',
        icon: 'text-accent-500',
        text: 'text-accent-800',
    },
    info: {
        bg: 'bg-primary-50',
        border: 'border-primary-200',
        icon: 'text-primary-500',
        text: 'text-primary-800',
    },
};

function getIcon(type) {
    return icons[type] || icons.info;
}

function getColors(type) {
    return colors[type] || colors.info;
}

function removeNotification(id) {
    notificationStore.remove(id);
}
</script>

<template>
    <div
        class="fixed bottom-6 right-6 z-50 flex flex-col gap-3"
        aria-live="polite"
        aria-atomic="true"
    >
        <TransitionGroup
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 translate-x-8"
            enter-to-class="opacity-100 translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 translate-x-0"
            leave-to-class="opacity-0 translate-x-8"
        >
            <div
                v-for="notification in notificationStore.notifications"
                :key="notification.id"
                role="alert"
                :aria-label="`${notification.type}: ${notification.message}`"
                :class="[
                    'flex items-start gap-3 px-4 py-3 rounded-xl border shadow-lg max-w-sm',
                    'transform transition-all hover:scale-105',
                    getColors(notification.type).bg,
                    getColors(notification.type).border
                ]"
            >
                <component
                    :is="getIcon(notification.type)"
                    :class="['w-5 h-5 flex-shrink-0 mt-0.5', getColors(notification.type).icon]"
                    aria-hidden="true"
                />
                <p :class="['text-sm font-medium flex-1', getColors(notification.type).text]">
                    {{ notification.message }}
                </p>
                <button
                    type="button"
                    @click="removeNotification(notification.id)"
                    :class="['flex-shrink-0 hover:opacity-70 transition-opacity', getColors(notification.type).icon]"
                    aria-label="Fechar notificação"
                >
                    <XMarkIcon class="w-5 h-5" aria-hidden="true" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

