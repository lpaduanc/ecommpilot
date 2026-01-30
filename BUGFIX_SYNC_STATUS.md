# Correção: Status de Sincronização não Atualizado em Caso de Falha

## Problema Identificado

Quando a sincronização de uma loja falhava no modo paralelo (padrão), o status da Store permanecia como `syncing`, impedindo que o usuário tentasse sincronizar novamente.

### Causa Raiz

No arquivo `app/Jobs/SyncStoreDataJob.php`, o callback `catch()` do Laravel Batch (modo paralelo) apenas registrava o erro no log, mas **não atualizava** o `sync_status` da Store para `failed`.

```php
// ANTES (linhas 233-241) - PROBLEMA
->catch(function (Batch $batch, \Throwable $e) use ($store, $logChannel) {
    // Algum job falhou
    Log::channel($logChannel)->error('!!! [PARALLEL MODE] Batch falhou parcialmente', [
        'store_id' => $store->id,
        'batch_id' => $batch->id,
        'failed_jobs' => $batch->failedJobs,
        'error' => $e->getMessage(),
    ]);
})
```

### Fluxo de Sincronização

**Modo Paralelo (padrão):**
```
SyncStoreDataJob::dispatch($store)
  ├─ markAsSyncing() → sync_status = 'syncing'
  ├─ Bus::batch([SyncProductsJob, SyncOrdersJob, SyncCouponsJob])
  │   ├─ then() → SUCESSO → markAsSynced() ✅
  │   ├─ catch() → FALHA → APENAS LOG ❌ (BUG)
  │   └─ finally() → sempre executado
  └─ failed() → só chamado se SyncStoreDataJob falha (não jobs filhos)
```

**Consequência:** Store ficava com `sync_status = 'syncing'` permanentemente após falha de qualquer job do batch.

## Solução Implementada

### 1. Adicionado `markAsFailed()` no callback `catch()`

O callback agora:
1. Atualiza o model Store (`refresh()`) para obter status mais recente
2. Verifica se o erro foi de token expirado (`TokenExpired`)
3. Se não for erro de token, marca como `failed` e notifica usuário
4. Preserva status `token_expired` quando aplicável (mais específico)

```php
// DEPOIS (linhas 234-255) - CORRIGIDO
->catch(function (Batch $batch, \Throwable $e) use ($store, $logChannel, $notificationServiceRef) {
    // Algum job falhou
    // Refresh store to get latest status (pode ter sido marcado como token_expired)
    $store->refresh();

    // Só marca como failed se não for erro de token
    // (token_expired é mais específico e deve ser preservado)
    if ($store->sync_status !== SyncStatus::TokenExpired) {
        $store->markAsFailed();

        // Notificar falha da sincronização (apenas se não for token_expired)
        $notificationServiceRef->notifySyncFailed($store, 'all', $e->getMessage());
    }

    Log::channel($logChannel)->error('!!! [PARALLEL MODE] Batch falhou', [
        'store_id' => $store->id,
        'batch_id' => $batch->id,
        'failed_jobs' => $batch->failedJobs,
        'sync_status' => $store->sync_status->value,
        'error' => $e->getMessage(),
    ]);
})
```

### 2. Importado `SyncStatus` Enum

Adicionado import no topo do arquivo para melhor legibilidade:

```php
use App\Enums\SyncStatus;
```

### 3. Preservação da Referência do NotificationService

Criada variável `$notificationServiceRef` para capturar o serviço na closure:

```php
$notificationServiceRef = $notificationService; // Captura para closures
```

## Arquivos Modificados

- `app/Jobs/SyncStoreDataJob.php`
  - Linha 4: Adicionado `use App\Enums\SyncStatus;`
  - Linha 184: Criada `$notificationServiceRef` para closures
  - Linhas 199, 218: Substituído `$notificationService` por `$notificationServiceRef`
  - Linhas 234-255: Reescrito callback `catch()` com lógica de marcação de falha

## Status de Sincronização

### Enum `SyncStatus` (app/Enums/SyncStatus.php)

