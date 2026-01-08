# Constants Documentation

Este diretório contém todas as constantes e enums centralizados do projeto, eliminando magic strings e valores hardcoded espalhados pelo código.

## Estrutura

```
constants/
├── index.ts           # Ponto de exportação central
├── notifications.ts   # Tipos e durações de notificações
├── stock.ts          # Status e configurações de estoque
├── routes.ts         # Nomes e paths de rotas
├── api.ts            # Endpoints da API e status HTTP
└── README.md         # Esta documentação
```

## Uso

### Importação

Você pode importar as constantes de forma individual ou múltipla:

```typescript
// Importação individual
import { NotificationType } from '@/constants/notifications';

// Importação múltipla via index
import { NotificationType, ROUTE_NAMES, API_ENDPOINTS } from '@/constants';
```

### Notifications (notifications.ts)

Constantes para o sistema de notificações.

#### NotificationType Enum

```typescript
import { NotificationType } from '@/constants';

// Uso em componentes
notificationStore.show({
  type: NotificationType.Success,
  message: 'Operação realizada com sucesso!'
});

notificationStore.error('Erro ao processar'); // Tipo Error
notificationStore.warning('Atenção!'); // Tipo Warning
notificationStore.info('Informação'); // Tipo Info
```

#### NOTIFICATION_DURATION

```typescript
import { NOTIFICATION_DURATION } from '@/constants';

// Durações pré-definidas
notificationStore.show({
  type: NotificationType.Success,
  message: 'Salvo!',
  duration: NOTIFICATION_DURATION.SHORT // 3000ms
});

notificationStore.show({
  type: NotificationType.Error,
  message: 'Erro crítico',
  duration: NOTIFICATION_DURATION.PERMANENT // Não fecha automaticamente
});
```

### Stock (stock.ts)

Constantes para gerenciamento de estoque.

#### StockStatus Enum

```typescript
import { StockStatus, getStockStatus } from '@/constants';

// Determinar status baseado na quantidade
const status = getStockStatus(product.stock); // Retorna StockStatus enum

// Uso direto
if (status === StockStatus.LowStock) {
  console.warn('Estoque baixo!');
}
```

#### STOCK_STATUS_CONFIG

```typescript
import { STOCK_STATUS_CONFIG, getStockStatus } from '@/constants';

const status = getStockStatus(product.stock);
const config = STOCK_STATUS_CONFIG[status];

// Renderizar badge com configuração
<Badge
  :label="config.label"
  :color="config.color"
  :variant="config.variant"
/>
```

#### STOCK_THRESHOLDS

```typescript
import { STOCK_THRESHOLDS } from '@/constants';

// Verificar limites customizados
if (product.stock <= STOCK_THRESHOLDS.LOW_STOCK) {
  sendLowStockAlert();
}
```

### Routes (routes.ts)

Constantes para navegação entre rotas.

#### ROUTE_NAMES

```typescript
import { ROUTE_NAMES } from '@/constants';

// Navegação programática type-safe
router.push({ name: ROUTE_NAMES.LOGIN });
router.push({ name: ROUTE_NAMES.DASHBOARD });
router.push({ name: ROUTE_NAMES.ADMIN_USERS });
```

#### ROUTE_PATHS

```typescript
import { ROUTE_PATHS } from '@/constants';

// Navegação por path
router.push(ROUTE_PATHS.PRODUCTS);

// Com parâmetros
router.push(ROUTE_PATHS.ADMIN_USER_DETAIL.replace(':id', userId));
```

#### ROUTE_PERMISSIONS

```typescript
import { ROUTE_PERMISSIONS } from '@/constants';

// Verificar permissões
if (authStore.hasPermission(ROUTE_PERMISSIONS.ADMIN_DASHBOARD)) {
  // Mostrar menu admin
}
```

#### Grupos de Rotas

```typescript
import { AUTH_ROUTES, APP_ROUTES, ADMIN_ROUTES } from '@/constants';

// Rotas de autenticação
router.push({ name: AUTH_ROUTES.LOGIN });
router.push({ name: AUTH_ROUTES.REGISTER });

// Rotas da aplicação
router.push({ name: APP_ROUTES.PRODUCTS });
router.push({ name: APP_ROUTES.ORDERS });

// Rotas administrativas
router.push({ name: ADMIN_ROUTES.USERS });
router.push({ name: ADMIN_ROUTES.SETTINGS });
```

### API (api.ts)

Constantes para chamadas de API e status HTTP.

#### API_ENDPOINTS

