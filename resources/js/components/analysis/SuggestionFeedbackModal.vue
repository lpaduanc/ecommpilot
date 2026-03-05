<script setup>
import { ref, computed, watch } from 'vue';
import BaseModal from '../common/BaseModal.vue';
import BaseButton from '../common/BaseButton.vue';
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    reviewedSuggestions: {
        type: Number,
        default: 0,
    },
    totalSuggestions: {
        type: Number,
        default: 0,
    },
    completedAnalyses: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['close', 'proceed']);

const notHelpful = ref(false);
const reason = ref('');
const reasonError = ref(false);

const canProceed = computed(() => {
    if (notHelpful.value) {
        return reason.value.trim().length > 0;
    }
    return true;
});

function resetState() {
    notHelpful.value = false;
    reason.value = '';
    reasonError.value = false;
}

function handleClose() {
    resetState();
    emit('close');
}

function handleProceed() {
    if (notHelpful.value && reason.value.trim().length === 0) {
        reasonError.value = true;
        return;
    }

    const feedbackData = notHelpful.value ? { reason: reason.value.trim() } : null;
    resetState();
    emit('proceed', feedbackData);
}

watch(() => reason.value, () => {
    if (reasonError.value && reason.value.trim().length > 0) {
        reasonError.value = false;
    }
});

watch(() => props.show, (val) => {
    if (!val) {
        resetState();
    }
});
</script>

<template>
    <BaseModal
        :show="show"
        size="md"
        @close="handleClose"
    >
        <div class="py-2">
            <!-- Icon -->
            <div class="flex justify-center mb-5">
                <div class="w-16 h-16 rounded-2xl bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center">
                    <ChatBubbleLeftRightIcon class="w-8 h-8 text-amber-600 dark:text-amber-400" />
                </div>
            </div>

            <!-- Title -->
            <h3 class="text-xl font-bold text-gray-900 dark:text-white text-center mb-4">
                Revise suas sugest&otilde;es anteriores
            </h3>

            <!-- Context box -->
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl p-4 mb-4">
                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                    Voc&ecirc; tem
                    <span class="font-semibold">{{ completedAnalyses }} {{ completedAnalyses === 1 ? 'an&aacute;lise completa' : 'an&aacute;lises completas' }}</span>
                    com
                    <span class="font-semibold">{{ totalSuggestions }} {{ totalSuggestions === 1 ? 'sugest&atilde;o' : 'sugest&otilde;es' }}</span>,
                    mas apenas
                    <span class="font-semibold">{{ reviewedSuggestions }}</span>
                    {{ reviewedSuggestions === 1 ? 'foi avaliada' : 'foram avaliadas' }}.
                </p>
            </div>

            <!-- Explanation paragraph -->
            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mb-5">
                Avaliar as sugest&otilde;es anteriores &mdash; indicando se funcionaram ou n&atilde;o &mdash; ajuda a IA a aprender com o contexto da sua loja e gerar recomenda&ccedil;&otilde;es cada vez mais precisas e relevantes nas pr&oacute;ximas an&aacute;lises.
            </p>

            <!-- Checkbox -->
            <label class="flex items-start gap-3 cursor-pointer mb-4 group">
                <div class="relative flex-shrink-0 mt-0.5">
                    <input
                        v-model="notHelpful"
                        type="checkbox"
                        class="sr-only peer"
                    />
                    <div
                        class="w-5 h-5 rounded border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 peer-checked:border-amber-500 peer-checked:bg-amber-500 dark:peer-checked:border-amber-500 dark:peer-checked:bg-amber-500 transition-colors flex items-center justify-center"
                        :class="notHelpful ? 'border-amber-500 bg-amber-500 dark:border-amber-500 dark:bg-amber-500' : ''"
                    >
                        <svg
                            v-if="notHelpful"
                            class="w-3 h-3 text-white"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="3"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
                <span class="text-sm text-gray-700 dark:text-gray-300 leading-snug group-hover:text-gray-900 dark:group-hover:text-gray-100 transition-colors">
                    As an&aacute;lises anteriores n&atilde;o me ajudaram
                </span>
            </label>

            <!-- Conditional textarea -->
            <Transition
                enter-active-class="transition-all duration-200 ease-out overflow-hidden"
                enter-from-class="opacity-0 max-h-0"
                enter-to-class="opacity-100 max-h-40"
                leave-active-class="transition-all duration-150 ease-in overflow-hidden"
                leave-from-class="opacity-100 max-h-40"
                leave-to-class="opacity-0 max-h-0"
            >
                <div v-if="notHelpful" class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Conte-nos o motivo <span class="text-danger-500">*</span>
                    </label>
                    <textarea
                        v-model="reason"
                        rows="3"
                        placeholder="Ex: As sugestões eram muito genéricas e não se aplicavam ao meu nicho de mercado..."
                        :class="[
                            'w-full px-3 py-2.5 text-sm rounded-lg border transition-colors resize-none',
                            'bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100',
                            'placeholder-gray-400 dark:placeholder-gray-500',
                            'focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-0',
                            reasonError
                                ? 'border-danger-400 dark:border-danger-500 focus:ring-danger-500'
                                : 'border-gray-300 dark:border-gray-600 focus:border-primary-500 dark:focus:border-primary-500'
                        ]"
                    ></textarea>
                    <p
                        v-if="reasonError"
                        class="mt-1.5 text-xs text-danger-600 dark:text-danger-400"
                    >
                        Por favor, descreva o motivo para continuar.
                    </p>
                </div>
            </Transition>

            <!-- Footer buttons -->
            <div class="flex gap-3 mt-2">
                <button
                    type="button"
                    @click="handleClose"
                    class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                >
                    Voltar e revisar
                </button>
                <button
                    type="button"
                    @click="handleProceed"
                    :disabled="!canProceed"
                    :class="[
                        'flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition-all',
                        canProceed
                            ? 'bg-gradient-to-r from-primary-500 to-secondary-500 shadow-md hover:shadow-lg'
                            : 'bg-gray-300 dark:bg-gray-600 cursor-not-allowed opacity-60'
                    ]"
                >
                    Prosseguir mesmo assim
                </button>
            </div>
        </div>
    </BaseModal>
</template>
