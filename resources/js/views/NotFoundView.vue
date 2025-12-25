<script setup>
import { useRouter } from 'vue-router';
import BaseButton from '../components/common/BaseButton.vue';
import { HomeIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';

const router = useRouter();

function goBack() {
    router.back();
}

function goHome() {
    router.push({ name: 'dashboard' });
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-8 bg-gradient-to-br from-gray-50 to-gray-100">
        <div class="text-center">
            <div class="relative mb-8">
                <span class="text-[200px] font-display font-bold text-gray-100 leading-none">404</span>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-6xl">🔍</span>
                </div>
            </div>
            
            <h1 class="text-3xl font-display font-bold text-gray-900 mb-4">
                Página não encontrada
            </h1>
            <p class="text-gray-500 mb-8 max-w-md mx-auto">
                Desculpe, não conseguimos encontrar a página que você está procurando.
                Ela pode ter sido removida ou o endereço está incorreto.
            </p>
            
            <div class="flex items-center justify-center gap-4">
                <BaseButton variant="secondary" @click="goBack">
                    <ArrowLeftIcon class="w-4 h-4" />
                    Voltar
                </BaseButton>
                <BaseButton @click="goHome">
                    <HomeIcon class="w-4 h-4" />
                    Ir para o Dashboard
                </BaseButton>
            </div>
        </div>
    </div>
</template>

