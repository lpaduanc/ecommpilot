<script setup>
import { computed } from 'vue';
import { SparklesIcon, UserIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    message: { type: Object, required: true },
});

const isUser = computed(() => props.message.role === 'user');

const formattedTime = computed(() => {
    const date = new Date(props.message.created_at);
    return date.toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    });
});
</script>

<template>
    <div :class="['flex items-start gap-3', isUser ? 'flex-row-reverse' : '']">
        <!-- Avatar -->
        <div
            :class="[
                'w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0',
                isUser ? 'bg-gray-200' : 'bg-gradient-to-br from-primary-500 to-secondary-600'
            ]"
        >
            <UserIcon v-if="isUser" class="w-4 h-4 text-gray-600" />
            <SparklesIcon v-else class="w-4 h-4 text-white" />
        </div>

        <!-- Message -->
        <div :class="['max-w-[80%]', isUser ? 'text-right' : '']">
            <div
                :class="[
                    'px-4 py-3 rounded-2xl',
                    isUser
                        ? 'bg-primary-500 text-white rounded-tr-md'
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-tl-md'
                ]"
            >
                <p class="whitespace-pre-wrap text-sm">{{ message.content }}</p>
            </div>
            <span :class="['text-xs mt-1 block', isUser ? 'text-gray-400' : 'text-gray-400']">
                {{ formattedTime }}
            </span>
        </div>
    </div>
</template>

