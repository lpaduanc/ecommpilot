<script setup>
import { useRouter } from 'vue-router';
import BaseButton from '../common/BaseButton.vue';
import { LinkIcon, SparklesIcon, ChartBarIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';

const router = useRouter();

function goToIntegrations() {
    router.push({ name: 'integrations' });
}
</script>

<template>
    <div class="max-w-2xl mx-auto text-center py-16">
        <!-- Icon -->
        <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-primary-500 to-secondary-600 flex items-center justify-center mx-auto mb-8 shadow-lg shadow-primary-500/30">
            <LinkIcon class="w-12 h-12 text-white" />
        </div>

        <!-- Title -->
        <h2 class="text-3xl font-display font-bold text-gray-900 dark:text-gray-100 mb-4">
            Conecte sua loja para começar
        </h2>
        <p class="text-lg text-gray-500 dark:text-gray-400 mb-8">
            Para visualizar seu dashboard e receber análises inteligentes, 
            primeiro conecte sua loja de e-commerce.
        </p>

        <!-- CTA -->
        <BaseButton size="lg" @click="goToIntegrations">
            <LinkIcon class="w-5 h-5" />
            Conectar Loja
        </BaseButton>

        <!-- Features -->
        <div class="mt-8 sm:mt-12 md:mt-16 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8">
            <div class="text-center">
                <div class="w-14 h-14 rounded-2xl bg-primary-100 flex items-center justify-center mx-auto mb-4">
                    <ChartBarIcon class="w-7 h-7 text-primary-600" />
                </div>
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Dashboard Completo</h3>
                <p class="text-sm text-gray-500">
                    Visualize receita, pedidos, produtos e muito mais em tempo real
                </p>
            </div>

            <div class="text-center">
                <div class="w-14 h-14 rounded-2xl bg-secondary-100 flex items-center justify-center mx-auto mb-4">
                    <SparklesIcon class="w-7 h-7 text-secondary-600" />
                </div>
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Análises com IA</h3>
                <p class="text-sm text-gray-500">
                    Receba sugestões personalizadas para aumentar suas vendas
                </p>
            </div>

            <div class="text-center">
                <div class="w-14 h-14 rounded-2xl bg-accent-100 flex items-center justify-center mx-auto mb-4">
                    <ChatBubbleLeftRightIcon class="w-7 h-7 text-accent-600" />
                </div>
                <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Chat Inteligente</h3>
                <p class="text-sm text-gray-500">
                    Tire dúvidas e explore estratégias com nosso assistente IA
                </p>
            </div>
        </div>
    </div>
</template>

