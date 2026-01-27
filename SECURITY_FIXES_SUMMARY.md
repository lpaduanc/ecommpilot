# Resumo das Correções de Segurança - Frontend

**Data:** 2026-01-27
**Desenvolvedor:** Claude Code
**Status:** ✅ Correções Críticas Aplicadas | ⚠️ Ações Pendentes Documentadas

---

## Correções Implementadas ✅

### 1. DOMPurify Instalado e Configurado
**Vulnerabilidade:** XSS em qualquer componente usando v-html
**Severidade:** 🔴 CRÍTICA

**Ação:**
```bash
npm install dompurify @types/dompurify --save
```

**Resultado:**
- ✅ Pacote instalado com sucesso
- ✅ TypeScript definitions incluídas
- ✅ Pronto para uso em toda a aplicação

---

### 2. ChatMessage.vue Sanitizado
**Vulnerabilidade:** XSS via markdown parsing sem sanitização
**Severidade:** 🔴 CRÍTICA

**Antes:**
```vue
<script setup>
import { marked } from 'marked';

const parsedContent = computed(() => {
    return marked.parse(props.message.content); // ❌ Sem sanitização
});
</script>

<template>
    <div v-html="parsedContent"></div> <!-- ❌ XSS possível -->
</template>
```

**Depois:**
```vue
<script setup>
import { marked } from 'marked';
import DOMPurify from 'dompurify'; // ✅ Importado

const parsedContent = computed(() => {
    const html = marked.parse(props.message.content);
    return DOMPurify.sanitize(html, {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'b', 'i', 'ul', 'ol', 'li',
                       'code', 'pre', 'blockquote', 'h1', 'h2', 'h3', 'h4',
                       'h5', 'h6', 'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td'],
        ALLOWED_ATTR: ['href', 'target', 'rel'],
        ALLOW_DATA_ATTR: false, // ✅ Previne data-* maliciosos
    });
});
</script>
```

**Resultado:**
- ✅ XSS bloqueado em mensagens do chat
- ✅ Markdown renderizado com segurança
- ✅ Tags perigosas removidas automaticamente

**Testes:**
```javascript
// Payloads testados e bloqueados:
"<img src=x onerror=alert(1)>"
"<script>alert(document.cookie)</script>"
"<iframe src='javascript:alert(1)'></iframe>"
```

---

### 3. Logger Utilitário Criado
**Vulnerabilidade:** console.log expondo dados sensíveis em produção
**Severidade:** 🔴 CRÍTICA

**Arquivo:** `resources/js/utils/logger.ts`

**Implementação:**
```typescript
const isDev = import.meta.env.DEV;

export const logger = {
  log(...args: any[]): void {
    if (isDev) console.log(...args); // Só em dev
  },

  error(...args: any[]): void {
    if (isDev) {
      console.error(...args);
    } else {
      // Genérico em produção
      console.error('An error occurred. Please check the application logs.');
    }
  },

  warn(...args: any[]): void {
    if (isDev) console.warn(...args);
  },
};
```

**Resultado:**
- ✅ Utilitário criado e pronto para uso
- ⚠️ **PENDENTE:** Migrar 87 ocorrências de console.* para logger.*

---

### 4. Comentários de Segurança Adicionados
**Vulnerabilidade:** Desenvolvedores confiando em validações client-side
**Severidade:** 🟠 ALTA

**Arquivos Modificados:**
- `resources/js/stores/authStore.js` - Plan limits
- `resources/js/composables/useValidation.ts` - Validações
- `resources/js/router/index.js` - Guards de rota

**Exemplo:**
```javascript
// SECURITY: These checks are for UI/UX only (hide/show features).
// Backend MUST validate ALL plan permissions before executing actions.
// Client-side checks can be bypassed via DevTools.
const canAccessAiAnalysis = computed(() => {
    if (isAdmin.value) return true;
    return planLimits.value?.has_ai_analysis ?? false;
});
```

**Resultado:**
- ✅ Desenvolvedores alertados sobre limitações de segurança client-side
- ✅ Documentação inline para manutenção futura

---

### 5. Polling com Rate Limiting
**Vulnerabilidade:** Polling infinito mesmo com erros consecutivos
**Severidade:** 🟡 MÉDIA

**Antes:**
```javascript
setInterval(async () => {
    try {
        const response = await api.get('/analysis/current');
        // ...
    } catch {
        // Silently ignore polling errors // ❌ Continua indefinidamente
    }
}, 5000);
```

**Depois:**
```javascript
let pollingErrorCount = 0;
const MAX_POLLING_ERRORS = 5;

setInterval(async () => {
    try {
        const response = await api.get('/analysis/current');
        pollingErrorCount = 0; // Reset on success
        // ...
    } catch (err) {
        pollingErrorCount++;
        if (pollingErrorCount >= MAX_POLLING_ERRORS) {
            stopPolling(); // ✅ Para após 5 erros consecutivos
            error.value = 'Erro ao verificar status. Recarregue a página.';
        }
    }
}, 5000);
```

