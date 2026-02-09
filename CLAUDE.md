# CLAUDE.md

Guia para Claude Code ao trabalhar neste repositório.

## Regras Críticas

> **AVISO: Código já foi perdido no passado por não seguir estas regras.**

### Edição de Código - OBRIGATÓRIO

1. **Sempre leia o arquivo ANTES de editar** - Use Read para obter o estado atual
2. **Use Edit ao invés de Write** - Edit faz substituições precisas, Write sobrescreve tudo
3. **Edições cirúrgicas** - Mude APENAS as linhas necessárias
4. **Preserve código existente** - Não modifique funções que funcionam
5. **Execute testes após mudanças** - `composer test` para PHP, `npm run build` para Vue

### Proibições

- ❌ Nunca use Write para atualizar arquivo existente
- ❌ Nunca remova imports/funções sem verificar se são usados
- ❌ Nunca prossiga se os testes falharem
- ❌ Nunca implemente workarounds - sempre solução definitiva

### Stack do Projeto

- **Backend:** PHP 8.2+ / Laravel 12 / PostgreSQL
- **Frontend:** Vue 3 Composition API + TypeScript, Pinia 3, Tailwind CSS v4
- **Build:** Vite 7, npm
- **AI:** OpenAI, Google Gemini, Anthropic Claude
- **Auth:** Laravel Sanctum, Spatie Permission
- **Queue:** Laravel Horizon (database/redis)

## Comandos

```bash
# Ambiente de desenvolvimento completo
composer dev

# Comandos individuais
php artisan serve           # Servidor Laravel localhost:8000
npm run dev                 # Vite com HMR
php artisan queue:work --queue=analysis,default --tries=3 --timeout=700

# Build e testes
npm run build
composer test
./vendor/bin/pint           # Lint PHP

# Database
php artisan migrate
php artisan db:seed
```

## Arquitetura

Laravel 12 + Vue 3 SPA para analytics de e-commerce com insights via IA. Integra com Nuvemshop para sync de dados.

### Backend (`app/`)

**Services Layer** (`app/Services/`) - ~50 services
- `AI/AIManager` - Strategy pattern para providers (OpenAI, Gemini, Anthropic)
- `AI/Agents/StoreAnalysisService` - Orquestra pipeline de análise com stage-based progress (9 estágios, resumable)
- `AI/Agents/LiteStoreAnalysisService` - Versão simplificada para análises rápidas
- `AI/Agents/*AgentService` - Collector, Analyst, Strategist, Critic
- `AI/Memory/` - HistoryService, HistorySummaryService, FeedbackLoopService
- `AI/RAG/KnowledgeBaseService` - Base de conhecimento com embeddings
- `AI/Prompts/` - 7 prompts (incluindo Lite variants e SimilarityCheckPrompt)
- `AI/Utilities/` - JsonExtractor, EmbeddingService, PlatformContextService, ProductTableFormatter
- `Analysis/` - AnalysisService, AnalysisLogService, SuggestionImpactAnalysisService
- `Analysis/Traits` - SuggestionDeduplicationTrait, FeedbackLoopTrait, HistoricalMetricsTrait
- `Integration/NuvemshopService` - API Nuvemshop + adapters (Product, Order, Coupon), token reconnection
- `ExternalData/` - ExternalDataAggregator, CompetitorAnalysisService, MarketDataService, GoogleTrendsService, DecodoProxyService, DecodoParserService
- `Business/` - DashboardService, ProductAnalyticsService, DiscountAnalyticsService, ChatbotService, NotificationService, PlanLimitService, SettingsService, EmailConfigurationService, BrazilLocationsService

**Policies** (`app/Policies/`)
- `StorePolicy` - Autorização de acesso (view, create, update, delete, sync, viewAnalytics, requestAnalysis)

**Middleware** (`app/Http/Middleware/`)
- `SanitizeSearchInput` - Prevenção SQL injection para ILIKE
- `SecurityHeaders` - Headers de segurança (CSP, X-Frame-Options)

**Traits** (`app/Models/Traits/`)
- `SafeILikeSearch` - Proteção SQL injection para buscas PostgreSQL ILIKE

**Resources** (`app/Http/Resources/`) - 11 resources
- `UserResource`, `UserManagementResource` (com `is_employee`, `role`)
- `StoreResource`, `ProductResource`, `OrderResource`, `CouponResource`
- `AnalysisResource`, `AdminAnalysisResource`, `AdminAnalysisDetailResource`
- `SuggestionResource`, `NotificationResource`

**Jobs** (`app/Jobs/`)
- `ProcessAnalysisJob` - Análise AI assíncrona (timeout: 600s, tries: 3)
- `Sync/SyncStoreDataJob` - Coordena todos os syncs
- `Sync/SyncProductsJob`, `SyncOrdersJob`, `SyncCustomersJob`, `SyncCouponsJob`
- `Sync/SyncBrazilLocationsJob` - Localizações brasileiras

