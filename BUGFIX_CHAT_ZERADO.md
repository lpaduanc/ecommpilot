# Bugfix: Chat mostrando dados zerados

## Problema

O chat AI estava retornando R$ 0,00 em vendas mesmo quando o usuário tinha muitas vendas no período.

## Causa Raiz

O método `ChatbotService::getStoreData()` estava fazendo uma query incorreta na tabela `synced_products`:

```php
// INCORRETO - coluna 'quantity' não existe
$lowStockProducts = SyncedProduct::where('store_id', $store->id)
    ->whereNotNull('quantity')  // ❌ ERRO
    ->where('quantity', '>', 0)
    ->where('quantity', '<=', 5)
```

A coluna correta é `stock_quantity`, não `quantity`.

Quando esta query falhava com erro SQL (`column "quantity" does not exist`), a exception era capturada e o método retornava `getEmptyStoreData()`, que tem todos os valores zerados.

## Solução

Corrigido para usar a coluna correta `stock_quantity`:

```php
// CORRETO
$lowStockProducts = SyncedProduct::where('store_id', $store->id)
    ->whereNotNull('stock_quantity')  // ✅ CORRETO
    ->where('stock_quantity', '>', 0)
    ->where('stock_quantity', '<=', 5)
```

## Arquivo Alterado

- `app/Services/ChatbotService.php` (linhas 369-379)

## Verificação

Após a correção, o chat agora retorna os dados corretos:

```
Store: ISZI Cosméticos
Period: 10/01/2026 to 25/01/2026
Total Revenue: R$ 199.232,66
Total Orders: 1.150
Average Ticket: R$ 173,25
```

## Teste Manual

Execute no Tinker para verificar:

```bash
docker compose exec -T app php artisan tinker --execute="
use App\Services\ChatbotService;
use App\Models\User;

\$user = User::find(12);
\$chatService = app(ChatbotService::class);

\$response = \$chatService->getResponse(
    \$user,
    null,
    'Como estão minhas vendas?',
    null
);

echo substr(\$response, 0, 500);
"
```

## Logs Relevantes

Antes do fix, os logs mostravam:

```
[2026-01-25 14:46:24] local.INFO: ChatbotService: Paid orders in period
{"paid_count":1150,"total_revenue":199232.66}

[2026-01-25 14:46:25] local.WARNING: Error fetching store data for chat:
SQLSTATE[42703]: Undefined column: 7 ERROR: column "quantity" does not exist
```

Após o fix, não há mais erros e os dados são retornados corretamente.
