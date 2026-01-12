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

- **Backend:** PHP 8.2+ / Laravel 12
- **Frontend:** Vue 3 Composition API, Pinia, Tailwind CSS v4
- **Build:** Vite, npm
- **AI:** OpenAI, Google Gemini

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

**Services Layer** (`app/Services/`)
- `AI/AIManager` - Strategy pattern para providers (OpenAI, Gemini)
- `AI/Agents/StoreAnalysisService` - Orquestra pipeline de análise
- `AI/Agents/*AgentService` - Collector, Analyst, Strategist, Critic
- `Integration/NuvemshopService` - API Nuvemshop e sync

**Jobs** (`app/Jobs/`)
- `SyncStoreDataJob` - Sync de produtos, pedidos, clientes
- `ProcessAnalysisJob` - Análise AI assíncrona (timeout: 600s, tries: 3)

**Models Principais**
- `User` - Multi-store (`active_store_id`), `ai_credits`
- `Store` - Lojas conectadas, `sync_status`
- `SyncedProduct`, `SyncedOrder`, `SyncedCustomer` - Dados sincronizados
- `Analysis` - Análises com `persistentSuggestions()` relationship
- `Suggestion` - Sugestões persistentes com status e prioridade

**Enums** - `SyncStatus`, `AnalysisStatus`, `OrderStatus`, `PaymentStatus`, `Platform`, `UserRole`

### Frontend (`resources/js/`)

**Stores Pinia** (`stores/`)
- `authStore` - Auth, permissões, `hasPermission()`
- `dashboardStore` - Stats, filtros, loja ativa
- `analysisStore` - Análise AI, sugestões por prioridade

**Components** (`components/`)
- `common/` - BaseButton, BaseCard, BaseInput, BaseModal, LoadingSpinner
- `layout/` - TheSidebar, TheHeader, StoreSelector

**Views** - Dashboard, Products, Orders, Analysis, Chat, Settings

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
    $maxTokens = min($maxTokens * 2, 32768);
}
```

### Prompts em Português

Todos os prompts (`app/Services/AI/Prompts/`) geram respostas em português brasileiro:
- `CollectorAgentPrompt` - Contexto histórico
- `AnalystAgentPrompt` - Métricas e anomalias
- `StrategistAgentPrompt` - 9 sugestões (3 high, 3 medium, 3 low)
- `CriticAgentPrompt` - Validação e melhoria

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
'status'             // pending|in_progress|completed|ignored
```

## Integração Nuvemshop

**Header de Auth (não-padrão):**
```php
Http::withHeaders(['Authentication' => 'bearer ' . $token]);
```

**Rate Limit:** 60 requests/minuto por loja

**Tokens:** Não expiram, mas podem ser invalidados. Sem refresh_token.

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
OPENAI_API_KEY
GOOGLE_AI_API_KEY
NUVEMSHOP_CLIENT_ID
NUVEMSHOP_CLIENT_SECRET
QUEUE_CONNECTION=database
```

## Credenciais Padrão

Admin: `admin@plataforma.com` / `changeme123`
