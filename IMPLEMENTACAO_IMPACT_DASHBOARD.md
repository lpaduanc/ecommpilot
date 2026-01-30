# Implementação: Dashboard de Impacto Automático nas Vendas

## Resumo

Sistema de análise automática de impacto das sugestões nas vendas, baseado em correlação temporal entre implementação de sugestões e métricas de vendas.

## Arquivos Criados

### Migrations
1. **2026_01_29_000001_add_in_progress_at_to_suggestions_table.php**
   - Adiciona campo `in_progress_at` na tabela `suggestions`
   - Armazena timestamp quando sugestão entra em progresso

2. **2026_01_29_000002_add_has_impact_dashboard_to_plans_table.php**
   - Adiciona campo `has_impact_dashboard` na tabela `plans`
   - Ativa automaticamente para plano Enterprise

### Services
3. **app/Services/Analysis/SuggestionImpactAnalysisService.php**
   - Serviço principal de análise de impacto
   - Métodos:
     - `getImpactDashboard()` - Dashboard consolidado
     - `getConsolidatedSummary()` - Métricas antes/depois
     - `getTrendAnalysis()` - Análise de tendências
     - `getImpactByCategory()` - Impacto por categoria
     - `getTimelineData()` - Timeline de sugestões e vendas

### Controllers
4. **app/Http/Controllers/Api/SuggestionImpactDashboardController.php**
   - `index()` - GET /api/suggestions/impact-dashboard
   - `updateFeedback()` - PATCH /api/suggestions/{suggestion}/feedback
   - Validação de permissão do plano (Enterprise only)

## Arquivos Modificados

### Models
5. **app/Models/Plan.php**
   - Adicionado `has_impact_dashboard` aos `$fillable`
   - Adicionado cast boolean para `has_impact_dashboard`

6. **app/Models/Suggestion.php**
   - Adicionado `in_progress_at` aos `$fillable`
   - Adicionado cast datetime para `in_progress_at`
   - Atualizado `markAsInProgress()` para setar `in_progress_at`
   - **REMOVIDO** relacionamento `impacts()` (hasMany SuggestionImpact)

### Services
7. **app/Services/PlanLimitService.php**
   - Adicionado método `canAccessImpactDashboard(User $user): bool`
   - Atualizado `getUserLimits()` para incluir `has_impact_dashboard`
   - Atualizado `getUserUsage()` features para incluir `has_impact_dashboard`

### Routes
8. **routes/api.php**
   - **ADICIONADO**: `GET /api/suggestions/impact-dashboard`
   - **ADICIONADO**: `PATCH /api/suggestions/{suggestion}/feedback`
   - **REMOVIDO**: Todas as rotas de `suggestion-impacts` (CRUD)
   - **REMOVIDO**: Import do `SuggestionImpactController`
   - **ADICIONADO**: Import do `SuggestionImpactDashboardController`

### Controllers
9. **app/Http/Controllers/Api/AdminPlanController.php**
   - Adicionado `has_impact_dashboard` nas validações de `store()`
   - Adicionado `has_impact_dashboard` nas validações de `update()`
   - Adicionado tratamento boolean explícito para `has_impact_dashboard`

### Seeders
10. **database/seeders/PlanSeeder.php**
    - Starter: `has_impact_dashboard` => false
    - Business: `has_impact_dashboard` => false
    - Enterprise: `has_impact_dashboard` => true

## Arquivos Removidos

11. **app/Models/SuggestionImpact.php** ❌ DELETADO
12. **app/Http/Controllers/Api/SuggestionImpactController.php** ❌ DELETADO

## Estrutura de Dados

### Endpoint: GET /api/suggestions/impact-dashboard

```json
{
  "success": true,
  "data": {
    "summary": {
      "has_data": true,
      "suggestions_in_progress": 3,
      "suggestions_completed": 5,
      "before": {
        "revenue": 45000.00,
        "orders": 120,
        "avg_ticket": 375.00,
        "days": 60,
        "daily_revenue": 750.00,
        "daily_orders": 2.0
      },
      "after": {
        "revenue": 58000.00,
        "orders": 145,
        "avg_ticket": 400.00,
        "days": 30,
        "daily_revenue": 1933.33,
        "daily_orders": 4.83
      },
      "period": {
        "before": {
          "start": "2026-01-01",
          "end": "2026-03-01"
        },
        "after": {
          "start": "2026-03-02",
          "end": "2026-03-29"
        }
      }
    },
    "by_category": [
      {
        "category": "coupon",
        "count": 3,
        "in_progress": 1,
        "completed": 2,
        "successful": 2
      },
      {
        "category": "product",
        "count": 5,
        "in_progress": 2,
        "completed": 3,
        "successful": 1
      }
    ],
    "timeline": {
      "suggestions": [
        {
          "id": 1,
          "title": "Criar cupom de frete grátis",
          "category": "coupon",
          "status": "completed",
          "in_progress_at": "2026-03-02",
          "completed_at": "2026-03-10"
        }
      ],
      "daily_metrics": [
        {
          "date": "2026-03-01",
          "revenue": 1200.50,
          "orders": 4
        },
        {
          "date": "2026-03-02",
          "revenue": 1850.00,
          "orders": 6
        }
      ]
    },
    "trend_analysis": {
      "has_data": true,
      "pre_trend": 5.2,
      "post_trend": 28.9,
      "acceleration": 23.7,
      "interpretation": "significant_improvement"
    }
  }
}
```

