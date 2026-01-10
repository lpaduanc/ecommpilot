# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## CRITICAL RULES - READ FIRST

### Preventing Code Regressions

**NEVER replace working code with old or incomplete versions.** Follow these mandatory rules:

1. **Always read the CURRENT file before editing** - Use the Read tool to get the latest state of the file. Never assume you know the current content based on earlier reads in the conversation.

2. **Use Edit instead of Write for modifications** - The Edit tool makes precise replacements. The Write tool overwrites the entire file and can cause code loss.

3. **Surgical edits, not complete replacements** - When modifying a file, change only the necessary section. Never rewrite functions, methods, or entire sections that don't need to be changed.

4. **Preserve existing working code** - If a function/component is already working, don't modify it unless explicitly requested.

5. **Verify before removing** - Before removing any code, confirm it is truly no longer needed.

6. **After significant edits, validate** - Run `npm run build` for frontend or `./vendor/bin/pint` for backend to ensure there are no syntax errors.

### Mandatory Flow for Edits

```
1. Read current file → 2. Identify specific section → 3. Edit only what's necessary → 4. Validate build/lint
```

### What to NEVER Do

- Never use Write to "update" an existing file without reading it first
- Never copy code from earlier messages in the conversation as the "current version"
- Never remove imports, functions, or variables without verifying they are unused
- Never simplify or "clean up" code that wasn't requested to be changed

### Technology Stack Consistency

**Use ONLY the project's stack technologies.** Don't introduce external languages or tools to solve problems that can be solved with the existing stack.

**Project Stack:**
- **Backend:** PHP/Laravel 12
- **Frontend:** Vue 3, JavaScript/TypeScript, Tailwind CSS v4
- **Build:** Vite, npm

**What to NEVER do:**
- Never create Python, Ruby, or other language scripts for automation that can be done with JavaScript/PHP
- Never add dependencies from languages outside the stack without explicit approval
- Never use external CLI tools when an equivalent exists in the stack (e.g., use Vite/npm instead of complex shell scripts)

**Example:** To apply changes to Vue files, use JavaScript/Node.js or Vue/Vite ecosystem tools, not Python scripts.

### Definitive Solutions, Not Workarounds

**NEVER implement workarounds or hacks.** All solutions must be definitive and solve the problem at its root.

**What is considered a workaround:**
- Adding temporary CSS classes that are removed via JavaScript after a delay
- Using `setTimeout` or `requestAnimationFrame` to "work around" visual issues
- Creating flags or control variables to mask unwanted behaviors
- Temporarily disabling functionality instead of fixing the cause
- Adding code that "hides" the problem instead of solving it

**What to do instead:**
- Identify the root cause of the problem
- Remove or modify the code that is causing the unwanted behavior
- Implement the correct solution, even if it requires more changes
- If the correct solution is complex, ask the user before proceeding

**Example:** If a CSS transition is causing an unwanted effect on theme change, the correct solution is to remove or adjust the transition on the affected elements, NOT to add a class that temporarily disables transitions.

## Build and Development Commands

```bash
# Full development environment (server + queue + logs + vite in parallel)
composer dev

# Individual commands
php artisan serve           # Laravel server on localhost:8000
npm run dev                 # Vite dev server with HMR
php artisan queue:work      # Process background jobs
php artisan pail            # Real-time log viewer

# Build for production
npm run build

# Run all tests
composer test

# Run specific test file or filter
php artisan test --filter=ExampleTest
php artisan test tests/Feature/ExampleTest.php

# Lint PHP code
./vendor/bin/pint

# Database setup
php artisan migrate
php artisan db:seed         # Seeds admin + demo data

# Clear all caches
php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear
```

## Architecture Overview

Laravel 12 + Vue 3 SPA for e-commerce analytics with AI-powered insights. Integrates with Nuvemshop to sync store data and uses OpenAI/Gemini for analysis.

### Laravel 12 Specifics

**Release Notes:**
- Laravel 12 focuses on minimal breaking changes - most apps upgrade without code changes
- New starter kits for React, Vue, and Livewire with Inertia 2, TypeScript, shadcn/ui
- Optional WorkOS AuthKit for social auth, passkeys, and SSO
- Follows Semantic Versioning - major releases yearly (~Q1)

**Upgrade Notes:**
- This project uses Laravel 12's built-in Sanctum for SPA authentication
- No WorkOS integration - uses standard Laravel auth system
- Tailwind CSS for styling (not Flux UI)

