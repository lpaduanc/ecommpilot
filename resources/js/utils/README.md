# API Error Handling Utilities

Este diretório contém utilitários para tratamento padronizado de erros em chamadas de API.

## Arquivos

### 1. `apiHelpers.ts`
Fornece interfaces e funções para tratamento consistente de erros de API.

**Principais recursos:**
- `ApiResponse<T>` - Interface para respostas de API
- `ApiError` - Interface para erros de API
- `Result<T>` - Tipo discriminado para sucesso/erro
- `handleApiCall<T>()` - Função wrapper para chamadas de API

### 2. `requestCache.ts`
Sistema de deduplicação de requests para evitar chamadas duplicadas.

**Principais recursos:**
- `dedupeRequest<T>()` - Deduplica requests idênticos
- `clearRequestCache()` - Limpa cache específico
- `clearAllRequestCache()` - Limpa todos os caches
- `buildCacheKey()` - Helper para criar chaves consistentes

### 3. `retryRequest.ts`
Lógica de retry automático com exponential backoff.

**Principais recursos:**
- `retryRequest<T>()` - Retry com backoff exponencial
- `createRetryWrapper()` - Factory para funções de retry personalizadas
- `isRetryableError()` - Verifica se erro deve ser retentado
- `getRetryDelayWithJitter()` - Calcula delay com jitter

### 4. `api.ts`
Instância Axios configurada com interceptors.

**Principais recursos:**
- Retry automático em erros temporários
- Timeout configurável (5s, 15s, 30s, 60s, 120s)
- Cancelamento de requests
- CSRF token automático
- Tratamento de erros com notificações

## Exemplos de Uso

### 1. Padrão Básico com `handleApiCall`

```typescript
import { handleApiCall, type Result } from '@/utils/apiHelpers';
import api from '@/services/api';

async function login(credentials: LoginCredentials): Promise<Result<User>> {
  isLoading.value = true;

  const result = await handleApiCall<AuthResponse>(
    () => api.post('/auth/login', credentials)
  );

  isLoading.value = false;

  if (result.success) {
    // Sucesso - TypeScript sabe que result.data existe
    user.value = result.data.user;
    token.value = result.data.token;
    return result;
  } else {
    // Erro - TypeScript sabe que result.error existe
    notificationStore.error(result.error.message);
    return result;
  }
}
```

### 2. Deduplicação de Requests

```typescript
import { dedupeRequest, buildCacheKey } from '@/utils/requestCache';
import api from '@/services/api';

async function fetchStats() {
  const params = buildFilterParams();
  const cacheKey = buildCacheKey('dashboard-stats', params);

  // Se chamado múltiplas vezes, só executa uma vez
  const response = await dedupeRequest(
    cacheKey,
    () => api.get('/dashboard/stats', { params }),
    5000 // TTL: 5 segundos
  );

  stats.value = response.data;
}
```

### 3. Request Cancelável

```typescript
import { createCancelableRequest } from '@/services/api';

async function fetchRevenueChart() {
  const { cancelToken, cleanup } = createCancelableRequest('revenue-chart');

  try {
    const response = await api.get('/dashboard/charts/revenue', {
      cancelToken
    });
    revenueChart.value = response.data;
  } catch (error) {
    if (!axios.isCancel(error)) {
      console.error('Error:', error);
    }
  } finally {
    cleanup();
  }
}
```

### 4. Retry Manual com Configuração Customizada

```typescript
import { retryRequest } from '@/utils/retryRequest';
import api from '@/services/api';

async function processAnalysis() {
  const data = await retryRequest(
    () => api.post('/analysis/request'),
    {
      maxRetries: 5,
      delay: 2000,
      backoff: true,
      retryOn: [500, 502, 503, 504],
      onRetry: (attempt, error) => {
        console.log(`Tentativa ${attempt}:`, error.message);
      }
    }
  );

  return data;
}
```

### 5. Diferentes Timeouts

```typescript
import { apiWithCustomTimeout } from '@/services/api';

// Autocomplete - timeout curto (5s)
const results = await apiWithCustomTimeout.short.get('/products/search', {
  params: { q: query }
});

// Operação padrão - timeout médio (15s)
const products = await apiWithCustomTimeout.medium.get('/products');

// Análise IA - timeout longo (60s)
const analysis = await apiWithCustomTimeout.long.post('/analysis/request');

// Upload pesado - timeout extra longo (120s)
const result = await apiWithCustomTimeout.extraLong.post('/import', formData);
```

### 6. Combinando Múltiplas Estratégias

