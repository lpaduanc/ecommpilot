# Quick Reference - Constantes

Referência rápida para uso diário das constantes. Para documentação completa, consulte README.md.

## Import Statement

```typescript
import {
  // Notifications
  NotificationType,
  NOTIFICATION_DURATION,

  // Stock
  StockStatus,
  STOCK_THRESHOLDS,
  STOCK_STATUS_CONFIG,
  getStockStatus,

  // Routes
  ROUTE_NAMES,
  ROUTE_PATHS,
  ROUTE_PERMISSIONS,

  // API
  API_ENDPOINTS,
  HTTP_STATUS,
  HTTP_STATUS_CATEGORY,
  buildEndpoint
} from '@/constants';
```

## Cheat Sheet

### Notificações

```typescript
// Tipos
NotificationType.Success   // 'success'
NotificationType.Error     // 'error'
NotificationType.Warning   // 'warning'
NotificationType.Info      // 'info'

// Durações
NOTIFICATION_DURATION.SHORT      // 3000ms
NOTIFICATION_DURATION.MEDIUM     // 5000ms
NOTIFICATION_DURATION.LONG       // 7000ms
NOTIFICATION_DURATION.PERMANENT  // 0ms (não fecha)

// Uso
notificationStore.show({
  type: NotificationType.Success,
  message: 'Sucesso!',
  duration: NOTIFICATION_DURATION.SHORT
});
```

### Estoque

```typescript
// Status
StockStatus.OutOfStock  // 'out_of_stock'
StockStatus.LowStock    // 'low_stock'
StockStatus.InStock     // 'in_stock'

// Limites
STOCK_THRESHOLDS.OUT_OF_STOCK  // 0
STOCK_THRESHOLDS.LOW_STOCK     // 10

// Helper
const status = getStockStatus(15);  // StockStatus.InStock
const config = STOCK_STATUS_CONFIG[status];
// { label: 'Em Estoque', color: 'green', variant: 'success' }
```

### Rotas

```typescript
// Auth
ROUTE_NAMES.LOGIN
ROUTE_NAMES.REGISTER
ROUTE_NAMES.FORGOT_PASSWORD
ROUTE_NAMES.RESET_PASSWORD

// App
ROUTE_NAMES.DASHBOARD
ROUTE_NAMES.PRODUCTS
ROUTE_NAMES.ORDERS
ROUTE_NAMES.ANALYSIS
ROUTE_NAMES.CHAT
ROUTE_NAMES.INTEGRATIONS
ROUTE_NAMES.SETTINGS

// Admin
ROUTE_NAMES.ADMIN_DASHBOARD
ROUTE_NAMES.ADMIN_USERS
ROUTE_NAMES.ADMIN_USER_DETAIL
ROUTE_NAMES.ADMIN_CLIENTS
ROUTE_NAMES.ADMIN_CLIENT_DETAIL
ROUTE_NAMES.ADMIN_SETTINGS

// Uso
router.push({ name: ROUTE_NAMES.DASHBOARD });
router.push(ROUTE_PATHS.PRODUCTS);
```

### API Endpoints