### Backend Structure

**Services Layer** (`app/Services/`)
- `AnalysisService` - Processes AI analysis requests, prepares store data, parses JSON responses
- `ChatbotService` - Handles AI chat conversations with context
- `DashboardService` - Aggregates dashboard statistics from synced data
- `AI/AIManager` - Provider abstraction (strategy pattern) for OpenAI and Gemini
- `Integration/NuvemshopService` - Nuvemshop API integration and data sync

**Integration Adapters** (`app/Services/Integration/`)
- `NuvemshopProductAdapter` - Transforms Nuvemshop product data to `SyncedProduct` structure
- `NuvemshopOrderAdapter` - Transforms Nuvemshop order data to `SyncedOrder` structure
  - Handles edge cases like `shipping: "table_default"` → `0.0`
  - Maps status: `open/pending` → `pending`, `closed/paid` → `paid`
  - Maps payment status: `paid` → `paid`, `refunded/voided` → `refunded`

**Background Jobs** (`app/Jobs/`)
- `SyncStoreDataJob` - Syncs products, orders, customers from Nuvemshop (retries 3x with 60s backoff)
- `ProcessAnalysisJob` - Runs AI analysis asynchronously

**Laravel 12 Job Patterns:**
```php
// Rate limiting middleware for jobs (Laravel 12)
use Illuminate\Queue\Middleware\RateLimited;

public function middleware(): array
{
    return [new RateLimited('api-sync')];
}

// Exception throttling - stops retrying after N exceptions in X seconds
use Illuminate\Queue\Middleware\ThrottlesExceptions;

public function middleware(): array
{
    return [new ThrottlesExceptions(10, 5 * 60)]; // 10 exceptions, 5 min delay
}

// Define rate limiters in AppServiceProvider
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('api-sync', function ($job) {
    return Limit::perMinute(60)->by($job->store->id);
});
```

**Queue Worker Commands:**
```bash
php artisan queue:work --tries=3 --backoff=60    # 3 retries, 60s between
php artisan queue:work redis --timeout=300       # 5 min timeout per job
```

**Key Models** (`app/Models/`)
- `User` - Supports multi-store with `active_store_id`, has `ai_credits` for rate limiting AI features
- `Store` - Connected e-commerce stores with `sync_status` tracking (Pending/Syncing/Completed/Failed/TokenExpired)
- `SyncedProduct`, `SyncedOrder`, `SyncedCustomer` - Cached data from integrations
- `Analysis` - AI-generated analyses with suggestions, alerts, opportunities
- `ChatConversation`, `ChatMessage` - AI chat history per user/store
- `SystemSetting` - Key-value store for global settings (including AI provider config)

**Enums** (`app/Enums/`) - `SyncStatus`, `AnalysisStatus`, `OrderStatus`, `PaymentStatus`, `Platform`, `UserRole`

**Contracts** (`app/Contracts/`)
- `AIProviderInterface` - Contract for AI providers (OpenAI, Gemini)
- `ProductAdapterInterface` - Contract for transforming external product data
- `OrderAdapterInterface` - Contract for transforming external order data

**API Resources** (`app/Http/Resources/`)
- `OrderResource` - Formats order data for API responses (includes items, shipping_address as JSON)
- `ProductResource` - Formats product data for API responses

**Laravel 12 Resource Patterns:**
```php
// Paginated collections - auto-includes meta & links
return new UserCollection(User::paginate());
// Or use convenience method:
return User::paginate()->toResourceCollection();

// Response structure for paginated resources:
{
    "data": [...],
    "links": { "first", "last", "prev", "next" },
    "meta": { "current_page", "from", "last_page", "per_page", "to", "total" }
}

// Custom collection with metadata
class OrderCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'stats' => ['total_revenue' => $this->collection->sum('total')],
        ];
    }
}
```

### Frontend Structure

Vue 3 SPA with Pinia stores and Vue Router. Entry point: `resources/js/app.js`

**Pinia Stores** (`resources/js/stores/`)
- `authStore` - Authentication state, permissions, user data
- `dashboardStore` - Dashboard data, filters, active store selection
- `analysisStore` - AI analysis state and history
- `chatStore` - Chat conversation state
- `notificationStore` - Global toast notifications

**Base Components** (`resources/js/components/common/`)
- `BaseButton` - Variants: primary/secondary/danger/success/ghost, sizes: sm/md/lg
- `BaseCard` - Container with padding options and hover effects
- `BaseInput` - Input fields with validation
- `BaseModal` - Modal with Teleport to body
- `LoadingSpinner` - Animated spinner
- `NotificationToast` - Global notification system

