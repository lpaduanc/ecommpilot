# BUGFIX: Token Expirado Durante Sincronização

## Problema

Quando o usuário clica no botão "Sincronizar" no frontend e o token OAuth da Nuvemshop está expirado, ele recebe mensagem de erro e a sincronização não é iniciada.

## Causa Raiz

1. **Store com status `TokenExpired`**: De sync anteriores, a store pode ter `sync_status = 'token_expired'` e `token_requires_reconnection = true`
2. **Estratégias de refresh que não funcionam**: O método `refreshAccessToken()` tentava 3 estratégias que **não existem** na API Nuvemshop:
   - Estratégia 1: `client_credentials` grant
   - Estratégia 2: `authorization_code` com `user_id` direto
   - Estratégia 3: OAuth server-side (só funciona se app já estiver autorizada)
3. **Nenhuma limpeza de flags**: Quando o usuário clicava "Sincronizar", as flags antigas de token expirado não eram limpadas

## Caminho do Código (Antes da Correção)

```
Frontend (integrationStore.js:176)
    ↓
POST /api/integrations/stores/{storeId}/sync
    ↓
IntegrationController::sync() (linha 437)
    ↓ [SE store já tem sync_status=TokenExpired, continua normalmente]
    ↓
SyncStoreDataJob::dispatch()
    ↓
SyncStoreDataJob::handle() (linha 125)
    ↓ [Verifica token_requires_reconnection]
    ↓
NuvemshopService::attemptReconnection() (linha 835)
    ↓
NuvemshopService::refreshAccessToken() (linha 845)
    ↓ [Tenta 3 estratégias que NÃO funcionam]
    ↓ [FALHA]
    ↓
Job limpa flags e continua sync (linha 146-149)
    ↓
makeRequest() recebe 401 (linha 747)
    ↓
Tenta refreshAccessToken() novamente (linha 750)
    ↓ [FALHA novamente]
    ↓
Lança exceção RuntimeException
    ↓
Job marca como Failed
```

## Solução Implementada

### 1. Novo Processo de Reconexão OAuth (NuvemshopService.php)

**Substituído o `refreshAccessToken()` completo** para usar o **processo de conexão completo**:

```php
private function refreshAccessToken(Store $store): bool
{
    // Gera URL de autorização OAuth (mesmo processo de conexão normal)
    $authUrl = $this->getAuthorizationUrl($store->user_id, $domain);

    // Segue a cadeia de redirects procurando nosso callback com code
    $code = $this->followRedirectsForCode($authUrl);

    if ($code) {
        // Usa handleCallback() para trocar code por token
        $this->handleCallback($code, $store->user_id, $domain);
        return true;
    }

    return false;
}
```

**Novo método `followRedirectsForCode()`**:
- Segue até 10 redirects seguindo a cadeia OAuth
- Procura nosso callback URL com `code=` no query string
- Se encontrar, extrai o code e retorna
- Usa `withoutVerifying()` para aceitar certificados SSL em dev

### 2. Limpeza de Flags no Controller (IntegrationController.php)

**Adicionado ao método `sync()`** (linha 453):

```php
// Sempre limpa flags de reconexão quando usuário solicita sync manualmente
if ($store->token_requires_reconnection || $store->sync_status === SyncStatus::TokenExpired) {
    Log::info('Limpando flags de reconexao antes de iniciar sync manual', [
        'store_id' => $store->id,
        'previous_status' => $store->sync_status->value,
    ]);

    $store->update([
        'token_requires_reconnection' => false,
        'sync_status' => SyncStatus::Pending,
    ]);

    $store->refresh();
}
```

**Por que isso funciona:**
- Quando o usuário clica "Sincronizar" manualmente, SEMPRE damos uma nova chance
- Limpamos as flags antigas de token expirado
- O job vai tentar reconectar via OAuth automático
- Se falhar, tenta sync normalmente (makeRequest vai detectar 401 e tentar refresh)
- **Se tudo falhar**, o job falha mas o usuário pode tentar novamente

### 3. Código Removido

**Removidas as 3 estratégias antigas que não funcionam:**
- Estratégia 1: `client_credentials` (linhas 853-895)
- Estratégia 2: `authorization_code` com `user_id` (linhas 897-943)
- Método `attemptServerSideOAuth()` (foi substituído por `followRedirectsForCode()`)
- Método `exchangeCodeForToken()` (substituído por `handleCallback()`)

