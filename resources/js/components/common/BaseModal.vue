<script setup>
import { watch, onMounted, onUnmounted, ref, computed, nextTick } from 'vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { useFocusTrap } from '../../composables/useKeyboard';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg', 'xl'].includes(v),
    },
    closable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

const sizeClasses = {
    sm: 'max-w-md',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
};

// Refs para acessibilidade
const modalRef = ref(null);
const closeButtonRef = ref(null);

// IDs únicos para ARIA
const titleId = computed(() => `modal-title-${Math.random().toString(36).substring(2, 9)}`);
const descriptionId = computed(() => `modal-description-${Math.random().toString(36).substring(2, 9)}`);

// Focus trap para modal
const { activate: activateFocusTrap, deactivate: deactivateFocusTrap } = useFocusTrap(
    modalRef,
    {
        initialFocus: closeButtonRef,
        returnFocus: true,
    }
);

function close() {
    if (props.closable) {
        emit('close');
    }
}

function handleEscape(event) {
    if (event.key === 'Escape' && props.show && props.closable) {
        close();
    }
}

function handleBackdropClick(event) {
    if (event.target === event.currentTarget && props.closable) {
        close();
    }
}

watch(() => props.show, async (newVal) => {
    if (newVal) {
        document.body.style.overflow = 'hidden';
        // Ativar focus trap após o modal ser montado
        await nextTick();
        activateFocusTrap();
    } else {
        document.body.style.overflow = '';
        deactivateFocusTrap();
    }
});

onMounted(() => {
    document.addEventListener('keydown', handleEscape);
});

onUnmounted(() => {
    document.removeEventListener('keydown', handleEscape);
    document.body.style.overflow = '';
    deactivateFocusTrap();
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 dark:bg-black/60 backdrop-blur-sm"
                @click="handleBackdropClick"
                aria-hidden="false"
            >
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="show"
                        ref="modalRef"
                        role="dialog"
                        aria-modal="true"
                        :aria-labelledby="titleId"
                        :aria-describedby="descriptionId"
                        :class="['w-full bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden', sizeClasses[size]]"
                    >
                        <!-- Header -->
                        <div v-if="title || closable" class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 v-if="title" :id="titleId" class="text-lg font-semibold text-gray-900 dark:text-gray-100 dark:text-white">{{ title }}</h3>
                            <button
                                v-if="closable"
                                ref="closeButtonRef"
                                type="button"
                                aria-label="Fechar modal"
                                @click="close"
                                class="p-2 -mr-2 rounded-lg text-gray-400 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                            >
                                <XMarkIcon class="w-5 h-5" aria-hidden="true" />
                            </button>
                        </div>

                        <!-- Content -->
                        <div :id="descriptionId" class="px-6 py-4">
                            <slot />
                        </div>

                        <!-- Footer -->
                        <div v-if="$slots.footer" class="px-6 py-4 bg-gray-50 dark:bg-gray-900 border-t border-gray-100 dark:border-gray-700">
                            <slot name="footer" />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

