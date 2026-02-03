<script setup lang="ts">
import { useRouter } from 'vue-router';
import { EyeSlashIcon, SparklesIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    featureName: {
        type: String,
        default: 'Esta funcionalidade'
    }
});

const emit = defineEmits(['close']);

const router = useRouter();

function goToUpgrade() {
    router.push('/settings#plans');
}

function handleClose() {
    emit('close');
}
</script>

<template>
    <div class="sticky top-0 left-0 right-0 z-40 bg-gradient-to-r from-amber-500 to-orange-500 shadow-lg mb-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center flex-shrink-0">
                        <EyeSlashIcon class="w-5 h-5 text-white" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white">
                            Modo Visualização
                        </p>
                        <p class="text-xs text-amber-50">
                            {{ featureName }} requer upgrade de plano. Você está visualizando em modo desabilitado.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <button
                        @click="goToUpgrade"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-white text-amber-600 rounded-lg hover:bg-amber-50 transition-colors shadow-sm"
                    >
                        <SparklesIcon class="w-4 h-4" />
                        Fazer Upgrade
                    </button>
                    <button
                        @click="handleClose"
                        class="p-1.5 rounded-lg hover:bg-white/10 transition-colors"
                        aria-label="Fechar"
                    >
                        <XMarkIcon class="w-5 h-5 text-white" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
