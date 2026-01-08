# Loading States - Componentes UI

Este diretório contém componentes padronizados de loading states para o projeto ecommpilot.

## Componentes Disponíveis

### LoadingState.vue

Componente versátil que suporta múltiplas variantes de loading.

**Props:**
- `variant`: `'fullscreen' | 'overlay' | 'inline' | 'skeleton'` (padrão: `'inline'`)
- `message`: `string` (padrão: `'Carregando...'`)

**Exemplos de Uso:**

```vue
<!-- Fullscreen Loading -->
<LoadingState variant="fullscreen" message="Carregando dashboard..." />

<!-- Overlay Loading -->
<div class="relative">
  <LoadingState v-if="isLoading" variant="overlay" />
  <div v-else>
    <!-- Seu conteúdo aqui -->
  </div>
</div>

<!-- Inline Loading -->
<LoadingState variant="inline" message="Carregando dados..." />

<!-- Skeleton Loading -->
<LoadingState variant="skeleton" />
```

### ProductCardSkeleton.vue

Skeleton loader específico para cards de produto.

**Exemplo de Uso:**

```vue
<template>
  <div class="grid grid-cols-3 gap-4">
    <!-- Loading State -->
    <ProductCardSkeleton v-if="isLoading" v-for="i in 6" :key="i" />

    <!-- Dados Carregados -->
    <ProductCard
      v-else
      v-for="product in products"
      :key="product.id"
      :product="product"
    />
  </div>
</template>
```

### TableRowSkeleton.vue

Skeleton loader para linhas de tabela com número configurável de colunas.

**Props:**
- `columns`: `number` (padrão: `4`)

**Exemplo de Uso:**

```vue
<template>
  <table>
    <thead>
      <tr>
        <th>Nome</th>
        <th>Email</th>
        <th>Status</th>
        <th>Ações</th>
      </tr>
    </thead>
    <tbody>
      <!-- Loading State -->
      <TableRowSkeleton v-if="isLoading" :columns="4" v-for="i in 5" :key="i" />

      <!-- Dados Carregados -->
      <tr v-else v-for="user in users" :key="user.id">
        <td>{{ user.name }}</td>
        <td>{{ user.email }}</td>
        <td>{{ user.status }}</td>
        <td>...</td>
      </tr>
    </tbody>
  </table>
</template>
```

## Composable useLoadingState

Composable para gerenciar estados de loading e erro de forma padronizada.

**Retorna:**
- `isLoading`: `Ref<boolean>` - Estado de carregamento
- `error`: `Ref<string | null>` - Mensagem de erro
- `execute`: `<T>(fn: () => Promise<T>) => Promise<T | undefined>` - Função para executar operações assíncronas
- `clearError`: `() => void` - Limpa o estado de erro

**Exemplo de Uso Completo:**

```vue
<script setup lang="ts">
import { ref } from 'vue';
import { useLoadingState } from '@/composables';
import LoadingState from '@/components/shared/ui/LoadingState.vue';
import api from '@/services/api';

interface Product {
  id: number;
  name: string;
  price: number;
}

const products = ref<Product[]>([]);
const { isLoading, error, execute, clearError } = useLoadingState();

// Carregar produtos
const loadProducts = async () => {
  const result = await execute(async () => {
    const response = await api.get<Product[]>('/products');
    return response.data;
  });

  if (result) {
    products.value = result;
  }
};

// Executar ao montar
loadProducts();
</script>

<template>
  <div class="container">
    <!-- Loading State -->
    <LoadingState v-if="isLoading" variant="inline" message="Carregando produtos..." />

    <!-- Error State -->
    <div v-else-if="error" class="bg-danger-50 p-4 rounded-lg">
      <p class="text-danger-800">{{ error }}</p>
      <button @click="clearError(); loadProducts();" class="mt-2 text-danger-600">
        Tentar novamente
      </button>
    </div>

    <!-- Success State -->
    <div v-else class="grid grid-cols-3 gap-4">
      <div v-for="product in products" :key="product.id" class="product-card">
        <h3>{{ product.name }}</h3>
        <p>{{ product.price }}</p>
      </div>
    </div>
  </div>
</template>
```

## Padrões de Uso Recomendados

### 1. Loading de Página Inteira

```vue
<script setup lang="ts">
import { onMounted } from 'vue';
import { useLoadingState } from '@/composables';
import LoadingState from '@/components/shared/ui/LoadingState.vue';

const { isLoading, execute } = useLoadingState();

onMounted(async () => {
  await execute(async () => {
    // Carregar dados da página
  });
});
</script>

<template>
  <LoadingState v-if="isLoading" variant="fullscreen" message="Carregando página..." />
  <div v-else>
    <!-- Conteúdo da página -->
  </div>
</template>
```

### 2. Loading em Seções/Cards

```vue
<template>
  <div class="card relative">
    <LoadingState v-if="isLoading" variant="overlay" />
    <div v-else>
      <!-- Conteúdo do card -->
    </div>
  </div>
</template>
```

### 3. Loading em Listas com Skeleton

```vue
<template>
  <div class="grid grid-cols-3 gap-4">
    <template v-if="isLoading">
      <ProductCardSkeleton v-for="i in 6" :key="i" />
    </template>
    <template v-else>
      <ProductCard v-for="product in products" :key="product.id" :product="product" />
    </template>
  </div>
</template>
```

### 4. Loading em Tabelas

```vue
<template>
  <table>
    <thead>...</thead>
    <tbody>
      <template v-if="isLoading">
        <TableRowSkeleton :columns="5" v-for="i in 10" :key="i" />
      </template>
      <template v-else>
        <tr v-for="item in items" :key="item.id">...</tr>
      </template>
    </tbody>
  </table>
</template>
```

## Acessibilidade

Todos os componentes de loading incluem:
- Atributo `role="status"` para leitores de tela
- Atributo `aria-live="polite"` para anunciar mudanças de estado
- Labels descritivas via `aria-label`

## Boas Práticas

1. **Sempre forneça mensagens descritivas** ao usar `LoadingState` com variantes `fullscreen` ou `inline`
2. **Use skeleton loaders** para melhor UX em listas e grids
3. **Combine useLoadingState com tratamento de erro** para uma experiência completa
4. **Evite múltiplos loading states simultâneos** na mesma tela
5. **Use variant="overlay"** para operações em seções específicas sem bloquear toda a tela

## Performance

Os skeleton loaders usam:
- Classe `animate-pulse` do Tailwind (otimizada)
- Estrutura HTML simples e leve
- Sem dependências externas
