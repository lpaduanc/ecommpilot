<script setup>
import { useRouter } from 'vue-router';
import { ExclamationTriangleIcon, CubeIcon } from '@heroicons/vue/24/outline';

const router = useRouter();

const props = defineProps({
    products: { type: Array, default: () => [] },
});

function getStockLevel(quantity) {
    if (quantity === 0) return 'critical';
    if (quantity < 5) return 'danger';
    return 'warning';
}

const stockColors = {
    critical: 'bg-danger-100 text-danger-700',
    danger: 'bg-danger-50 text-danger-600',
    warning: 'bg-accent-50 text-accent-600',
};

function goToProduct(product) {
    router.push({
        path: '/products',
        query: { search: product.name }
    });
}
</script>

<template>
    <div class="space-y-3 max-h-[300px] overflow-y-auto scrollbar-thin">
        <template v-if="products.length > 0">
            <div
                v-for="product in products"
                :key="product.id"
                @click="goToProduct(product)"
                role="button"
                tabindex="0"
                @keydown.enter="goToProduct(product)"
                :aria-label="`Ver detalhes do produto ${product.name}`"
                class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800 hover:bg-primary-50 dark:hover:bg-primary-900/30 cursor-pointer transition-all hover:shadow-sm hover:ring-1 hover:ring-primary-200 dark:hover:ring-primary-700"
            >
                <div class="w-10 h-10 rounded-lg bg-white dark:bg-gray-700 flex items-center justify-center overflow-hidden border border-gray-100 dark:border-gray-600">
                    <img
                        v-if="product.images?.[0]"
                        :src="product.images[0]"
                        :alt="product.name"
                        class="w-full h-full object-cover"
                    />
                    <CubeIcon v-else class="w-5 h-5 text-gray-400" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ product.name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ product.sku || 'N/A' }}</p>
                </div>
                <span
                    :class="[
                        'px-2 py-1 rounded-full text-xs font-semibold flex items-center gap-1',
                        stockColors[getStockLevel(product.stock_quantity)]
                    ]"
                >
                    <ExclamationTriangleIcon v-if="product.stock_quantity === 0" class="w-3 h-3" />
                    {{ product.stock_quantity }} un.
                </span>
            </div>
        </template>
        
        <div v-else class="text-center py-8 text-gray-400 dark:text-gray-500">
            <ExclamationTriangleIcon class="w-12 h-12 mx-auto mb-2 opacity-50" />
            <p class="text-sm">Nenhum produto com estoque baixo</p>
        </div>
    </div>
</template>

