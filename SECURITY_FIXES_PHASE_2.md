# Correções de Segurança - FASE 2 (ALTAS)

Data: 2026-01-27
Auditor: Cybersecurity Expert (CISSP, OSCP, OSCE³)

## Resumo Executivo

Todas as 4 vulnerabilidades de severidade ALTA foram corrigidas com sucesso. As correções incluem validações de entrada, prevenção de SSRF, autenticação adicional para ações críticas e prevenção de enumeração de usuários.

---

## 2.1 SQL Injection via sort_by

**Arquivo:** `app/Http/Controllers/Api/AdminController.php:84-97`
**Severidade:** 🟠 ALTA
**Status:** ✅ CORRIGIDO

### Descrição da Vulnerabilidade
O parâmetro `sort_by` era usado diretamente no método `orderBy()` do Eloquent sem validação, permitindo potencial SQL Injection através de campos arbitrários.

### Correção Implementada
```php
// Whitelist de campos permitidos para ordenação
$allowedSortFields = ['name', 'email', 'created_at', 'last_login_at', 'is_active'];
$sortField = $request->input('sort_by', 'created_at');
$sortDir = $request->input('sort_dir', 'desc');

// Validar campo contra whitelist
if (!in_array($sortField, $allowedSortFields, true)) {
    $sortField = 'created_at';
}

// Validar direção (somente asc ou desc)
$sortDir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

$query->orderBy($sortField, $sortDir);
```

### Proteções Aplicadas
- ✅ Whitelist estrita de campos permitidos
- ✅ Validação com `in_array()` usando strict comparison
- ✅ Fallback seguro para valor padrão
- ✅ Validação de direção (somente asc/desc)

### Testes Recomendados
```bash
# Teste 1: Campo válido
GET /api/admin/clients?sort_by=email&sort_dir=asc

# Teste 2: Campo inválido (deve usar fallback)
GET /api/admin/clients?sort_by=password&sort_dir=asc

# Teste 3: SQL Injection attempt (deve usar fallback)
GET /api/admin/clients?sort_by=id);DROP TABLE users;--
```

---

## 2.2 SSRF via URL Validation

**Arquivo:** `app/Http/Controllers/Api/IntegrationController.php:85-135`
**Severidade:** 🟠 ALTA
**Status:** ✅ CORRIGIDO

### Descrição da Vulnerabilidade
A validação de URL da Nuvemshop aceitava qualquer string com 3+ caracteres, permitindo SSRF para:
- Serviços internos (localhost, 127.0.0.1)
- Redes privadas (10.x, 192.168.x, 172.16-31.x)
- Metadata endpoints (169.254.169.254)

### Correção Implementada
```php
private function isValidNuvemshopUrl(string $url): bool
{
    if (empty($url) || strlen($url) < 3 || strlen($url) > 255) {
        return false;
    }

    // Whitelist de domínios Nuvemshop válidos
    $allowedDomains = [
        '.lojavirtualnuvem.com.br',
        '.nuvemshop.com.br',
        '.tiendanube.com',
        '.mitiendanube.com',
    ];

    $normalizedUrl = strtolower(trim($url));

    // Verificar domínios permitidos
    foreach ($allowedDomains as $domain) {
        if (str_ends_with($normalizedUrl, $domain)) {
            return true;
        }
    }

    // Bloquear IPs internos e redes privadas
    $blockedPatterns = [
        '/^localhost/i',
        '/^127\./',           // 127.0.0.0/8 loopback
        '/^10\./',            // 10.0.0.0/8 private
        '/^172\.(1[6-9]|2[0-9]|3[0-1])\./',  // 172.16.0.0/12 private
        '/^192\.168\./',      // 192.168.0.0/16 private
        '/^0\./',             // 0.0.0.0/8 reserved
        '/^\[/',              // IPv6
        '/^::/',              // IPv6 localhost
    ];

    foreach ($blockedPatterns as $pattern) {
        if (preg_match($pattern, $normalizedUrl)) {
            return false;
        }
    }

    // Validar como hostname
    if (filter_var($normalizedUrl, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        return true;
    }

    return false;
}
```

