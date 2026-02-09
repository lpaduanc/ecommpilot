# Backend EcommPilot - Agent Memory

## Stack
- PHP 8.2+ / Laravel 12 / PostgreSQL
- Laravel Sanctum (auth), Spatie Permission (roles)
- Laravel Horizon (queue: database/redis)
- Docker (WSL2): `docker exec ecommpilot-app` para comandos PHP

## Ambiente
- Testes: `docker exec ecommpilot-app php artisan test --testsuite=Unit`
- Lint: `docker exec ecommpilot-app ./vendor/bin/pint`
- Feature tests bloqueados por migrate:fresh guard
- Pre-existing failures: NuvemshopOrderAdapterTest, NuvemshopProductAdapterTest (7 failures)

## Models (26 total)

### Auth (3)
- `User` - Multi-store, Spatie Roles, UUID routing, parent/child hierarchy
  - Traits: HasApiTokens, HasFactory, HasRoles, Notifiable
  - Key: `isAdmin()`, `isClient()`, `isEmployee()`, `getOwnerUser()`, `hasAccessToStore()`, `activeSubscription()`, `currentPlan()`
  - Hierarchy: `parent_user_id` (null=client/admin, set=employee)
- `Plan` - Feature flags: ai_analysis, auto_analysis, ai_chat, suggestion_discussion, custom_dashboards, external_integrations, impact_dashboard. Limits: orders, stores, analysis_per_day, analysis_history, external_integrations
- `Subscription` - Statuses: Active, Trial, Expired, Cancelled. Methods: `isActive()`, `isOnTrial()`, `hasAccess()`

### Store & Sync (5)
- `Store` - SoftDeletes, platform (Nuvemshop), sync_status, access_token (nullable), authorization_code (encrypted), token_requires_reconnection, auto_analysis_enabled, niche, competitors, tracking_settings
  - Key: `isSyncing()`, `markAsTokenExpired()`, `requiresReconnection()`, `isEligibleForAutoAnalysis()`
- `SyncedProduct` - SafeILikeSearch, SoftDeletes. `hasLowStock()`, `isOutOfStock()`, `hasDiscount()`, `isGift()`
- `SyncedOrder` - SafeILikeSearch, SoftDeletes. `isPaid()`, `isCancelled()`, `calculateCost()`, `calculateMargin()`
- `SyncedCustomer` - SafeILikeSearch, SoftDeletes. `averageOrderValue()`
- `SyncedCoupon` - SafeILikeSearch, SoftDeletes. `isActive()`, `isExpired()`, `hasReachedMaxUses()`

### Analysis & Suggestions (9)
- `Analysis` - Stage-based (9 stages), resumable. Fields: status, current_stage, stage_data, stage_retry_count, is_resuming
- `Suggestion` - Persistent, workflow. Statuses: new -> rejected | accepted -> in_progress -> completed. Categories: inventory|coupon|product|marketing|operational|customer|conversion|pricing. Relations: steps, tasks, comments, result
- `SuggestionStep` - Ordered steps (legacy workflow). Statuses: pending, completed
- `SuggestionTask` - Tasks with due dates (new workflow). Statuses: pending, in_progress, completed
- `SuggestionComment` - Comments on suggestions/steps
- `SuggestionResult` - Outcome tracking
- `AnalysisExecutionLog` - Execution details
- `AnalysisUsage` - Daily usage tracking per store
- `KnowledgeEmbedding` - RAG embeddings. Categories: benchmark, strategy, case, seasonality

### Chat (2)
- `ChatConversation` - Sessions (general or suggestion-specific)
- `ChatMessage` - Individual messages

### System (6)
- `Notification`, `ActivityLog` (polymorphic), `SystemSetting`, `EmailConfiguration`, `CategoryStats`, `SuccessCase`, `FailureCase`

