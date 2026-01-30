# Conversa: Impacto Automático nas Vendas

> Data: 2026-01-28
> Arquivo gerado a partir da conversa com Claude Code

---

## Problema Inicial

O usuário identificou que a seção "Impacto nas Vendas" na tela de workflow depende do cliente preencher dados manualmente. Na prática, a maioria dos clientes não preenche, tornando a funcionalidade ineficaz.

**Objetivo:** Remover a seção de impacto manual e criar uma forma automática de mostrar o impacto nas vendas.

---

## Desafio Central

**Pergunta do usuário:** "Mesmo sabendo que o cliente interagiu com as sugestões, como garantir que as sugestões impactaram nas vendas?"

### Resposta:

Não existe forma de **provar causalidade** sem:
- Um grupo de controle (loja paralela que não implementou)
- Parâmetros de rastreamento fixos (UTM, cupom único, etc.)

Isso é um problema clássico de atribuição em analytics. Qualquer ferramenta que afirme "a sugestão X gerou R$Y" está sendo desonesta.

---

## Soluções Possíveis para Aumentar Confiança

### 1. Análise de Tendência Prévia

```
Se antes da sugestão a loja crescia 2%/mês
E depois passou a crescer 15%/mês
→ Há forte indício de impacto
```

Calcular a "tendência base" dos 60-90 dias anteriores e comparar com o período pós-implementação.

### 2. Métricas Específicas por Categoria

| Categoria | Métrica Monitorada |
|-----------|-------------------|
| Cupom | Pedidos com cupom, taxa de uso |
| Pricing | Ticket médio, margem |
| Marketing | Número de pedidos novos |
| Estoque | Produtos vendidos que estavam parados |

Se a sugestão foi "crie cupom de primeira compra" e o uso de cupons subiu 40%, a correlação é mais forte.

### 3. Janela de Impacto

Comparar impacto em 7, 15 e 30 dias:
- Se o efeito aparece nos 7 dias e se mantém → mais confiável
- Se só aparece em 30 dias → pode ser coincidência

### 4. Comparação YoY (opcional)

Se tivermos dados do ano anterior, normalizar sazonalidade:
```
Dezembro 2025: +30%
Dezembro 2024: +28%
Diferença real: +2% (não os 30%)
```

---

## Abordagem Honesta para o Cliente

Em vez de afirmar causalidade, usar linguagem de **correlação**:

```
📊 Evolução das Métricas

Suas métricas desde que você começou a agir nas sugestões:

Receita:  R$ 45.200 → R$ 52.800  (+16,8%)
Pedidos:  142 → 168  (+18,3%)

💡 Suas métricas melhoraram! Continue acompanhando
   para validar o impacto das suas ações.
```

A mensagem é positiva, útil, mas **não promete o que não pode entregar**.

---

## Decisões Tomadas

### 1. Local de Exibição
**Escolha:** Dashboard principal

### 2. Período de Comparação
**Escolha:** 30 dias (antes vs depois)

### 3. Marco Temporal
**Escolha:** Usar tanto `in_progress_at` quanto `completed_at`
- Necessário adicionar campo `in_progress_at` ao model Suggestion

### 4. Visualizações
**Escolha:** Todas as opções:
- Impacto geral consolidado
- Impacto por categoria
- Timeline visual (gráfico com marcadores)

### 5. Disclaimer
**Escolha:** Sim, mostrar nota sutil
> "Estas métricas refletem a evolução geral da sua loja no período analisado."

### 6. Limpeza
**Escolha:** Remover completamente arquivos do SuggestionImpact antigo

### 7. Feedback ao Concluir
**Escolha:** Adicionar botão simples "Funcionou? 👍👎🤷" ao concluir sugestão
- Usar campo `was_successful` já existente

### 8. Controle por Plano
**Escolha:** Funcionalidade exclusiva para plano Enterprise
- Adicionar campo `has_impact_dashboard` no model Plan
- Usuários sem acesso veem card de upgrade (não desabilitado)

---

## Limitações Conhecidas

1. **Correlação, não causalidade** - Os números mostram evolução, não comprovação de causa
2. **Fatores externos** - Sazonalidade, campanhas, mercado afetam os resultados
3. **Múltiplas sugestões** - Quando várias estão ativas, o impacto é consolidado
4. **Sem grupo de controle** - Não há forma de comparar com cenário alternativo

