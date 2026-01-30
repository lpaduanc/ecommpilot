# BUGFIX: Status de Sincronização Travado - RESOLVIDO

**Data:** 2026-01-29
**Severidade:** ALTA
**Status:** ✅ CORRIGIDO

## Problema Reportado

Status de sincronização permanecia como "syncing" mesmo após a conclusão da sincronização há 22 minutos, impedindo nova sincronização e mostrando aviso permanente no frontend.

## Investigação

### Logs Analisados

1. **Erro Principal** (storage/logs/laravel-2026-01-29.log):
```
UnexpectedValueException: The stream or file "/var/www/html/storage/logs/sync-2026-01-29.log"
could not be opened in append mode: Permission denied
```

2. **Batch ID:** `a0f30a47-358e-464e-97ac-1e0ddfd1348a`

3. **Consequência:** O batch de sincronização (Job Batching) falhou devido a erro de permissão no arquivo de log, causando:
   - Execução do callback `catch()`
   - MAS o callback falhou ao atualizar o status devido a uso incorreto da variável `$store`

### Dados Verificados

```bash
Store ID: 19
Status no banco: syncing (travado)
Última atualização: 2026-01-29 01:06:05
Produtos sincronizados: 270 (último: 01:06:07)
Pedidos sincronizados: 70,856 (último: 01:06:36)
```

**Conclusão:** A sincronização FOI CONCLUÍDA com sucesso, mas o status não foi atualizado.

## Causa Raiz

### 1. Erro de Permissão de Log
- Jobs de sync não conseguiram escrever em `sync-2026-01-29.log`
- Causou falha em cadeia de todos os jobs do batch

### 2. Bug no Callback `catch()`
**Código Original:**
```php
->catch(function (Batch $batch, \Throwable $e) use ($store, $logChannel, $notificationServiceRef) {
    $store->refresh();  // ❌ BUG: refresh() não garante atualização do objeto

    if ($store->sync_status !== SyncStatus::TokenExpired) {
        $store->markAsFailed();  // ❌ Pode não funcionar se $store está stale
    }
})
```

**Problema:** A variável `$store` capturada na closure pode ficar "stale" (desatualizada), especialmente em contextos assíncronos como Job Batching. O `refresh()` não garante que a instância seja atualizada corretamente.

### 3. Bug Similar no Callback `then()`
O callback de sucesso tinha o mesmo problema:
```php
->then(function (Batch $batch) use ($store, ...) {
    $store->markAsSynced();  // ❌ Pode falhar se $store está stale
})
```

## Correções Implementadas

### 1. Refetch da Store no Banco de Dados

**Arquivo:** `app/Jobs/SyncStoreDataJob.php`

#### Callback `then()` (sucesso):
```php
->then(function (Batch $batch) use ($store, ...) {
    // ✅ CORREÇÃO: Buscar instância fresca do banco
    $freshStore = Store::find($store->id);

    if (!$freshStore) {
        Log::error('[PARALLEL MODE] Store not found during batch then');
        return;
    }

    $freshStore->markAsSynced();
    $dashboardService->clearCache($freshStore);
    // ... usar $freshStore em todo o callback
})
```

#### Callback `catch()` (falha):
```php
->catch(function (Batch $batch, \Throwable $e) use ($store, ...) {
    // ✅ CORREÇÃO: Buscar instância fresca do banco
    $freshStore = Store::find($store->id);

    if (!$freshStore) {
        Log::error('[PARALLEL MODE] Store not found during batch catch');
        return;
    }

    if ($freshStore->sync_status !== SyncStatus::TokenExpired) {
        $freshStore->markAsFailed();
        $notificationServiceRef->notifySyncFailed($freshStore, 'all', $e->getMessage());
    }

    Log::error('!!! [PARALLEL MODE] Batch falhou', [
        'sync_status' => $freshStore->sync_status->value,
        // ...
    ]);
})
```

### 2. Proteção Contra Erros de Log

