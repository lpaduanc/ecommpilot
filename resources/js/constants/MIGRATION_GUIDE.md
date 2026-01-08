# Guia de Migração para Constantes

Este guia mostra como migrar código existente para usar as novas constantes centralizadas.

## 1. Notificações

### Antes
```javascript
// ❌ Magic strings e valores hardcoded
notificationStore.show({
  type: 'success',
  message: 'Salvo com sucesso',
  duration: 5000
});

notificationStore.show({
  type: 'error',
  message: 'Erro ao processar',
  duration: 0
});
```

### Depois
```typescript
// ✅ Usando constantes tipadas
import { NotificationType, NOTIFICATION_DURATION } from '@/constants';

notificationStore.show({
  type: NotificationType.Success,
  message: 'Salvo com sucesso',
  duration: NOTIFICATION_DURATION.MEDIUM
});

notificationStore.show({
  type: NotificationType.Error,
  message: 'Erro ao processar',
  duration: NOTIFICATION_DURATION.PERMANENT
});
```

## 2. Status de Estoque

### Antes
```javascript
// ❌ Lógica duplicada e valores mágicos
function getStockBadge(stock) {
  if (stock <= 0) {
    return { label: 'Fora de Estoque', color: 'red' };
  }
  if (stock <= 10) {
    return { label: 'Estoque Baixo', color: 'orange' };
  }
  return { label: 'Em Estoque', color: 'green' };
}

// Em vários componentes
const stockStatus = stock <= 0 ? 'out' : stock <= 10 ? 'low' : 'ok';
```

### Depois
```typescript
// ✅ Usando helpers e configurações centralizadas
import { getStockStatus, STOCK_STATUS_CONFIG } from '@/constants';

const status = getStockStatus(product.stock);
const config = STOCK_STATUS_CONFIG[status];

// config.label, config.color, config.variant já prontos para uso
```

## 3. Navegação entre Rotas

### Antes
```javascript
// ❌ Strings hardcoded (typos não detectados)
router.push({ name: 'dashboard' });
router.push({ name: 'admin-users' });
router.push({ name: 'analisys' }); // Typo! Não vai falhar em runtime

// Paths duplicados
router.push('/admin/users');
router.push('/products');
```

### Depois
```typescript
// ✅ Type-safe routing
import { ROUTE_NAMES, ROUTE_PATHS } from '@/constants';

router.push({ name: ROUTE_NAMES.DASHBOARD });
router.push({ name: ROUTE_NAMES.ADMIN_USERS });
router.push({ name: ROUTE_NAMES.ANALISYS }); // ❌ Erro de compilação!

// Paths centralizados
router.push(ROUTE_PATHS.ADMIN_USERS);
router.push(ROUTE_PATHS.PRODUCTS);
```

## 4. Chamadas de API

### Antes
```javascript
// ❌ Endpoints espalhados pelo código
await api.get('/auth/user');
await api.post('/auth/login', data);
await api.get(`/products/${id}`);
await api.get('/dashboard/stats');

// Status HTTP hardcoded
if (error.response.status === 401) {
  // redirect
}
if (error.response.status === 422) {
  // validation
}
```

### Depois
```typescript
// ✅ Endpoints centralizados e type-safe
import { API_ENDPOINTS, HTTP_STATUS, buildEndpoint } from '@/constants';

await api.get(API_ENDPOINTS.AUTH.GET_USER);
await api.post(API_ENDPOINTS.AUTH.LOGIN, data);
await api.get(buildEndpoint(API_ENDPOINTS.PRODUCTS.DETAIL, { id }));
await api.get(API_ENDPOINTS.DASHBOARD.STATS);

// Status HTTP com nomes descritivos
if (error.response.status === HTTP_STATUS.UNAUTHORIZED) {
  // redirect
}
if (error.response.status === HTTP_STATUS.UNPROCESSABLE_ENTITY) {
  // validation
}
```

## 5. Verificação de Permissões

### Antes
```javascript
// ❌ Strings de permissão duplicadas
if (authStore.hasPermission('admin.access')) {
  // ...
}

if (authStore.hasPermission('analytics.view')) {
  // ...
}

// Em rotas
meta: { permission: 'chat.use' }
```

### Depois
```typescript
// ✅ Permissões centralizadas
import { ROUTE_PERMISSIONS } from '@/constants';

if (authStore.hasPermission(ROUTE_PERMISSIONS.ADMIN_DASHBOARD)) {
  // ...
}

if (authStore.hasPermission(ROUTE_PERMISSIONS.ANALYSIS)) {
  // ...
}

// Em rotas
meta: { permission: ROUTE_PERMISSIONS.CHAT }
```

## 6. Interceptors e Error Handling

### Antes
```javascript
// services/api.js
api.interceptors.response.use(
  response => response,
  error => {
    if (error.response) {
      switch (error.response.status) {
        case 401:
          window.location.href = '/login';
          break;
        case 403:
          notificationStore.error('Sem permissão');
          break;
        case 404:
          notificationStore.error('Não encontrado');
          break;
      }
    }
  }
);
```