**Layout Components** (`resources/js/components/layout/`)
- `TheSidebar` - Collapsible sidebar with menu items and permissions
- `TheHeader` - Top header with search, store selector, notifications
- `StoreSelector` - Dropdown to select active store

**Views** (`resources/js/views/`)
- `DashboardView` - Stats cards, charts, low stock alerts
- `ProductsView` - Product list with detail panel
- `OrdersView` - Order list with detail modal
- `AnalysisView` - AI analysis with health score and suggestions
- `ChatView` - AI chat interface

**Composables** (`resources/js/composables/`)
- `useFormatters` - Currency (BRL), date formatting
- `useValidation` - Common validations
- `useKeyboard` - Keyboard navigation
- `useLoadingState` - Loading state management

**Types** (`resources/js/types/`)
- TypeScript interfaces for: User, Store, Product, Order, Customer, Analysis, Chat, API responses

**API Client** (`resources/js/services/api.ts`)
- Axios instance with interceptors
- Auto 401 redirect to login
- CSRF token handling
- Retry with exponential backoff
- Request cancellation support

**Path alias**: `@` maps to `resources/js/` (configured in vite.config.js)

**Styling**: Tailwind CSS with custom design tokens (primary, secondary, accent, success, warning, danger)

### API Routes

All routes in `routes/api.php`. Protected routes require Sanctum auth.

- `/api/auth/*` - Authentication (login, register, password reset)
- `/api/dashboard/*` - Stats and charts (revenue, orders, top products)
- `/api/products/*` - Paginated products with search and filters
- `/api/orders/*` - Paginated orders with search, status filter, and stats
- `/api/integrations/*` - Nuvemshop OAuth flow and sync triggers
- `/api/analysis/*` - AI analysis requests (rate-limited to 1/hour per user)
- `/api/chat/*` - AI chat conversations (rate-limited to 20 msgs/min)
- `/api/admin/*` - Admin-only routes (requires `admin.access` permission)

### Key Flows

**Nuvemshop Integration Flow:**
1. User clicks connect -> `GET /api/integrations/nuvemshop/connect` -> redirects to Nuvemshop OAuth
2. Nuvemshop callback -> `GET /api/integrations/nuvemshop/callback` -> creates Store, dispatches `SyncStoreDataJob`
3. Job syncs products, orders, customers using Adapters -> marks store as Completed/Failed

**Nuvemshop API Specifics:**
- Rate limit: 60 requests/minute per store (handled by `RateLimiter`)
- Auth header: `Authentication: bearer {token}` (NOT standard `Authorization`)
- Tokens do NOT expire but can be invalidated (app uninstall or new token)
- No refresh_token support - user must reconnect via OAuth when token invalid (401)
- Store marked as `TokenExpired` on 401 - requires reconnection

## Módulo de Análise IA para Lojas de E-commerce

### Objetivo
Sistema inteligente de análise que gera sugestões acionáveis para aumentar vendas das lojas conectadas, com aprendizado contínuo e personalização por loja/nicho.

### Arquitetura
Pipeline de agentes especializados com RAG e memória persistente:

```
Dados da Loja → Agente Coletor → Agente Analista → Agente Estrategista → Agente Crítico → Sugestões
                     ↓                                                            ↓
              [RAG: Knowledge Base]                                    [Memória: Histórico]
                     ↓                                                            ↓
              Benchmarks, boas práticas,                              Sugestões anteriores,
              cases por nicho                                         feedback, resultados
```

### Stack Técnica
- **Backend:** Laravel (PHP 8.x)
- **Frontend:** Vue.js
- **Banco:** PostgreSQL + pgvector (embeddings)
- **LLM:** Claude API (Anthropic)
- **Embeddings:** OpenAI text-embedding-3-small ou Voyage AI

### Componentes Principais

1. **RAG (Retrieval Augmented Generation)**
   - Base de conhecimento vetorial com boas práticas de e-commerce
   - Benchmarks por nicho (moda, eletrônicos, etc.)
   - Estratégias de cupons, precificação, sazonalidade
   - Tabela: `knowledge_embeddings`

2. **Sistema de Memória por Loja**
   - Histórico de análises e sugestões
   - Status de cada sugestão (pendente/feita/ignorada)
   - Resultados após implementação (feedback loop)
   - Tabelas: `analises`, `sugestoes`, `resultados`

