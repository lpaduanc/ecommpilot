# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## REGRAS CRÍTICAS - LEIA PRIMEIRO

### Prevenção de Regressões de Código

**NUNCA substitua código funcional por versões antigas ou incompletas.** Siga estas regras obrigatórias:

1. **Sempre leia o arquivo ATUAL antes de editar** - Use a ferramenta Read para obter o estado mais recente do arquivo. Nunca assuma que você sabe o conteúdo atual baseado em leituras anteriores na conversa.

2. **Use Edit em vez de Write para modificações** - A ferramenta Edit faz substituições precisas. A ferramenta Write sobrescreve o arquivo inteiro e pode causar perda de código.

3. **Edições cirúrgicas, não substituições completas** - Ao modificar um arquivo, altere apenas o trecho necessário. Nunca reescreva funções, métodos ou seções inteiras que não precisam ser alteradas.

4. **Preserve código existente que funciona** - Se uma função/componente já está funcionando, não a modifique a menos que seja explicitamente solicitado.

5. **Verifique antes de remover** - Antes de remover qualquer código, confirme que ele realmente não é mais necessário.

6. **Após edições significativas, valide** - Execute `npm run build` para frontend ou `./vendor/bin/pint` para backend para garantir que não há erros de sintaxe.

### Fluxo Obrigatório para Edições

```
1. Read arquivo atual → 2. Identificar trecho específico → 3. Edit apenas o necessário → 4. Validar build/lint
```

### O que NUNCA fazer

- Nunca usar Write para "atualizar" um arquivo existente sem ler primeiro
- Nunca copiar código de mensagens anteriores da conversa como "versão atual"
- Nunca remover imports, funções ou variáveis sem verificar se são usados
- Nunca simplificar ou "limpar" código que não foi solicitado alterar

### Consistência da Stack Tecnológica

**Utilize APENAS as tecnologias da stack do projeto.** Não introduza linguagens ou ferramentas externas para resolver problemas que podem ser resolvidos com a stack existente.

**Stack do Projeto:**
- **Backend:** PHP/Laravel 12
- **Frontend:** Vue 3, JavaScript/TypeScript, Tailwind CSS v4
- **Build:** Vite, npm

**O que NUNCA fazer:**
- Nunca criar scripts Python, Ruby, ou outras linguagens para automação que pode ser feita com JavaScript/PHP
- Nunca adicionar dependências de linguagens fora da stack sem aprovação explícita
- Nunca usar ferramentas CLI externas quando existe equivalente na stack (ex: usar Vite/npm em vez de scripts shell complexos)

**Exemplo:** Para aplicar mudanças em arquivos Vue, use JavaScript/Node.js ou ferramentas do ecossistema Vue/Vite, não scripts Python.

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

**AI Analysis Flow:**
1. `POST /api/analysis/request` -> creates Analysis record, dispatches `ProcessAnalysisJob`
2. Job prepares store data -> sends to AI provider -> parses JSON response -> updates Analysis
3. Response contains: health_score, suggestions (5), alerts (2), opportunities (2)

### AI Integration

`AIManager` uses provider interface pattern. Default provider configurable via `SystemSetting::get('ai.provider')`.

Providers:
- `OpenAIProvider` - Uses openai-php/laravel package
- `GeminiProvider` - Google Gemini API via HTTP

Analysis prompts expect structured JSON. The `AnalysisService::parseResponse()` handles markdown removal and validation.

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