### Depois
```typescript
// services/api.ts
import {
  HTTP_STATUS,
  HTTP_STATUS_CATEGORY,
  NotificationType,
  NOTIFICATION_DURATION,
  ROUTE_PATHS
} from '@/constants';

api.interceptors.response.use(
  response => response,
  error => {
    if (error.response) {
      const status = error.response.status;

      switch (status) {
        case HTTP_STATUS.UNAUTHORIZED:
          window.location.href = ROUTE_PATHS.LOGIN;
          break;
        case HTTP_STATUS.FORBIDDEN:
          notificationStore.show({
            type: NotificationType.Error,
            message: 'Sem permissão',
            duration: NOTIFICATION_DURATION.MEDIUM
          });
          break;
        case HTTP_STATUS.NOT_FOUND:
          notificationStore.show({
            type: NotificationType.Error,
            message: 'Não encontrado',
            duration: NOTIFICATION_DURATION.MEDIUM
          });
          break;
      }

      // Ou usar helpers
      if (HTTP_STATUS_CATEGORY.isServerError(status)) {
        notificationStore.show({
          type: NotificationType.Error,
          message: 'Erro no servidor',
          duration: NOTIFICATION_DURATION.LONG
        });
      }
    }
  }
);
```

## 7. Componentes Vue (Exemplo Completo)

### Antes
```vue
<template>
  <div>
    <Badge
      :label="stock <= 0 ? 'Fora de Estoque' : stock <= 10 ? 'Estoque Baixo' : 'Em Estoque'"
      :color="stock <= 0 ? 'red' : stock <= 10 ? 'orange' : 'green'"
    />
    <button @click="navigateToProduct">Ver Produto</button>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';

const router = useRouter();
const props = defineProps(['product']);

async function navigateToProduct() {
  router.push(`/products/${props.product.id}`);
}

async function deleteProduct() {
  try {
    await api.delete(`/products/${props.product.id}`);
    notificationStore.show({
      type: 'success',
      message: 'Produto excluído',
      duration: 3000
    });
    router.push('/products');
  } catch (error) {
    if (error.response.status === 403) {
      notificationStore.show({
        type: 'error',
        message: 'Sem permissão',
        duration: 5000
      });
    }
  }
}
</script>
```

### Depois
```vue
<template>
  <div>
    <Badge
      :label="stockConfig.label"
      :color="stockConfig.color"
      :variant="stockConfig.variant"
    />
    <button @click="navigateToProduct">Ver Produto</button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import {
  getStockStatus,
  STOCK_STATUS_CONFIG,
  ROUTE_NAMES,
  API_ENDPOINTS,
  buildEndpoint,
  HTTP_STATUS,
  NotificationType,
  NOTIFICATION_DURATION
} from '@/constants';

interface Props {
  product: {
    id: number;
    stock: number;
  };
}

const router = useRouter();
const props = defineProps<Props>();

const stockStatus = computed(() => getStockStatus(props.product.stock));
const stockConfig = computed(() => STOCK_STATUS_CONFIG[stockStatus.value]);

function navigateToProduct() {
  router.push({
    name: ROUTE_NAMES.PRODUCTS,
    params: { id: props.product.id }
  });
}

async function deleteProduct() {
  try {
    const endpoint = buildEndpoint(API_ENDPOINTS.PRODUCTS.DETAIL, {
      id: props.product.id
    });

    await api.delete(endpoint);

    notificationStore.show({
      type: NotificationType.Success,
      message: 'Produto excluído',
      duration: NOTIFICATION_DURATION.SHORT
    });

    router.push({ name: ROUTE_NAMES.PRODUCTS });
  } catch (error) {
    if (error.response.status === HTTP_STATUS.FORBIDDEN) {
      notificationStore.show({
        type: NotificationType.Error,
        message: 'Sem permissão',
        duration: NOTIFICATION_DURATION.MEDIUM
      });
    }
  }
}
</script>
```

## Checklist de Migração

- [ ] Substituir tipos de notificação por `NotificationType` enum
- [ ] Substituir durações hardcoded por `NOTIFICATION_DURATION`
- [ ] Substituir lógica de estoque por `getStockStatus()` e `STOCK_STATUS_CONFIG`
- [ ] Substituir nomes de rotas por `ROUTE_NAMES`
- [ ] Substituir paths de rotas por `ROUTE_PATHS`
- [ ] Substituir endpoints por `API_ENDPOINTS` e usar `buildEndpoint()`
- [ ] Substituir códigos HTTP por `HTTP_STATUS`
- [ ] Substituir permissões por `ROUTE_PERMISSIONS`
- [ ] Adicionar tipos TypeScript quando migrar componentes
- [ ] Testar todas as funcionalidades após migração

## Benefícios Observados Após Migração

1. **Autocomplete**: IDE sugere valores válidos
2. **Type Safety**: Erros detectados em tempo de compilação
3. **Refatoração Segura**: Rename/find usages funciona perfeitamente
4. **Documentação**: Código auto-documentado e consistente
5. **Manutenibilidade**: Mudanças em um único lugar
6. **Menor Taxa de Bugs**: Impossível ter typos em strings

## Prioridade de Migração

1. **Alta**: Rotas e navegação (impacto em toda a aplicação)
2. **Alta**: API endpoints (centralização crítica)
3. **Média**: Notificações (melhora UX consistency)
4. **Média**: Status HTTP (melhora error handling)
5. **Baixa**: Stock status (apenas em módulos de produtos)

## Dicas

- Migre arquivo por arquivo, testando após cada mudança
- Use find & replace com regex para mudanças em massa
- Aproveite para adicionar tipagem TypeScript
- Documente casos especiais ou exceções
- Mantenha testes atualizados