3. **Pipeline de Agentes**
   - `AgenteColetorService`: contexto + histórico + benchmarks via RAG
   - `AgenteAnalistaService`: métricas, padrões, anomalias
   - `AgenteEstrategistaService`: gera sugestões novas e personalizadas
   - `AgenteCriticoService`: filtra genéricas, valida, prioriza

4. **Feedback Loop**
   - Compara métricas antes/depois de sugestão implementada
   - Alimenta base de conhecimento com o que funcionou
   - Melhora sugestões futuras

### Estrutura de Diretórios
```
app/
├── Services/
│   └── IA/
│       ├── AnaliseLojaService.php      # Orquestrador principal
│       ├── Agentes/
│       │   ├── AgenteColetorService.php
│       │   ├── AgenteAnalistaService.php
│       │   ├── AgenteEstrategistaService.php
│       │   └── AgenteCriticoService.php
│       ├── RAG/
│       │   ├── EmbeddingService.php
│       │   └── KnowledgeBaseService.php
│       └── Memoria/
│           ├── HistoricoService.php
│           └── FeedbackLoopService.php
├── Models/
│   ├── Analise.php
│   ├── Sugestao.php
│   ├── Resultado.php
│   └── KnowledgeEmbedding.php
```

### Fluxo Principal
```php
// AnaliseLojaService@executar($lojaId)
1. Coletar contexto (histórico, sugestões anteriores, benchmarks RAG)
2. Analisar dados atuais (pedidos, produtos, estoque, cupons)
3. Calcular métricas e detectar anomalias
4. Gerar sugestões via Agente Estrategista
5. Filtrar e validar via Agente Crítico
6. Verificar similaridade (evitar repetições via embedding)
7. Salvar e retornar sugestões priorizadas
```

### Regras de Negócio
- Sugestões não devem repetir (similaridade > 0.85 = descarta)
- Cada sugestão tem impacto esperado (alto/médio/baixo)
- Sugestões marcadas como "feitas" disparam comparação de métricas
- Nicho da loja é identificado automaticamente pelos produtos
- Benchmarks são específicos por nicho

### Dependências
```bash
composer require anthropic-ai/anthropic-php  # Claude API
composer require pgvector/pgvector           # PostgreSQL vectors
```

### Documentação Detalhada
Ver: `docs/arquitetura-ia-ecommerce.md`

### Authentication & Authorization

- Laravel Sanctum with SPA cookie-based auth
- Roles: `Admin`, `Client` (UserRole enum)
- Permissions via spatie/laravel-permission
- Frontend router guards check `meta.requiresAuth` and `meta.permission`
- Users can have `must_change_password` flag forcing password change on login

### Synced Data Structures

**SyncedOrder** stores:
- `items` (JSON): `[{product_id, variant_id, name, quantity, price}]`
- `shipping_address` (JSON): `{address, number, floor, locality, city, province, zipcode, country}`

**SyncedProduct** stores:
- `images` (JSON): Array of image URLs
- `categories` (JSON): Array of category names
- `variants` (JSON): Full variant data with stock, prices, SKU

### Testing

Tests use SQLite in-memory database (configured in phpunit.xml). Test suites: Unit, Feature.

### Database Seeders

Run in order: `PermissionSeeder` -> `AdminSeeder` -> demo data seeders (User, Store, Product, Customer, Order, Analysis)

**Default admin credentials:** admin@plataforma.com / changeme123

### Key Environment Variables

```
OPENAI_API_KEY              # Required for OpenAI provider
GOOGLE_AI_API_KEY           # Required for Gemini provider
NUVEMSHOP_CLIENT_ID         # Nuvemshop OAuth
NUVEMSHOP_CLIENT_SECRET
NUVEMSHOP_REDIRECT_URI      # Default: http://localhost:8000/api/integrations/nuvemshop/callback
QUEUE_CONNECTION            # Use 'database' or 'redis' for background jobs
```

### Code Patterns & Conventions

**Backend:**
- Controllers return JSON with consistent structure: `{data, total, last_page}` for lists
- Use Form Requests for validation
- Services handle business logic, Controllers handle HTTP
- Adapters transform external data to internal models

**Frontend:**
- Vue 3 Composition API with `<script setup>`
- Pinia for state management
- TypeScript for type safety
- Tailwind CSS for styling
- Components use props with types, emit events for parent communication
