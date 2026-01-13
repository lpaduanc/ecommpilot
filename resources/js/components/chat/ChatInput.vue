<script setup>
import { ref } from 'vue';
import { PaperAirplaneIcon } from '@heroicons/vue/24/solid';

const props = defineProps({
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['send']);

const message = ref('');

function handleSubmit() {
    if (!message.value.trim() || props.disabled) return;

    emit('send', message.value);
    message.value = '';
}

function handleKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleSubmit();
    }
}
</script>

<template>
    <form @submit.prevent="handleSubmit" class="flex items-end gap-3">
        <div class="flex-1 relative">
            <textarea
                v-model="message"
                @keydown="handleKeydown"
                :disabled="disabled"
                placeholder="Digite sua pergunta sobre vendas, produtos, clientes..."
                rows="1"
                class="w-full px-4 py-3 rounded-xl bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 resize-none focus:border-primary-400 dark:focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 focus:outline-none transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                style="min-height: 52px; max-height: 120px;"
            ></textarea>
        </div>
        <button
            type="submit"
            :disabled="!message.trim() || disabled"
            class="p-3.5 rounded-xl bg-gradient-to-r from-primary-500 to-primary-600 hover:from-primary-600 hover:to-primary-700 text-white transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-lg hover:shadow-xl disabled:shadow-sm"
        >
            <PaperAirplaneIcon class="w-5 h-5" />
        </button>
    </form>
</template>

