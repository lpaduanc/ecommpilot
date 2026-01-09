# Implementação de Descontos/Cupons - Backend Completo

## Resumo
Implementação completa do backend para funcionalidade de descontos/cupons no projeto ecommpilot (Laravel 12 + Vue 3 SPA).

## Data de Implementação
2026-01-08

---

## 1. Database - Migration

### Arquivo: `database/migrations/2026_01_08_210000_create_synced_coupons_table.php`

Tabela `synced_coupons` criada com os seguintes campos:

- `id` - Primary key
- `store_id` - Foreign key para stores
- `external_id` - ID do cupom na Nuvemshop
- `code` - Código do cupom
- `type` - Tipo do cupom (percentage, absolute, shipping)
- `value` - Valor do desconto
- `valid` - Se o cupom está válido (boolean)
- `used` - Quantidade de usos
- `max_uses` - Máximo de usos permitido (nullable)
- `start_date` - Data de início (nullable)
- `end_date` - Data de fim (nullable)
- `min_price` - Valor mínimo do pedido (nullable)
- `categories` - Categorias aplicáveis (JSON, nullable)
- `timestamps` - created_at, updated_at
- `deleted_at` - Soft delete

**Índices criados:**
- Unique: `[store_id, external_id]`
- Index: `[store_id, code]`
- Index: `[store_id, valid]`
- Index: `end_date`

### Arquivo: `database/migrations/2026_01_08_210001_add_coupon_to_synced_orders_table.php`

Adicionado campo `coupon` (JSON, nullable) na tabela `synced_orders` para armazenar informações do cupom usado no pedido.

**Status:** ✅ Migrations executadas com sucesso

---

## 2. Model - SyncedCoupon

### Arquivo: `app/Models/SyncedCoupon.php`

**Características:**
- Soft Deletes habilitado
- Relacionamento `belongsTo` com Store
- Casts automáticos para campos (value, valid, used, max_uses, dates, categories)

**Métodos auxiliares:**
- `isActive()` - Verifica se o cupom está ativo
- `isExpired()` - Verifica se o cupom expirou
- `hasReachedMaxUses()` - Verifica se atingiu o máximo de usos

**Scopes:**
- `valid()` - Filtra cupons válidos
- `active()` - Filtra cupons ativos (válidos, dentro do período, com usos disponíveis)
- `expired()` - Filtra cupons expirados
- `search($search)` - Busca por código

---

## 3. Integração Nuvemshop

### Contract: `app/Contracts/CouponAdapterInterface.php`

Interface que define o comportamento para transformar dados de cupons de plataformas externas.

**Métodos:**
- `transform(array $externalData): array` - Transforma dados externos
- `mapCouponType(?string $externalType): string` - Mapeia tipo de cupom

### Adapter: `app/Services/Integration/NuvemshopCouponAdapter.php`

Implementa `CouponAdapterInterface` para transformar dados da Nuvemshop.

**Características:**
- Mapeia tipos: percentage, absolute, shipping/free_shipping
- Sanitiza valores numéricos
- Extrai categorias (array de IDs)
- Trata dados ausentes com valores padrão

### Service: `app/Services/Integration/NuvemshopService.php`

**Método adicionado:**
- `syncCoupons(Store $store)` - Sincroniza cupons da API Nuvemshop
- Paginação de 200 itens por request
- Rate limiting aplicado (60 req/min)
- Usa adapter para transformar dados

**OAuth Scope atualizado:**
- Adicionado `read_coupons` ao scope de autenticação

### Job: `app/Jobs/SyncStoreDataJob.php`

**Atualizado para incluir sync de cupons:**
- Checkpoint 'coupons' adicionado para idempotência
- Executado após sync de customers
- Logs adicionados

---

## 4. Service - DiscountAnalyticsService

### Arquivo: `app/Services/DiscountAnalyticsService.php`

Calcula métricas e analytics para descontos/cupons.

**Método principal:**
- `calculateDiscountAnalytics(Store $store): array`

**Estatísticas Gerais:**
- Total de pedidos
- Pedidos com desconto
- Pedidos com cupom
- Receita total
- Desconto total
- Percentual de desconto

**Analytics por Cupom:**
- Receita de Produtos (soma dos subtotals)
- Receita de Frete (soma dos shipping)
- Total Vendido (produtos + frete)
- Total de Descontos aplicados
- Número de Pedidos
- Percentual de Desconto (desconto/receita * 100)
- Desconto Médio por Pedido
- Ticket Médio do Pedido
- Número de Novos Clientes (primeira compra com cupom)
- % Recompra (clientes que compraram novamente)

