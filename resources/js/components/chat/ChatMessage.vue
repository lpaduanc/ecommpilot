<script setup>
import { ref, computed } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import { SparklesIcon, UserIcon, HandThumbUpIcon, HandThumbDownIcon, ClipboardDocumentIcon, CheckIcon } from '@heroicons/vue/24/outline';
import { logger } from '@/utils/logger';

const props = defineProps({
    message: { type: Object, required: true },
});

const copied = ref(false);

const isUser = computed(() => props.message.role === 'user');
const isWelcome = computed(() => props.message.id === 'welcome');

// Configure marked for safe rendering
marked.setOptions({
    breaks: true,
    gfm: true,
});

// Parse markdown content for assistant messages and sanitize HTML
const parsedContent = computed(() => {
    if (isUser.value || isWelcome.value) {
        return props.message.content;
    }
    const html = marked.parse(props.message.content);
    // Sanitize HTML to prevent XSS attacks
    return DOMPurify.sanitize(html, {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'b', 'i', 'ul', 'ol', 'li', 'code', 'pre', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td'],
        ALLOWED_ATTR: ['href', 'target', 'rel'],
        ALLOW_DATA_ATTR: false,
    });
});

const formattedTime = computed(() => {
    const date = new Date(props.message.created_at);
    return date.toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    });
});

async function copyMessage() {
    try {
        await navigator.clipboard.writeText(props.message.content);
        copied.value = true;
        setTimeout(() => copied.value = false, 2000);
    } catch (err) {
        logger.error('Failed to copy:', err);
    }
}
</script>

