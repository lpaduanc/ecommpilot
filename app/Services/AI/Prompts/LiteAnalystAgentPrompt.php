<?php

namespace App\Services\AI\Prompts;

/**
 * Prompt lite para análise - otimizado para Anthropic com limite de 30k tokens/minuto.
 * Usa dados compactos e solicita análise mais focada.
 */
class LiteAnalystAgentPrompt
{
    public static function get(array $data): string
    {
        $storeData = json_encode($data['store_data'] ?? [], JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Você é um analista de e-commerce brasileiro. Analise os dados e retorne métricas e anomalias.

## Dados da Loja
```json
{$storeData}
```

## Formato de Saída (JSON obrigatório)
```json
{
  "metrics": {
    "sales": {
      "total": 0,
      "daily_average": 0,
      "trend": "growing|stable|falling"
    },
    "average_order_value": {
      "value": 0,
      "benchmark": 150
    },
    "cancellation_rate": 0,
    "inventory": {
      "out_of_stock_products": 0,
      "critical_stock_products": 0
    },
    "coupons": {
      "usage_rate": 0,
      "ticket_impact": 0
    }
  },
  "anomalies": [
    {
      "type": "string",
      "description": "descrição em português",
      "severity": "high|medium|low"
    }
  ],
  "overall_health": {
    "score": 0,
    "classification": "critical|attention|healthy|excellent",
    "main_points": ["ponto 1", "ponto 2"]
  }
}
```

## Instruções
1. Retorne APENAS JSON válido
2. Identifique no máximo 3 anomalias (as mais críticas)
3. Score de saúde: 0-100 (0=crítico, 100=excelente)
4. RESPONDA EM PORTUGUÊS BRASILEIRO
PROMPT;
    }
}