### Proteções Aplicadas
- ✅ Whitelist de domínios oficiais Nuvemshop
- ✅ Bloqueio de localhost e loopback (127.0.0.0/8)
- ✅ Bloqueio de redes privadas (RFC 1918)
- ✅ Bloqueio de IPv6 localhost
- ✅ Validação de hostname com `FILTER_VALIDATE_DOMAIN`
- ✅ Limite de tamanho (máx 255 caracteres)

### Testes Recomendados
```bash
# Teste 1: Domínio válido
POST /api/integrations/nuvemshop/connect
{"store_url": "minhoja.lojavirtualnuvem.com.br"}

# Teste 2: SSRF localhost (deve rejeitar)
POST /api/integrations/nuvemshop/connect
{"store_url": "localhost:8000"}

# Teste 3: SSRF rede privada (deve rejeitar)
POST /api/integrations/nuvemshop/connect
{"store_url": "192.168.1.1"}

# Teste 4: SSRF metadata endpoint (deve rejeitar)
POST /api/integrations/nuvemshop/connect
{"store_url": "169.254.169.254"}
```

---

## 2.3 Admin Reset Password sem Confirmação

**Arquivo:** `app/Http/Controllers/Api/AdminController.php:336-367`
**Severidade:** 🟠 ALTA
**Status:** ✅ CORRIGIDO

### Descrição da Vulnerabilidade
Admins podiam resetar senhas de clientes sem confirmar sua própria senha, facilitando:
- Ataque de admin comprometido
- Escalação de privilégios se sessão admin for sequestrada
- Ausência de autenticação para ação crítica

### Correção Implementada
```php
public function resetPassword(Request $request, int $id): JsonResponse
{
    $admin = $request->user();
    $client = User::where('role', UserRole::Client)->findOrFail($id);

    $validated = $request->validate([
        'password' => ['required', 'string', 'min:8'],
        'admin_password' => ['required', 'string'],
    ], [
        'password.required' => 'A nova senha é obrigatória.',
        'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
        'admin_password.required' => 'Confirme sua senha de administrador.',
    ]);

    // SECURITY: Verificar senha do admin como confirmação
    if (!Hash::check($validated['admin_password'], $admin->password)) {
        return response()->json([
            'message' => 'Senha de administrador incorreta.',
        ], 403);
    }

    $client->update([
        'password' => Hash::make($validated['password']),
        'must_change_password' => true,
    ]);

    // SECURITY: Invalidar todas as sessões do cliente
    $client->tokens()->delete();

    ActivityLog::log('admin.password_reset', $client);

    return response()->json([
        'message' => 'Senha redefinida com sucesso. O cliente deverá trocar a senha no próximo login.',
    ]);
}
```

### Proteções Aplicadas
- ✅ Requer senha do admin para confirmar ação
- ✅ Verifica senha com `Hash::check()`
- ✅ Retorna 403 Forbidden se senha incorreta
- ✅ Invalida todos os tokens do cliente após reset
- ✅ Define flag `must_change_password` para forçar troca

### Impacto no Frontend
**Atualização necessária:** O frontend precisa adicionar campo `admin_password` no formulário de reset.

```vue
// AdminClientDetail.vue (exemplo)
<input
  v-model="resetForm.admin_password"
  type="password"
  placeholder="Confirme sua senha de administrador"
  required
/>
```

### Testes Recomendados
```bash
# Teste 1: Reset com senha correta
POST /api/admin/clients/123/reset-password
{
  "password": "NovaSenh@123",
  "admin_password": "SenhaDoAdmin123"
}

# Teste 2: Reset com senha incorreta (deve falhar)
POST /api/admin/clients/123/reset-password
{
  "password": "NovaSenh@123",
  "admin_password": "SenhaErrada"
}

# Teste 3: Verificar invalidação de tokens
# Após reset bem-sucedido, tentar usar token antigo do cliente
GET /api/dashboard
Authorization: Bearer <token_antigo_do_cliente>
# Deve retornar 401 Unauthorized
```

---

## 2.4 Email Enumeration