**Models** (26 modelos em `app/Models/`)
- **Auth:** `User` (multi-store, roles via Spatie, parent/child hierarchy), `Subscription`, `Plan`
- **Store:** `Store` (SoftDeletes, token reconnection), `SyncedProduct`, `SyncedOrder`, `SyncedCustomer`, `SyncedCoupon`
- **Analysis:** `Analysis` (stage-based, resumable), `Suggestion`, `SuggestionStep`, `SuggestionTask`, `SuggestionComment`, `SuggestionResult`, `AnalysisExecutionLog`, `AnalysisUsage`
- **Chat:** `ChatConversation`, `ChatMessage`
- **Sistema:** `Notification`, `ActivityLog`, `SystemSetting`, `EmailConfiguration`
- **ML/RAG:** `KnowledgeEmbedding`, `CategoryStats`, `SuccessCase`, `FailureCase`

**DTOs** (`app/DTOs/`) - `StoreDataDTO`, `StoreInfoDTO`, `MetricsDTO`

**Contracts** (`app/Contracts/`) - 7 interfaces para serviços e adapters

**Exceptions** (`app/Exceptions/`) - `TokenExpiredException` (OAuth token expirado com context)

**Enums** - `SyncStatus` (inclui TokenExpired), `AnalysisStatus`, `OrderStatus`, `PaymentStatus`, `Platform`, `UserRole`, `NotificationType`, `SubscriptionStatus`

### Frontend (`resources/js/`)

**Stores Pinia** (`stores/`) - 12 stores
- `authStore` - Auth, permissões, plan limits, `hasPermission()`
- `dashboardStore` - Stats, filtros, bulk API (7→1 requests), impact dashboard
- `analysisStore` - Análise AI, sugestões por prioridade, polling, workflow (steps/tasks/comments)
- `chatStore` - Chat com IA, suggestion-specific chats
- `discountStore` - Cupons e descontos com filtros avançados
- `integrationStore` - Integrações Nuvemshop, sync polling com timeout safety (5min)
- `notificationStore` - Toast notifications (client-side)
- `systemNotificationStore` - Notificações backend com polling (60s)
- `userManagementStore` - CRUD usuários/employees com paginação
- `adminAnalysesStore` - Admin análises com filtros
- `sidebarStore`, `themeStore` - UI state (dark/light/system)

**Components** (`components/`) - 52 componentes em 10 pastas
- `common/` - BaseButton, BaseCard, BaseInput, BaseModal, LoadingSpinner, ConfirmDialog, SyncStatusBanner, AnalysisStatusBanner, ThemeToggle, NotificationToast, PreviewModeBanner, UpgradeBanner
- `layout/` - TheSidebar, TheHeader, StoreSelector
- `dashboard/` - StatCard, RevenueChart, OrdersStatusChart, TopProductsChart, DashboardFilters, EmptyStoreState, LazyChart, LowStockAlert, ProductDetailPanel, SuggestionImpactCard
- `analysis/` - SuggestionCard, SuggestionDetailModal, HealthScore, OpportunitiesPanel, OpportunityDetailModal, SuggestionChatPanel, SuggestionComments, SuggestionStepItem, SuggestionStepsPanel, SuggestionTaskItem, SuggestionTasksPanel, AnalysisAlerts
- `chat/` - ChatContainer, ChatInput, ChatMessage, ChatModal
- `admin/` - AnalysisDetailModal
- `notifications/` - NotificationDropdown, NotificationItem
- `shared/ui/` - LoadingState, ErrorBoundary, OptimizedImage, ProductCardSkeleton, TableRowSkeleton
- `users/` - PermissionCheckbox, UserFormModal

**Composables** (`composables/`) - 13 composables
- `useFormatters` - Formatação de dados (moeda, datas)
- `useValidation` - Validação de formulários
- `useLoadingState` - Estados de loading
- `useConfirmDialog` - Dialogs de confirmação
- `useSanitize` - Sanitização HTML (XSS)
- `useAsyncComponent` - Carregamento lazy
- `useKeyboard`, `useScroll` - Eventos DOM
- `usePreviewMode` - Estado de preview mode
- `useRelativeTime` - Formatação tempo relativo
- `useTracking` - Analytics tracking
- `useIntegration` - Helpers de integração

**Types** (`types/`) - 11 arquivos TypeScript
- `analysis.ts`, `api.ts`, `chat.ts`, `customer.ts`, `dashboard.ts`
- `notification.ts`, `order.ts`, `product.ts`, `store.ts`, `user.ts`