---

## Valor Entregue ao Cliente

Mesmo com as limitações:

1. **Visualização automática** - Não depende do cliente preencher nada
2. **Análise de tendência** - Mostra se houve aceleração além do padrão natural
3. **Feedback opcional** - Quem responder ajuda a calibrar futuras análises
4. **Transparência** - Linguagem honesta sobre o que os dados representam

---

## Resumo Técnico

### Arquivos a Criar (Backend)
- `database/migrations/2026_01_28_000001_add_in_progress_at_to_suggestions_table.php`
- `database/migrations/2026_01_28_000002_add_has_impact_dashboard_to_plans_table.php`
- `app/Services/Analysis/SuggestionImpactAnalysisService.php`
- `app/Http/Controllers/Api/SuggestionImpactDashboardController.php`

### Arquivos a Criar (Frontend)
- `resources/js/components/dashboard/SuggestionImpactCard.vue`
- `resources/js/components/dashboard/SuggestionImpactTimeline.vue`
- `resources/js/components/dashboard/SuggestionImpactByCategory.vue`

### Arquivos a Modificar
- `app/Models/Plan.php` - Adicionar `has_impact_dashboard`
- `app/Models/Suggestion.php` - Adicionar `in_progress_at`, remover `impacts()`
- `app/Services/PlanLimitService.php` - Adicionar `canAccessImpactDashboard()`
- `routes/api.php` - Adicionar rota, remover rotas antigas
- `resources/js/views/SuggestionWorkflowView.vue` - Remover ImpactPanel, adicionar feedback modal
- `resources/js/views/DashboardView.vue` - Adicionar SuggestionImpactCard
- `resources/js/stores/analysisStore.js` - Remover funções antigas, adicionar novas
- `resources/js/stores/dashboardStore.js` - Adicionar função de buscar impact dashboard

### Arquivos a Remover
- `resources/js/components/analysis/SuggestionImpactPanel.vue`
- `resources/js/components/analysis/SuggestionImpactField.vue`
- `app/Models/SuggestionImpact.php`
- `app/Http/Controllers/Api/SuggestionImpactController.php`
- `database/migrations/2026_01_27_000006_create_suggestion_impacts_table.php`

---

## Lógica de Cálculo

```php
// Período de análise: 30 dias
private const ANALYSIS_PERIOD_DAYS = 30;

// Buscar sugestões in_progress ou completed
$suggestions = $store->suggestions()
    ->whereIn('status', ['in_progress', 'completed'])
    ->whereNotNull('in_progress_at')
    ->get();

// Determinar marco temporal
$firstActionDate = $suggestions->min('in_progress_at');

// Período ANTES: 30 dias antes da primeira ação
$beforeStart = $firstActionDate->subDays(60);
$beforeEnd = $firstActionDate->subDays(1);

// Período DEPOIS: da primeira ação até hoje
$afterStart = $firstActionDate;
$afterEnd = now();

// Métricas
$metrics = $store->syncedOrders()
    ->paid()
    ->inPeriod($start, $end)
    ->get();

$revenue = $metrics->sum('total');
$orders = $metrics->count();
$avgTicket = $orders > 0 ? $revenue / $orders : 0;

// Variação
$variation = (($after - $before) / $before) * 100;
```

---

## Análise de Tendência

```php
// Calcular tendência PRÉ-sugestões (60 dias antes divididos em 2 períodos)
$period1 = getMetrics(-60, -31); // Há 60-31 dias
$period2 = getMetrics(-30, -1);  // Há 30-1 dias

$preTrend = (($period2->revenue - $period1->revenue) / $period1->revenue) * 100;

// Calcular tendência PÓS-sugestões
$postTrend = (($after->revenue - $before->revenue) / $before->revenue) * 100;

// Aceleração = postTrend - preTrend
// Se positivo → houve melhora além da tendência natural
// Ex: Se crescia 5%/mês e agora cresce 15%/mês → aceleração de 10%
```

---

## Execução

Usar agentes especializados:
- **backend-architect**: Para migrations, models, services, controllers
- **frontend-ecommpilot**: Para componentes Vue, stores, types
