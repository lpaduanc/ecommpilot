<?php

namespace App\Services\AI\Prompts;

class SimilarityCheckPrompt
{
    public static function get(array $data): string
    {
        $previousSuggestions = json_encode($data['previous_suggestions'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
# SIMILARITY CHECK — DETECÇÃO DE DUPLICATAS

## TAREFA
Analisar sugestões anteriores e gerar "zonas proibidas" para o Strategist evitar repetições.

## REGRAS
1. Analisar TODAS as sugestões anteriores
2. Mínimo 3 variações proibidas por sugestão
3. Mínimo 2 abordagens válidas por categoria coberta
4. PORTUGUÊS BRASILEIRO

---

## Sugestões Anteriores
```json
{$previousSuggestions}
```

---

## FEW-SHOT: EXEMPLO DE ANÁLISE

### Sugestão: "Reposição Urgente de Produtos com Alta Demanda"

```json
{
  "id": 154,
  "original_title": "Reposição Urgente de Produtos com Alta Demanda",
  "problem_category": "estoque",
  "problem_description": "Produtos sem estoque causando perda de vendas",
  "solution_type": "reposição",
  "keywords": ["reposição", "estoque", "ruptura", "OOS", "esgotado"],
  "prohibited_variations": [
    "Sistema de alerta de estoque baixo",
    "Gestão proativa de estoque",
    "Monitoramento de estoque crítico"
  ]
}
```

**Abordagens VÁLIDAS para Estoque (não cobertas):**
- Pré-venda de produtos esgotados
- Lista de espera com desconto
- Sugestão de produtos alternativos
- Bundle com produtos similares

---

## CATEGORIAS DE PROBLEMA

| Categoria | Exemplos |
|-----------|----------|
| estoque | ruptura, falta, excesso, giro |
| ticket | valor médio, AOV |
| conversao | taxa de conversão, abandono |
| retencao | recompra, churn, LTV |
| cupons | descontos, promoções |
| marketing | aquisição, tráfego |
| operacional | entrega, atendimento |
| produto | descrições, fotos |

## TIPOS DE SOLUÇÃO

reposição, desconto, email, fidelidade, upsell, crosssell, bundle, social, conteudo, ux

---

## FORMATO DE SAÍDA

```json
{
  "prohibited_zones": [
    {
      "id": 0,
      "original_title": "string",
      "problem_category": "string",
      "problem_description": "string",
      "solution_type": "string",
      "keywords": [],
      "prohibited_variations": []
    }
  ],
  "allowed_approaches": {
    "estoque": [],
    "ticket": [],
    "retencao": [],
    "conversao": [],
    "cupons": [],
    "marketing": []
  },
  "coverage_summary": {
    "categories_covered": [],
    "categories_gaps": [],
    "total_analyzed": 0
  },
  "strategist_guidance": "Resumo do que evitar e onde há oportunidades"
}
```

**RESPONDA APENAS COM O JSON. PORTUGUÊS BRASILEIRO.**
PROMPT;
    }

    /**
     * Template resumido para referência.
     */
    public static function getTemplate(): string
    {
        return <<<'TEMPLATE'
# SIMILARITY CHECK — DETECÇÃO DE DUPLICATAS

## TAREFA
Analisar sugestões anteriores e gerar zonas proibidas para evitar repetições.

## OUTPUT
JSON com prohibited_zones, allowed_approaches, coverage_summary, strategist_guidance.

PORTUGUÊS BRASILEIRO
TEMPLATE;
    }
}
