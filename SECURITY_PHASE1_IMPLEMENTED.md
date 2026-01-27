# Correções de Segurança - FASE 1 (CRÍTICAS)

Status: ✅ **IMPLEMENTADO**

Data de Implementação: 2026-01-27

---

## Vulnerabilidades Corrigidas

### 1.5 Configuração CORS Multi-Ambiente

**Arquivo Criado:** `config/cors.php`

**Severidade:** 🔴 CRÍTICO

**Descrição:**
Configuração CORS adaptativa que permite desenvolvimento local seguro e produção restrita.

**Implementação:**
```php
// Detecção automática de ambiente
$isLocal = app()->isLocal() || app()->environment('development', 'testing');

// Fallback seguro para desenvolvimento
$defaultOrigins = $isLocal
    ? 'http://localhost:5173,http://localhost:8000,http://127.0.0.1:5173,http://127.0.0.1:8000'
    : '';

// Configuração via variável de ambiente
'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', $defaultOrigins)))
```

**Benefícios:**
- ✅ Desenvolvimento local funciona sem configuração
- ✅ Produção requer configuração explícita via `CORS_ALLOWED_ORIGINS`
- ✅ Suporta credenciais (Sanctum)
- ✅ Headers de segurança apropriados

**Configuração para Produção:**
```env
CORS_ALLOWED_ORIGINS=https://app.ecommpilot.com,https://admin.ecommpilot.com
```

---

### 1.6 Security Headers Middleware

**Arquivo Criado:** `app/Http/Middleware/SecurityHeaders.php`

**Severidade:** 🔴 CRÍTICO

**Descrição:**
Middleware que adiciona headers de segurança essenciais para prevenir ataques XSS, clickjacking e outros vetores.

**Headers Implementados:**

1. **Content-Security-Policy (CSP)**
   - Adaptativo ao ambiente (permite HMR em desenvolvimento)
   - Controla sources de scripts, estilos, imagens, conexões
   - `unsafe-eval` apenas em desenvolvimento

2. **X-Frame-Options: SAMEORIGIN**
   - Previne clickjacking

3. **X-Content-Type-Options: nosniff**
   - Previne MIME type sniffing

4. **X-XSS-Protection: 1; mode=block**
   - Ativa proteção XSS do navegador

5. **Referrer-Policy: strict-origin-when-cross-origin**
   - Controla informações de referência

6. **Permissions-Policy**
   - Bloqueia geolocalização, microfone, câmera

**Configuração de Desenvolvimento:**
```php
// CSP permite WebSocket e HMR
$connectSrc .= " ws://localhost:* ws://127.0.0.1:* http://localhost:* http://127.0.0.1:*";

// unsafe-eval permitido para Vite HMR
$unsafeEval = env('CSP_UNSAFE_EVAL', $isLocal) ? " 'unsafe-eval'" : '';
```

**Configuração para Produção:**
```env
CSP_UNSAFE_EVAL=false
```

---

### 1.7 Session Security Configuration

**Arquivo Modificado:** `config/session.php`

**Severidade:** 🔴 CRÍTICO

**Mudança:**
```php
// ANTES
'secure' => env('SESSION_SECURE_COOKIE'),

// DEPOIS (com fallback seguro)
'secure' => env('SESSION_SECURE_COOKIE', null),
```

**Comportamento:**
- **null**: Laravel detecta automaticamente (HTTPS = true, HTTP = false)
- **true**: Força HTTPS apenas (falha em HTTP)
- **false**: Permite HTTP (inseguro, apenas desenvolvimento)

**Recomendação para Produção:**
```env
# Deixar vazio ou null para auto-detecção
SESSION_SECURE_COOKIE=

# Ou forçar HTTPS explicitamente
SESSION_SECURE_COOKIE=true
```

---

### 1.8 Registro do Middleware

**Arquivo Modificado:** `bootstrap/app.php`

**Mudança:**
```php
use App\Http\Middleware\SecurityHeaders;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        SecurityHeaders::class,
    ]);
})
```

**Resultado:**
- SecurityHeaders aplicado em todas as rotas web
- Headers de segurança em todas as respostas HTTP

---

## Variáveis de Ambiente Adicionadas

**Arquivo Atualizado:** `.env.example`

```env
# Session Security
SESSION_SECURE_COOKIE=null
SESSION_SAME_SITE=lax
SESSION_HTTP_ONLY=true

# CORS Configuration
CORS_ALLOWED_ORIGINS=

# Content Security Policy
CSP_UNSAFE_EVAL=

# API Rate Limiting
RATE_LIMIT_API=60
RATE_LIMIT_AUTH=5
RATE_LIMIT_ANALYSIS=10

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000,127.0.0.1:5173,127.0.0.1:8000
```

---

## Checklist de Deploy

Antes de fazer deploy em produção, configure:

1. **CORS**
   ```env
   CORS_ALLOWED_ORIGINS=https://seudominio.com
   ```

2. **CSP**
   ```env
   CSP_UNSAFE_EVAL=false
   ```

3. **Session**
   ```env
   SESSION_SECURE_COOKIE=true
   SESSION_SAME_SITE=lax
   ```

4. **Sanctum**
   ```env
   SANCTUM_STATEFUL_DOMAINS=seudominio.com
   ```

5. **Rate Limiting** (opcional, já tem defaults)
   ```env
   RATE_LIMIT_API=60
   RATE_LIMIT_AUTH=5
   RATE_LIMIT_ANALYSIS=10
   ```

---

## Testes de Validação

### 1. Testar CORS

```bash
# Desenvolvimento - deve funcionar
curl -H "Origin: http://localhost:5173" \
     -H "Access-Control-Request-Method: POST" \
     -H "Access-Control-Request-Headers: X-Requested-With" \
     --head \
     http://localhost:8000/api/login

# Deve retornar:
# Access-Control-Allow-Origin: http://localhost:5173
# Access-Control-Allow-Credentials: true
```

### 2. Testar Security Headers

```bash
curl -I http://localhost:8000/api/dashboard

# Deve incluir:
# Content-Security-Policy: ...
# X-Frame-Options: SAMEORIGIN
# X-Content-Type-Options: nosniff
# X-XSS-Protection: 1; mode=block
```

### 3. Testar Ambiente

```bash
# Desenvolvimento
php artisan tinker
>>> app()->isLocal()
=> true

# Produção
APP_ENV=production php artisan tinker
>>> app()->isLocal()
=> false
```

---

## Próximos Passos

- [ ] Implementar FASE 2 - Vulnerabilidades ALTAS
- [ ] Implementar FASE 3 - Vulnerabilidades MÉDIAS
- [ ] Executar testes de penetração
- [ ] Configurar monitoramento de segurança

---

## Referências

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Security](https://laravel.com/docs/12.x/security)
- [MDN CSP Guide](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)
- [MDN CORS Guide](https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS)