**Lógica de matching:**
- Match por `coupon.id` ou `coupon.code` do pedido
- Case-insensitive para código
- Apenas pedidos pagos são considerados

---

## 5. Controller e Rotas

### Controller: `app/Http/Controllers/Api/DiscountController.php`

**Métodos:**

#### `index(Request $request): JsonResponse`
Lista cupons com analytics.

**Query params:**
- `search` - Busca por código
- `status` - Filtra por status (active, expired, invalid)
- `page` - Página atual
- `per_page` - Itens por página (padrão: 20)

**Resposta:**
```json
{
  "data": [...],
  "total": 100,
  "last_page": 5,
  "current_page": 1
}
```

#### `stats(Request $request): JsonResponse`
Retorna estatísticas gerais de descontos.

**Resposta:**
```json
{
  "total_orders": 1500,
  "orders_with_discount": 800,
  "orders_with_coupon": 500,
  "total_revenue": 150000.00,
  "total_discount": 15000.00,
  "discount_percentage": 9.09,
  "active_coupons": 10,
  "expired_coupons": 5
}
```

### Resource: `app/Http/Resources/CouponResource.php`

Formata dados do cupom para resposta da API.

**Campos retornados:**
- Todos os campos do model
- `is_active` - Status calculado
- `is_expired` - Se expirou
- `has_reached_max_uses` - Se atingiu limite
- `analytics` - Analytics quando disponível (incluído condicionalmente)

### Rotas: `routes/api.php`

```php
Route::prefix('discounts')->middleware('can:marketing.access')->group(function () {
    Route::get('/', [DiscountController::class, 'index']);
    Route::get('/stats', [DiscountController::class, 'stats']);
});
```

**Proteção:** Requer permissão `marketing.access`

---

## 6. Permissões

### Atualizado: `database/seeders/PermissionSeeder.php`

**Permissões adicionadas:**
- `dashboard.view` - Visualizar Dashboard
- `products.view` - Visualizar Produtos
- `orders.view` - Visualizar Pedidos
- `analysis.view` - Visualizar Análises (corrigido de analytics.view)
- `analysis.request` - Solicitar Análise (corrigido de analytics.request)
- `marketing.access` - Acessar Marketing e Descontos ✅
- `settings.view` - Visualizar Configurações
- `settings.edit` - Editar Configurações

**Role `client` atualizado:**
Agora inclui todas as permissões necessárias:
- dashboard.view
- products.view
- orders.view
- integrations.manage
- analysis.view
- analysis.request
- chat.use
- marketing.access ✅
- settings.view
- settings.edit

**Status:** ✅ Seeder executado com sucesso

---

## 7. Atualizações em Modelos Existentes

### Atualizado: `app/Http/Resources/OrderResource.php`

Adicionado campo `coupon` na resposta:
```php
'coupon' => $this->coupon,
```

Agora pedidos retornam informações do cupom usado (se houver).

---

## 8. API Endpoints Disponíveis

### GET `/api/discounts`
Lista cupons com analytics paginada.

**Auth:** Required (Sanctum)
**Permission:** `marketing.access`
**Query Params:**
- `search` (string, opcional)
- `status` (string, opcional): active, expired, invalid
- `page` (int, opcional)
- `per_page` (int, opcional, padrão: 20)

### GET `/api/discounts/stats`
Retorna estatísticas gerais de descontos.

**Auth:** Required (Sanctum)
**Permission:** `marketing.access`

---

## 9. Estrutura de Dados

### Cupom na Nuvemshop (API)
```json
{
  "id": 123456,
  "code": "DESCONTO10",
  "type": "percentage",
  "value": "10.00",
  "valid": true,
  "used": 5,
  "max_uses": 100,
  "start_date": "2025-01-01T00:00:00+0000",
  "end_date": "2025-12-31T23:59:59+0000",
  "min_price": "50.00",
  "categories": [123, 456]
}
```

### Cupom no Pedido (synced_orders.coupon)
```json
{
  "id": "123456",
  "code": "DESCONTO10",
  "type": "percentage",
  "value": 10.00
}
```

### Resposta da API (CouponResource)
```json
{
  "id": 1,
  "external_id": "123456",
  "code": "DESCONTO10",
  "type": "percentage",
  "value": 10.00,
  "valid": true,
  "used": 5,
  "max_uses": 100,
  "start_date": "2025-01-01T00:00:00.000Z",
  "end_date": "2025-12-31T23:59:59.000Z",
  "min_price": 50.00,
  "categories": [123, 456],
  "is_active": true,
  "is_expired": false,
  "has_reached_max_uses": false,
  "created_at": "2026-01-08T00:00:00.000Z",
  "updated_at": "2026-01-08T00:00:00.000Z",
  "analytics": {
    "revenue_products": 5000.00,
    "revenue_shipping": 250.00,
    "total_revenue": 5250.00,
    "total_discount": 500.00,
    "number_of_orders": 5,
    "discount_percentage": 9.52,
    "average_discount_per_order": 100.00,
    "average_ticket": 1050.00,
    "new_customers": 2,
    "repurchase_rate": 40.00
  }
}
```

