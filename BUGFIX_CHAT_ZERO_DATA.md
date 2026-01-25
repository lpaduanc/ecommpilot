# Bugfix: Chat mostrando dados zerados

## Problema

Quando o usuário clicava em "Como estão minhas vendas?" no chat, a resposta mostrava:
- Faturamento Total: R$ 0,00
- Pedidos Totais: 0
- Ticket Médio: R$ 0,00

Mesmo que a loja tivesse vendas reais no período.

## Causa Raiz

O problema estava na forma como o código filtrava pedidos pagos.

### Arquivos afetados:

1. **ChatbotService.php** (linha 319-326)
2. **AnalysisService.php** (linhas 192, 240-241, 275)
3. **StoreAnalysisService.php** (linha 805)

### O que estava errado:

O modelo `SyncedOrder` tem um cast para o enum `PaymentStatus`:

```php
// SyncedOrder.php
protected function casts(): array {
    return [
        'payment_status' => PaymentStatus::class,
        // ...
    ];
}
```

Mas o código estava tentando comparar com strings:

```php
// ❌ ERRADO - comparando enum com string
$orders->filter(fn ($o) => $o->payment_status?->value === 'paid' || $o->payment_status === 'paid');

// ❌ ERRADO - usando whereIn com strings
->whereIn('payment_status', ['paid', 'pago'])
```

Quando o Laravel faz o cast, `payment_status` é um objeto `PaymentStatus`, não uma string. As comparações falhavam e nenhum pedido era retornado.

## Solução

Usar o enum diretamente nas comparações e queries:

```php
// ✅ CORRETO - comparando enum com enum
$orders->filter(fn ($o) => $o->payment_status === PaymentStatus::Paid);

// ✅ CORRETO - usando where com enum
->where('payment_status', PaymentStatus::Paid)
```

## Mudanças Realizadas

### 1. ChatbotService.php (app/Services/ChatbotService.php)

**Antes (linhas 308-331):**
```php
// Get paid orders in period - check all payment statuses first
$allOrders = SyncedOrder::where('store_id', $store->id)
    ->whereBetween('external_created_at', [$startDate, $endDate])
    ->get();

\Log::info('ChatbotService: All orders in period', [
    'total' => $allOrders->count(),
    'by_status' => $allOrders->groupBy('payment_status')->map->count()->toArray(),
]);

// Filter to paid orders (handle both enum and string comparisons)
$orders = $allOrders->filter(function ($order) {
    $status = $order->payment_status;
    if ($status instanceof PaymentStatus) {
        return $status === PaymentStatus::Paid;
    }
    return $status === 'paid' || $status === PaymentStatus::Paid->value;
});

\Log::info('ChatbotService: Paid orders after filter', [
    'paid_count' => $orders->count(),
    'total_revenue' => $orders->sum('total'),
]);
```

**Depois (linhas 308-318):**
```php
// Get paid orders in period using query scope
$orders = SyncedOrder::where('store_id', $store->id)
    ->whereBetween('external_created_at', [$startDate, $endDate])
    ->where('payment_status', PaymentStatus::Paid)
    ->get();

\Log::info('ChatbotService: Paid orders in period', [
    'paid_count' => $orders->count(),
    'total_revenue' => $orders->sum('total'),
    'start_date' => $startDate->toDateString(),
    'end_date' => $endDate->toDateString(),
]);
```

**Benefícios:**
- Query no banco ao invés de filtrar em memória (mais eficiente)
- Comparação correta usando enum
- Logs mais claros com datas incluídas

### 2. AnalysisService.php (app/Services/AnalysisService.php)

**Mudanças:**

Linha 192:
```php
// Antes:
$paidOrders = $orders->filter(fn ($o) => $o->payment_status?->value === 'paid' || $o->payment_status === 'paid');

// Depois:
$paidOrders = $orders->filter(fn ($o) => $o->payment_status === PaymentStatus::Paid);
```

Linhas 240-241:
```php
// Antes:
$currentPaid = $currentOrders->filter(fn ($o) => $o->payment_status?->value === 'paid' || $o->payment_status === 'paid');
$previousPaid = $previousOrders->filter(fn ($o) => $o->payment_status?->value === 'paid' || $o->payment_status === 'paid');

// Depois:
$currentPaid = $currentOrders->filter(fn ($o) => $o->payment_status === PaymentStatus::Paid);
$previousPaid = $previousOrders->filter(fn ($o) => $o->payment_status === PaymentStatus::Paid);
```

Linha 275:
```php
// Antes:
$paidOrders = $orders->filter(fn ($o) => $o->payment_status?->value === 'paid' || $o->payment_status === 'paid');

// Depois:
$paidOrders = $orders->filter(fn ($o) => $o->payment_status === PaymentStatus::Paid);
```

Linhas 473, 476, 479:
```php
// Antes:
$refundedCount = $orders->filter(fn ($o) => ($o->payment_status?->value ?? $o->payment_status) === 'refunded')->count();
$paymentPendingCount = $orders->filter(fn ($o) => ($o->payment_status?->value ?? $o->payment_status) === 'pending')->count();
$paymentConfirmedCount = $orders->filter(fn ($o) => ($o->payment_status?->value ?? $o->payment_status) === 'paid')->count();

// Depois:
$refundedCount = $orders->filter(fn ($o) => $o->payment_status === PaymentStatus::Refunded)->count();
$paymentPendingCount = $orders->filter(fn ($o) => $o->payment_status === PaymentStatus::Pending)->count();
$paymentConfirmedCount = $orders->filter(fn ($o) => $o->payment_status === PaymentStatus::Paid)->count();
```