## Enums (8)
- `SyncStatus` - Pending, Syncing, Completed, Failed, **TokenExpired**, Disconnected
- `AnalysisStatus` - Pending, Processing, Completed, Failed
- `OrderStatus`, `PaymentStatus`, `Platform`, `UserRole`, `NotificationType`, `SubscriptionStatus`

## Exceptions
- `TokenExpiredException` - OAuth token expired. Properties: storeId, storeName, context()

## Controllers (25 total)

### Auth & Admin (3)
- `AuthController` - Login, register, logout, reset password, profile
- `AdminController` - Dashboard stats, clients CRUD, impersonate, toggle status
- `UserManagementController` - Users/employees CRUD (hierarchy-aware)

### Core (6)
- `DashboardController` - Stats, charts (revenue, orders, top products, payment methods, categories, low stock)
- `AnalysisController` - CRUD analises, suggestions accept/reject
- `ChatController` - AI chat, suggestion-specific chats
- `ProductController`, `OrderController`, `DiscountController`

### Suggestion Workflow (4)
- `SuggestionStepController` - CRUD steps
- `SuggestionTaskController` - CRUD tasks
- `SuggestionCommentController` - CRUD comments
- `SuggestionImpactDashboardController` - Impact dashboard, feedback

### Admin (5)
- `AdminAnalysesController`, `AdminPlanController`, `AdminSettingsController`
- `AdminEmailConfigurationController`, `AdminIntegrationsController`

### Integracao & Config (7)
- `IntegrationController` - Connect/authorize/disconnect Nuvemshop, OAuth callback
- `StoreConfigController`, `StoreSettingsController`, `UserStoreSettingsController`
- `TrackingSettingsController` - Pixels (GA, Meta, Clarity, Hotjar)
- `NotificationController`, `LocationController`

## Services (~50 total)

### AI Core (4)
AIManager, OpenAIProvider, GeminiProvider (65k output), AnthropicProvider

### AI Agents (6)
StoreAnalysisService (9-stage, resumable), LiteStoreAnalysisService, CollectorAgentService, AnalystAgentService, StrategistAgentService, CriticAgentService

### AI Prompts (7)
CollectorAgentPrompt, AnalystAgentPrompt, StrategistAgentPrompt, CriticAgentPrompt, LiteAnalystAgentPrompt, LiteStrategistAgentPrompt, SimilarityCheckPrompt

### AI Memory & RAG (4)
HistoryService, HistorySummaryService, FeedbackLoopService, KnowledgeBaseService

### AI Utilities (4)
JsonExtractor, EmbeddingService, PlatformContextService, ProductTableFormatter

### Analysis (3 + 3 Traits)
AnalysisService, AnalysisLogService, SuggestionImpactAnalysisService
Traits: SuggestionDeduplicationTrait, FeedbackLoopTrait, HistoricalMetricsTrait

### Integration (4)
NuvemshopService (token reconnection), NuvemshopProductAdapter, NuvemshopOrderAdapter, NuvemshopCouponAdapter

### External Data (6)
ExternalDataAggregator, CompetitorAnalysisService, MarketDataService, GoogleTrendsService, DecodoProxyService, DecodoParserService

### Business (9)
DashboardService, ProductAnalyticsService, DiscountAnalyticsService, ChatbotService, NotificationService, PlanLimitService, SettingsService, EmailConfigurationService, BrazilLocationsService

## Jobs (7)
- `ProcessAnalysisJob` - Timeout: 600s, Tries: 3, Backoff: [60, 120, 240], WithoutOverlapping
- `SyncStoreDataJob` - Coordena todos os syncs
- `Sync/SyncProductsJob`, `SyncOrdersJob`, `SyncCustomersJob`, `SyncCouponsJob` - Verificam `requiresReconnection()` antes de executar
- `SyncBrazilLocationsJob`

