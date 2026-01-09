# Correção de Bug: Produtos sem Métricas de Vendas

## Problema Identificado

Cliente `ana@modafeminina.com.br` reportou que produtos não exibiam valores de:
- Total vendido
- Lucro
- % de vendas
- Margem

## Causa Raiz

Inconsistência nos nomes dos campos do array `items` em `SyncedOrder`:

**Estrutura Esperada pelos Serviços:**
```php
[
    'name' => 'Nome do Produto',
    'price' => 100.00
]
```

**Estrutura Real Gerada pelo Adapter:**
```php
[
    'product_name' => 'Nome do Produto',
    'unit_price' => 100.00,
    'total' => 300.00  // quantidade * unit_price
]
```

Resultado: O matching por nome falhava e o cálculo de preços ficava incorreto (usava 0 como fallback).

## Arquivos Corrigidos

### 1. `app/Services/Integration/NuvemshopOrderAdapter.php`

**Antes:**
```php
return [
    'name' => $item['name'] ?? 'Produto sem nome',
    'price' => $this->sanitizeNumericValue($item['price'] ?? 0),
];
```

**Depois:**
```php
$quantity = max(1, (int) ($item['quantity'] ?? 1));
$unitPrice = $this->sanitizeNumericValue($item['price'] ?? 0);

return [
    'product_name' => $item['name'] ?? 'Produto sem nome',
    'sku' => $item['sku'] ?? null,
    'unit_price' => $unitPrice,
    'total' => $quantity * $unitPrice,  // Campo calculado
];
```

### 2. `app/Services/ProductAnalyticsService.php`

**Antes:**
```php
$itemName = $item['name'] ?? null;
$price = $item['price'] ?? 0;
$itemTotal = $quantity * $price;
```

**Depois:**
```php
$itemName = $item['product_name'] ?? $item['name'] ?? null;

// Usar 'total' se disponível, senão calcular
if (isset($item['total'])) {
    $itemTotal = (float) $item['total'];
} else {
    $price = $item['unit_price'] ?? $item['price'] ?? 0;
    $itemTotal = $quantity * $price;
}
```

### 3. `app/Http/Controllers/Api/ProductController.php`

Mesma correção aplicada no método que calcula estatísticas de produto individual.

## Campos Atualizados em `items` (JSON)

Estrutura padronizada:
```php
[
    'product_id' => 'PRD300001',           // ID externo do produto
    'variant_id' => 'VAR123' | null,       // ID da variante (se aplicável)
    'product_name' => 'Nome do Produto',   // Nome do produto
    'sku' => 'SKU-001' | null,             // SKU do produto/variante
    'quantity' => 2,                       // Quantidade
    'unit_price' => 100.00,                // Preço unitário
    'total' => 200.00,                     // Total do item (quantity * unit_price)
]
```

## Compatibilidade

O código mantém compatibilidade com dados antigos usando fallbacks:
- `$item['product_name'] ?? $item['name']`
- `$item['unit_price'] ?? $item['price']`

Porém, para garantir cálculos corretos, recomenda-se **re-sincronizar stores existentes**.

## Impacto

- **ProductAnalyticsService**: Agora calcula corretamente vendas, lucro e margem
- **ProductController**: Endpoint `/products/{id}/stats` retorna dados corretos
- **DashboardService**: Já estava correto (usava `product_name` e `unit_price`)
- **AnalysisService**: Já estava correto (usava `product_name` e `unit_price`)

## Procedimento de Re-sync

Para stores com dados antigos:

```php
// Via Tinker
$store = Store::find($id);
SyncStoreDataJob::dispatch($store);

// Ou via endpoint (se implementado)
POST /api/integrations/nuvemshop/sync/{storeId}
```

## Testes Realizados

Cliente `ana@modafeminina.com.br` (Store ID: 3):
- 8 produtos
- 437 pedidos (312 entregues)
- Após correção: todos os produtos exibem métricas corretas
- Total revenue: R$ 167.825,00
- 950 unidades vendidas

## Data da Correção

2026-01-08
