<script setup>
import { computed, ref, watch } from 'vue';
import api from '../../services/api';
import BaseButton from '../common/BaseButton.vue';
import LoadingSpinner from '../common/LoadingSpinner.vue';
import { XMarkIcon, CubeIcon, CalendarIcon, ChartBarIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    product: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const performance = ref(null);
const isLoadingPerformance = ref(false);

const stockStatus = computed(() => {
    const qty = props.product?.stock_quantity || 0;
    if (qty === 0) return { label: 'Sem Estoque', color: 'danger' };
    if (qty < 10) return { label: 'Estoque Baixo', color: 'warning' };
    return { label: 'Em Estoque', color: 'success' };
});

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value || 0);
}

function formatDate(date) {
    if (!date) return 'N/A';
    return new Date(date).toLocaleDateString('pt-BR');
}

async function fetchPerformance() {
    if (!props.product?.id) return;
    
    isLoadingPerformance.value = true;
    try {
        const response = await api.get(`/products/${props.product.id}/performance`);
        performance.value = response.data;
    } catch {
        performance.value = null;
    } finally {
        isLoadingPerformance.value = false;
    }
}

// Watch for product changes and fetch performance
watch(() => props.product?.id, (newId) => {
    if (newId) {
        fetchPerformance();
    }
}, { immediate: true });
</script>

<template>
    <!-- Backdrop for mobile -->
    <div
        class="fixed inset-0 bg-black/50 z-40 xl:hidden"
        @click="emit('close')"
    ></div>

    <div class="fixed inset-y-0 right-0 w-full sm:w-96 max-w-full bg-white dark:bg-gray-800 shadow-2xl border-l border-gray-100 dark:border-gray-700 z-50 overflow-y-auto">
        <!-- Header -->
        <div class="sticky top-0 bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900 dark:text-gray-100">Detalhes do Produto</h3>
            <button
                @click="emit('close')"
                class="p-2 rounded-lg text-gray-400 hover:text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            >
                <XMarkIcon class="w-5 h-5" />
            </button>
        </div>

        <!-- Content -->
        <div class="p-4 sm:p-6 space-y-4 sm:space-y-6">
            <!-- Product Image -->
            <div class="aspect-square rounded-xl sm:rounded-2xl bg-gray-100 dark:bg-gray-700 overflow-hidden">
                <img
                    v-if="product.images?.[0]"
                    :src="product.images[0]"
                    :alt="product.name"
                    class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full flex items-center justify-center">
                    <CubeIcon class="w-12 sm:w-16 h-12 sm:h-16 text-gray-300 dark:text-gray-500" />
                </div>
            </div>

            <!-- Product Info -->
            <div>
                <h2 class="text-lg sm:text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ product.name }}</h2>
                <span :class="['badge', `badge-${stockStatus.color}`]">
                    {{ stockStatus.label }}
                </span>
            </div>

            <!-- Price Section -->
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3 sm:p-4">
                <div class="flex items-baseline gap-2">
                    <span class="text-xl sm:text-2xl font-bold text-gray-900 dark:text-gray-100">{{ formatCurrency(product.price) }}</span>
                    <span
                        v-if="product.compare_at_price"
                        class="text-sm text-gray-400 line-through"
                    >
                        {{ formatCurrency(product.compare_at_price) }}
                    </span>
                </div>
                <p v-if="product.compare_at_price" class="text-sm text-success-600 dark:text-success-400 mt-1">
                    {{ Math.round((1 - product.price / product.compare_at_price) * 100) }}% de desconto
                </p>
            </div>

            <!-- Details Grid -->
            <div class="grid grid-cols-2 gap-3 sm:gap-4">
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3 sm:p-4">
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mb-1">SKU</p>
                    <p class="font-semibold text-gray-900 dark:text-gray-100 text-sm sm:text-base truncate">{{ product.sku || 'N/A' }}</p>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-3 sm:p-4">
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mb-1">Estoque</p>
                    <p class="font-semibold text-gray-900 dark:text-gray-100 text-sm sm:text-base">{{ product.stock_quantity }} un.</p>
                </div>
            </div>

            <!-- Performance Section -->
            <div class="space-y-3">
                <h4 class="font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2 text-sm sm:text-base">
                    <ChartBarIcon class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400" />
                    Desempenho (30 dias)
                </h4>

                <div v-if="isLoadingPerformance" class="flex items-center justify-center py-6">
                    <LoadingSpinner size="md" class="text-primary-500" />
                </div>

                <div v-else class="space-y-2 sm:space-y-3">
                    <div class="flex items-center justify-between p-2.5 sm:p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <span class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Qtd. Vendida</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100 text-sm">
                            {{ performance?.quantity_sold ?? 0 }} un.
                        </span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 sm:p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <span class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Receita</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100 text-sm">
                            {{ formatCurrency(performance?.revenue_generated ?? 0) }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between p-2.5 sm:p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <span class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">Média/Dia</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100 text-sm">
                            {{ performance?.average_per_day ?? 0 }} un.
                        </span>
                    </div>
                </div>
            </div>

            <!-- Dates -->
            <div class="space-y-2 pt-4 border-t border-gray-100 dark:border-gray-700">
                <div class="flex items-center justify-between text-xs sm:text-sm">
                    <span class="text-gray-500 dark:text-gray-400 flex items-center gap-2">
                        <CalendarIcon class="w-4 h-4" />
                        Criado em
                    </span>
                    <span class="text-gray-900 dark:text-gray-100">{{ formatDate(product.external_created_at) }}</span>
                </div>
                <div class="flex items-center justify-between text-xs sm:text-sm">
                    <span class="text-gray-500 dark:text-gray-400 flex items-center gap-2">
                        <CalendarIcon class="w-4 h-4" />
                        Atualizado em
                    </span>
                    <span class="text-gray-900 dark:text-gray-100">{{ formatDate(product.external_updated_at) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