## Console Commands (8)
- `AutoAnalysisCommand` - Auto-analise pos-sync, distribui em 120 min, verifica token/plan/sync_status
- `SyncAllStoresCommand` - Sync todas lojas, distribui em 240 min, `storeId % WINDOW_MINUTES`
- `ForceResyncStoreCommand`, `FixStuckSyncStatus`, `SyncBrazilLocationsCommand`, `SafeMigrateCommand`, `PopulateUuids`, `FixKnowledgeCategories`

## Policies
- `StorePolicy` - viewAny, view, create, update, delete, sync, viewAnalytics, requestAnalysis, restore, forceDelete. Employees acessam stores do parent, admins acessam tudo

## Middleware
- `SanitizeSearchInput` - SQL injection prevention para ILIKE
- `SecurityHeaders` - CSP, X-Frame-Options, etc.

## Resources (11)
UserResource, UserManagementResource (is_employee, role), StoreResource, ProductResource, OrderResource, CouponResource, AnalysisResource, AdminAnalysisResource, AdminAnalysisDetailResource, SuggestionResource, NotificationResource

## Hierarquia de Usuarios
- Admin (`admin@plataforma.com`) -> cria Clients
- Client (role: `client`, `parent_user_id: null`) -> cria Employees
- Employee (role: `client`, `parent_user_id: {client_id}`) -> subordinado
- `User::isEmployee()` e `User::getOwnerUser()` para navegacao
- `PlanLimitService::resolveOwnerUser()` -> employees herdam plano do parent
- Admin queries: `whereNull('parent_user_id')` para excluir employees
- Employees nao criam sub-users (bloqueado no UserManagementController)
- Permissoes de employees: intersecao com permissoes do parent

## Integracao Nuvemshop
- Header: `Authentication: bearer {token}` (nao-padrao, nao `Authorization`)
- Rate limit: 60 req/min por loja
- Tokens nao expiram mas podem ser invalidados
- Token Reconnection: `markAsTokenExpired()` -> `SyncStatus::TokenExpired` -> `token_requires_reconnection = true`
- OAuth: `authorization_code` (encrypted) na store, `access_token` nullable
- Sync jobs e commands verificam `requiresReconnection()` antes de executar

## Padroes Importantes

### AI Pipeline
```
Store Data -> Collector -> Analyst -> Strategist -> Critic -> Suggestions
                |                                      |
          [RAG: Benchmarks]                     [Memory: Historico]
```
- 9 estagios com retry (MAX_STAGE_RETRIES = 3, delays: [30, 60, 120]s)
- AI providers com retry (maxRetries = 3, delays: [5, 15, 30]s)
- Auto-retry com tokens dobrados se MAX_TOKENS (ate 65536 no Gemini)
- JsonExtractor: robusto, repara JSON truncado
- Periodo de analise: 15 dias

### Deduplicacao de Sugestoes
- `SuggestionDeduplicationTrait`
- Threshold similaridade titulo: 75% (`similar_text()` + `levenshtein()`)
- Temas saturados: sugeridos 2+ vezes sao evitados
- Temas monitorados: quiz, frete_gratis, fidelidade, kits, estoque, email, etc.

### SafeILikeSearch
- Trait para prevenir SQL injection em buscas PostgreSQL
- Usado por: SyncedProduct, SyncedOrder, SyncedCustomer, SyncedCoupon

### Permissions (Spatie)
- `dashboard.view`, `products.view`, `orders.view`, `marketing.access`
- `analysis.view`, `analysis.request` (NAO `analytics.*`)
- `chat.use`, `integrations.manage`, `settings.view`, `settings.edit`
- `users.view`, `users.create`, `users.edit`, `users.delete`
- `admin.access`

## Database
- PostgreSQL via Docker (port 5434)
- Queue: database (default) ou redis (opcional)
- Campos encriptados: authorization_code, access_token
- SoftDeletes em: Store, SyncedProduct, SyncedOrder, SyncedCustomer, SyncedCoupon
- UUID routing para User
