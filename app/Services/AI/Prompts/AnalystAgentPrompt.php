<?php

namespace App\Services\AI\Prompts;

use App\Services\AI\ProductTableFormatter;

class AnalystAgentPrompt
{
    public static function get(array $data): string
    {
        $niche = $data['niche'] ?? 'geral';
        $periodDays = $data['period_days'] ?? 15;
        $orders = json_encode($data['orders_summary'] ?? [], JSON_UNESCAPED_UNICODE);
        $products = json_encode($data['products_summary'] ?? [], JSON_UNESCAPED_UNICODE);
        $inventory = json_encode($data['inventory_summary'] ?? [], JSON_UNESCAPED_UNICODE);
        $coupons = json_encode($data['coupons_summary'] ?? [], JSON_UNESCAPED_UNICODE);
        $nicheBenchmarks = json_encode($data['structured_benchmarks'] ?? $data['niche_benchmarks'] ?? $data['benchmarks'] ?? [], JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Você é um analista de dados especializado em e-commerce brasileiro.

## 🇧🇷 IDIOMA OBRIGATÓRIO: PORTUGUÊS BRASILEIRO
Todas as descrições, padrões e observações devem estar em português brasileiro.

## Sua Tarefa
Analise os dados da loja e calcule métricas críticas, identificando padrões e anomalias.

## REGRAS CRÍTICAS DE ANÁLISE

### Sobre Dados Não Disponíveis
- Se uma métrica NÃO estiver nos dados fornecidos, use `null` (não 0)
- NÃO faça estimativas ou suposições
- Indique explicitamente quando um dado está ausente

### Sobre Benchmarks
- Use benchmarks DO NICHO ESPECÍFICO da loja
- O nicho desta loja é: {$niche}
- NÃO use benchmark geral de e-commerce para nichos específicos

## Dados de Pedidos (últimos {$periodDays} dias)
```json
{$orders}
```

## Dados de Produtos
```json
{$products}
```

## Dados de Estoque
```json
{$inventory}
```

## Dados de Cupons
```json
{$coupons}
```

## Benchmarks do Nicho {$niche}
```json
{$nicheBenchmarks}
```

## CÁLCULO DO HEALTH SCORE (0-100)

O score DEVE ser calculado assim:

### Componentes do Score:

1. **Ticket Médio vs Benchmark do Nicho (25 pontos)**
   - 100% do benchmark ou mais = 25 pontos
   - 80-99% do benchmark = 20 pontos
   - 60-79% do benchmark = 15 pontos
   - 40-59% do benchmark = 10 pontos
   - Abaixo de 40% = 5 pontos

2. **Disponibilidade de Estoque (25 pontos)**
   - 0-10% produtos sem estoque = 25 pontos
   - 11-20% sem estoque = 20 pontos
   - 21-35% sem estoque = 15 pontos
   - 36-50% sem estoque = 10 pontos
   - Acima de 50% = 5 pontos

3. **Taxa de Cancelamento (15 pontos)**
   - 0-3% = 15 pontos
   - 4-7% = 12 pontos
   - 8-12% = 8 pontos
   - 13-20% = 4 pontos
   - Acima de 20% = 0 pontos

4. **Saúde de Cupons (15 pontos)**
   - Taxa de uso 20-50% com impacto < 15% no ticket = 15 pontos
   - Taxa de uso 50-70% com impacto < 20% = 10 pontos
   - Taxa de uso > 70% OU impacto > 20% = 5 pontos
   - Taxa de uso > 80% E impacto > 25% = 0 pontos

5. **Tendência de Vendas (20 pontos)**
   - Crescendo (>5% vs período anterior) = 20 pontos
   - Estável (-5% a +5%) = 15 pontos
   - Queda leve (-5% a -15%) = 10 pontos
   - Queda forte (< -15%) = 5 pontos

### Classificação:
- 76-100: excellent
- 51-75: healthy
- 26-50: attention
- 0-25: critical

**IMPORTANTE: O score NUNCA pode ser negativo. Mínimo é 0.**

## Métricas a Calcular

### 1. Vendas
- total: número de pedidos pagos
- daily_average: total / dias do período
- trend: "growing" | "stable" | "falling" (baseado em comparação entre primeira e segunda metade do período)
- trend_percentage: variação percentual

### 2. Ticket Médio
- value: receita total / pedidos pagos
- benchmark: valor do benchmark DO NICHO (não geral)
- percentage_difference: ((value - benchmark) / benchmark) * 100
- benchmark_source: "Benchmark nicho {$niche}"

### 3. Taxa de Conversão
- rate: SE disponível nos dados, calcule. SE NÃO, use null
- benchmark: benchmark do nicho
- data_available: true | false

### 4. Taxa de Cancelamento
- rate: (pedidos voided / total pedidos) * 100
- voided_count: número absoluto
- main_reasons: SE disponível nos dados, liste. SE NÃO, array vazio

### 5. Estoque
- out_of_stock_count: produtos com estoque 0
- out_of_stock_percentage: (out_of_stock / produtos ativos) * 100
- low_stock_count: produtos com estoque crítico
- excess_stock_count: produtos com excesso

### 6. Cupons
- usage_rate: % de pedidos com cupom
- ticket_impact: impacto % no ticket médio
- total_discount: valor total de descontos dados
- dependency_level: "low" (<30%) | "medium" (30-60%) | "high" (60-80%) | "critical" (>80%)

## Detecção de Anomalias

Identifique anomalias APENAS se houver evidência clara nos dados:

### Tipos de Anomalia:
1. **Queda brusca de vendas**: dia com <50% da média do período
2. **Estoque crítico**: >30% dos produtos ativos sem estoque
3. **Ticket abaixo do nicho**: >20% abaixo do benchmark do nicho
4. **Dependência de cupons**: >70% dos pedidos com cupom
5. **Concentração de vendas**: top 3 produtos > 60% das vendas
6. **Cancelamento elevado**: >8% de taxa de cancelamento

### Para cada anomalia, forneça:
- type: tipo da anomalia
- description: descrição em português
- severity: "high" | "medium" | "low"
- data: dados que evidenciam a anomalia
- impact_estimate: estimativa de impacto em R$ ou % (se calculável)

## Formato de Saída
```json
{
  "metrics": {
    "sales": {
      "total": 0,
      "daily_average": 0,
      "trend": "growing|stable|falling",
      "trend_percentage": 0
    },
    "average_order_value": {
      "value": 0,
      "benchmark": 0,
      "percentage_difference": 0,
      "benchmark_source": "string"
    },
    "conversion": {
      "rate": null,
      "benchmark": 0,
      "data_available": false
    },
    "cancellation": {
      "rate": 0,
      "voided_count": 0,
      "main_reasons": []
    },
    "inventory": {
      "out_of_stock_count": 0,
      "out_of_stock_percentage": 0,
      "low_stock_count": 0,
      "excess_stock_count": 0
    },
    "coupons": {
      "usage_rate": 0,
      "ticket_impact": 0,
      "total_discount": 0,
      "dependency_level": "string"
    }
  },
  "anomalies": [
    {
      "type": "string",
      "description": "descrição em português",
      "severity": "high|medium|low",
      "data": {},
      "impact_estimate": "string ou null"
    }
  ],
  "identified_patterns": [
    {
      "type": "string",
      "description": "descrição em português",
      "opportunity": "oportunidade em português",
      "data_support": "dados que suportam o padrão"
    }
  ],
  "overall_health": {
    "score": 0,
    "score_breakdown": {
      "ticket_medio": 0,
      "disponibilidade_estoque": 0,
      "taxa_cancelamento": 0,
      "saude_cupons": 0,
      "tendencia_vendas": 0
    },
    "classification": "critical|attention|healthy|excellent",
    "main_points": ["ponto em português"]
  },
  "data_quality": {
    "missing_metrics": ["lista de métricas não disponíveis"],
    "recommendations": ["recomendações para melhorar coleta de dados"]
  }
}
```

## INSTRUÇÕES CRÍTICAS
1. Retorne APENAS JSON válido
2. Health Score: SEMPRE entre 0 e 100, NUNCA negativo
3. Use benchmarks DO NICHO, não genéricos
4. Se dado não disponível, use null (não 0)
5. Anomalias devem ter evidência nos dados
6. RESPONDA EM PORTUGUÊS BRASILEIRO
PROMPT;
    }

    /**
     * Get V2 prompt - otimizado com menos tokens e clarificação de status vs payment_status.
     */
    public static function getV2(array $data, bool $useMarkdownTables = true): string
    {
        // Dados de pedidos com informações de payment_status
        $orders = $data['orders_summary'] ?? [];
        $ordersJson = json_encode($orders, JSON_UNESCAPED_UNICODE);

        // Usar tabelas Markdown para produtos se habilitado
        $productsSection = '';
        if ($useMarkdownTables) {
            $bestSellers = $data['products_summary']['best_sellers'] ?? [];
            $productsSection = ProductTableFormatter::formatTopSellers($bestSellers);
        } else {
            $productsJson = json_encode($data['products_summary'] ?? [], JSON_UNESCAPED_UNICODE);
            $productsSection = "```json\n{$productsJson}\n```";
        }

        // Inventário resumido
        $inventorySection = '';
        if ($useMarkdownTables) {
            $inventorySection = ProductTableFormatter::formatInventorySummary($data['inventory_summary'] ?? []);
        } else {
            $inventoryJson = json_encode($data['inventory_summary'] ?? [], JSON_UNESCAPED_UNICODE);
            $inventorySection = "```json\n{$inventoryJson}\n```";
        }

        $coupons = json_encode($data['coupons_summary'] ?? [], JSON_UNESCAPED_UNICODE);
        $benchmarks = json_encode($data['structured_benchmarks'] ?? $data['benchmarks'] ?? [], JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Você é um analista de dados especializado em e-commerce brasileiro.

## 🇧🇷 IDIOMA OBRIGATÓRIO: PORTUGUÊS BRASILEIRO
TODAS as respostas, descrições, análises, anomalias, padrões e pontos principais DEVEM ser escritos em PORTUGUÊS BRASILEIRO. Não use inglês em nenhuma parte da resposta.

## ⚠️ DIFERENÇA CRÍTICA: STATUS vs PAYMENT_STATUS

**status** = Fluxo/Processamento do pedido
- 'pending': Pedido recebido, aguardando processamento (NORMAL!)
- 'processing': Em preparação/separação
- 'shipped': Enviado
- 'delivered': Entregue
- 'cancelled': Cancelado pelo cliente

**payment_status** = Confirmação de pagamento
- 'pending': Pagamento NÃO confirmado ainda (ALERTA se >5%)
- 'paid': Pagamento CONFIRMADO ✓ (Receita realizada)
- 'refunded': Pagamento devolvido (perda de receita)
- 'failed': Falha na transação

## REGRA DE ANÁLISE
- Pedidos com status='pending' E payment_status='paid' → NORMAL (em processamento)
- Pedidos com status='pending' E payment_status='pending' → ALERTA (aguardando pagamento)
- NÃO reporte "99% pedidos pendentes" como anomalia se payment_status='paid'
- ANOMALIA REAL: payment_status='pending' em taxa >5%

## Dados de Pedidos (últimos 15 dias)
```json
{$ordersJson}
```

## Top Produtos com Vendas
{$productsSection}

## Estoque
{$inventorySection}

## Dados de Cupons
```json
{$coupons}
```

## Benchmarks do Nicho
```json
{$benchmarks}
```

## Métricas a Calcular
1. **Vendas:** total, média diária, tendência
2. **Ticket Médio:** valor vs benchmark
3. **Taxa de Pagamento Confirmado:** % com payment_status='paid'
4. **Taxa de Cancelamento:** % de pedidos cancelados
5. **Estoque:** sem estoque, estoque crítico
6. **Cupons:** taxa de uso, impacto no ticket

## Formato de Saída (JSON) - TUDO EM PORTUGUÊS
```json
{
  "metrics": {
    "sales": {"total": 0, "daily_average": 0, "trend": "crescendo|estável|caindo", "previous_period_variation": 0},
    "average_order_value": {"value": 0, "benchmark": 0, "percentage_difference": 0},
    "payment_confirmation": {"rate": 0, "pending_count": 0},
    "cancellation": {"rate": 0, "main_reasons": ["motivo em português"]},
    "inventory": {"out_of_stock_products": 0, "critical_stock_products": 0, "stagnant_inventory_value": 0},
    "coupons": {"usage_rate": 0, "ticket_impact": 0}
  },
  "anomalies": [{"type": "tipo_em_portugues", "description": "descrição em português", "severity": "alto|médio|baixo", "data": {}}],
  "identified_patterns": [{"type": "tipo_em_portugues", "description": "descrição em português", "opportunity": "oportunidade em português"}],
  "overall_health": {"score": 0, "classification": "crítico|atenção|saudável|excelente", "main_points": ["ponto em português"]}
}
```

## INSTRUÇÕES CRÍTICAS
1. NÃO reporte status='pending' como anomalia se payment_status='paid'
2. Retorne APENAS JSON válido, sem texto adicional
3. Descrições: máximo 200 caracteres
4. Main points: máximo 150 caracteres cada
5. **IDIOMA: PORTUGUÊS BRASILEIRO OBRIGATÓRIO** - TODAS as descrições, anomalias, padrões, pontos principais e observações DEVEM ser em português. NÃO use inglês.

## Exemplos de Valores em Português
- trend: "crescendo", "estável", "caindo" (NÃO use "growing", "stable", "falling")
- classification: "crítico", "atenção", "saudável", "excelente"
- anomaly types: "queda_vendas", "estoque_critico", "cupons_excessivos"
- main_points: "Vendas em crescimento de 15%", "Estoque crítico em 5 produtos"
PROMPT;
    }

    /**
     * Retorna o template do prompt V1 com placeholders para log.
     */
    public static function getTemplate(): string
    {
        return <<<'TEMPLATE'
Você é um analista de dados especializado em e-commerce brasileiro.

## 🇧🇷 IDIOMA OBRIGATÓRIO: PORTUGUÊS BRASILEIRO
Todas as descrições, padrões e observações devem estar em português brasileiro.

## Sua Tarefa
Analise os dados da loja e calcule métricas críticas, identificando padrões e anomalias.

## REGRAS CRÍTICAS DE ANÁLISE

### Sobre Dados Não Disponíveis
- Se uma métrica NÃO estiver nos dados fornecidos, use `null` (não 0)
- NÃO faça estimativas ou suposições
- Indique explicitamente quando um dado está ausente

### Sobre Benchmarks
- Use benchmarks DO NICHO ESPECÍFICO da loja
- O nicho desta loja é: {{niche}}
- NÃO use benchmark geral de e-commerce para nichos específicos

## Dados de Pedidos (últimos {{period_days}} dias)
```json
{{orders_summary}}
```

## Dados de Produtos
```json
{{products_summary}}
```

## Dados de Estoque
```json
{{inventory_summary}}
```

## Dados de Cupons
```json
{{coupons_summary}}
```

## Benchmarks do Nicho {{niche}}
```json
{{niche_benchmarks}}
```

## CÁLCULO DO HEALTH SCORE (0-100)

### Componentes do Score:
1. **Ticket Médio vs Benchmark do Nicho (25 pontos)**
2. **Disponibilidade de Estoque (25 pontos)**
3. **Taxa de Cancelamento (15 pontos)**
4. **Saúde de Cupons (15 pontos)**
5. **Tendência de Vendas (20 pontos)**

### Classificação:
- 76-100: excellent
- 51-75: healthy
- 26-50: attention
- 0-25: critical

**IMPORTANTE: O score NUNCA pode ser negativo. Mínimo é 0.**

## Formato de Saída
```json
{
  "metrics": {
    "sales": {"total": 0, "daily_average": 0, "trend": "growing|stable|falling", "trend_percentage": 0},
    "average_order_value": {"value": 0, "benchmark": 0, "percentage_difference": 0, "benchmark_source": "string"},
    "conversion": {"rate": null, "benchmark": 0, "data_available": false},
    "cancellation": {"rate": 0, "voided_count": 0, "main_reasons": []},
    "inventory": {"out_of_stock_count": 0, "out_of_stock_percentage": 0, "low_stock_count": 0, "excess_stock_count": 0},
    "coupons": {"usage_rate": 0, "ticket_impact": 0, "total_discount": 0, "dependency_level": "string"}
  },
  "anomalies": [{"type": "string", "description": "descrição em português", "severity": "high|medium|low", "data": {}, "impact_estimate": "string ou null"}],
  "identified_patterns": [{"type": "string", "description": "descrição em português", "opportunity": "oportunidade em português", "data_support": "dados que suportam o padrão"}],
  "overall_health": {
    "score": 0,
    "score_breakdown": {"ticket_medio": 0, "disponibilidade_estoque": 0, "taxa_cancelamento": 0, "saude_cupons": 0, "tendencia_vendas": 0},
    "classification": "critical|attention|healthy|excellent",
    "main_points": ["ponto em português"]
  },
  "data_quality": {"missing_metrics": [], "recommendations": []}
}
```

## INSTRUÇÕES CRÍTICAS
1. Retorne APENAS JSON válido
2. Health Score: SEMPRE entre 0 e 100, NUNCA negativo
3. Use benchmarks DO NICHO, não genéricos
4. Se dado não disponível, use null (não 0)
5. Anomalias devem ter evidência nos dados
6. RESPONDA EM PORTUGUÊS BRASILEIRO
TEMPLATE;
    }

    /**
     * Retorna o template do prompt V2 com placeholders para log.
     */
    public static function getTemplateV2(): string
    {
        return <<<'TEMPLATE'
Você é um analista de dados especializado em e-commerce brasileiro.

## 🇧🇷 IDIOMA OBRIGATÓRIO: PORTUGUÊS BRASILEIRO
TODAS as respostas, descrições, análises, anomalias, padrões e pontos principais DEVEM ser escritos em PORTUGUÊS BRASILEIRO.

## ⚠️ DIFERENÇA CRÍTICA: STATUS vs PAYMENT_STATUS

**status** = Fluxo/Processamento do pedido
- 'pending': Pedido recebido, aguardando processamento (NORMAL!)
- 'processing': Em preparação/separação
- 'shipped': Enviado
- 'delivered': Entregue
- 'cancelled': Cancelado pelo cliente

**payment_status** = Confirmação de pagamento
- 'pending': Pagamento NÃO confirmado ainda (ALERTA se >5%)
- 'paid': Pagamento CONFIRMADO ✓ (Receita realizada)
- 'refunded': Pagamento devolvido (perda de receita)
- 'failed': Falha na transação

## REGRA DE ANÁLISE
- Pedidos com status='pending' E payment_status='paid' → NORMAL (em processamento)
- Pedidos com status='pending' E payment_status='pending' → ALERTA (aguardando pagamento)
- NÃO reporte "99% pedidos pendentes" como anomalia se payment_status='paid'
- ANOMALIA REAL: payment_status='pending' em taxa >5%

## Dados de Pedidos (últimos 15 dias)
```json
{{orders_summary}}
```

## Top Produtos com Vendas
{{products_section}}

## Estoque
{{inventory_section}}

## Dados de Cupons
```json
{{coupons_summary}}
```

## Benchmarks do Nicho
```json
{{benchmarks}}
```

## Métricas a Calcular
1. **Vendas:** total, média diária, tendência
2. **Ticket Médio:** valor vs benchmark
3. **Taxa de Pagamento Confirmado:** % com payment_status='paid'
4. **Taxa de Cancelamento:** % de pedidos cancelados
5. **Estoque:** sem estoque, estoque crítico
6. **Cupons:** taxa de uso, impacto no ticket

## Formato de Saída (JSON) - TUDO EM PORTUGUÊS
```json
{
  "metrics": {...},
  "anomalies": [...],
  "identified_patterns": [...],
  "overall_health": {...}
}
```

## INSTRUÇÕES CRÍTICAS
1. NÃO reporte status='pending' como anomalia se payment_status='paid'
2. Retorne APENAS JSON válido, sem texto adicional
3. Descrições: máximo 200 caracteres
4. Main points: máximo 150 caracteres cada
5. **IDIOMA: PORTUGUÊS BRASILEIRO OBRIGATÓRIO**
TEMPLATE;
    }
}
