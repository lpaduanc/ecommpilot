<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { useChatStore } from '../../stores/chatStore';
import { useIntegrationStore } from '../../stores/integrationStore';
import BaseCard from '../common/BaseCard.vue';
import ChatMessage from './ChatMessage.vue';
import ChatInput from './ChatInput.vue';
import LoadingSpinner from '../common/LoadingSpinner.vue';
import { SparklesIcon, LightBulbIcon } from '@heroicons/vue/24/outline';

const chatStore = useChatStore();
const integrationStore = useIntegrationStore();
const messagesContainer = ref(null);

const messages = computed(() => chatStore.messages);
const isLoading = computed(() => chatStore.isLoading);
const isSending = computed(() => chatStore.isSending);
const isStoreSyncing = computed(() => integrationStore.isActiveStoreSyncing);
const isChatDisabled = computed(() => isSending.value || isStoreSyncing.value);


const suggestionQuestions = [
    'Como estão minhas vendas?',
    'Qual foi minha receita no último mês?',
    'Quais produtos mais contribuíram para minha receita?',
    'Quais produtos têm menor estoque?',
    'Quem são meus melhores clientes?',
    'Qual o ticket médio dos meus clientes?',
    'Como estão performando minhas campanhas?',
    'Qual canal traz mais conversões?',
];

async function handleSendMessage(content) {
    await chatStore.sendMessage(content);
    scrollToBottom();
}

function handleSuggestionClick(question) {
    handleSendMessage(question);
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
    scrollToBottom();
});
</script>

<template>
    <div class="flex flex-col h-full">
        <!-- Messages Area -->
        <BaseCard padding="none" class="flex-1 overflow-hidden flex flex-col">
            <div
                ref="messagesContainer"
                class="flex-1 overflow-y-auto scrollbar-thin px-4 sm:px-6 py-6"
            >
                <div v-if="isLoading && messages.length === 0" class="flex items-center justify-center h-full">
                    <div class="relative">
                        <div class="w-20 h-20 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500"></div>
                        <LoadingSpinner size="xl" class="absolute inset-0 m-auto text-white" />
                    </div>
                </div>

                <div v-else class="space-y-6 max-w-4xl mx-auto">
                    <ChatMessage
                        v-for="message in messages"
                        :key="message.id"
                        :message="message"
                    />

                    <!-- Typing Indicator -->
                    <div v-if="isSending" class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30 flex items-center justify-center flex-shrink-0 shadow-sm">
                            <SparklesIcon class="w-5 h-5 text-primary-600 dark:text-primary-400 animate-pulse" />
                        </div>
                        <div class="px-5 py-3 rounded-2xl rounded-tl-md bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-800/50 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-primary-400 dark:bg-primary-500 animate-bounce" style="animation-delay: 0ms"></span>
                                <span class="w-2 h-2 rounded-full bg-primary-400 dark:bg-primary-500 animate-bounce" style="animation-delay: 150ms"></span>
                                <span class="w-2 h-2 rounded-full bg-primary-400 dark:bg-primary-500 animate-bounce" style="animation-delay: 300ms"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Suggestions - Always Visible at Bottom -->
            <div class="border-t border-gray-200 dark:border-gray-700 bg-gradient-to-b from-gray-50/50 to-white dark:from-gray-800/50 dark:to-gray-900 px-4 sm:px-6 py-4">
                <div class="max-w-4xl mx-auto">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-6 h-6 rounded-lg bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center">
                            <LightBulbIcon class="w-4 h-4 text-primary-600 dark:text-primary-400" />
                        </div>
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Sugestões de perguntas</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="question in suggestionQuestions"
                            :key="question"
                            @click="handleSuggestionClick(question)"
                            :disabled="isChatDisabled"
                            class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:text-primary-700 dark:hover:text-primary-400 hover:border-primary-300 dark:hover:border-primary-700 rounded-lg border border-gray-200 dark:border-gray-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-sm hover:shadow-md"
                        >
                            {{ question }}
                        </button>
                    </div>
                </div>
            </div>
        </BaseCard>

        <!-- Input Area -->
        <div class="mt-4">
            <BaseCard padding="normal">
                <ChatInput
                    @send="handleSendMessage"
                    :disabled="isChatDisabled"
                />
                <p v-if="isStoreSyncing" class="text-xs text-amber-600 dark:text-amber-500 mt-3 text-center flex items-center justify-center gap-1.5">
                    <SparklesIcon class="w-4 h-4 animate-spin" />
                    Chat desabilitado durante a sincronização da loja
                </p>
            </BaseCard>
        </div>
    </div>
</template>