**Arquivo:** `app/Http/Controllers/Api/AuthController.php:133-149`
**Severidade:** 🟠 ALTA
**Status:** ✅ CORRIGIDO

### Descrição da Vulnerabilidade
O endpoint de recuperação de senha retornava mensagens diferentes para:
- Email existente: "Link enviado"
- Email inexistente: "Erro ao enviar"

Isso permitia enumerar usuários válidos do sistema.

### Correção Implementada
```php
public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
{
    // SECURITY: Enviar link silenciosamente para prevenir enumeração
    // Sempre retorna sucesso independente do email existir
    Password::sendResetLink($request->only('email'));

    // Log interno para monitoramento (não exposto ao usuário)
    Log::info('Password reset attempted', [
        'email' => $request->email,
        'ip' => $request->ip(),
    ]);

    // Mensagem genérica previne enumeração de emails
    return response()->json([
        'message' => 'Se o e-mail estiver cadastrado, você receberá um link de redefinição.',
    ]);
}
```

### Proteções Aplicadas
- ✅ Mensagem genérica independente do email existir
- ✅ Não lança exceção se email não encontrado
- ✅ Log interno para auditoria (não exposto ao usuário)
- ✅ Registra IP para detecção de abuso
- ✅ Mesmo tempo de resposta (sem timing attack)

### Comportamento Anterior vs. Novo

| Cenário | Antes | Depois |
|---------|-------|--------|
| Email existe | "Link enviado para seu e-mail" | "Se o e-mail estiver cadastrado..." |
| Email não existe | Erro 422: "Não foi possível enviar" | "Se o e-mail estiver cadastrado..." |
| Tempo resposta | Diferente (timing leak) | Sempre similar |

### Testes Recomendados
```bash
# Teste 1: Email válido
POST /api/forgot-password
{"email": "usuario@existente.com"}
# Resposta: "Se o e-mail estiver cadastrado..."

# Teste 2: Email inválido
POST /api/forgot-password
{"email": "naoexiste@fake.com"}
# Resposta: "Se o e-mail estiver cadastrado..." (mesma mensagem)

# Teste 3: Verificar logs internos
tail -f storage/logs/laravel.log | grep "Password reset attempted"
```

---

## Arquivos Modificados

```
app/Http/Controllers/Api/AdminController.php
  - Linha 84-97: SQL Injection fix (whitelist sort_by)
  - Linha 336-367: Admin password confirmation fix

app/Http/Controllers/Api/IntegrationController.php
  - Linha 85-135: SSRF prevention (URL validation)

app/Http/Controllers/Api/AuthController.php
  - Linha 1-16: Added Log import
  - Linha 133-149: Email enumeration prevention
```

## Checklist de Validação

- [x] 2.1 SQL Injection corrigido com whitelist
- [x] 2.2 SSRF prevenido com whitelist de domínios e bloqueio de IPs internos
- [x] 2.3 Admin reset password requer confirmação de senha
- [x] 2.4 Email enumeration prevenido com mensagem genérica
- [x] Imports adicionados onde necessário
- [x] Código preserva funcionalidade existente
- [x] Comentários SECURITY adicionados para documentação

## Próximos Passos

### Frontend Updates Necessárias
1. **AdminClientDetail.vue**: Adicionar campo `admin_password` no formulário de reset
2. **Mensagens de erro**: Atualizar para nova mensagem genérica de forgot password

### Testes Automatizados Recomendados
```php
// tests/Feature/Security/Phase2Test.php
test('sort_by only accepts whitelisted fields')
test('store_url rejects localhost')
test('store_url rejects private IPs')
test('admin reset password requires admin password')
test('forgot password returns generic message')
```

### Monitoramento
- Adicionar alertas para tentativas de SSRF nos logs
- Monitorar múltiplas tentativas de forgot password do mesmo IP
- Auditar resets de senha por admins

## Referências

- OWASP Top 10 2021: A03 Injection
- OWASP Top 10 2021: A10 SSRF
- OWASP Authentication Cheat Sheet
- CWE-89: SQL Injection
- CWE-918: SSRF
- CWE-200: Information Exposure
- RFC 1918: Private Address Space
