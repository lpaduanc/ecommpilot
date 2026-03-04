# Instrucoes: Ressincronizacao Completa de Clientes

Este documento descreve como usar o comando `customers:resync` para limpar e ressincronizar
completamente os dados de clientes de uma ou mais lojas no EcommPilot.

## Quando usar

Use este comando quando os dados na tabela `synced_customers` estiverem inconsistentes
(por exemplo, apos correcao de bugs na sync de clientes, duplicatas, ou campos com valores
incorretos). O comando realiza um hard-delete de todos os registros existentes e dispara
uma sync completa ignorando o filtro incremental de 24 horas.

**Nao use** este comando como rotina. Ele e um procedimento corretivo, nao operacional.

## O que o comando faz

1. Exibe uma tabela com as lojas afetadas e a contagem atual de registros
2. Pede confirmacao interativa (a menos que `--force` seja passado)
3. Para cada loja:
   - Verifica se o token OAuth esta valido (pula lojas que precisam de reconexao)
   - Hard-delete (`forceDelete`) de todos os registros em `synced_customers` da loja,
     incluindo os soft-deleted
   - Invalida o cache RFM da loja
   - Dispara `SyncCustomersJob` com `updatedSince = null` (sync completa, sem filtro de data)

O `SyncCustomersJob` e processado pela fila `sync` e, ao terminar, invalida novamente
o cache RFM e salva os dados atualizados da Nuvemshop.

**A sync normal das proximas 24 horas continua funcionando normalmente** porque o
`SyncStoreDataJob` controla o filtro incremental de pedidos/clientes pelo campo
`last_sync_at` da loja, que nao e alterado por este comando.

## Sintaxe

```
php artisan customers:resync [opcoes]

Opcoes:
  --store={id}   ID da loja especifica para ressincronizar
  --all          Ressincronizar clientes de todas as lojas
  --dry-run      Mostrar o que seria feito sem executar nada
  --force        Pular a confirmacao interativa
```

## Exemplos de uso

### Ver o que seria feito (sem alterar nada)

```bash
# Uma loja especifica
php artisan customers:resync --store=5 --dry-run

# Todas as lojas
php artisan customers:resync --all --dry-run
```

### Ressincronizar uma loja especifica

```bash
php artisan customers:resync --store=5
```

O comando exibira a tabela de impacto e pedira confirmacao antes de executar.

### Ressincronizar todas as lojas (com confirmacao)

```bash
php artisan customers:resync --all
```

### Ressincronizar sem confirmacao (para scripts/automacao)

```bash
# Uma loja
php artisan customers:resync --store=5 --force

# Todas as lojas
php artisan customers:resync --all --force
```

## Checklist para execucao em producao

Antes de executar em producao, siga esta sequencia:

1. **Dry-run primeiro** — sempre confira o impacto antes de aplicar:
   ```bash
   php artisan customers:resync --store={id} --dry-run
   ```

2. **Confirme o numero de registros** — a tabela do dry-run mostra quantos registros
   serao apagados. Certifique-se de que o numero faz sentido.

3. **Verifique o status da fila** — certifique-se de que o worker da fila `sync` esta
   rodando em producao:
   ```bash
   php artisan queue:work --queue=sync --tries=3 --timeout=700
   ```
   Ou via Horizon, se configurado.

4. **Execute o comando**:
   ```bash
   php artisan customers:resync --store={id}
   ```

5. **Acompanhe o progresso** — verifique os logs da fila sync:
   ```bash
   tail -f storage/logs/sync.log
   ```
   Ou pelo painel do Horizon em `/horizon`.

6. **Confirme a conclusao** — apos o job terminar, verifique a contagem de clientes:
   ```bash
   php artisan tinker --execute="echo App\Models\SyncedCustomer::where('store_id', {id})->count();"
   ```

## Comportamento com lojas com token invalido

Se uma loja tem `token_requires_reconnection = true` (token OAuth invalidado), o comando
**pula automaticamente** essa loja e exibe um aviso. Isso e intencional: nao adianta
limpar os dados se a sync subsequente vai falhar por falta de autorizacao.

Para ressincronizar uma loja com token invalido:
1. Acesse a interface e reconecte a loja (fluxo OAuth)
2. Execute o `customers:resync` novamente

## Impacto nos sistemas dependentes

| Sistema | Impacto | Recuperacao |
|---------|---------|-------------|
| Segmentos RFM | Cache invalidado imediatamente | Recalculado apos sync concluir |
| Dashboard de clientes | Dados ausentes ate sync concluir | Automatico |
| Chat IA (consultas de clientes) | Dados ausentes ate sync concluir | Automatico |
| Analises AI | Nao afetado | - |
| Pedidos e produtos | Nao afetado | - |

O tempo de indisponibilidade dos dados de clientes depende do volume da loja e da
velocidade da API da Nuvemshop (rate limit: 60 req/min por loja).

## Resolucao de problemas

### Job nao executa

Verifique se o worker da fila `sync` esta rodando:
```bash
# Via Horizon
php artisan horizon:status

# Manualmente
php artisan queue:work --queue=sync
```

### Sync falha com erro de token

Execute o reconnect OAuth pela interface e tente novamente.

### Clientes nao aparecem apos sync

Verifique os logs:
```bash
grep "CUSTOMERS" storage/logs/sync.log | tail -50
```

Se o job concluiu mas os dados nao aparecem, pode ser cache. Limpe:
```bash
php artisan cache:clear
```
