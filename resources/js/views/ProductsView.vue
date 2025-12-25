<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import api from '../services/api';
import BaseCard from '../components/common/BaseCard.vue';
import BaseButton from '../components/common/BaseButton.vue';
import BaseInput from '../components/common/BaseInput.vue';
import LoadingSpinner from '../components/common/LoadingSpinner.vue';
import ProductDetailPanel from '../components/dashboard/ProductDetailPanel.vue';
import {
    CubeIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';

const route = useRoute();

const products = ref([]);
const selectedProduct = ref(null);
const isLoading = ref(false);
const searchQuery = ref(route.query.search || '');
const currentPage = ref(1);
const totalPages = ref(1);
const totalItems = ref(0);

const showDetailPanel = computed(() => !!selectedProduct.value);

async function fetchProducts() {
    isLoading.value = true;
    try {
        const response = await api.get('/products', {
            params: {
                search: searchQuery.value,
                page: currentPage.value,
                per_page: 20,
            },
        });
        products.value = response.data.data;
        totalPages.value = response.data.last_page;
        totalItems.value = response.data.total;
    } catch {
        products.value = [];
    } finally {
        isLoading.value = false;
    }
}

function selectProduct(product) {
    selectedProduct.value = product;
}

function closeDetailPanel() {
    selectedProduct.value = null;
}

function handleSearch() {
    currentPage.value = 1;
    fetchProducts();
}

function goToPage(page) {
    if (page < 1 || page > totalPages.value) return;
    currentPage.value = page;
    fetchProducts();
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
}

function getStockStatus(quantity) {
    if (quantity === 0) return { label: 'Sem Estoque', color: 'danger' };
    if (quantity < 10) return { label: 'Estoque Baixo', color: 'warning' };
    return { label: 'Em Estoque', color: 'success' };
}

watch(() => route.query.search, (newSearch) => {
    if (newSearch) {
        searchQuery.value = newSearch;
        handleSearch();
    }
});

onMounted(() => {
    fetchProducts();
});
</script>

<template>
    <div class="min-h-screen -m-8 -mt-8">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 px-8 py-12">
            <!-- Animated Background Elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 rounded-full blur-3xl animate-pulse-soft"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 rounded-full blur-3xl animate-pulse-soft" style="animation-delay: 1s;"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-accent-500/10 rounded-full blur-3xl"></div>
                <!-- Grid Pattern -->
                <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
            </div>
            
            <div class="relative z-10 max-w-7xl mx-auto">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 flex items-center justify-center shadow-lg shadow-primary-500/30">
                                <CubeIcon class="w-7 h-7 text-white" />
                            </div>
                            <div>
                                <h1 class="text-3xl lg:text-4xl font-display font-bold text-white">
                                    Produtos
                                </h1>
                                <p class="text-primary-200/80 text-sm lg:text-base">
                                    {{ totalItems }} produtos sincronizados
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Search Bar -->
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1 max-w-md">
                            <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                            <input
                                v-model="searchQuery"
                                @keyup.enter="handleSearch"
                                type="text"
                                placeholder="Buscar produto por nome ou SKU..."
                                class="w-full pl-12 pr-4 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white placeholder-white/60 focus:bg-white/20 focus:border-white/30 focus:ring-2 focus:ring-primary-500/50 focus:outline-none transition-all"
                            />
                        </div>
                        <button
                            @click="handleSearch"
                            class="px-6 py-3 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 text-white font-medium hover:bg-white/20 transition-all"
                        >
                            <FunnelIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 min-h-[calc(100vh-200px)]">
            <div class="max-w-7xl mx-auto">
                <div class="flex gap-6">
                    <!-- Products Table -->
                    <div :class="['flex-1 transition-all duration-300', showDetailPanel ? 'pr-96' : '']">
                        <BaseCard padding="none" class="overflow-hidden animate-fade-in">
                            <!-- Loading -->
                            <div v-if="isLoading" class="flex items-center justify-center py-20">
                                <div class="relative">
                                    <div class="w-16 h-16 rounded-full bg-gradient-to-r from-primary-500 to-secondary-500 animate-pulse"></div>
                                    <LoadingSpinner size="lg" class="absolute inset-0 m-auto text-white" />
                                </div>
                            </div>

                            <!-- Table -->
                            <div v-else-if="products.length > 0" class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                                        <tr>
                                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                                Produto
                                            </th>
                                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                                SKU
                                            </th>
                                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                                Preço
                                            </th>
                                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                                Estoque
                                            </th>
                                            <th class="text-left px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr
                                            v-for="(product, index) in products"
                                            :key="product.id"
                                            @click="selectProduct(product)"
                                            :class="[
                                                'hover:bg-gradient-to-r hover:from-primary-50/50 hover:to-transparent cursor-pointer transition-all duration-200',
                                                selectedProduct?.id === product.id ? 'bg-primary-50' : '',
                                                'animate-slide-up'
                                            ]"
                                            :style="{ animationDelay: `${index * 30}ms` }"
                                        >
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center overflow-hidden shadow-sm">
                                                        <img
                                                            v-if="product.images?.[0]"
                                                            :src="product.images[0]"
                                                            :alt="product.name"
                                                            class="w-full h-full object-cover"
                                                        />
                                                        <CubeIcon v-else class="w-6 h-6 text-gray-400" />
                                                    </div>
                                                    <div>
                                                        <p class="font-medium text-gray-900">{{ product.name }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                {{ product.sku || '-' }}
                                            </td>
                                            <td class="px-6 py-4">
                                                <p class="font-medium text-gray-900">{{ formatCurrency(product.price) }}</p>
                                                <p v-if="product.compare_at_price" class="text-sm text-gray-400 line-through">
                                                    {{ formatCurrency(product.compare_at_price) }}
                                                </p>
                                            </td>
                                            <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                                {{ product.stock_quantity }}
                                            </td>
                                            <td class="px-6 py-4">
                                                <span
                                                    :class="[
                                                        'badge',
                                                        `badge-${getStockStatus(product.stock_quantity).color}`
                                                    ]"
                                                >
                                                    {{ getStockStatus(product.stock_quantity).label }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <div v-else class="text-center py-20 animate-fade-in">
                                <div class="relative inline-block mb-6">
                                    <div class="w-32 h-32 rounded-3xl bg-gradient-to-br from-primary-100 to-secondary-100 flex items-center justify-center">
                                        <CubeIcon class="w-16 h-16 text-primary-400" />
                                    </div>
                                    <div class="absolute -bottom-2 -right-2 w-8 h-8 rounded-full bg-accent-400 flex items-center justify-center">
                                        <SparklesIcon class="w-4 h-4 text-white" />
                                    </div>
                                </div>
                                <h3 class="text-2xl font-display font-bold text-gray-900 mb-3">
                                    Nenhum produto encontrado
                                </h3>
                                <p class="text-gray-500">
                                    {{ searchQuery ? 'Tente uma busca diferente' : 'Conecte sua loja para sincronizar produtos' }}
                                </p>
                            </div>

                            <!-- Pagination -->
                            <div v-if="totalPages > 1" class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50/50">
                                <p class="text-sm text-gray-500">
                                    Mostrando {{ (currentPage - 1) * 20 + 1 }} a {{ Math.min(currentPage * 20, totalItems) }} de {{ totalItems }}
                                </p>
                                <div class="flex items-center gap-2">
                                    <BaseButton
                                        variant="ghost"
                                        size="sm"
                                        :disabled="currentPage === 1"
                                        @click="goToPage(currentPage - 1)"
                                    >
                                        <ChevronLeftIcon class="w-4 h-4" />
                                    </BaseButton>
                                    <span class="text-sm text-gray-600 px-3">
                                        {{ currentPage }} / {{ totalPages }}
                                    </span>
                                    <BaseButton
                                        variant="ghost"
                                        size="sm"
                                        :disabled="currentPage === totalPages"
                                        @click="goToPage(currentPage + 1)"
                                    >
                                        <ChevronRightIcon class="w-4 h-4" />
                                    </BaseButton>
                                </div>
                            </div>
                        </BaseCard>
                    </div>

                    <!-- Detail Panel -->
                    <ProductDetailPanel
                        v-if="showDetailPanel"
                        :product="selectedProduct"
                        @close="closeDetailPanel"
                        class="animate-slide-left"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
