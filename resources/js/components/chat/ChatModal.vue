<script setup>
import { watch, onUnmounted } from 'vue';
import ChatContainer from './ChatContainer.vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    show: { type: Boolean, default: false },
    initialContext: { type: Object, default: null },
});

const emit = defineEmits(['close']);

// Handle body scroll lock
watch(() => props.show, (newVal) => {
    if (newVal) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
}, { immediate: true });

// Cleanup on unmount
onUnmounted(() => {
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
            >
                <!-- Backdrop -->
                <div
                    @click="emit('close')"
                    class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm"
                ></div>

                <!-- Modal -->
                <div class="relative w-full max-w-4xl max-h-[80vh] bg-white dark:bg-gray-800 rounded-3xl shadow-2xl flex flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="px-6 py-4 bg-gradient-to-r from-primary-500 to-secondary-500 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-white">Chat com IA</h2>
                                <p v-if="initialContext?.suggestion" class="text-white/80 text-sm">
                                    Discutindo: {{ initialContext.suggestion.title }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="emit('close')"
                            class="w-10 h-10 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition-colors"
                        >
                            <XMarkIcon class="w-5 h-5 text-white" />
                        </button>
                    </div>

                    <!-- Chat Container -->
                    <div class="flex-1 overflow-hidden">
                        <ChatContainer :initial-context="initialContext" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