<template>
    <div :class="['flex items-start gap-3', isUser ? 'flex-row-reverse' : '']">
        <!-- Avatar -->
        <div
            :class="[
                'w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 shadow-sm',
                isUser
                    ? 'bg-gradient-to-br from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-600'
                    : 'bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30'
            ]"
        >
            <UserIcon v-if="isUser" class="w-5 h-5 text-gray-700 dark:text-gray-300" />
            <SparklesIcon v-else class="w-5 h-5 text-primary-600 dark:text-primary-400" />
        </div>

        <!-- Message -->
        <div :class="['flex-1 max-w-3xl', isUser ? 'text-right' : '']">
            <!-- Welcome Message with special formatting -->
            <div v-if="isWelcome" class="text-gray-700 dark:text-gray-300 leading-relaxed">
                <p class="mb-5 text-base">
                    <span class="font-bold text-gray-900 dark:text-gray-100 text-lg">Olá!</span>
                    <span class="text-2xl ml-1">👋</span>
                    <br>
                    Eu sou seu assistente de IA. Estou aqui para ajudá-lo a analisar seus dados de e-commerce e descobrir
                    <span class="text-primary-600 dark:text-primary-400 font-semibold">insights valiosos</span> para o seu negócio.
                </p>

                <div class="bg-gradient-to-br from-primary-50 to-primary-100/50 dark:from-primary-900/20 dark:to-primary-900/10 rounded-xl p-5 mb-5 border border-primary-200 dark:border-primary-800">
                    <p class="font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                        <SparklesIcon class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                        Como posso ajudar:
                    </p>
                    <ul class="space-y-2.5">
                        <li class="flex items-start gap-2 text-gray-700 dark:text-gray-300">
                            <CheckIcon class="w-5 h-5 text-success-600 dark:text-success-400 flex-shrink-0 mt-0.5" />
                            <span>Análise de vendas e performance de produtos</span>
                        </li>
                        <li class="flex items-start gap-2 text-gray-700 dark:text-gray-300">
                            <CheckIcon class="w-5 h-5 text-success-600 dark:text-success-400 flex-shrink-0 mt-0.5" />
                            <span>Insights sobre comportamento de clientes</span>
                        </li>
                        <li class="flex items-start gap-2 text-gray-700 dark:text-gray-300">
                            <CheckIcon class="w-5 h-5 text-success-600 dark:text-success-400 flex-shrink-0 mt-0.5" />
                            <span>Recomendações de estratégias de <span class="text-primary-600 dark:text-primary-400 font-semibold">marketing</span></span>
                        </li>
                        <li class="flex items-start gap-2 text-gray-700 dark:text-gray-300">
                            <CheckIcon class="w-5 h-5 text-success-600 dark:text-success-400 flex-shrink-0 mt-0.5" />
                            <span>Relatórios personalizados e métricas</span>
                        </li>
                    </ul>
                </div>

                <div class="bg-gradient-to-br from-gray-50 to-gray-100/50 dark:from-gray-800/50 dark:to-gray-800/30 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
                    <p class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Para começar, você pode perguntar:</p>
                    <ul class="space-y-2.5">
                        <li class="flex items-start gap-2 text-gray-600 dark:text-gray-400">
                            <span class="text-primary-600 dark:text-primary-400 flex-shrink-0 font-bold">→</span>
                            <span>"Quais são os <span class="text-primary-600 dark:text-primary-400 font-semibold">meus produtos mais vendidos</span> este mês?"</span>
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 dark:text-gray-400">
                            <span class="text-primary-600 dark:text-primary-400 flex-shrink-0 font-bold">→</span>
                            <span>"Como está o desempenho das <span class="text-primary-600 dark:text-primary-400 font-semibold">minhas campanhas</span>?"</span>
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 dark:text-gray-400">
                            <span class="text-primary-600 dark:text-primary-400 flex-shrink-0 font-bold">→</span>
                            <span>"Quais clientes têm <span class="text-primary-600 dark:text-primary-400 font-semibold">maior potencial de recompra</span>?"</span>
                        </li>
                    </ul>
                </div>

                <p class="text-gray-700 dark:text-gray-300 mt-5 text-base">
                    O que gostaria de saber sobre seu negócio hoje? 🚀
                </p>
            </div>

            <!-- Regular Message -->
            <div v-else>
                <!-- User Message -->
                <div
                    v-if="isUser"
                    class="px-5 py-3 rounded-2xl inline-block shadow-sm bg-gradient-to-br from-primary-500 to-primary-600 text-white rounded-tr-md"
                >
                    <p class="whitespace-pre-wrap text-sm leading-relaxed">{{ message.content }}</p>
                </div>

                <!-- Assistant Message with Markdown -->
                <div
                    v-else
                    class="px-5 py-4 rounded-2xl shadow-sm bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-800/50 text-gray-900 dark:text-gray-100 rounded-tl-md border border-gray-200 dark:border-gray-700 max-w-none"
                >
                    <div
                        class="prose prose-sm dark:prose-invert max-w-none prose-headings:text-primary-600 dark:prose-headings:text-primary-400 prose-headings:font-semibold prose-headings:mt-4 prose-headings:mb-2 prose-p:my-2 prose-ul:my-2 prose-ol:my-2 prose-li:my-0.5 prose-table:my-3 prose-th:bg-gray-100 dark:prose-th:bg-gray-700 prose-th:px-3 prose-th:py-2 prose-td:px-3 prose-td:py-2 prose-td:border-gray-200 dark:prose-td:border-gray-600 prose-strong:text-primary-600 dark:prose-strong:text-primary-400"
                        v-html="parsedContent"
                    ></div>
                </div>
            </div>

            <!-- Action Buttons for Assistant Messages -->
            <div v-if="!isUser" class="flex items-center gap-1 mt-2.5">
                <button
                    @click="() => {}"
                    class="p-2 text-gray-400 hover:text-success-600 dark:hover:text-success-400 hover:bg-success-50 dark:hover:bg-success-900/20 rounded-lg transition-all"
                    title="Útil"
                >
                    <HandThumbUpIcon class="w-4 h-4" />
                </button>
                <button
                    @click="() => {}"
                    class="p-2 text-gray-400 hover:text-danger-600 dark:hover:text-danger-400 hover:bg-danger-50 dark:hover:bg-danger-900/20 rounded-lg transition-all"
                    title="Não útil"
                >
                    <HandThumbDownIcon class="w-4 h-4" />
                </button>
                <button
                    @click="copyMessage"
                    :class="[
                        'p-2 rounded-lg transition-all',
                        copied
                            ? 'text-success-600 dark:text-success-400 bg-success-50 dark:bg-success-900/20'
                            : 'text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/20'
                    ]"
                    :title="copied ? 'Copiado!' : 'Copiar'"
                >
                    <CheckIcon v-if="copied" class="w-4 h-4" />
                    <ClipboardDocumentIcon v-else class="w-4 h-4" />
                </button>
                <span v-if="!isWelcome" class="text-xs text-gray-400 dark:text-gray-500 ml-2">{{ formattedTime }}</span>
            </div>

            <!-- Time for User Messages -->
            <span v-if="isUser" class="text-xs text-gray-400 dark:text-gray-500 mt-2 block">
                {{ formattedTime }}
            </span>
        </div>
    </div>
</template>

