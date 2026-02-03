<script setup>
import { useRouter } from 'vue-router';
import BaseButton from './BaseButton.vue';
import { LockClosedIcon, EyeIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    title: {
        type: String,
        default: 'Recurso não disponível no seu plano'
    },
    description: {
        type: String,
        default: 'Faça upgrade do seu plano para desbloquear este recurso.'
    },
    featureName: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['enable-preview']);

const router = useRouter();

function goToUpgrade() {
    router.push('/settings#plans');
}

function handleEnablePreview() {
    emit('enable-preview', props.featureName);
}
</script>

<template>
    <div class="max-w-2xl mx-auto py-12">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 text-center p-8">
            <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-warning-100 to-warning-200 dark:from-warning-900/30 dark:to-warning-800/30 flex items-center justify-center">
                <LockClosedIcon class="w-10 h-10 text-warning-600 dark:text-warning-400" />
            </div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-3">
                {{ title }}
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-8 max-w-md mx-auto">
                {{ description }}
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <BaseButton
                    variant="primary"
                    size="lg"
                    @click="goToUpgrade"
                    class="bg-gradient-to-r from-primary-500 to-secondary-500 hover:from-primary-600 hover:to-secondary-600"
                >
                    Ver planos disponíveis
                </BaseButton>
                <BaseButton
                    variant="secondary"
                    size="lg"
                    @click="handleEnablePreview"
                >
                    <EyeIcon class="w-5 h-5" />
                    Ver funcionalidade
                </BaseButton>
            </div>
        </div>
    </div>
</template>