**Views** (`views/`) - 30 views
- **Principal (16):** Dashboard, Analysis, Chat, Suggestions, SuggestionWorkflow, ImpactDashboard, Products, Orders, Discounts, Integrations, StoreConfig, Settings, UsersManagement, Notifications, TrackingSettings, NotFound
- **Auth (5):** Login, Register, ForgotPassword, ResetPassword, ChangePassword
- **Admin (9):** AdminDashboard, Analyses, Clients, ClientDetail, Users, UserDetail, Plans, Settings, Integrations

## Módulo de Análise AI

### Arquitetura do Pipeline

```
Store Data → Collector → Analyst → Strategist → Critic → Suggestions
                ↓                                    ↓
         [RAG: Benchmarks]                    [Memory: Histórico]
```

### Configurações Importantes

**Período de Análise:** 15 dias anteriores à solicitação
```php
// StoreAnalysisService.php
private const ANALYSIS_PERIOD_DAYS = 15;
```

**ProcessAnalysisJob:**
```php
public int $tries = 3;
public array $backoff = [60, 120, 240];
public int $timeout = 600;  // 10 minutos

// Middleware para evitar duplicação
public function middleware(): array {
    return [(new WithoutOverlapping($this->analysis->id))
        ->releaseAfter(600)->expireAfter(900)];
}
```

**AI Providers com Retry:**
```php
// GeminiProvider e OpenAIProvider
private int $maxRetries = 3;
private array $retryDelays = [5, 15, 30];  // segundos

// Auto-retry com tokens dobrados se MAX_TOKENS
if ($finishReason === 'MAX_TOKENS' && $attempt < $this->maxRetries) {
    $maxTokens = min($maxTokens * 2, 65536);  // Gemini 2.5 suporta até 65k output
}
```

### Google Gemini 2.5 Flash

**Modelos Disponíveis:**

| Modelo | Descrição | Input Tokens | Output Tokens |
|--------|-----------|--------------|---------------|
| `gemini-2.5-flash` | Melhor custo-benefício, processamento em larga escala | 1,048,576 | 65,536 |
| `gemini-2.5-flash-lite` | Leve e eficiente, inferência rápida | 1,048,576 | 65,536 |

**Inputs Suportados:** Texto, Imagens, Vídeo, Áudio, PDF

**Capacidades:**
- ✅ Batch API, Caching, Code Execution
- ✅ Function Calling, Structured Outputs
- ✅ Search Grounding, File Search
- ✅ Thinking Mode (raciocínio estendido)
- ✅ URL Context
- ❌ Audio/Image Generation, Live API

**Knowledge Cutoff:** Janeiro 2025

**Configuração Recomendada:**
```php
// config/services.php ou SystemSettings
'ai.gemini.model' => 'gemini-2.5-flash',
'ai.gemini.max_tokens' => 16384,  // Pode ir até 65536
'ai.gemini.temperature' => 0.7,
```

### Prompts em Português

Todos os prompts (`app/Services/AI/Prompts/`) geram respostas em português brasileiro:
- `CollectorAgentPrompt` - Contexto histórico
- `AnalystAgentPrompt` - Métricas e anomalias
- `StrategistAgentPrompt` - 9 sugestões (3 high, 3 medium, 3 low)
- `CriticAgentPrompt` - Validação e melhoria
- `LiteAnalystAgentPrompt` - Versão simplificada do Analyst
- `LiteStrategistAgentPrompt` - Versão simplificada do Strategist
- `SimilarityCheckPrompt` - Detecção de sugestões similares

### JsonExtractor

Extração robusta de JSON das respostas AI (`app/Services/AI/JsonExtractor.php`):
1. Tenta markdown code blocks
2. Tenta parse direto
3. Encontra chaves balanceadas
4. Limpa e tenta novamente
5. Repara JSON truncado (adiciona fechamentos faltantes)

### AnalysisResource

Carrega sugestões do relacionamento `persistentSuggestions()`:
```php
$suggestions = $this->persistentSuggestions()
    ->orderBy('priority')
    ->get()
    ->map(fn($s) => [
        'id' => $s->id,
        'priority' => $s->expected_impact,  // high|medium|low
        // ...
    ]);
```

### Suggestion Model

```php
// Campos principais
'category'           // inventory|coupon|product|marketing|operational|customer|conversion|pricing
'title'              // Título em português
'description'        // Descrição do problema
'recommended_action' // Passos para implementar
'expected_impact'    // high|medium|low
'priority'           // Ordem numérica (1, 2, 3...)
'status'             // new|accepted|in_progress|completed|rejected
'was_successful'     // Feedback se a sugestão funcionou
```

### Sistema de Deduplicação

O `SuggestionDeduplicationTrait` evita sugestões repetidas:
- Identifica temas saturados (sugeridos 2+ vezes)
- Valida unicidade via similaridade de título (threshold: 75%)
- Usa `similar_text()` e `levenshtein()` para comparação
- Temas monitorados: quiz, frete_gratis, fidelidade, kits, estoque, email, etc.