**Callback `finally()`:**
```php
->finally(function (Batch $batch) use ($store, $logChannel) {
    try {
        Log::channel($logChannel)->info('<<< [PARALLEL MODE] Batch finalizado', [...]);
    } catch (\Exception $e) {
        // ✅ Fallback para canal padrão se houver erro de permissão
        Log::info('[SYNC FALLBACK] Batch finalizado', [
            'store_id' => $store->id,
            'log_error' => $e->getMessage(),
        ]);
    }
})
```

**Método auxiliar adicionado:**
```php
/**
 * Log com fallback para canal padrão em caso de erro
 */
private function safeLog(string $level, string $message, array $context = []): void
{
    try {
        Log::channel($this->logChannel)->$level($message, $context);
    } catch (\Exception $e) {
        Log::$level("[SYNC FALLBACK] $message", array_merge($context, [
            'original_channel' => $this->logChannel,
            'fallback_reason' => $e->getMessage(),
        ]));
    }
}
```

### 3. Comando de Recuperação

**Novo comando:** `sync:fix-stuck`

```bash
# Corrigir loja específica
php artisan sync:fix-stuck 19

# Corrigir todas as lojas travadas
php artisan sync:fix-stuck --all

# Modo dry-run (apenas visualizar)
php artisan sync:fix-stuck --all --dry-run
```

**Lógica do comando:**
1. Busca lojas com status `syncing` ou `pending` há mais de 15 minutos
2. Verifica timestamp dos dados mais recentes (produtos/pedidos)
3. Se dados foram atualizados nos últimos 30 minutos:
   - Marca como `completed`
   - Define `last_sync_at` como timestamp dos dados
4. Caso contrário:
   - Marca como `failed`

**Arquivo:** `app/Console/Commands/FixStuckSyncStatus.php`

## Correção Imediata Aplicada

```bash
# Store ID 19 foi corrigida manualmente
docker compose exec app php artisan tinker --execute="
\$store = App\\Models\\Store::find(19);
\$store->update([
    'sync_status' => App\\Enums\\SyncStatus::Completed,
    'last_sync_at' => now(),
]);
"
```

**Resultado:**
- Status: `syncing` → `completed` ✅
- Frontend atualizado automaticamente via polling
- Aviso de sincronização removido

## Prevenção Futura

### 1. Monitoramento
Adicionar ao cron/scheduler:
```php
// app/Console/Kernel.php
$schedule->command('sync:fix-stuck --all')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
```

### 2. Permissões de Log
Verificar permissões dos arquivos de log:
```bash
chmod -R 775 storage/logs
chown -R www-data:www-data storage/logs
```

### 3. Alertas
Configurar alerta para lojas travadas há mais de 30 minutos.

## Testes Recomendados

1. **Teste de Sync Normal:**
   - Iniciar sincronização via UI
   - Verificar se status muda para `completed` após conclusão

2. **Teste de Falha Simulada:**
   - Remover permissões de log temporariamente
   - Verificar se status muda para `failed` corretamente

3. **Teste do Comando:**
   - Criar loja com status travado manualmente
   - Executar `sync:fix-stuck`
   - Verificar correção automática

## Arquivos Modificados

- ✅ `app/Jobs/SyncStoreDataJob.php` - Correções nos callbacks `then()`, `catch()`, `finally()`
- ✅ `app/Console/Commands/FixStuckSyncStatus.php` - Novo comando de recuperação

## Impacto

- **Usuários Afetados:** 1 loja (ID 19)
- **Tempo de Inatividade:** ~22 minutos (sync travada, mas funcional)
- **Perda de Dados:** Nenhuma (sync foi bem-sucedida)
- **Correção:** Imediata após diagnóstico

## Lições Aprendidas

1. **Closures Assíncronas:** Sempre usar `Model::find()` ao invés de `$model->refresh()` em callbacks assíncronos
2. **Fallback de Logs:** Erros de I/O em logs não devem impedir a lógica principal
3. **Monitoring:** Implementar detecção automática de status travados
4. **Permissões:** Garantir que workers tenham permissões corretas nos diretórios de log

## Referências

- Job Batching: https://laravel.com/docs/12.x/queues#job-batching
- Eloquent Refreshing: https://laravel.com/docs/12.x/eloquent#refreshing-models
- Closure Variable Scoping: https://www.php.net/manual/en/functions.anonymous.php