### Interpretações de Tendência

- `significant_improvement`: aceleração > 10%
- `slight_improvement`: aceleração > 0% e <= 10%
- `stable`: aceleração > -10% e <= 0%
- `decline`: aceleração <= -10%

## Lógica de Análise

### Período de Análise
- **Antes**: 60 dias antes da primeira sugestão em progresso
- **Depois**: Da primeira sugestão em progresso até hoje

### Cálculo de Tendência
1. **Período 1**: 60-31 dias antes da primeira sugestão
2. **Período 2**: 30-1 dias antes da primeira sugestão
3. **Tendência Pré**: Crescimento entre P1 e P2
4. **Tendência Pós**: Crescimento entre P2 e Depois
5. **Aceleração**: Diferença entre Tendência Pós e Pré

### Métricas Calculadas
- Revenue (receita total)
- Orders (número de pedidos)
- Avg Ticket (ticket médio)
- Daily Revenue (receita diária média)
- Daily Orders (pedidos diários médios)

## Permissões

### Planos com Acesso
- ✅ **Enterprise** - has_impact_dashboard = true
- ❌ **Business** - has_impact_dashboard = false
- ❌ **Starter** - has_impact_dashboard = false
- ✅ **Admin** - Sempre tem acesso

### Validação
```php
if (!$planService->canAccessImpactDashboard($user)) {
    return response()->json([
        'error' => 'feature_not_available',
        'message' => 'Faça upgrade para o plano Enterprise...',
        'upgrade_required' => true,
    ], 403);
}
```

## Migration da Estrutura Antiga

### SuggestionImpact (REMOVIDO)
A tabela `suggestion_impacts` não é mais usada. Os dados antigos:
- Eram preenchidos manualmente pelo cliente
- Rastreavam coupons, campaigns, methods específicos
- Tinham campos: type, label, value, numeric_value, metadata

### Novo Sistema
- **Automático**: Correlação temporal entre sugestões e vendas
- **Baseado em dados reais**: Orders da tabela `synced_orders`
- **Análise estatística**: Tendências, aceleração, períodos comparativos

## Próximos Passos (Frontend)

1. Criar componente `ImpactDashboard.vue`
2. Criar gráfico comparativo Before/After
3. Criar timeline visual de sugestões + vendas
4. Mostrar indicadores de tendência (↗️ ↘️ →)
5. Adicionar filtros por categoria
6. Implementar tela de upgrade para planos sem acesso

## Comandos de Teste

```bash
# Rodar migrations
php artisan migrate

# Rodar seeder (atualizar planos)
php artisan db:seed --class=PlanSeeder

# Testar endpoint
curl -H "Authorization: Bearer {token}" \
  http://localhost:8000/api/suggestions/impact-dashboard

# Atualizar feedback de sugestão
curl -X PATCH \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"was_successful": true}' \
  http://localhost:8000/api/suggestions/{uuid}/feedback
```

## Notas Técnicas

### Scope `paid()` em SyncedOrder
```php
public function scopePaid($query) {
    return $query->where('payment_status', PaymentStatus::Paid);
}
```

### Scope `inPeriod()` em SyncedOrder
```php
public function scopeInPeriod($query, $startDate, $endDate) {
    return $query->whereBetween('external_created_at', [$startDate, $endDate]);
}
```

### Timestamps Usados
- `suggestions.in_progress_at` - Quando cliente marcou como "em andamento"
- `suggestions.accepted_at` - Fallback se in_progress_at não existir
- `suggestions.completed_at` - Quando completou a sugestão

## Segurança

- ✅ Validação de plano antes de retornar dados
- ✅ Verificação de loja ativa (`activeStore`)
- ✅ Apenas dados da loja do usuário logado
- ✅ UUID para identificação de sugestões (não IDs numéricos)
