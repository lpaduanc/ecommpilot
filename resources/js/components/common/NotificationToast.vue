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
        bg: 'bg-success-50 dark:bg-success-900/40',
        border: 'border-success-300 dark:border-success-700',
        icon: 'text-success-600 dark:text-success-400',
        text: 'text-success-900 dark:text-success-100',
    },
    error: {
        bg: 'bg-danger-50 dark:bg-danger-900/40',
        border: 'border-danger-300 dark:border-danger-700',
        icon: 'text-danger-600 dark:text-danger-400',
        text: 'text-danger-900 dark:text-danger-100',
    },
    warning: {
        bg: 'bg-accent-50 dark:bg-accent-900/40',
        border: 'border-accent-300 dark:border-accent-700',
        icon: 'text-accent-600 dark:text-accent-400',
        text: 'text-accent-900 dark:text-accent-100',
    },
    info: {
        bg: 'bg-primary-50 dark:bg-primary-900/40',
        border: 'border-primary-300 dark:border-primary-700',
        icon: 'text-primary-600 dark:text-primary-400',
        text: 'text-primary-900 dark:text-primary-100',
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
        class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 left-4 sm:left-auto z-[9999] flex flex-col gap-3"
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
                    'flex items-start gap-4 px-5 py-4 rounded-xl border-2 shadow-xl w-full sm:max-w-md',
                    'transform transition-transform hover:scale-[1.02]',
                    getColors(notification.type).bg,
                    getColors(notification.type).border
                ]"
            >
                <component
                    :is="getIcon(notification.type)"
                    :class="['w-6 h-6 flex-shrink-0 mt-0.5', getColors(notification.type).icon]"
                    aria-hidden="true"
                />
                <p :class="['text-base font-semibold flex-1 leading-snug', getColors(notification.type).text]">
                    {{ notification.message }}
                </p>
                <button
                    type="button"
                    @click="removeNotification(notification.id)"
                    :class="['flex-shrink-0 hover:opacity-70 transition-opacity mt-0.5', getColors(notification.type).icon]"
                    aria-label="Fechar notificação"
                >
                    <XMarkIcon class="w-6 h-6" aria-hidden="true" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

