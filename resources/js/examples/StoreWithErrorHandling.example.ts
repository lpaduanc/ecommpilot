/**
 * EXEMPLO: Store com Tratamento de Erros Padronizado
 *
 * Este arquivo demonstra as melhores práticas para implementar uma Pinia store
 * com o novo padrão de tratamento de erros.
 *
 * IMPORTANTE: Este é apenas um exemplo de referência.
 * Não importe este arquivo em código de produção.
 */

import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api, { createCancelableRequest, apiWithCustomTimeout } from '../services/api';
import { handleApiCall, type Result } from '../utils/apiHelpers';
import { dedupeRequest, buildCacheKey, clearRequestCache } from '../utils/requestCache';
import { retryRequest } from '../utils/retryRequest';
import { useNotificationStore } from '../stores/notificationStore';

/**
 * Types
 */
interface Product {
  id: number;
  name: string;
  price: number;
  stock: number;
}

interface ProductFilters {
  search: string;
  category: string | null;
  minPrice: number | null;
  maxPrice: number | null;
}

/**
 * Example Store
 */
export const useProductStore = defineStore('products', () => {
  /**
   * STATE
   */
  const products = ref<Product[]>([]);
  const selectedProduct = ref<Product | null>(null);
  const isLoading = ref(false);
  const error = ref<string | null>(null);
  const filters = ref<ProductFilters>({
    search: '',
    category: null,
    minPrice: null,
    maxPrice: null,
  });

  /**
   * COMPUTED
   */
  const hasProducts = computed(() => products.value.length > 0);
  const filteredCount = computed(() => products.value.length);

  /**
   * ACTIONS
   */

  /**
   * EXEMPLO 1: Fetch básico com handleApiCall
   */
  async function fetchProducts(): Promise<Result<Product[]>> {
    isLoading.value = true;
    error.value = null;

    const result = await handleApiCall<Product[]>(
      () => api.get('/products')
    );

    isLoading.value = false;

    if (result.success) {
      products.value = result.data;
    } else {
      error.value = result.error.message;
    }

    return result;
  }

  /**
   * EXEMPLO 2: Fetch com deduplicação de requests
   */
  async function fetchProductsWithDedup(): Promise<void> {
    isLoading.value = true;
    error.value = null;

    try {
      const params = buildFilterParams();
      const cacheKey = buildCacheKey('products-list', params);

      // Múltiplas chamadas simultâneas são deduplicadas
      const response = await dedupeRequest(
        cacheKey,
        () => api.get('/products', { params }),
        5000 // TTL: 5 segundos
      );

      products.value = response.data;
    } catch (err: any) {
      error.value = err.message || 'Erro ao carregar produtos';
    } finally {
      isLoading.value = false;
    }
  }

  /**
   * EXEMPLO 3: Request cancelável (útil para search/autocomplete)
   */
  async function searchProducts(query: string): Promise<void> {
    const { cancelToken, cleanup } = createCancelableRequest('product-search');

    try {
      // Use timeout curto para searches
      const response = await apiWithCustomTimeout.short.get('/products/search', {
        params: { q: query },
        cancelToken,
      });

      products.value = response.data;
    } catch (err) {
      // Não mostre erro se foi cancelado
      if (!api.isCancel?.(err)) {
        error.value = 'Erro ao buscar produtos';
      }
    } finally {
      cleanup();
    }
  }

  /**
   * EXEMPLO 4: Operação com retry manual customizado
   */
  async function syncProductsFromAPI(): Promise<Result<void>> {
    isLoading.value = true;
    const notificationStore = useNotificationStore();

    const result = await handleApiCall<void>(
      () => retryRequest(
        () => apiWithCustomTimeout.long.post('/products/sync'),
        {
          maxRetries: 5,
          delay: 2000,
          backoff: true,
          onRetry: (attempt, error) => {
            notificationStore.warning(
              `Tentando sincronizar novamente (tentativa ${attempt})...`
            );
          }
        }
      )
    );

    isLoading.value = false;

    if (result.success) {
      notificationStore.success('Produtos sincronizados com sucesso!');
    } else {
      notificationStore.error(`Falha na sincronização: ${result.error.message}`);
    }

    return result;
  }

  /**
   * EXEMPLO 5: Fetch de produto único
   */
  async function fetchProduct(id: number): Promise<Result<Product>> {
    const result = await handleApiCall<Product>(
      () => api.get(`/products/${id}`)
    );

    if (result.success) {
      selectedProduct.value = result.data;
    }

    return result;
  }

  /**
   * EXEMPLO 6: Update com optimistic update + rollback
   */
  async function updateProduct(
    id: number,
    data: Partial<Product>
  ): Promise<Result<Product>> {
    const notificationStore = useNotificationStore();

    // Encontrar produto atual
    const productIndex = products.value.findIndex((p) => p.id === id);
    if (productIndex === -1) {
      return {
        success: false,
        error: { message: 'Produto não encontrado' },
      };
    }

    // Salvar estado anterior (para rollback)
    const previousProduct = { ...products.value[productIndex] };

    // Optimistic update - atualizar UI imediatamente
    products.value[productIndex] = {
      ...products.value[productIndex],
      ...data,
    };

    // Fazer request
    const result = await handleApiCall<Product>(
      () => api.put(`/products/${id}`, data)
    );

    if (result.success) {
      // Atualizar com dados do servidor
      products.value[productIndex] = result.data;

      // Limpar cache relacionado
      clearRequestCache(`products-${id}`);
      clearRequestCache('products-list');

      notificationStore.success('Produto atualizado com sucesso!');
    } else {
      // Rollback em caso de erro
      products.value[productIndex] = previousProduct;
      notificationStore.error(`Erro ao atualizar: ${result.error.message}`);
    }

    return result;
  }

  /**
   * EXEMPLO 7: Delete com confirmação
   */
  async function deleteProduct(id: number): Promise<Result<void>> {
    const notificationStore = useNotificationStore();

    const result = await handleApiCall<void>(
      () => api.delete(`/products/${id}`)
    );

    if (result.success) {
      // Remover da lista local
      products.value = products.value.filter((p) => p.id !== id);

      // Limpar caches relacionados
      clearRequestCache(`products-${id}`);
      clearRequestCache('products-list');

      notificationStore.success('Produto removido com sucesso!');
    } else {
      notificationStore.error(`Erro ao remover: ${result.error.message}`);
    }

    return result;
  }

  /**
   * EXEMPLO 8: Batch operation com Promise.allSettled
   */
  async function deleteMultipleProducts(ids: number[]): Promise<void> {
    isLoading.value = true;
    const notificationStore = useNotificationStore();

    // Deletar todos em paralelo
    const results = await Promise.allSettled(
      ids.map((id) => api.delete(`/products/${id}`))
    );

    // Contar sucessos e falhas
    const succeeded = results.filter((r) => r.status === 'fulfilled').length;
    const failed = results.filter((r) => r.status === 'rejected').length;

    // Atualizar lista local removendo os que foram deletados
    const deletedIds = ids.slice(0, succeeded);
    products.value = products.value.filter((p) => !deletedIds.includes(p.id));

    // Limpar cache
    clearRequestCache('products-list');

    // Notificar resultado
    if (failed === 0) {
      notificationStore.success(`${succeeded} produtos removidos com sucesso!`);
    } else {
      notificationStore.warning(
        `${succeeded} produtos removidos, ${failed} falharam.`
      );
    }

    isLoading.value = false;
  }

  /**
   * Helper: Build filter params
   */
  function buildFilterParams(): Record<string, any> {
    const params: Record<string, any> = {};

    if (filters.value.search) {
      params.search = filters.value.search;
    }

    if (filters.value.category) {
      params.category = filters.value.category;
    }

    if (filters.value.minPrice !== null) {
      params.min_price = filters.value.minPrice;
    }

    if (filters.value.maxPrice !== null) {
      params.max_price = filters.value.maxPrice;
    }

    return params;
  }

  /**
   * Set filters and invalidate cache
   */
  function setFilters(newFilters: Partial<ProductFilters>): void {
    filters.value = { ...filters.value, ...newFilters };

    // Limpar cache quando filtros mudam
    clearRequestCache('products-list');
  }

  /**
   * Reset filters
   */
  function resetFilters(): void {
    filters.value = {
      search: '',
      category: null,
      minPrice: null,
      maxPrice: null,
    };

    clearRequestCache('products-list');
  }

  return {
    // State
    products,
    selectedProduct,
    isLoading,
    error,
    filters,

    // Computed
    hasProducts,
    filteredCount,

    // Actions
    fetchProducts,
    fetchProductsWithDedup,
    searchProducts,
    syncProductsFromAPI,
    fetchProduct,
    updateProduct,
    deleteProduct,
    deleteMultipleProducts,
    setFilters,
    resetFilters,
  };
});

/**
 * EXEMPLO DE USO EM COMPONENTE VUE
 */

// <script setup lang="ts">
// import { onMounted, onUnmounted } from 'vue';
// import { useProductStore } from '@/stores/productStore';
// import { cancelAllRequests } from '@/services/api';
//
// const productStore = useProductStore();
//
// onMounted(async () => {
//   // Carregar produtos ao montar
//   await productStore.fetchProductsWithDedup();
// });
//
// onUnmounted(() => {
//   // Cancelar requests pendentes ao desmontar
//   cancelAllRequests('Component unmounted');
// });
//
// async function handleSearch(query: string) {
//   // Search com debounce automático via request cancellation
//   await productStore.searchProducts(query);
// }
//
// async function handleUpdateProduct(id: number, data: Partial<Product>) {
//   const result = await productStore.updateProduct(id, data);
//
//   if (result.success) {
//     console.log('Produto atualizado:', result.data);
//   } else {
//     // Erros de validação
//     if (result.error.errors) {
//       console.error('Erros de validação:', result.error.errors);
//     }
//   }
// }
// </script>