### Stage-Based Progress

O `StoreAnalysisService` usa progresso por estágios:
```php
private const MAX_STAGE_RETRIES = 3;
private const STAGE_RETRY_DELAYS = [30, 60, 120];  // segundos

// Estágios: collector → analyst → strategist → critic → saving
```

## API Controllers

**Controllers** (`app/Http/Controllers/Api/`) - 25 controllers

**Auth & Admin:**
- `AuthController` - Login, register, logout, reset password, profile
- `AdminController` - Dashboard admin, clients CRUD, impersonate, toggle status
- `UserManagementController` - CRUD de usuários/employees (hierarchy-aware)

**Core:**
- `DashboardController` - Stats, charts (revenue, orders, top products, payment methods, categories, low stock)
- `AnalysisController` - CRUD de análises AI, sugestões accept/reject
- `ChatController` - Chat com IA, suggestion-specific chats
- `ProductController`, `OrderController`, `DiscountController` - Dados sync

**Suggestion Workflow:**
- `SuggestionStepController` - CRUD steps (workflow legado)
- `SuggestionTaskController` - CRUD tasks (workflow novo)
- `SuggestionCommentController` - CRUD comentários
- `SuggestionImpactDashboardController` - Impact dashboard, feedback

**Admin Específico:**
- `AdminAnalysesController`, `AdminPlanController`, `AdminSettingsController`
- `AdminEmailConfigurationController`, `AdminIntegrationsController`

**Integração & Config:**
- `IntegrationController` - Connect/authorize/disconnect Nuvemshop, OAuth callback
- `StoreConfigController`, `StoreSettingsController`, `UserStoreSettingsController`
- `TrackingSettingsController` - Pixels (GA, Meta, Clarity, Hotjar)
- `NotificationController`, `LocationController`

## Integração Nuvemshop

**Header de Auth (não-padrão):**
```php
Http::withHeaders(['Authentication' => 'bearer ' . $token]);
```

**Rate Limit:** 60 requests/minuto por loja

**Tokens:** Não expiram, mas podem ser invalidados. Sem refresh_token.

### Token Expiration & Reconnection

Fluxo de reconexão quando token é invalidado:
- `Store::markAsTokenExpired()` → seta `sync_status = TokenExpired`, `token_requires_reconnection = true`
- `Store::requiresReconnection()` → verifica flag
- `TokenExpiredException` → exception customizada com `storeId` e `storeName`
- Sync jobs verificam `requiresReconnection()` antes de executar
- OAuth reconnection: `authorization_code` (encrypted) salvo na store, `access_token` nullable
- `AutoAnalysisCommand` e `SyncAllStoresCommand` pulam stores que requerem reconexão

## Hierarquia de Usuários

- **Admin** (`admin@plataforma.com`) → cria Clients (donos de loja)
- **Client** (role: `client`, `parent_user_id: null`) → cria Employees
- **Employee** (role: `client`, `parent_user_id: {client_id}`) → subordinado
- `User::isEmployee()` e `User::getOwnerUser()` para navegação
- `PlanLimitService::resolveOwnerUser()` → employees herdam plano do parent
- Admin queries filtram `whereNull('parent_user_id')` para excluir employees
- `UserManagementResource` retorna `is_employee` e `role`

## Commands (Console)

- `AutoAnalysisCommand` - Auto-análise pós-sync (distribui em 120 min)
- `SyncAllStoresCommand` - Sync de todas as lojas (distribui em 240 min)
- `ForceResyncStoreCommand` - Force resync de loja específica
- `FixStuckSyncStatus` - Corrige syncs travados
- `SyncBrazilLocationsCommand` - Sync localizações brasileiras
- `SafeMigrateCommand` - Migrate wrapper seguro

## Guidelines de UI

### Dark Mode
```html
class="bg-gray-50 dark:bg-gray-900"
class="text-gray-900 dark:text-gray-100"
class="border-gray-200 dark:border-gray-700"
```

### Color Tokens
- `primary-*` - Botões, links
- `success-*` - Estados positivos
- `warning-*` - Alertas
- `danger-*` - Erros

## Variáveis de Ambiente

```
# AI Providers
AI_PROVIDER=anthropic          # openai|gemini|anthropic
OPENAI_API_KEY=
GOOGLE_AI_API_KEY=
ANTHROPIC_API_KEY=

# Integração Nuvemshop
NUVEMSHOP_CLIENT_ID=
NUVEMSHOP_CLIENT_SECRET=

# Database & Queue
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5434
QUEUE_CONNECTION=database
CACHE_STORE=database

# Redis (opcional, para cache avançada)
REDIS_HOST=redis
REDIS_PORT=6379
```

## Credenciais Padrão

Admin: `admin@plataforma.com` / `changeme123`