**Resultado:**
- ✅ DoS acidental prevenido
- ✅ Usuário informado sobre falhas

---

### 6. Build Verificado
**Status:** ✅ SUCESSO

```bash
npm run build
# ✓ 830 modules transformed.
# ✓ built in 7.47s
```

**Resultado:**
- ✅ Sem erros de TypeScript
- ✅ DOMPurify importado corretamente
- ✅ Todas as mudanças compiladas

---

## Ações Pendentes ⚠️

### Críticas (Fazer AGORA)

#### 1. Migrar console.* para logger.*
**Prioridade:** 🔴 CRÍTICA
**Esforço:** 2-3 horas
**Impacto:** Alta redução de exposição de dados

**Arquivos Afetados:** 87 ocorrências

**Como Fazer:**
```bash
# Substituição automática (revisar depois)
find resources/js -type f \( -name "*.ts" -o -name "*.js" -o -name "*.vue" \) -exec sed -i 's/console\.log/logger.log/g' {} +
find resources/js -type f \( -name "*.ts" -o -name "*.js" -o -name "*.vue" \) -exec sed -i 's/console\.error/logger.error/g' {} +
find resources/js -type f \( -name "*.ts" -o -name "*.js" -o -name "*.vue" \) -exec sed -i 's/console\.warn/logger.warn/g' {} +

# Adicionar import onde necessário
import logger from '@/utils/logger';
```

**Arquivos Prioritários:**
- `resources/js/stores/authStore.ts` (linha 287)
- `resources/js/services/api.ts` (linhas 77, 101, 114)
- `resources/js/views/admin/ClientsView.vue` (linha 237)

---

#### 2. Implementar Content Security Policy (CSP)
**Prioridade:** 🔴 CRÍTICA
**Esforço:** 1 hora
**Impacto:** Defesa em profundidade contra XSS

**Implementação (Backend Laravel):**

Criar `app/Http/Middleware/SecurityHeaders.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Content Security Policy
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data: https:; " .
            "font-src 'self' data:; " .
            "connect-src 'self'; " .
            "frame-ancestors 'none';"
        );

        // Outras proteções
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
```

Registrar em `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\SecurityHeaders::class,
    ]);
})
```

---

#### 3. Migrar Token para httpOnly Cookies
**Prioridade:** 🟠 ALTA
**Esforço:** 4-6 horas
**Impacto:** Elimina roubo de token via XSS

**Problema Atual:**
```javascript
// authStore.ts
localStorage.setItem('token', token.value); // ❌ Acessível via JS
```

**Solução (Laravel Sanctum):**

1. Configurar Sanctum para SPA:

```php
// config/sanctum.php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
))),
```

2. Remover armazenamento de token no frontend:

```typescript
// authStore.ts
// ❌ Remover
localStorage.setItem('token', token.value);

// ✅ Sanctum usa cookies automaticamente
// Nenhum código necessário!
```

3. Garantir que backend retorna cookie:

```php
// AuthController.php
public function login(LoginRequest $request)
{
    // ...

    // ❌ NÃO retornar token no JSON
    // return response()->json(['token' => $token]);

    // ✅ Sanctum seta cookie automaticamente
    return response()->json(['user' => $user]);
}
```

---

### Altas (Fazer em 1 Semana)

#### 4. Validar Permissões no Backend
**Prioridade:** 🟠 ALTA
**Arquivo:** Todos os controllers em `app/Http/Controllers/Api/`

**Verificar TODAS as rotas:**
```php
// SEMPRE validar no backend
if (!$user->hasPermission('analysis.request')) {
    abort(403, 'Você não tem permissão para solicitar análises.');
}

// SEMPRE validar limites de plano
if (!$user->subscription->plan->has_ai_analysis) {
    abort(403, 'Seu plano não inclui análises IA.');
}
```

---

#### 5. Migrar para UUIDs
**Prioridade:** 🟠 ALTA
**Esforço:** 8-12 horas (requer migration)

**Problema:**
```javascript
router.push({ name: 'admin-client-detail', params: { id: 1 } }); // ❌ Enumerável
```

**Solução:**
1. Criar migration para adicionar coluna UUID
2. Popular UUIDs existentes
3. Atualizar models para usar UUID como route key
4. Atualizar frontend para usar UUID

```php
// Migration
Schema::table('users', function (Blueprint $table) {
    $table->uuid('uuid')->unique()->after('id');
});

// Model
class User extends Model
{
    public function getRouteKeyName()
    {
        return 'uuid';
    }
}

// Frontend
router.push({
    name: 'admin-client-detail',
    params: { id: client.uuid } // ✅ UUID
});
```

---

#### 6. Adicionar Banner de Impersonation
**Prioridade:** 🟠 ALTA
**Esforço:** 2 horas

**Implementação:**

