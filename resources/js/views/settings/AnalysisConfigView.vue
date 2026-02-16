<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import { useNotificationStore } from '@/stores/notificationStore';
import api from '@/services/api';
import BaseCard from '@/components/common/BaseCard.vue';
import BaseButton from '@/components/common/BaseButton.vue';
import LoadingSpinner from '@/components/common/LoadingSpinner.vue';
import {
    ChartBarIcon,
    MagnifyingGlassIcon,
    XMarkIcon,
    Cog6ToothIcon,
    CubeIcon,
    GiftIcon,
    ArchiveBoxXMarkIcon,
    EyeSlashIcon,
} from '@heroicons/vue/24/outline';

const authStore = useAuthStore();
const notificationStore = useNotificationStore();

// State
const isLoading = ref(false);
const isSaving = ref(false);
const activeTab = ref('products');
const storeId = ref(null);

// Product search
const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const showSearchDropdown = ref(false);
let searchTimeout = null;

// Configuration form
const config = reactive({
    excluded_product_ids: [],
    excluded_products: [],
    exclude_zero_stock: false,
    exclude_gift_products: false,
    exclude_inactive_products: false,
});

const canEdit = computed(() => {
    return authStore.hasPermission('integrations.manage');
});

// Watch search query for debounced search
watch(searchQuery, (newValue) => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    if (newValue.length < 2) {
        searchResults.value = [];
        showSearchDropdown.value = false;
        return;
    }

    isSearching.value = true;
    searchTimeout = setTimeout(async () => {
        await searchProducts(newValue);
    }, 300);
});

async function loadStoreAndConfig() {
    isLoading.value = true;
    try {
        const response = await api.get('/integrations/my-stores');
        const activeId = response.data.active_store_id;
        if (!activeId) {
            notificationStore.error('Nenhuma loja selecionada');
            return;
        }
        storeId.value = activeId;
        await loadConfig();
    } catch {
        notificationStore.error('Erro ao carregar informações da loja');
    } finally {
        isLoading.value = false;
    }
}

async function loadConfig() {
    if (!storeId.value) return;

    try {
        const response = await api.get(`/stores/${storeId.value}/analysis-config`);
        const data = response.data.data.products;

        config.excluded_product_ids = data.excluded_product_ids || [];
        config.excluded_products = data.excluded_products || [];
        config.exclude_zero_stock = data.exclude_zero_stock || false;
        config.exclude_gift_products = data.exclude_gift_products || false;
        config.exclude_inactive_products = data.exclude_inactive_products || false;
    } catch (error) {
        notificationStore.error(
            error.response?.data?.message || 'Erro ao carregar configurações de análise'
        );
    }
}

