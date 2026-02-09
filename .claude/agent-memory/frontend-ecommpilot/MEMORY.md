# Frontend EcommPilot - Agent Memory

## Stack
- Vue 3 Composition API + TypeScript
- Pinia 3 (Composition API syntax com `defineStore()`)
- Tailwind CSS v4
- Vite 7, npm
- Vue Router 4 com lazy loading

## Ambiente
- Node/npm: Rodam localmente no Windows (NAO no container Docker)
- Build: `npm run build` | Dev: `npm run dev`
- Vite roda localmente ou via `docker-compose --profile frontend`

## Stores Pinia (12 total)

### Core (6)
- `authStore.js` - Auth, token, permissoes, plan limits (`canAccessAiAnalysis`, `canAccessAiChat`, etc.), `hasPermission()`, `hasAnyPermission()`
- `dashboardStore.js` - Stats, charts, filtros, **bulk API** `fetchAllData()` (7 calls -> 1), impact dashboard
- `analysisStore.js` (MAIOR - 687 lines) - Analise AI, polling 5s, suggestions por prioridade (high/medium/low), workflow completo (steps/tasks/comments), accept/reject
- `integrationStore.js` - Nuvemshop, sync polling com timeout 5min (`MAX_POLLING_DURATION = 300000`), UUID para store IDs
- `chatStore.js` - Chat AI, suggestion-specific chats, welcome message auto
- `discountStore.js` - Cupons, filtros (search, status, type, sort, period), paginacao

### UI (3)
- `notificationStore.js` - Toast notifications client-side (success, error, warning, info), auto-dismiss
- `systemNotificationStore.js` - Notificacoes backend (sync, analysis, email), polling 60s, filtros (type, status, period)
- `sidebarStore.js` - Collapsed state, `sidebarWidth` (5rem/18rem), `contentMargin`
- `themeStore.js` - Dark/light/system, media query listener, `cycleTheme()`

### Admin (2)
- `userManagementStore.js` - CRUD users/employees, paginacao (10/20/50/100)
- `adminAnalysesStore.js` - Analises admin com filtros (store_id, user_id, status, date_range, search)

## Components (52 total, 10 dirs)

### common/ (12)
BaseButton, BaseCard, BaseInput, BaseModal, LoadingSpinner, ConfirmDialog, SyncStatusBanner, AnalysisStatusBanner, ThemeToggle, NotificationToast, PreviewModeBanner, UpgradeBanner

### layout/ (3)
TheSidebar, TheHeader, StoreSelector

### dashboard/ (10)
StatCard, RevenueChart, OrdersStatusChart, TopProductsChart, DashboardFilters, EmptyStoreState, LazyChart, LowStockAlert, ProductDetailPanel, SuggestionImpactCard

### analysis/ (12)
SuggestionCard, SuggestionDetailModal, HealthScore, OpportunitiesPanel, OpportunityDetailModal, SuggestionChatPanel, SuggestionComments, SuggestionStepItem, SuggestionStepsPanel, SuggestionTaskItem, SuggestionTasksPanel, AnalysisAlerts

### chat/ (4)
ChatContainer, ChatInput, ChatMessage, ChatModal

### admin/ (1)
AnalysisDetailModal

### notifications/ (2)
NotificationDropdown, NotificationItem

### shared/ui/ (5)
LoadingState, ErrorBoundary, OptimizedImage, ProductCardSkeleton, TableRowSkeleton

### users/ (2)
PermissionCheckbox, UserFormModal

## Views (30 total)

### Principal (16)
DashboardView, AnalysisView, SuggestionsView, SuggestionWorkflowView (:id), ImpactDashboardView, ChatView, ProductsView, OrdersView, DiscountsView, IntegrationsView, StoreConfigView (:id), SettingsView, UsersManagementView, NotificationsView, TrackingSettingsView, NotFoundView

### Auth (5)
LoginView, RegisterView, ForgotPasswordView, ResetPasswordView, ChangePasswordView (force password change)

### Admin (9)
AdminDashboardView, AnalysesView, ClientsView, ClientDetailView (:id), UsersView, UserDetailView (:id), PlansView, SettingsView, IntegrationsView

## Composables (13)

### TypeScript (8)
- `useFormatters.ts` - Moeda, datas, numeros
- `useValidation.ts` - Validacao formularios
- `useLoadingState.ts` - Loading state management
- `useSanitize.ts` - XSS protection (HTML sanitization)
- `useKeyboard.ts` - Keyboard events
- `useScroll.ts` - Scroll behavior
- `useAsyncComponent.ts` - Lazy component loading
- `usePreviewMode.ts` - Preview mode state

### JavaScript (4)
- `useConfirmDialog.js` - Confirmation dialogs
- `useRelativeTime.js` - Relative time formatting
- `useTracking.js` - Analytics tracking (GA, Meta Pixel, etc.)
- `useIntegration.js` - Integration helpers

## Types (11 arquivos)
`analysis.ts`, `api.ts`, `chat.ts`, `customer.ts`, `dashboard.ts`, `notification.ts`, `order.ts`, `product.ts`, `store.ts`, `user.ts`, `index.ts`

## Router (`router/index.js`)
- Vue Router 4 com `createWebHistory()`
- Lazy loading com webpack chunk names + prefetch para rotas prioritarias
- Navigation guards: auth check, permission check, force password change, admin redirect
- Admin pode ser redirecionado (ex: `/settings/users` -> `/admin/clients`)

## API Service (`services/api.js`)
- Axios com base URL `/api`
- Response interceptor: 401 auto-logout, 403 permission denied, 429 rate limit, 500 server error
- Headers: Content-Type, Accept, X-Requested-With

## Padroes Importantes

### Polling
- Analysis: 5s interval, max 5 errors antes de parar
- Sync status: 5s interval, timeout 5min (300000ms)
- System notifications: 60s interval
- Sempre cleanup no `onUnmounted()`

### Bulk API
- Dashboard: `fetchAllData()` reduz 7 chamadas para 1
- Discounts: `fetchAllData()` endpoint bulk

### Hierarquia de Usuarios (Frontend)
- Admin -> cria Clients
- Client (parent_user_id: null) -> cria Employees
- Employee (parent_user_id: {client_id}) -> subordinado
- `UserManagementResource` retorna `is_employee` e `role`
- UsersManagementView: badges "Funcionario" | "Proprietario" | "Usuario"
- Admin guard: admins redirecionados para `/admin/clients` ao acessar `/settings/users`

### Dark Mode
```
bg-gray-50 dark:bg-gray-900
text-gray-900 dark:text-gray-100
border-gray-200 dark:border-gray-700
```

### Color Tokens
- `primary-*` - Botoes, links
- `success-*` - Estados positivos
- `warning-*` - Alertas
- `danger-*` - Erros

### Responsive
- Mobile/tablet: card layout
- Desktop: table layout
- Breakpoints padrao Tailwind

### Permission Check
- Router guard usa `authStore.hasPermission()`
- Client-side checks sao apenas UX (backend valida)
- Permissions: `dashboard.view`, `products.view`, `orders.view`, `marketing.access`, `analysis.view`, `analysis.request`, `chat.use`, `integrations.manage`, `settings.view`, `settings.edit`, `users.view`, `users.create`, `users.edit`, `users.delete`, `admin.access`