`components/common/ImpersonationBanner.vue`:
```vue
<template>
    <div v-if="isImpersonating" class="bg-danger-600 text-white px-4 py-2 text-center">
        ⚠️ Você está visualizando como: {{ impersonatedUser }}
        <button @click="stopImpersonation" class="ml-4 underline">
            Voltar para Admin
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import api from '@/services/api';

const authStore = useAuthStore();

const isImpersonating = computed(() => {
    return !!localStorage.getItem('admin_token');
});

const impersonatedUser = computed(() => {
    return authStore.userName;
});

async function stopImpersonation() {
    const adminToken = localStorage.getItem('admin_token');
    localStorage.removeItem('admin_token');
    localStorage.setItem('token', adminToken);
    window.location.reload();
}
</script>
```

Adicionar em `App.vue`:
```vue
<template>
    <ImpersonationBanner />
    <!-- resto do layout -->
</template>
```

---

### Médias (Fazer em 1 Mês)

#### 7. Error Handling Genérico em Produção
**Prioridade:** 🟡 MÉDIA

Atualizar todos os catch blocks:
```javascript
} catch (err) {
    if (import.meta.env.DEV) {
        error.value = err.response?.data?.message;
    } else {
        error.value = 'Erro ao processar solicitação. Tente novamente.';
        logger.error('Operation failed:', err);
    }
}
```

---

#### 8. CSRF Retry Limitado
**Prioridade:** 🟡 MÉDIA
**Arquivo:** `resources/js/services/api.ts`

Já documentado em SECURITY_AUDIT_FRONTEND.md, seção 3.4.

---

#### 9. Backoff Exponencial em Erros
**Prioridade:** 🟡 MÉDIA

Implementar em `retryRequest.ts` para aumentar delay entre tentativas.

---

### Baixas (Quando Possível)

#### 10. Desabilitar Vue DevTools em Produção
**Arquivo:** `vite.config.js`

```javascript
export default defineConfig({
    define: {
        __VUE_PROD_DEVTOOLS__: false,
    },
});
```

---

#### 11. Remover console.* no Build de Produção
**Arquivo:** `vite.config.js`

```javascript
export default defineConfig({
    build: {
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true,
            },
        },
    },
});
```

---

#### 12. npm audit no CI/CD
**Adicionar no GitHub Actions / GitLab CI:**

```yaml
- name: Security Audit
  run: |
    npm audit --audit-level=moderate
    npm run build
```

---

## Métricas de Segurança

### Antes da Auditoria
- **Vulnerabilidades Críticas:** 3 🔴
- **Vulnerabilidades Altas:** 4 🟠
- **Vulnerabilidades Médias:** 5 🟡
- **Vulnerabilidades Baixas:** 2 🔵
- **TOTAL:** 14 vulnerabilidades

### Após Correções Imediatas
- **Vulnerabilidades Críticas:** 1 🔴 (console.log pendente)
- **Vulnerabilidades Altas:** 4 🟠 (validações backend pendentes)
- **Vulnerabilidades Médias:** 4 🟡 (melhorias em andamento)
- **Vulnerabilidades Baixas:** 2 🔵
- **TOTAL:** 11 vulnerabilidades

### Meta Final (Após Todas as Ações)
- **Vulnerabilidades Críticas:** 0 ✅
- **Vulnerabilidades Altas:** 0 ✅
- **Vulnerabilidades Médias:** 0 ✅
- **Vulnerabilidades Baixas:** Aceitáveis com mitigação
- **Nível de Segurança:** 🟢 SEGURO

---

## Testes de Regressão

Após cada correção, executar:

```bash
# Build
npm run build

# Testes manuais
1. Login/Logout
2. Chat com IA (verificar sanitização)
3. Análise de dados (verificar polling)
4. Admin impersonation (verificar banner)
5. DevTools inspection (verificar exposição)
```

---

## Checklist de Deploy

Antes de fazer deploy em produção:

- [ ] DOMPurify instalado e funcionando
- [ ] ChatMessage.vue sanitizando markdown
- [ ] Logger utilitário implementado
- [ ] console.* migrado para logger.* (87 ocorrências)
- [ ] CSP implementado no backend
- [ ] Tokens em httpOnly cookies (se possível)
- [ ] Validações duplicadas no backend
- [ ] Banner de impersonation adicionado
- [ ] Error handling genérico em produção
- [ ] Vue DevTools desabilitado em prod
- [ ] npm audit sem vulnerabilidades HIGH/CRITICAL
- [ ] Build de produção sem warnings
- [ ] Testes de regressão passando

---

## Documentação Relacionada

- `SECURITY_AUDIT_FRONTEND.md` - Relatório completo de auditoria
- `resources/js/utils/logger.ts` - Utilitário de logging seguro
- `CLAUDE.md` - Instruções gerais do projeto

---

## Contato e Suporte

Para questões sobre estas correções:
- Revisar: `SECURITY_AUDIT_FRONTEND.md`
- Verificar: Comentários inline no código
- Testar: Seguir checklist de testes acima

**Última atualização:** 2026-01-27
**Próxima revisão:** Após implementação de ações críticas