```typescript
// Auth
API_ENDPOINTS.AUTH.LOGIN
API_ENDPOINTS.AUTH.REGISTER
API_ENDPOINTS.AUTH.LOGOUT
API_ENDPOINTS.AUTH.GET_USER
API_ENDPOINTS.AUTH.UPDATE_PROFILE
API_ENDPOINTS.AUTH.UPDATE_PASSWORD
API_ENDPOINTS.AUTH.FORGOT_PASSWORD
API_ENDPOINTS.AUTH.RESET_PASSWORD

// Dashboard
API_ENDPOINTS.DASHBOARD.STATS
API_ENDPOINTS.DASHBOARD.REVENUE_CHART
API_ENDPOINTS.DASHBOARD.ORDERS_STATUS_CHART
API_ENDPOINTS.DASHBOARD.TOP_PRODUCTS
API_ENDPOINTS.DASHBOARD.PAYMENT_METHODS_CHART
API_ENDPOINTS.DASHBOARD.CATEGORIES_CHART
API_ENDPOINTS.DASHBOARD.LOW_STOCK

// Products
API_ENDPOINTS.PRODUCTS.LIST
API_ENDPOINTS.PRODUCTS.DETAIL         // '/products/:id'
API_ENDPOINTS.PRODUCTS.PERFORMANCE    // '/products/:id/performance'

// Orders
API_ENDPOINTS.ORDERS.LIST
API_ENDPOINTS.ORDERS.DETAIL           // '/orders/:id'

// Integrations
API_ENDPOINTS.INTEGRATIONS.STORES
API_ENDPOINTS.INTEGRATIONS.MY_STORES
API_ENDPOINTS.INTEGRATIONS.SELECT_STORE      // '/integrations/select-store/:storeId'
API_ENDPOINTS.INTEGRATIONS.NUVEMSHOP_CONNECT
API_ENDPOINTS.INTEGRATIONS.SYNC_STORE        // '/integrations/stores/:storeId/sync'
API_ENDPOINTS.INTEGRATIONS.DISCONNECT_STORE  // '/integrations/stores/:storeId'

// Analysis
API_ENDPOINTS.ANALYSIS.CURRENT
API_ENDPOINTS.ANALYSIS.REQUEST
API_ENDPOINTS.ANALYSIS.HISTORY
API_ENDPOINTS.ANALYSIS.DETAIL                // '/analysis/:id'
API_ENDPOINTS.ANALYSIS.MARK_SUGGESTION_DONE  // '/analysis/:analysisId/suggestions/:suggestionId/done'

// Chat
API_ENDPOINTS.CHAT.CONVERSATION
API_ENDPOINTS.CHAT.SEND_MESSAGE
API_ENDPOINTS.CHAT.CLEAR_CONVERSATION

// Admin
API_ENDPOINTS.ADMIN.STATS
API_ENDPOINTS.ADMIN.CLIENTS_LIST
API_ENDPOINTS.ADMIN.CREATE_CLIENT
API_ENDPOINTS.ADMIN.CLIENT_DETAIL         // '/admin/clients/:id'
API_ENDPOINTS.ADMIN.UPDATE_CLIENT         // '/admin/clients/:id'
API_ENDPOINTS.ADMIN.DELETE_CLIENT         // '/admin/clients/:id'
API_ENDPOINTS.ADMIN.TOGGLE_CLIENT_STATUS  // '/admin/clients/:id/toggle-status'
API_ENDPOINTS.ADMIN.ADD_CREDITS           // '/admin/clients/:id/add-credits'
API_ENDPOINTS.ADMIN.REMOVE_CREDITS        // '/admin/clients/:id/remove-credits'
API_ENDPOINTS.ADMIN.RESET_PASSWORD        // '/admin/clients/:id/reset-password'
API_ENDPOINTS.ADMIN.IMPERSONATE           // '/admin/clients/:id/impersonate'
API_ENDPOINTS.ADMIN.GET_AI_SETTINGS
API_ENDPOINTS.ADMIN.UPDATE_AI_SETTINGS
API_ENDPOINTS.ADMIN.TEST_AI_PROVIDER

// Settings
API_ENDPOINTS.SETTINGS.GET_NOTIFICATIONS
API_ENDPOINTS.SETTINGS.UPDATE_NOTIFICATIONS

// Helper para endpoints com parâmetros
const url = buildEndpoint(API_ENDPOINTS.PRODUCTS.DETAIL, { id: 123 });
// Resultado: '/products/123'
```

### HTTP Status

