<?php

namespace App\Services\AI\Prompts;

/**
 * LITE ANALYST V5 - Versão compacta para limites de tokens.
 * Gera diagnóstico rápido com máximo 3 anomalias.
 */
class LiteAnalystAgentPrompt
{
    public static function get(array $data): string
    {
        $storeData = json_encode($data['store_data'] ?? [], JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
# LITE ANALYST — DIAGNÓSTICO RÁPIDO

## TAREFA
Analisar dados e retornar métricas + anomalias de forma concisa.

## REGRAS
1. Máximo 3 anomalias (apenas as mais críticas)
2. Health score 0-100 baseado nos dados
3. Todos os textos em PORTUGUÊS BRASILEIRO

---

## Dados da Loja
```json
{$storeData}
```

**IMPORTANTE:** Os dados de estoque EXCLUEM produtos que são brindes/amostras grátis. O campo `gifts_filtered` indica quantos produtos foram excluídos. Não considere brindes em alertas de estoque.

---

## EXEMPLO DE SAÍDA

```json
{
  "metrics": {
    "sales": {"total": 15420, "daily_average": 1028, "trend": "estável"},
    "average_order_value": {"value": 142, "benchmark": 150},
    "cancellation_rate": 4.2,
    "inventory": {"out_of_stock_products": 23, "critical_stock_products": 8},
    "coupons": {"usage_rate": 35, "ticket_impact": -12}
  },
  "anomalies": [
    {"type": "estoque", "description": "23 produtos sem estoque incluindo 2 best-sellers", "severity": "alto"}
  ],
  "overall_health": {
    "score": 62,
    "classification": "atenção",
    "main_points": ["Estoque crítico afetando vendas", "Ticket médio 5% abaixo do benchmark"]
  }
}
```

---

## FORMATO DE SAÍDA

```json
{
  "metrics": {
    "sales": {"total": 0, "daily_average": 0, "trend": "crescendo|estável|caindo"},
    "average_order_value": {"value": 0, "benchmark": 150},
    "cancellation_rate": 0,
    "inventory": {"out_of_stock_products": 0, "critical_stock_products": 0},
    "coupons": {"usage_rate": 0, "ticket_impact": 0}
  },
  "anomalies": [
    {"type": "string", "description": "string", "severity": "alto|médio|baixo"}
  ],
  "overall_health": {
    "score": 0,
    "classification": "crítico|atenção|saudável|excelente",
    "main_points": ["ponto 1", "ponto 2"]
  }
}
```

**RESPONDA APENAS COM O JSON. PORTUGUÊS BRASILEIRO.**
PROMPT;
    }
}