---

## 10. Padrões Seguidos

✅ **Adapter Pattern** - Para transformação de dados externos
✅ **Contract/Interface** - Para extensibilidade
✅ **API Resources** - Para formatação de respostas
✅ **Service Layer** - Para lógica de negócio
✅ **Soft Deletes** - Para dados críticos
✅ **Permissions** - Controle de acesso via spatie/laravel-permission
✅ **Rate Limiting** - Respeitando limites da API Nuvemshop
✅ **Idempotency** - Job com checkpoints
✅ **Logging** - Para debug de operações críticas
✅ **Code Formatting** - Laravel Pint executado com sucesso

---

## 11. Próximos Passos (Frontend)

Para completar a implementação, será necessário criar no frontend:

1. **View de Descontos** (`resources/js/views/DiscountsView.vue`)
   - Lista de cupons com filtros
   - Cards de estatísticas gerais
   - Tabela com analytics por cupom

2. **Store Pinia** (`resources/js/stores/discountStore.js`)
   - State management para cupons e stats

3. **Componentes**
   - `CouponCard.vue` - Card de cupom
   - `CouponTable.vue` - Tabela de cupons com analytics
   - `DiscountStatsCards.vue` - Cards de estatísticas

4. **Rota no Router**
   - Adicionar rota `/discounts` com guard de permissão

5. **Menu**
   - Adicionar item no sidebar com ícone de cupom
   - Guard de permissão `marketing.access`

---

## 12. Testes

### Verificações Realizadas:
✅ Migrations executadas com sucesso
✅ Seeder de permissões executado
✅ Rotas configuradas corretamente
✅ Código formatado com Laravel Pint
✅ OAuth scope atualizado para incluir read_coupons

### Testes Recomendados:
- [ ] Testar sincronização de cupons via Nuvemshop
- [ ] Testar cálculo de analytics com dados reais
- [ ] Testar filtros e paginação
- [ ] Testar permissões de acesso
- [ ] Criar testes unitários para DiscountAnalyticsService
- [ ] Criar testes feature para DiscountController

---

## 13. Observações Importantes

1. **Nuvemshop OAuth**: O scope `read_coupons` foi adicionado. Usuários que já conectaram suas lojas precisarão reconectar para obter as novas permissões.

2. **Performance**: O cálculo de analytics é feito em memória. Para grandes volumes de dados, considerar cache ou processamento assíncrono.

3. **Matching de Cupons**: O matching entre cupons e pedidos é feito por `id` ou `code`. Importante que os dados do campo `coupon` no pedido estejam corretos.

4. **Novos Clientes**: A lógica considera "novo cliente" aquele cuja primeira compra foi com o cupom específico.

5. **Recompra**: Calculado como percentual de clientes únicos que fizeram mais de uma compra (independente de usar cupom novamente).

---

## Arquivos Modificados/Criados

### Criados:
- `database/migrations/2026_01_08_210000_create_synced_coupons_table.php`
- `database/migrations/2026_01_08_210001_add_coupon_to_synced_orders_table.php`
- `app/Models/SyncedCoupon.php`
- `app/Contracts/CouponAdapterInterface.php`
- `app/Services/Integration/NuvemshopCouponAdapter.php`
- `app/Services/DiscountAnalyticsService.php`
- `app/Http/Controllers/Api/DiscountController.php`
- `app/Http/Resources/CouponResource.php`

### Modificados:
- `app/Services/Integration/NuvemshopService.php` (adicionado syncCoupons e scope)
- `app/Jobs/SyncStoreDataJob.php` (adicionado checkpoint de coupons)
- `database/seeders/PermissionSeeder.php` (permissões atualizadas)
- `app/Http/Resources/OrderResource.php` (adicionado campo coupon)
- `routes/api.php` (já estava com as rotas configuradas)

---

## Conclusão

O backend da funcionalidade de descontos/cupons foi implementado com sucesso seguindo todos os padrões e práticas do projeto ecommpilot. A implementação inclui:

- ✅ Estrutura de dados completa
- ✅ Integração com Nuvemshop API
- ✅ Cálculo de analytics detalhado
- ✅ Controle de permissões
- ✅ API RESTful documentada
- ✅ Código formatado e organizado

A implementação está pronta para uso e aguarda apenas a criação do frontend para visualização e interação com os dados.
