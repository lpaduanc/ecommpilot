# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## ⛔ CRITICAL RULES - READ FIRST ⛔

> **WARNING: CODE HAS BEEN LOST IN THE PAST DUE TO NOT FOLLOWING THESE RULES.**
> **THESE RULES ARE NON-NEGOTIABLE AND MUST BE FOLLOWED 100% OF THE TIME.**

### Preventing Code Regressions - MANDATORY

**NEVER replace working code with old or incomplete versions.** Follow these mandatory rules:

1. **Always read the CURRENT file IMMEDIATELY before editing** - Use the Read tool to get the latest state of the file RIGHT BEFORE making any edit. Never assume you know the current content based on earlier reads in the conversation. Files may have changed since your last read.

2. **Use Edit instead of Write for modifications** - The Edit tool makes precise replacements. The Write tool overwrites the entire file and can cause code loss. **ONLY use Write for creating NEW files that don't exist yet.**

3. **Surgical edits, not complete replacements** - When modifying a file, change ONLY the specific lines that need to change. Never rewrite functions, methods, or entire sections that don't need to be changed. If you need to change 5 lines, edit only those 5 lines.

4. **Preserve existing working code** - If a function/component is already working, don't modify it unless explicitly requested. Working code is sacred.

5. **Verify before removing** - Before removing any code, confirm it is truly no longer needed. When in doubt, ASK the user.

6. **MANDATORY: Run tests after ANY code change** - After making any edit to PHP or Vue files, run the appropriate tests to ensure nothing was broken. See "Mandatory Testing" section below.

### Mandatory Flow for Edits

```
1. Read current file (IMMEDIATELY before editing)
   ↓
2. Identify the SPECIFIC lines to change
   ↓
3. Edit ONLY those lines (minimal change)
   ↓
4. Run tests: `composer test` for PHP, `npm run build` for Vue
   ↓
5. If tests fail, FIX immediately before proceeding
```

### What to NEVER Do - STRICT PROHIBITIONS

- ❌ **NEVER** use Write to "update" an existing file - use Edit instead
- ❌ **NEVER** copy code from earlier messages in the conversation as the "current version"
- ❌ **NEVER** remove imports, functions, or variables without verifying they are unused
- ❌ **NEVER** simplify or "clean up" code that wasn't requested to be changed
- ❌ **NEVER** proceed to the next task if tests are failing
- ❌ **NEVER** assume a file's content - always Read it first
- ❌ **NEVER** make multiple unrelated changes in a single edit

### Mandatory Testing - RUN AFTER EVERY CHANGE

**After ANY code modification, you MUST run tests:**

```bash
# After PHP changes (controllers, services, models, jobs, etc.)
composer test

# After Vue/JS changes
npm run build

# After both PHP and Vue changes
composer test && npm run build
```

**If tests fail:**
1. **STOP** - Do not proceed with other tasks
2. **FIX** - Resolve the failing test immediately
3. **RE-RUN** - Confirm all tests pass before continuing

**No exceptions.** A passing test suite is required before moving to the next task.

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

## AI Analysis Module for E-commerce Stores

### Objective
Intelligent analysis system that generates actionable suggestions to increase sales for connected stores, with continuous learning and personalization by store/niche.

### Architecture
Pipeline of specialized agents with RAG and persistent memory:

```
Store Data → Collector Agent → Analyst Agent → Strategist Agent → Critic Agent → Suggestions
                  ↓                                                         ↓
           [RAG: Knowledge Base]                                 [Memory: History]
                  ↓                                                         ↓
           Benchmarks, best practices,                          Previous suggestions,
           cases by niche                                       feedback, results
```

### Technical Stack
- **Backend:** Laravel (PHP 8.x)
- **Frontend:** Vue.js
- **Database:** PostgreSQL + pgvector (embeddings)
- **LLM:** Claude API (Anthropic)
- **Embeddings:** OpenAI text-embedding-3-small or Voyage AI

### Main Components

1. **RAG (Retrieval Augmented Generation)**
   - Vector knowledge base with e-commerce best practices
   - Benchmarks by niche (fashion, electronics, etc.)
   - Coupon strategies, pricing, seasonality
   - Table: `knowledge_embeddings`

2. **Store Memory System**
   - Analysis and suggestions history
   - Status of each suggestion (pending/done/ignored)
   - Results after implementation (feedback loop)
   - Tables: `analyses`, `suggestions`, `results`

3. **Agent Pipeline**
   - `CollectorAgentService`: context + history + benchmarks via RAG
   - `AnalystAgentService`: metrics, patterns, anomalies
   - `StrategistAgentService`: generates new and personalized suggestions
   - `CriticAgentService`: filters generic ones, validates, prioritizes

4. **Feedback Loop**
   - Compares metrics before/after implemented suggestion
   - Feeds knowledge base with what worked
   - Improves future suggestions

