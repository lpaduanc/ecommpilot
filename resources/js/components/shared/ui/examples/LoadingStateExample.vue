<script setup lang="ts">
import { ref } from 'vue';
import { useLoadingState } from '@/composables';
import LoadingState from '../LoadingState.vue';
import ProductCardSkeleton from '../ProductCardSkeleton.vue';
import TableRowSkeleton from '../TableRowSkeleton.vue';

// Estado
const selectedVariant = ref<'fullscreen' | 'overlay' | 'inline' | 'skeleton'>('inline');
const { isLoading, error, execute } = useLoadingState();

// Simular loading
const simulateLoading = async (shouldFail = false) => {
  const result = await execute(async () => {
    await new Promise((resolve) => setTimeout(resolve, 2000));

    if (shouldFail) {
      throw new Error('Erro simulado para demonstração');
    }

    return { data: 'Dados carregados com sucesso!' };
  });

  if (result) {
    console.log('Resultado:', result);
  }
};

// Dados de exemplo
const products = ref([
  { id: 1, name: 'Produto 1', price: 99.99 },
  { id: 2, name: 'Produto 2', price: 149.99 },
  { id: 3, name: 'Produto 3', price: 199.99 },
]);

const users = ref([
  { id: 1, name: 'João Silva', email: 'joao@example.com', status: 'Ativo' },
  { id: 2, name: 'Maria Santos', email: 'maria@example.com', status: 'Ativo' },
]);
</script>

<template>
  <div class="p-8 space-y-8">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h2 class="text-2xl font-bold mb-4">Exemplos de Loading States</h2>

      <!-- Controles -->
      <div class="flex gap-4 mb-6">
        <button
          v-for="variant in ['fullscreen', 'overlay', 'inline', 'skeleton']"
          :key="variant"
          @click="selectedVariant = variant as any"
          :class="[
            'px-4 py-2 rounded-lg transition-colors',
            selectedVariant === variant
              ? 'bg-primary-600 text-white'
              : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
          ]"
        >
          {{ variant }}
        </button>
      </div>

      <div class="flex gap-4 mb-6">
        <button
          @click="simulateLoading(false)"
          class="px-4 py-2 bg-success-600 text-white rounded-lg hover:bg-success-700 transition-colors"
          :disabled="isLoading"
        >
          Simular Loading (Sucesso)
        </button>

        <button
          @click="simulateLoading(true)"
          class="px-4 py-2 bg-danger-600 text-white rounded-lg hover:bg-danger-700 transition-colors"
          :disabled="isLoading"
        >
          Simular Loading (Erro)
        </button>
      </div>

      <!-- Error Display -->
      <div v-if="error" class="mb-6 bg-danger-50 border border-danger-200 rounded-lg p-4">
        <p class="text-danger-800 font-medium">{{ error }}</p>
      </div>

      <!-- Loading State Examples -->
      <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 min-h-[300px]">
        <h3 class="text-lg font-semibold mb-4">Variante: {{ selectedVariant }}</h3>

        <!-- Fullscreen Demo -->
        <div v-if="selectedVariant === 'fullscreen'" class="relative h-[400px] bg-gray-50 dark:bg-gray-900 rounded-lg">
          <LoadingState v-if="isLoading" variant="fullscreen" message="Carregando página completa..." />
          <div v-else class="flex items-center justify-center h-full">
            <p class="text-gray-600">Conteúdo da página (simula tela cheia)</p>
          </div>
        </div>

        <!-- Overlay Demo -->
        <div v-else-if="selectedVariant === 'overlay'" class="relative h-[300px] bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
          <LoadingState v-if="isLoading" variant="overlay" />
          <div v-else>
            <h4 class="font-semibold mb-2">Card com Overlay Loading</h4>
            <p class="text-gray-600">Este é um exemplo de loading overlay sobre um card ou seção específica.</p>
            <p class="text-gray-600 dark:text-gray-400 mt-2">O resto da página continua acessível enquanto esta seção carrega.</p>
          </div>
        </div>

        <!-- Inline Demo -->
        <div v-else-if="selectedVariant === 'inline'">
          <LoadingState v-if="isLoading" variant="inline" message="Carregando dados..." />
          <div v-else class="text-center py-8">
            <p class="text-gray-600">Dados carregados com sucesso!</p>
          </div>
        </div>

        <!-- Skeleton Demo -->
        <div v-else-if="selectedVariant === 'skeleton'">
          <LoadingState v-if="isLoading" variant="skeleton" />
          <div v-else>
            <p class="text-gray-800 font-medium">Título do Conteúdo</p>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Este é um exemplo de conteúdo que seria exibido após o carregamento.</p>
            <p class="text-gray-600 dark:text-gray-400 mt-2">O skeleton loader mostra uma prévia da estrutura antes dos dados chegarem.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Product Cards Example -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h3 class="text-xl font-bold mb-4">Exemplo: Product Card Skeleton</h3>

      <div class="grid grid-cols-3 gap-4">
        <template v-if="isLoading">
          <ProductCardSkeleton v-for="i in 3" :key="i" />
        </template>
        <template v-else>
          <div
            v-for="product in products"
            :key="product.id"
            class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow"
          >
            <div class="w-full h-48 bg-gradient-to-br from-primary-100 to-primary-200 rounded-lg mb-4 flex items-center justify-center">
              <span class="text-primary-600 font-semibold">Imagem</span>
            </div>
            <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ product.name }}</h4>
            <p class="text-primary-600 font-bold">R$ {{ product.price.toFixed(2) }}</p>
          </div>
        </template>
      </div>
    </div>

    <!-- Table Example -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <h3 class="text-xl font-bold mb-4">Exemplo: Table Row Skeleton</h3>

      <table class="w-full">
        <thead>
          <tr class="border-b border-gray-200">
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Nome</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Email</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ações</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="isLoading">
            <TableRowSkeleton :columns="4" v-for="i in 3" :key="i" />
          </template>
          <template v-else>
            <tr v-for="user in users" :key="user.id" class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50">
              <td class="px-6 py-4 text-sm text-gray-900">{{ user.name }}</td>
              <td class="px-6 py-4 text-sm text-gray-600">{{ user.email }}</td>
              <td class="px-6 py-4">
                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-success-100 text-success-800">
                  {{ user.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-600">
                <button class="text-primary-600 hover:text-primary-800">Editar</button>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>