## Fluxo Após Correção

```
Usuario clica "Sincronizar"
    ↓
IntegrationController::sync()
    ↓ [LIMPA flags antigas: token_requires_reconnection=false, sync_status=Pending]
    ↓
SyncStoreDataJob::dispatch()
    ↓
SyncStoreDataJob::handle()
    ↓ [Como token_requires_reconnection=false, pula reconexão inicial]
    ↓
makeRequest() para /products
    ↓ [Recebe 401]
    ↓
refreshAccessToken() (NOVA IMPLEMENTAÇÃO)
    ↓
getAuthorizationUrl() → gera URL OAuth
    ↓
followRedirectsForCode() → segue redirects
    ↓ [Encontra callback com code]
    ↓
handleCallback() → troca code por token
    ↓ [Token renovado!]
    ↓
makeRequest() tenta novamente com novo token
    ↓ [SUCESSO]
    ↓
Sync continua normalmente
```

## Logs para Rastreamento

Todos os logs da reconexão usam prefixo `[RECONEXAO]`:

```php
Log::info('[RECONEXAO] Iniciando processo de conexao automatica', [...]);
Log::info('[RECONEXAO] URL de autorizacao gerada', [...]);
Log::info('[RECONEXAO] Seguindo redirect hop 1', [...]);
Log::info('[RECONEXAO] Code encontrado no hop 3!', [...]);
Log::info('[RECONEXAO] Token renovado com sucesso', [...]);
```

## Comportamento Esperado

### Cenário 1: Token válido
- Usuario clica "Sincronizar"
- Flags antigas são limpas
- Sync inicia normalmente
- ✅ Sucesso

### Cenário 2: Token expirado, app ainda autorizada
- Usuario clica "Sincronizar"
- Flags antigas são limpas
- makeRequest recebe 401
- refreshAccessToken() segue redirects OAuth
- Nuvemshop reconhece app autorizada e redireciona com code
- handleCallback() troca code por novo token
- makeRequest tenta novamente
- ✅ Sucesso

### Cenário 3: Token expirado, app desautorizada
- Usuario clica "Sincronizar"
- Flags antigas são limpas
- makeRequest recebe 401
- refreshAccessToken() segue redirects OAuth
- Nuvemshop NÃO redireciona automaticamente (requer interação do usuário)
- refreshAccessToken() retorna false
- makeRequest lança exceção RuntimeException
- Job marca como Failed
- ❌ Usuario precisa reconectar manualmente

## Arquivos Modificados

1. `app/Services/Integration/NuvemshopService.php`
   - Método `refreshAccessToken()`: Reescrito completamente (linhas 845-905)
   - Método `followRedirectsForCode()`: Novo método (linhas 911-973)
   - Métodos removidos: `attemptServerSideOAuth()`, `exchangeCodeForToken()`

2. `app/Http/Controllers/Api/IntegrationController.php`
   - Método `sync()`: Adicionada limpeza de flags (linhas 453-471)

## Teste Manual

1. Marcar uma store com token expirado:
```sql
UPDATE stores SET token_requires_reconnection = true, sync_status = 'token_expired' WHERE id = 123;
```

2. No frontend, clicar "Sincronizar"
3. Verificar logs em `storage/logs/sync.log`:
   - Deve mostrar `[RECONEXAO] Iniciando processo de conexao automatica`
   - Deve mostrar tentativas de seguir redirects
   - Se app autorizada: deve mostrar `[RECONEXAO] Token renovado com sucesso`
   - Se app desautorizada: deve mostrar `[RECONEXAO] Nenhum code recebido nos redirects`

4. Verificar que a sync SEMPRE inicia, mesmo que reconexão falhe

## Notas Importantes

- ⚠️ A Nuvemshop **NÃO** oferece refresh_token - tokens não expiram, mas podem ser invalidados
- ⚠️ Se a app for desautorizada, o único jeito é OAuth completo com interação do usuário
- ✅ A reconexão automática via `followRedirectsForCode()` só funciona se a app **ainda estiver autorizada**
- ✅ O job NUNCA é bloqueado por token expirado - sempre tenta sincronizar
- ✅ Quando o usuário clica "Sincronizar", SEMPRE damos uma nova chance limpando flags antigas
