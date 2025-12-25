<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { useChatStore } from '../../stores/chatStore';
import ChatMessage from './ChatMessage.vue';
import ChatInput from './ChatInput.vue';
import LoadingSpinner from '../common/LoadingSpinner.vue';
import BaseButton from '../common/BaseButton.vue';
import { TrashIcon, SparklesIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    compact: { type: Boolean, default: false },
});

const chatStore = useChatStore();
const messagesContainer = ref(null);

const messages = computed(() => chatStore.messages);
const isLoading = computed(() => chatStore.isLoading);
const isSending = computed(() => chatStore.isSending);

async function handleSendMessage(content) {
    await chatStore.sendMessage(content);
    scrollToBottom();
}

async function handleClearChat() {
    if (confirm('Tem certeza que deseja limpar a conversa?')) {
        await chatStore.clearConversation();
        chatStore.addWelcomeMessage();
    }
}

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
}

watch(messages, () => {
    scrollToBottom();
}, { deep: true });

onMounted(() => {
    chatStore.fetchConversation();
    chatStore.addWelcomeMessage();
    scrollToBottom();
});
</script>

<template>
    <div :class="['flex flex-col h-full', compact ? '' : 'p-4']">
        <!-- Header -->
        <div :class="['flex items-center justify-between border-b border-gray-100', compact ? 'px-4 py-3' : 'pb-4']">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-secondary-600 flex items-center justify-center">
                    <SparklesIcon class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Assistente IA</h3>
                    <p class="text-xs text-gray-500">Online</p>
                </div>
            </div>
            <BaseButton
                variant="ghost"
                size="sm"
                @click="handleClearChat"
                title="Limpar conversa"
            >
                <TrashIcon class="w-4 h-4" />
            </BaseButton>
        </div>

        <!-- Messages -->
        <div
            ref="messagesContainer"
            :class="['flex-1 overflow-y-auto scrollbar-thin', compact ? 'px-4 py-4' : 'py-4']"
        >
            <div v-if="isLoading && messages.length === 0" class="flex items-center justify-center h-full">
                <LoadingSpinner size="lg" class="text-primary-500" />
            </div>

            <div v-else class="space-y-4">
                <ChatMessage
                    v-for="message in messages"
                    :key="message.id"
                    :message="message"
                />

                <!-- Typing Indicator -->
                <div v-if="isSending" class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-secondary-600 flex items-center justify-center flex-shrink-0">
                        <SparklesIcon class="w-4 h-4 text-white" />
                    </div>
                    <div class="px-4 py-3 rounded-2xl rounded-tl-md bg-gray-100">
                        <div class="flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 0ms"></span>
                            <span class="w-2 h-2 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 150ms"></span>
                            <span class="w-2 h-2 rounded-full bg-gray-400 animate-bounce" style="animation-delay: 300ms"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Input -->
        <div :class="['border-t border-gray-100', compact ? 'px-4 py-3' : 'pt-4']">
            <ChatInput
                @send="handleSendMessage"
                :disabled="isSending"
            />
        </div>
    </div>
</template>