```typescript
// Success (2xx)
HTTP_STATUS.OK                    // 200
HTTP_STATUS.CREATED               // 201
HTTP_STATUS.ACCEPTED              // 202
HTTP_STATUS.NO_CONTENT            // 204

// Redirection (3xx)
HTTP_STATUS.MOVED_PERMANENTLY     // 301
HTTP_STATUS.FOUND                 // 302
HTTP_STATUS.NOT_MODIFIED          // 304

// Client Errors (4xx)
HTTP_STATUS.BAD_REQUEST           // 400
HTTP_STATUS.UNAUTHORIZED          // 401
HTTP_STATUS.PAYMENT_REQUIRED      // 402
HTTP_STATUS.FORBIDDEN             // 403
HTTP_STATUS.NOT_FOUND             // 404
HTTP_STATUS.METHOD_NOT_ALLOWED    // 405
HTTP_STATUS.NOT_ACCEPTABLE        // 406
HTTP_STATUS.REQUEST_TIMEOUT       // 408
HTTP_STATUS.CONFLICT              // 409
HTTP_STATUS.GONE                  // 410
HTTP_STATUS.UNPROCESSABLE_ENTITY  // 422
HTTP_STATUS.TOO_MANY_REQUESTS     // 429

// Server Errors (5xx)
HTTP_STATUS.INTERNAL_SERVER_ERROR // 500
HTTP_STATUS.NOT_IMPLEMENTED       // 501
HTTP_STATUS.BAD_GATEWAY           // 502
HTTP_STATUS.SERVICE_UNAVAILABLE   // 503
HTTP_STATUS.GATEWAY_TIMEOUT       // 504

// Helpers de categoria
HTTP_STATUS_CATEGORY.isSuccess(200)       // true
HTTP_STATUS_CATEGORY.isRedirect(302)      // true
HTTP_STATUS_CATEGORY.isClientError(404)   // true
HTTP_STATUS_CATEGORY.isServerError(500)   // true
HTTP_STATUS_CATEGORY.isError(404)         // true
```

### Permissões

```typescript
ROUTE_PERMISSIONS.ANALYSIS           // 'analytics.view'
ROUTE_PERMISSIONS.CHAT               // 'chat.use'
ROUTE_PERMISSIONS.INTEGRATIONS       // 'integrations.manage'
ROUTE_PERMISSIONS.ADMIN_DASHBOARD    // 'admin.access'
ROUTE_PERMISSIONS.ADMIN_USERS        // 'users.view'
ROUTE_PERMISSIONS.ADMIN_CLIENTS      // 'admin.access'
ROUTE_PERMISSIONS.ADMIN_SETTINGS     // 'admin.access'

// Uso
if (authStore.hasPermission(ROUTE_PERMISSIONS.ADMIN_DASHBOARD)) {
  // Mostrar menu admin
}
```

## Padrões Comuns

### 1. Navegação após ação
```typescript
await api.post(API_ENDPOINTS.AUTH.LOGIN, credentials);
router.push({ name: ROUTE_NAMES.DASHBOARD });
```

### 2. Notificação de sucesso
```typescript
notificationStore.show({
  type: NotificationType.Success,
  message: 'Salvo com sucesso!',
  duration: NOTIFICATION_DURATION.SHORT
});
```

### 3. Tratamento de erro 401
```typescript
if (error.response.status === HTTP_STATUS.UNAUTHORIZED) {
  router.push({ name: ROUTE_NAMES.LOGIN });
}
```

### 4. Badge de estoque
```typescript
const status = getStockStatus(product.stock);
const config = STOCK_STATUS_CONFIG[status];
<Badge :label="config.label" :color="config.color" />
```

### 5. Endpoint com parâmetro
```typescript
const endpoint = buildEndpoint(API_ENDPOINTS.PRODUCTS.DETAIL, { id: 123 });
await api.get(endpoint);
```

## Tips

1. Use autocomplete da IDE (Ctrl+Space) para explorar constantes disponíveis
2. Importe apenas o que precisa para otimizar tree-shaking
3. Use `buildEndpoint()` para URLs com parâmetros dinâmicos
4. Prefira `HTTP_STATUS_CATEGORY` para verificações genéricas de status
5. Consulte EXAMPLES.md para casos de uso mais complexos

## Arquivos de Documentação

- **README.md** - Documentação completa
- **MIGRATION_GUIDE.md** - Guia para migrar código existente
- **EXAMPLES.md** - Exemplos práticos completos
- **SUMMARY.md** - Visão geral executiva
- **QUICK_REFERENCE.md** - Este arquivo (referência rápida)