async function searchProducts(query) {
    if (!storeId.value) return;

    try {
        const response = await api.get(
            `/stores/${storeId.value}/analysis-config/products/search`,
            { params: { search: query } }
        );

        // Filter out products that are already excluded
        searchResults.value = response.data.data.filter(
            product => !config.excluded_product_ids.includes(product.id)
        );

        showSearchDropdown.value = searchResults.value.length > 0;
    } catch (error) {
        console.error('Erro ao buscar produtos:', error);
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
}

function addProductToExcluded(product) {
    if (config.excluded_product_ids.includes(product.id)) {
        return;
    }

    config.excluded_product_ids.push(product.id);
    config.excluded_products.push({
        id: product.id,
        name: product.name,
        sku: product.sku,
        price: product.price,
        image: product.image,
    });

    // Clear search
    searchQuery.value = '';
    searchResults.value = [];
    showSearchDropdown.value = false;
}

function removeProductFromExcluded(productId) {
    const index = config.excluded_product_ids.indexOf(productId);
    if (index > -1) {
        config.excluded_product_ids.splice(index, 1);
        config.excluded_products.splice(index, 1);
    }
}

async function saveConfig() {
    if (!storeId.value) {
        notificationStore.error('Nenhuma loja selecionada');
        return;
    }

    if (!canEdit.value) {
        notificationStore.error('Você não possui permissão para salvar esta configuração');
        return;
    }

    isSaving.value = true;
    try {
        await api.put(`/stores/${storeId.value}/analysis-config`, {
            products: {
                excluded_product_ids: config.excluded_product_ids,
                exclude_zero_stock: config.exclude_zero_stock,
                exclude_gift_products: config.exclude_gift_products,
                exclude_inactive_products: config.exclude_inactive_products,
            },
        });

        notificationStore.success('Configurações salvas com sucesso!');
    } catch (error) {
        notificationStore.error(
            error.response?.data?.message || 'Erro ao salvar configurações'
        );
    } finally {
        isSaving.value = false;
    }
}

function formatCurrency(value) {
    if (!value) return 'R$ 0,00';
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
}

onMounted(() => {
    loadStoreAndConfig();
});
</script>

<template>
    <div class="min-h-screen -m-8 -mt-8">
        <!-- Hero Header with Gradient -->
        <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-8 py-12">
            <!-- Background Elements -->
            <div class="absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500/20 rounded-full blur-3xl"></div>
                <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-secondary-500/20 rounded-full blur-3xl"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-accent-500/10 rounded-full blur-3xl"></div>
                <!-- Grid Pattern -->
                <div class="absolute inset-0" style="background-image: radial-gradient(rgba(255,255,255,0.03) 1px, transparent 1px); background-size: 40px 40px;"></div>
            </div>

            <div class="relative z-10 max-w-7xl mx-auto">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="w-10 sm:w-12 lg:w-14 h-10 sm:h-12 lg:h-14 rounded-xl sm:rounded-2xl bg-gradient-to-br from-blue-400 to-indigo-500 flex items-center justify-center shadow-lg shadow-blue-500/30 flex-shrink-0">
                        <ChartBarIcon class="w-5 sm:w-6 lg:w-7 h-5 sm:h-6 lg:h-7 text-white" />
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-xl sm:text-2xl lg:text-4xl font-display font-bold text-white">
                            Configuração de Análise
                        </h1>
                        <p class="text-primary-200/80 text-xs sm:text-sm lg:text-base">
                            Configure quais produtos devem ser considerados nas análises AI
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="px-8 py-8 bg-gradient-to-b from-gray-100 to-gray-50 dark:from-gray-900 dark:to-gray-950 min-h-[calc(100vh-200px)]">
            <!-- Loading State -->
            <div v-if="isLoading" class="flex items-center justify-center py-32">
                <LoadingSpinner size="xl" />
            </div>

            <!-- Content -->
            <div v-else class="max-w-5xl mx-auto space-y-8">
                <!-- Tabs Navigation -->
                <div class="flex items-center gap-2 border-b border-gray-200 dark:border-gray-700">
                    <button
                        @click="activeTab = 'products'"
                        class="px-4 py-3 text-sm font-medium border-b-2 transition-colors"
                        :class="activeTab === 'products'
                            ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                    >
                        <div class="flex items-center gap-2">
                            <CubeIcon class="w-4 h-4" />
                            Produtos
                        </div>
                    </button>
                </div>

                <!-- Products Tab -->
                <div v-if="activeTab === 'products'" class="space-y-6">
                    <!-- Search Section -->
                    <BaseCard padding="lg">
                        <div class="mb-6">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                                    <MagnifyingGlassIcon class="w-4 h-4 text-white" />
                                </div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Adicionar Produtos para Exclusão
                                </h2>
                            </div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">
                                Busque e selecione produtos que não devem ser considerados nas análises
                            </p>
                        </div>

                        <!-- Search Input -->
                        <div class="relative">
                            <div class="relative">
                                <MagnifyingGlassIcon class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Digite o nome ou SKU do produto..."
                                    :disabled="!canEdit"
                                    class="w-full pl-11 pr-4 py-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition-all disabled:opacity-60 disabled:cursor-not-allowed"
                                />
                                <div v-if="isSearching" class="absolute right-4 top-1/2 -translate-y-1/2">
                                    <LoadingSpinner size="sm" />
                                </div>
                            </div>

                            <!-- Search Dropdown -->
                            <Transition
                                enter-active-class="transition ease-out duration-100"
                                enter-from-class="transform opacity-0 scale-95"
                                enter-to-class="transform opacity-100 scale-100"
                                leave-active-class="transition ease-in duration-75"
                                leave-from-class="transform opacity-100 scale-100"
                                leave-to-class="transform opacity-0 scale-95"
                            >
                                <div
                                    v-if="showSearchDropdown && searchResults.length > 0"
                                    class="absolute z-50 w-full mt-2 rounded-lg shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 dark:ring-gray-700 max-h-80 overflow-y-auto"
                                >
                                    <button
                                        v-for="product in searchResults"
                                        :key="product.id"
                                        @click="addProductToExcluded(product)"
                                        class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                                    >
                                        <!-- Product Image -->
                                        <div class="flex-shrink-0 w-12 h-12 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700">
                                            <img
                                                v-if="product.image"
                                                :src="product.image"
                                                :alt="product.name"
                                                class="w-full h-full object-cover"
                                            />
                                            <div v-else class="w-full h-full flex items-center justify-center">
                                                <CubeIcon class="w-6 h-6 text-gray-400" />
                                            </div>
                                        </div>

                                        <!-- Product Info -->
                                        <div class="flex-1 text-left min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                                {{ product.name }}
                                            </p>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ product.sku || 'Sem SKU' }}
                                                </p>
                                                <span class="text-gray-300 dark:text-gray-600">•</span>
                                                <p class="text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    {{ formatCurrency(product.price) }}
                                                </p>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </Transition>
                        </div>

                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
                            Digite pelo menos 2 caracteres para buscar
                        </p>
                    </BaseCard>

                    <!-- Excluded Products List -->
                    <BaseCard padding="lg">
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-red-500 to-pink-600 flex items-center justify-center">
                                        <EyeSlashIcon class="w-4 h-4 text-white" />
                                    </div>
                                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        Produtos Excluídos
                                    </h2>
                                </div>
                                <span
                                    v-if="config.excluded_products.length > 0"
                                    class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300"
                                >
                                    {{ config.excluded_products.length }} {{ config.excluded_products.length === 1 ? 'produto' : 'produtos' }}
                                </span>
                            </div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">
                                Produtos que não serão considerados nas análises AI
                            </p>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-if="config.excluded_products.length === 0"
                            class="text-center py-12 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl"
                        >
                            <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mx-auto mb-4">
                                <EyeSlashIcon class="w-8 h-8 text-gray-400" />
                            </div>
                            <p class="text-gray-500 dark:text-gray-400">
                                Nenhum produto excluído
                            </p>
                            <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
                                Use a busca acima para adicionar produtos
                            </p>
                        </div>

                        <!-- Products Grid -->
                        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div
                                v-for="product in config.excluded_products"
                                :key="product.id"
                                class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 hover:shadow-md transition-shadow group"
                            >
                                <div class="flex items-start gap-3">
                                    <!-- Product Image -->
                                    <div class="flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700">
                                        <img
                                            v-if="product.image"
                                            :src="product.image"
                                            :alt="product.name"
                                            class="w-full h-full object-cover"
                                        />
                                        <div v-else class="w-full h-full flex items-center justify-center">
                                            <CubeIcon class="w-8 h-8 text-gray-400" />
                                        </div>
                                    </div>

                                    <!-- Product Info -->
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 line-clamp-2">
                                            {{ product.name }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ product.sku || 'Sem SKU' }}
                                        </p>
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mt-1">
                                            {{ formatCurrency(product.price) }}
                                        </p>
                                    </div>

                                    <!-- Remove Button -->
                                    <button
                                        v-if="canEdit"
                                        @click="removeProductFromExcluded(product.id)"
                                        class="p-2 rounded-lg text-danger-500 hover:bg-danger-50 dark:hover:bg-danger-900/20 transition-colors opacity-0 group-hover:opacity-100"
                                        title="Remover da lista"
                                    >
                                        <XMarkIcon class="w-5 h-5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </BaseCard>

                    <!-- Auto-Exclusion Rules -->
                    <BaseCard padding="lg">
                        <div class="mb-6">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center">
                                    <Cog6ToothIcon class="w-4 h-4 text-white" />
                                </div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Regras de Exclusão Automática
                                </h2>
                            </div>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">
                                Configure regras para excluir produtos automaticamente com base em suas características
                            </p>
                        </div>

                        <div class="space-y-4">
                            <!-- Exclude Zero Stock -->
                            <div class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center">
                                    <ArchiveBoxXMarkIcon class="w-5 h-5 text-white" />
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        Produtos sem estoque
                                    </h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Exclui produtos com quantidade em estoque igual a zero
                                    </p>
                                </div>
                                <button
                                    @click="config.exclude_zero_stock = !config.exclude_zero_stock"
                                    :disabled="!canEdit"
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-60 disabled:cursor-not-allowed"
                                    :class="config.exclude_zero_stock ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-600'"
                                >
                                    <span
                                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        :class="config.exclude_zero_stock ? 'translate-x-5' : 'translate-x-0'"
                                    ></span>
                                </button>
                            </div>

                            <!-- Exclude Gift Products -->
                            <div class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-gradient-to-br from-pink-500 to-rose-600 flex items-center justify-center">
                                    <GiftIcon class="w-5 h-5 text-white" />
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        Brindes e presentes
                                    </h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Exclui produtos marcados como brinde ou presente
                                    </p>
                                </div>
                                <button
                                    @click="config.exclude_gift_products = !config.exclude_gift_products"
                                    :disabled="!canEdit"
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-60 disabled:cursor-not-allowed"
                                    :class="config.exclude_gift_products ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-600'"
                                >
                                    <span
                                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        :class="config.exclude_gift_products ? 'translate-x-5' : 'translate-x-0'"
                                    ></span>
                                </button>
                            </div>

                            <!-- Exclude Inactive Products -->
                            <div class="flex items-start gap-4 p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                                <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-gradient-to-br from-gray-500 to-gray-600 flex items-center justify-center">
                                    <EyeSlashIcon class="w-5 h-5 text-white" />
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        Produtos inativos
                                    </h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Exclui produtos que foram desativados na plataforma
                                    </p>
                                </div>
                                <button
                                    @click="config.exclude_inactive_products = !config.exclude_inactive_products"
                                    :disabled="!canEdit"
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-60 disabled:cursor-not-allowed"
                                    :class="config.exclude_inactive_products ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-600'"
                                >
                                    <span
                                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        :class="config.exclude_inactive_products ? 'translate-x-5' : 'translate-x-0'"
                                    ></span>
                                </button>
                            </div>
                        </div>
                    </BaseCard>

                    <!-- Save Button -->
                    <div class="flex justify-end">
                        <BaseButton
                            v-if="canEdit"
                            @click="saveConfig"
                            :loading="isSaving"
                            :disabled="isLoading"
                        >
                            Salvar Configurações
                        </BaseButton>
                    </div>

                    <p v-if="!canEdit" class="text-center text-sm text-gray-500 dark:text-gray-400 italic">
                        Você não possui permissão para editar as configurações de análise.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