```php
enum SyncStatus: string
{
    case Pending = 'pending';       // Aguardando primeira sincronização
    case Syncing = 'syncing';       // Sincronização em andamento
    case Completed = 'completed';   // Sincronizado com sucesso
    case Failed = 'failed';         // Falha genérica (erro de API, rede, etc)
    case TokenExpired = 'token_expired'; // Token OAuth inválido - requer reconexão
}
```

### Fluxo de Status

```
┌─────────┐
│ Pending │ ← Store recém-conectada
└────┬────┘
     │ SyncStoreDataJob::dispatch()
     ↓
┌─────────┐
│ Syncing │ ← markAsSyncing()
└────┬────┘
     │
     ├─ SUCESSO ────────────────────────┐
     │                                   ↓
     │                            ┌───────────┐
     │                            │ Completed │
     │                            └───────────┘
     │
     ├─ ERRO DE API/REDE ───────────────┐
     │                                   ↓
     │                            ┌────────┐
     │                            │ Failed │ ← Usuário pode tentar novamente
     │                            └────────┘
     │
     └─ ERRO 401 (Token) ───────────────┐
                                         ↓
                                  ┌──────────────┐
                                  │ TokenExpired │ ← Requer reconexão OAuth
                                  └──────────────┘
```

## Tratamento de Erros de Token (401)

O `NuvemshopService` já tratava corretamente erros de autenticação:

1. **Recebe 401** no método `makeRequest()` (linha 747)
2. **Chama `refreshAccessToken()`** (linha 750)
3. **Marca como TokenExpired** via `$store->markAsTokenExpired()` (linha 840)
4. **Retorna false** indicando que refresh automático não é possível

**Importante:** Nuvemshop não suporta `refresh_token`. Tokens só expiram quando:
- Um novo token é gerado (invalida o anterior)
- O usuário desinstala o app

Portanto, erro 401 requer reconexão completa via OAuth.

## Testes Recomendados

### Teste 1: Falha de Sincronização Genérica

1. Simular erro em um dos jobs do batch (ex: SyncOrdersJob)
2. Verificar que Store é marcada como `failed`
3. Verificar que notificação de falha é enviada
4. Verificar que usuário pode clicar em "Sincronizar" novamente

### Teste 2: Erro de Token (401)

1. Invalidar token de acesso da Store
2. Tentar sincronizar
3. Verificar que Store é marcada como `token_expired` (NÃO `failed`)
4. Verificar que `token_requires_reconnection = true`
5. Verificar que UI mostra mensagem de reconexão

### Teste 3: Sincronização Bem-Sucedida

1. Sincronizar loja com credenciais válidas
2. Verificar que Store é marcada como `completed`
3. Verificar que `last_sync_at` é atualizado
4. Verificar que notificação de sucesso é enviada

## Endpoints Afetados

- `POST /api/integrations/stores/{store}/sync` (IntegrationController::sync)
  - Verifica `$store->isSyncing()` antes de disparar job
  - Verifica `$store->requiresReconnection()` para erro 401

- `GET /api/integrations/sync-status` (IntegrationController::syncStatus)
  - Retorna `sync_status` atual da loja ativa
  - Usado pelo frontend para mostrar status em tempo real

## Frontend

O frontend já monitora o `sync_status` via polling ou evento:

```typescript
// resources/js/stores/dashboardStore.js
async fetchSyncStatus() {
  const response = await axios.get('/api/integrations/sync-status');
  // Atualiza UI baseado em response.sync_status
  // 'failed' → Mostra botão "Tentar Novamente"
  // 'token_expired' → Mostra botão "Reconectar Loja"
}
```

## Conclusão

A correção garante que:

✅ Falhas de sincronização atualizam corretamente o status para `failed`
✅ Erros de token mantém status mais específico `token_expired`
✅ Usuários são notificados sobre falhas
✅ Usuários podem tentar sincronizar novamente após falha
✅ Logs registram status atual da Store para debug

**Data da Correção:** 2026-01-29
**Arquivos Modificados:** `app/Jobs/SyncStoreDataJob.php`
**Issue:** Status de sincronização não atualizado quando jobs do batch falham
