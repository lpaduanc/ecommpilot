<script setup>
import { onMounted } from 'vue';
import { useChatStore } from '../stores/chatStore';
import ChatContainer from '../components/chat/ChatContainer.vue';
import { ChatBubbleLeftRightIcon, SparklesIcon } from '@heroicons/vue/24/outline';

const chatStore = useChatStore();

onMounted(() => {
    chatStore.fetchConversation();
    chatStore.addWelcomeMessage();
});
</script>

<template>
    <div class="min-h-screen -m-8 -mt-8">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 px-8 py-12">
            <!-- Animated Background Elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 rounded-full blur-3xl animate-pulse-soft"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 rounded-full blur-3xl animate-pulse-soft" style="animation-delay: 1s;"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-accent-500/10 rounded-full blur-3xl"></div>
                <!-- Grid Pattern -->
                <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
            </div>
            
            <div class="relative z-10 max-w-7xl mx-auto">
                <div class="flex items-center gap-3">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/30">
                        <ChatBubbleLeftRightIcon class="w-7 h-7 text-white" />
                    </div>
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-display font-bold text-white">
                            Chat IA
                        </h1>
                        <p class="text-primary-200/80 text-sm lg:text-base">
                            Converse com seu assistente de marketing inteligente
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Container -->
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 min-h-[calc(100vh-200px)]">
            <div class="max-w-7xl mx-auto">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden h-[calc(100vh-300px)] animate-fade-in">
                    <ChatContainer />
                </div>
            </div>
        </div>
    </div>
</template>