Linha 465:
```php
// Antes:
$byPaymentStatus = $orders->groupBy(fn ($o) => $o->payment_status?->value ?? $o->payment_status ?? 'unknown')
    ->map->count()
    ->toArray();

// Depois:
$byPaymentStatus = $orders->groupBy(fn ($o) => $o->payment_status?->value ?? 'unknown')
    ->map->count()
    ->toArray();
```

**Import adicionado:**
```php
use App\Enums\PaymentStatus;
```

### 3. StoreAnalysisService.php (app/Services/AI/Agents/StoreAnalysisService.php)

Linha 805:
```php
// Antes:
$totalRevenue = $store->orders()
    ->whereIn('payment_status', ['paid', 'pago'])
    ->sum('total');

// Depois:
$totalRevenue = $store->orders()
    ->where('payment_status', PaymentStatus::Paid)
    ->sum('total');
```

**Import adicionado:**
```php
use App\Enums\PaymentStatus;
```

## Melhorias de Performance

Além de corrigir o bug, a mudança no `ChatbotService` trouxe melhoria de performance:

- **Antes:** Buscar TODOS os pedidos do período → Filtrar em memória (duas operações)
- **Depois:** Buscar APENAS pedidos pagos direto do banco (uma operação)

Isso reduz:
- Uso de memória (menos objetos carregados)
- Tempo de processamento (filtro no SQL é mais rápido)
- Tráfego de dados entre banco e aplicação

## Como Testar

Execute o script de teste:

```bash
php test_chat_data.php
```

Ou teste manualmente:
1. Acesse o chat no frontend
2. Clique em "Como estão minhas vendas?"
3. Verifique se os dados reais aparecem (faturamento, pedidos, ticket médio)

## Observação Importante

Este problema afetava qualquer código que tentasse filtrar pedidos por `payment_status` usando comparação de strings.

**Regra geral:** Sempre que um campo tem cast para enum no modelo, use o enum diretamente nas comparações:

```php
// ✅ CORRETO
->where('payment_status', PaymentStatus::Paid)
$order->payment_status === PaymentStatus::Paid

// ❌ ERRADO
->where('payment_status', 'paid')
$order->payment_status === 'paid'
$order->payment_status?->value === 'paid'
```

### 4. DiscountAnalyticsService.php (app/Services/DiscountAnalyticsService.php)

Linhas 95, 302, 348:
```php
// Antes:
->where('payment_status', 'paid')

// Depois:
->where('payment_status', PaymentStatus::Paid->value)
```

**Nota:** Usa `->value` porque é query SQL raw (`DB::table`)

**Import adicionado:**
```php
use App\Enums\PaymentStatus;
```

### 5. DashboardService.php (app/Services/DashboardService.php)

Linha 147 (query SQL raw):
```php
// Antes:
WHERE store_id = ?
    AND payment_status = 'paid'
    AND external_created_at BETWEEN ? AND ?

// Depois:
WHERE store_id = ?
    AND payment_status = ?
    AND external_created_at BETWEEN ? AND ?
```

Linha 170 (binding parameters):
```php
// Antes:
", [
    $store->id,
    $dateRange['start']->toDateTimeString(),
    $dateRange['end']->toDateTimeString(),
]);

// Depois:
", [
    $store->id,
    PaymentStatus::Paid->value,
    $dateRange['start']->toDateTimeString(),
    $dateRange['end']->toDateTimeString(),
]);
```

**Import adicionado:**
```php
use App\Enums\PaymentStatus;
```

### 6. ProductAnalyticsService.php (app/Services/ProductAnalyticsService.php)

Linha 140 (query SQL raw):
```php
// Antes:
WHERE store_id = ?
    AND payment_status = 'paid'
    AND external_created_at >= ?

// Depois:
WHERE store_id = ?
    AND payment_status = ?
    AND external_created_at >= ?
```

Linha 157 (binding parameters):
```php
// Antes:
", [$store->id, now()->subDays($periodDays)]);

// Depois:
", [$store->id, PaymentStatus::Paid->value, now()->subDays($periodDays)]);
```

**Import adicionado:**
```php
use App\Enums\PaymentStatus;
```

### 7. AdminController.php (app/Http/Controllers/Api/AdminController.php)

Linhas 35, 157:
```php
// Antes:
->where('payment_status', 'paid')

// Depois:
->where('payment_status', PaymentStatus::Paid)
```

**Import adicionado:**
```php
use App\Enums\PaymentStatus;
```

## Arquivos Modificados

- ✅ app/Services/ChatbotService.php
- ✅ app/Services/AnalysisService.php
- ✅ app/Services/AI/Agents/StoreAnalysisService.php
- ✅ app/Services/DiscountAnalyticsService.php
- ✅ app/Services/DashboardService.php
- ✅ app/Services/ProductAnalyticsService.php
- ✅ app/Http/Controllers/Api/AdminController.php

## Arquivos Criados

- test_chat_data.php (script de teste para verificar queries)
- BUGFIX_CHAT_ZERO_DATA.md (este documento)

## Diferença entre Eloquent e Query Builder

**Eloquent (Model):** O cast do enum funciona automaticamente
```php
// ✅ Usa o enum diretamente
SyncedOrder::where('payment_status', PaymentStatus::Paid)->get();
$order->payment_status === PaymentStatus::Paid
```

**Query Builder / SQL Raw:** Precisa usar o valor do enum
```php
// ✅ Usa ->value para obter string
DB::table('synced_orders')->where('payment_status', PaymentStatus::Paid->value)->get();
DB::select("... WHERE payment_status = ?", [PaymentStatus::Paid->value]);
```
