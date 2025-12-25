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
                placeholder="Digite sua mensagem..."
                rows="1"
                class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-100 text-gray-900 placeholder-gray-400 resize-none focus:bg-white focus:border-primary-200 focus:ring-2 focus:ring-primary-500/20 focus:outline-none transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                style="min-height: 48px; max-height: 120px;"
            ></textarea>
        </div>
        <button
            type="submit"
            :disabled="!message.trim() || disabled"
            class="p-3 rounded-xl bg-gradient-to-r from-primary-600 to-primary-500 text-white shadow-lg shadow-primary-500/25 hover:shadow-xl hover:shadow-primary-500/30 transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none"
        >
            <PaperAirplaneIcon class="w-5 h-5" />
        </button>
    </form>
</template>