```typescript
import { handleApiCall } from '@/utils/apiHelpers';
import { dedupeRequest, buildCacheKey, clearRequestCache } from '@/utils/requestCache';
import { createCancelableRequest } from '@/services/api';
import api from '@/services/api';

async function fetchDashboardData(): Promise<Result<DashboardStats>> {
  const { cancelToken, cleanup } = createCancelableRequest('dashboard-stats');

  try {
    const params = buildFilterParams();
    const cacheKey = buildCacheKey('dashboard-stats', params);

    // Combina: deduplicação + cancelamento + tratamento de erro
    const response = await dedupeRequest(
      cacheKey,
      () => api.get('/dashboard/stats', { params, cancelToken })
    );

    const result = await handleApiCall<DashboardStats>(
      async () => response
    );

    if (result.success) {
      stats.value = result.data;
    }

    return result;
  } finally {
    cleanup();
  }
}

// Limpar cache quando filtros mudam
function setFilters(newFilters: Partial<DashboardFilters>) {
  filters.value = { ...filters.value, ...newFilters };
  clearRequestCache('dashboard-stats');
}
```

## Boas Práticas

### 1. Sempre use `handleApiCall` para tratamento consistente
```typescript
// ✅ BOM
const result = await handleApiCall<User>(
  () => api.post('/auth/login', credentials)
);

if (result.success) {
  // Handle success
} else {
  // Handle error
}

// ❌ RUIM
try {
  const response = await api.post('/auth/login', credentials);
  // ...
} catch (error: any) {
  // Tratamento inconsistente
}
```

### 2. Use `dedupeRequest` para evitar chamadas duplicadas
```typescript
// ✅ BOM - chamadas simultâneas deduplicadas
async function fetchStats() {
  const response = await dedupeRequest(
    'dashboard-stats',
    () => api.get('/dashboard/stats')
  );
  stats.value = response.data;
}

// ❌ RUIM - múltiplas chamadas simultâneas
async function fetchStats() {
  const response = await api.get('/dashboard/stats');
  stats.value = response.data;
}
```

### 3. Limpe cache quando dados são modificados
```typescript
// ✅ BOM
async function updateProduct(id: number, data: ProductData) {
  await api.put(`/products/${id}`, data);
  clearRequestCache(`products-${id}`);
  clearRequestCache('products-list');
}

// ❌ RUIM - cache fica desatualizado
async function updateProduct(id: number, data: ProductData) {
  await api.put(`/products/${id}`, data);
  // Cache não é limpo
}
```

### 4. Cancele requests ao desmontar componentes
```typescript
// ✅ BOM
import { onUnmounted } from 'vue';

const { cancel } = createCancelableRequest('my-request');

onUnmounted(() => {
  cancel();
});

// ❌ RUIM - requests continuam após desmontagem
```

### 5. Use timeouts apropriados para cada tipo de operação
```typescript
// ✅ BOM
// Autocomplete - rápido
await apiWithCustomTimeout.short.get('/search', { params });

// Análise pesada - lento
await apiWithCustomTimeout.long.post('/analysis/request');

// ❌ RUIM - timeout genérico para tudo
await api.get('/search'); // Pode demorar demais
await api.post('/analysis/request'); // Pode timeout muito cedo
```

## Migração de Código Existente

### Antes (JavaScript)
```javascript
async function login(credentials) {
  isLoading.value = true;

  try {
    const response = await api.post('/auth/login', credentials);
    token.value = response.data.token;
    user.value = response.data.user;
    return { success: true };
  } catch (error) {
    return {
      success: false,
      message: error.response?.data?.message || 'Erro',
    };
  } finally {
    isLoading.value = false;
  }
}
```

### Depois (TypeScript + handleApiCall)
```typescript
async function login(credentials: LoginCredentials): Promise<Result<User>> {
  isLoading.value = true;

  const result = await handleApiCall<AuthResponse>(
    () => api.post('/auth/login', credentials)
  );

  isLoading.value = false;

  if (result.success) {
    token.value = result.data.token;
    user.value = result.data.user;
  }

  return result;
}
```

## Troubleshooting

### Problema: Requests duplicados mesmo com `dedupeRequest`
**Solução:** Certifique-se de usar a mesma chave de cache. Use `buildCacheKey` para consistência.

### Problema: Cache não está sendo limpo
**Solução:** Chame `clearRequestCache()` após mutações ou use TTL apropriado.

### Problema: Timeout muito cedo
**Solução:** Use `apiWithCustomTimeout.long` ou `apiWithCustomTimeout.extraLong` para operações pesadas.

### Problema: Erros não estão sendo retentados
**Solução:** Verifique se o status HTTP está na lista `retryOn` do interceptor.

## Referências

- [Axios Documentation](https://axios-http.com/docs/intro)
- [TypeScript Discriminated Unions](https://www.typescriptlang.org/docs/handbook/2/narrowing.html#discriminated-unions)
- [Exponential Backoff](https://en.wikipedia.org/wiki/Exponential_backoff)
