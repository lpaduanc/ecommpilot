# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

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

### Backend Structure

**Services Layer** (`app/Services/`)
- `AnalysisService` - Processes AI analysis requests, prepares store data, parses JSON responses
- `ChatbotService` - Handles AI chat conversations with context
- `DashboardService` - Aggregates dashboard statistics from synced data
- `AI/AIManager` - Provider abstraction (strategy pattern) for OpenAI and Gemini
- `Integration/NuvemshopService` - Nuvemshop API integration and data sync

**Background Jobs** (`app/Jobs/`)
- `SyncStoreDataJob` - Syncs products, orders, customers from Nuvemshop (retries 3x with 60s backoff)
- `ProcessAnalysisJob` - Runs AI analysis asynchronously

**Key Models** (`app/Models/`)
- `User` - Supports multi-store with `active_store_id`, has `ai_credits` for rate limiting AI features
- `Store` - Connected e-commerce stores with `sync_status` tracking (Pending/Syncing/Completed/Failed)
- `SyncedProduct`, `SyncedOrder`, `SyncedCustomer` - Cached data from integrations
- `Analysis` - AI-generated analyses with suggestions, alerts, opportunities
- `ChatConversation`, `ChatMessage` - AI chat history per user/store
- `SystemSetting` - Key-value store for global settings (including AI provider config)

**Enums** (`app/Enums/`) - `SyncStatus`, `AnalysisStatus`, `OrderStatus`, `PaymentStatus`, `Platform`, `UserRole`

**Contracts** (`app/Contracts/`) - `AIProviderInterface` defines the contract for AI providers

### Frontend Structure

Vue 3 SPA with Pinia stores and Vue Router. Entry point: `resources/js/app.js`

**Pinia Stores** (`resources/js/stores/`)
- `authStore` - Authentication state, permissions, user data
- `dashboardStore` - Dashboard data, filters, active store selection
- `analysisStore` - AI analysis state and history
- `chatStore` - Chat conversation state

**API Client** (`resources/js/services/api.js`) - Axios instance with interceptors for 401 redirect, error notifications

**Path alias**: `@` maps to `resources/js/` (configured in vite.config.js)

### API Routes

All routes in `routes/api.php`. Protected routes require Sanctum auth.

- `/api/auth/*` - Authentication (login, register, password reset)
- `/api/dashboard/*` - Stats and charts (revenue, orders, top products)
- `/api/products/*`, `/api/orders/*` - Paginated store data
- `/api/integrations/*` - Nuvemshop OAuth flow and sync triggers
- `/api/analysis/*` - AI analysis requests (rate-limited to 1/hour per user)
- `/api/chat/*` - AI chat conversations
- `/api/admin/*` - Admin-only routes (requires `admin.access` permission)

### Key Flows

**Nuvemshop Integration Flow:**
1. User clicks connect -> `GET /api/integrations/nuvemshop/connect` -> redirects to Nuvemshop OAuth
2. Nuvemshop callback -> `GET /api/integrations/nuvemshop/callback` -> creates Store, dispatches `SyncStoreDataJob`
3. Job syncs products, orders, customers -> marks store as Completed/Failed

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