### Directory Structure
```
app/
├── Services/
│   └── AI/
│       ├── StoreAnalysisService.php    # Main orchestrator
│       ├── Agents/
│       │   ├── CollectorAgentService.php
│       │   ├── AnalystAgentService.php
│       │   ├── StrategistAgentService.php
│       │   └── CriticAgentService.php
│       ├── RAG/
│       │   ├── EmbeddingService.php
│       │   └── KnowledgeBaseService.php
│       └── Memory/
│           ├── HistoryService.php
│           └── FeedbackLoopService.php
├── Models/
│   ├── Analysis.php
│   ├── Suggestion.php
│   ├── Result.php
│   └── KnowledgeEmbedding.php
```

### Main Flow
```php
// StoreAnalysisService@execute($storeId)
1. Collect context (history, previous suggestions, RAG benchmarks)
2. Analyze current data (orders, products, stock, coupons)
3. Calculate metrics and detect anomalies
4. Generate suggestions via Strategist Agent
5. Filter and validate via Critic Agent
6. Check similarity (avoid repetitions via embedding)
7. Save and return prioritized suggestions
```

### Business Rules
- Suggestions must not repeat (similarity > 0.85 = discard)
- Each suggestion has expected impact (high/medium/low)
- Suggestions marked as "done" trigger metrics comparison
- Store niche is automatically identified by products
- Benchmarks are niche-specific

### Dependencies
```bash
composer require anthropic-ai/anthropic-php  # Claude API
composer require pgvector/pgvector           # PostgreSQL vectors
```

### Detailed Documentation
See: `docs/ai-ecommerce-architecture.md`

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

## Performance Guidelines

### Backend Query Optimization

**ALWAYS prioritize performance** when implementing features that query the database:

1. **Use single batch queries instead of N+1 loops**
   ```php
   // BAD - N+1 queries
   foreach ($coupons as $coupon) {
       $analytics = $this->getAnalytics($coupon);
   }

   // GOOD - Single batch query
   $couponCodes = $coupons->pluck('code')->toArray();
   $analytics = $this->batchGetAnalytics($couponCodes);
   ```

2. **Use DB::table for read-only queries** - Faster than Eloquent when you don't need model features

3. **Cache expensive queries** - Use `Cache::remember()` with appropriate TTL (5 min for analytics)

4. **Index frequently queried columns** - Always add indexes for columns used in WHERE, JOIN, ORDER BY

5. **Limit result sets** - Always paginate, use `limit()`, avoid `get()` without limits on large tables

6. **Use selectRaw for aggregations** - Let the database do the heavy lifting
   ```php
   DB::table('orders')
       ->selectRaw('COUNT(*) as total, SUM(amount) as revenue')
       ->where('status', 'paid')
       ->first();
   ```

### Frontend Performance Optimization

**ALWAYS implement these patterns** in Vue components that display lists or tables:

1. **Debounce search inputs** - Minimum 300ms delay
   ```javascript
   const debouncedSearch = debounce(() => {
       store.fetchData();
   }, 300);
   ```

2. **Memoize expensive formatting operations**
   ```javascript
   const formatCache = new Map();
   function memoizedFormat(value, formatter) {
       if (formatCache.has(value)) return formatCache.get(value);
       const result = formatter(value);
       formatCache.set(value, result);
       return result;
   }
   ```

3. **Cleanup on unmount** - Clear timers, caches, event listeners
   ```javascript
   onUnmounted(() => {
       if (debounceTimer) clearTimeout(debounceTimer);
       formatCache.clear();
   });
   ```

4. **Use Intl API for formatting** - Create formatters once, reuse
   ```javascript
   const currencyFormatter = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
   ```

5. **Avoid inline functions in templates** - Define functions in script section

## Layout & UI Consistency Guidelines

### Standard List/Table View Pattern

All list/table views (Products, Orders, Discounts, etc.) MUST follow this structure:

1. **Hero Header with Gradient** - Dark gradient background with:
   - Icon + Title + Subtitle (item count)
   - Search input with glass effect
   - Filter toggle button

2. **Stats Cards** - 4-column grid with gradient backgrounds, white text

3. **Filter Section** - BaseCard with filter buttons organized by category

4. **Data Table** with:
   - `table-fixed` layout with explicit `colgroup` widths
   - Sticky header with gradient background
   - Hover row highlighting with gradient
   - Selection checkboxes (if applicable)
   - Totals row at bottom
   - Sortable columns with sort icons

5. **Pagination** - Footer with "Showing X to Y of Z" and prev/next buttons

### Dark Mode Support

**ALWAYS include dark mode classes** for every UI element:
```html
<!-- Background -->
class="bg-gray-50 dark:bg-gray-900"

<!-- Text -->
class="text-gray-900 dark:text-gray-100"

<!-- Borders -->
class="border-gray-200 dark:border-gray-700"

<!-- Cards -->
class="bg-white dark:bg-gray-800"
```

### Table Column Widths

Use explicit `colgroup` with fixed pixel widths:
```html
<colgroup>
    <col style="width: 50px">  <!-- Checkbox -->
    <col style="width: 180px"> <!-- Name -->
    <col style="width: 130px"> <!-- Value -->
</colgroup>
```

### Color Tokens

Use project color tokens, not raw Tailwind colors:
- `primary-*` - Main brand color (buttons, links)
- `secondary-*` - Secondary actions
- `accent-*` - Highlights, badges
- `success-*` - Positive states (active, completed)
- `warning-*` - Warnings, discounts
- `danger-*` - Errors, deletions