```typescript
import { API_ENDPOINTS, buildEndpoint } from '@/constants';

// Endpoints simples
await api.get(API_ENDPOINTS.AUTH.GET_USER);
await api.post(API_ENDPOINTS.AUTH.LOGIN, credentials);

// Endpoints com parâmetros
const endpoint = buildEndpoint(API_ENDPOINTS.PRODUCTS.DETAIL, { id: 123 });
await api.get(endpoint); // GET /products/123

// Endpoints agrupados por contexto
await api.get(API_ENDPOINTS.DASHBOARD.STATS);
await api.get(API_ENDPOINTS.ADMIN.CLIENTS_LIST);
```

#### HTTP_STATUS

```typescript
import { HTTP_STATUS, HTTP_STATUS_CATEGORY } from '@/constants';

// Verificar códigos específicos
if (error.response.status === HTTP_STATUS.UNAUTHORIZED) {
  router.push({ name: ROUTE_NAMES.LOGIN });
}

if (error.response.status === HTTP_STATUS.UNPROCESSABLE_ENTITY) {
  // Tratar erros de validação
}

// Verificar categorias
if (HTTP_STATUS_CATEGORY.isSuccess(response.status)) {
  notificationStore.success('Sucesso!');
}

if (HTTP_STATUS_CATEGORY.isServerError(error.response.status)) {
  notificationStore.error('Erro no servidor');
}
```

#### buildEndpoint Helper

```typescript
import { buildEndpoint, PRODUCTS_ENDPOINTS } from '@/constants';

// Construir URLs com parâmetros
const url = buildEndpoint(PRODUCTS_ENDPOINTS.DETAIL, { id: 123 });
// Resultado: '/products/123'

const url = buildEndpoint(ANALYSIS_ENDPOINTS.MARK_SUGGESTION_DONE, {
  analysisId: 456,
  suggestionId: 789
});
// Resultado: '/analysis/456/suggestions/789/done'
```

## Benefícios

### 1. Type Safety
```typescript
// ❌ Antes (propenso a erros)
router.push({ name: 'admin-usres' }); // Typo não detectado

// ✅ Depois (erro de compilação)
router.push({ name: ROUTE_NAMES.ADMIN_USERS }); // Autocomplete + validação
```

### 2. Centralização
```typescript
// ❌ Antes (valores duplicados)
// arquivo1.vue: duration: 5000
// arquivo2.vue: duration: 5000
// arquivo3.vue: duration: 3000 // Inconsistência!

// ✅ Depois (fonte única da verdade)
duration: NOTIFICATION_DURATION.MEDIUM // Sempre 5000ms
```

### 3. Manutenibilidade
```typescript
// Mudança de endpoint em um único lugar
// Antes: buscar e substituir em 20+ arquivos
// Depois: alterar apenas em api.ts
```

### 4. Documentação
```typescript
// As constantes servem como documentação viva
// Todos os endpoints, rotas e status ficam visíveis e organizados
```

## Convenções

1. **Naming**: Use SCREAMING_SNAKE_CASE para constantes e PascalCase para enums
2. **Exports**: Sempre use `as const` para melhor inferência de tipos
3. **Agrupamento**: Agrupe constantes relacionadas em objetos
4. **Type Helpers**: Forneça type aliases quando útil para TypeScript

## Extensão

Para adicionar novas constantes:

1. Crie um novo arquivo `.ts` se for um novo contexto
2. Defina as constantes com `as const`
3. Exporte types utilitários
4. Adicione as exports em `index.ts`
5. Documente o uso neste README

## Exemplo Completo

```typescript
import {
  NotificationType,
  NOTIFICATION_DURATION,
  ROUTE_NAMES,
  API_ENDPOINTS,
  HTTP_STATUS,
  buildEndpoint
} from '@/constants';

async function deleteProduct(productId: number) {
  try {
    const endpoint = buildEndpoint(API_ENDPOINTS.PRODUCTS.DETAIL, {
      id: productId
    });

    const response = await api.delete(endpoint);

    if (response.status === HTTP_STATUS.NO_CONTENT) {
      notificationStore.show({
        type: NotificationType.Success,
        message: 'Produto excluído com sucesso',
        duration: NOTIFICATION_DURATION.SHORT
      });

      router.push({ name: ROUTE_NAMES.PRODUCTS });
    }
  } catch (error) {
    if (error.response.status === HTTP_STATUS.FORBIDDEN) {
      notificationStore.show({
        type: NotificationType.Error,
        message: 'Sem permissão para excluir',
        duration: NOTIFICATION_DURATION.LONG
      });
    }
  }
}
```
