<script setup>
import { watch, onUnmounted } from 'vue';
import ChatContainer from '../chat/ChatContainer.vue';
import { XMarkIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    show: { type: Boolean, default: false },
    initialContext: { type: Object, default: null },
});

const emit = defineEmits(['close']);

// Handle body scroll lock
watch(() => props.show, (newVal) => {
    if (newVal) {
        // Lock scroll but don't change overflow (modal already handles this)
    } else {
        // Don't restore scroll - let the modal handle it
    }
}, { immediate: true });

// Cleanup on unmount
onUnmounted(() => {
    // Don't restore scroll here - modal will handle
});
</script>

<template>
    <Teleport to="body">
        <Transition name="slide-panel">
            <div
                v-if="show"
                class="fixed top-0 right-0 bottom-0 w-full lg:w-1/2 z-[60] pointer-events-auto"
            >
                <!-- Chat Panel -->
                <div class="relative h-full w-full bg-white dark:bg-gray-800 shadow-2xl flex flex-col border-l border-gray-200 dark:border-gray-700">
                    <!-- Header -->
                    <div class="px-6 py-4 bg-gradient-to-r from-primary-500 to-secondary-500 flex items-center justify-between flex-shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                <ChatBubbleLeftRightIcon class="w-6 h-6 text-white" />
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-white">Chat com IA</h2>
                                <p v-if="initialContext?.suggestion" class="text-white/80 text-sm truncate">
                                    Discutindo: {{ initialContext.suggestion.title }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="emit('close')"
                            class="w-10 h-10 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition-colors flex-shrink-0"
                        >
                            <XMarkIcon class="w-5 h-5 text-white" />
                        </button>
                    </div>

                    <!-- Chat Container with same layout as ChatView -->
                    <div class="flex-1 min-h-0 overflow-hidden flex flex-col bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950">
                        <ChatContainer :initial-context="initialContext" :show-quick-suggestions="false" class="h-full" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.slide-panel-enter-active,
.slide-panel-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-panel-enter-from,
.slide-panel-leave-to {
    transform: translateX(100%);
    opacity: 0;
}

.slide-panel-enter-to,
.slide-panel-leave-from {
    transform: translateX(0);
    opacity: 1;
}
</style>
