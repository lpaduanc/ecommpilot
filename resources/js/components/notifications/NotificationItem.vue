<script setup>
import { computed } from 'vue';
import {
    ArrowPathIcon,
    SparklesIcon,
    EnvelopeIcon,
    ExclamationCircleIcon,
    CheckCircleIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import BaseButton from '../common/BaseButton.vue';

const props = defineProps({
    notification: {
        type: Object,
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['mark-as-read', 'delete']);

// Get icon based on notification type
const notificationIcon = computed(() => {
    const icons = {
        sync: ArrowPathIcon,
        analysis: SparklesIcon,
        email: EnvelopeIcon,
        error: ExclamationCircleIcon,
        success: CheckCircleIcon,
    };
    return icons[props.notification.type] || SparklesIcon;
});

// Get color classes based on notification type
const colorClasses = computed(() => {
    const colors = {
        sync: 'bg-primary-100 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400',
        analysis: 'bg-purple-100 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400',
        email: 'bg-success-100 dark:bg-success-900/20 text-success-600 dark:text-success-400',
        error: 'bg-danger-100 dark:bg-danger-900/20 text-danger-600 dark:text-danger-400',
        success: 'bg-success-100 dark:bg-success-900/20 text-success-600 dark:text-success-400',
    };
    return colors[props.notification.type] || 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400';
});

const isUnread = computed(() => !props.notification.read_at);

// Format relative time
const relativeTime = computed(() => {
    if (!props.notification.created_at) return '';

    const now = new Date();
    const created = new Date(props.notification.created_at);
    const diffMs = now - created;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Agora mesmo';
    if (diffMins < 60) return `Há ${diffMins} ${diffMins === 1 ? 'minuto' : 'minutos'}`;
    if (diffHours < 24) return `Há ${diffHours} ${diffHours === 1 ? 'hora' : 'horas'}`;
    if (diffDays < 7) return `Há ${diffDays} ${diffDays === 1 ? 'dia' : 'dias'}`;

    return created.toLocaleDateString('pt-BR');
});

function handleMarkAsRead() {
    emit('mark-as-read', props.notification.id);
}

function handleDelete() {
    emit('delete', props.notification.id);
}
</script>

<template>
    <div
        :class="[
            'group transition-colors duration-200',
            compact ? 'p-3' : 'p-4',
            isUnread ? 'bg-primary-50/50 dark:bg-primary-900/10' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50'
        ]"
    >
        <div class="flex items-start gap-3">
            <!-- Icon -->
            <div :class="['p-2 rounded-lg flex-shrink-0', colorClasses]">
                <component :is="notificationIcon" class="w-5 h-5" />
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1">
                        <p :class="[
                            'font-medium text-sm',
                            isUnread ? 'text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300'
                        ]">
                            {{ notification.title }}
                        </p>
                        <p v-if="!compact" class="text-gray-600 dark:text-gray-400 text-sm mt-1 line-clamp-2">
                            {{ notification.message }}
                        </p>
                    </div>

                    <!-- Unread indicator -->
                    <div v-if="isUnread" class="w-2 h-2 rounded-full bg-primary-500 flex-shrink-0 mt-1.5"></div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between mt-2">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ relativeTime }}
                    </span>

                    <!-- Actions -->
                    <div v-if="!compact" class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button
                            v-if="isUnread"
                            @click="handleMarkAsRead"
                            class="p-1.5 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                            title="Marcar como lida"
                        >
                            <CheckCircleIcon class="w-4 h-4" />
                        </button>
                        <button
                            @click="handleDelete"
                            class="p-1.5 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-danger-100 dark:hover:bg-danger-900/20 hover:text-danger-600 dark:hover:text-danger-400 transition-colors"
                            title="Deletar"
                        >
                            <TrashIcon class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
