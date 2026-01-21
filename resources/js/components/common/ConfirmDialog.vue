<script setup>
import BaseModal from './BaseModal.vue';
import BaseButton from './BaseButton.vue';
import { ExclamationTriangleIcon, QuestionMarkCircleIcon } from '@heroicons/vue/24/outline';
import { useConfirmDialog } from '../../composables/useConfirmDialog';

const { isOpen, dialogState, handleConfirm, handleCancel, close } = useConfirmDialog();

function getIcon() {
    if (dialogState.variant === 'danger' || dialogState.variant === 'warning') {
        return ExclamationTriangleIcon;
    }
    return QuestionMarkCircleIcon;
}

function getIconClass() {
    if (dialogState.variant === 'danger') {
        return 'text-danger-500 dark:text-danger-400';
    }
    if (dialogState.variant === 'warning') {
        return 'text-warning-500 dark:text-warning-400';
    }
    return 'text-primary-500 dark:text-primary-400';
}

function getIconBgClass() {
    if (dialogState.variant === 'danger') {
        return 'bg-danger-100 dark:bg-danger-900/20';
    }
    if (dialogState.variant === 'warning') {
        return 'bg-warning-100 dark:bg-warning-900/20';
    }
    return 'bg-primary-100 dark:bg-primary-900/20';
}
</script>

<template>
    <BaseModal
        :show="isOpen"
        :title="dialogState.title"
        size="sm"
        @close="handleCancel"
    >
        <div class="space-y-4">
            <!-- Icon -->
            <div :class="['w-12 h-12 rounded-full flex items-center justify-center mx-auto', getIconBgClass()]">
                <component :is="getIcon()" :class="['w-6 h-6', getIconClass()]" />
            </div>

            <!-- Message -->
            <p class="text-center text-gray-700 dark:text-gray-300">
                {{ dialogState.message }}
            </p>
        </div>

        <template #footer>
            <div class="flex items-center justify-end gap-3">
                <BaseButton
                    variant="secondary"
                    @click="handleCancel"
                >
                    {{ dialogState.cancelText }}
                </BaseButton>
                <BaseButton
                    :variant="dialogState.variant"
                    @click="handleConfirm"
                >
                    {{ dialogState.confirmText }}
                </BaseButton>
            </div>
        </template>
    </BaseModal>
</template>
