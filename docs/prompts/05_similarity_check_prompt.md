# SIMILARITY CHECK PROMPT — DETECÇÃO DE DUPLICATAS (V5)

## TAREFA
Analisar sugestões anteriores e gerar "zonas proibidas" para o Strategist evitar repetições.

## REGRAS
1. Analisar TODAS as sugestões anteriores
2. Mínimo 3 variações proibidas por sugestão
3. Mínimo 2 abordagens válidas por categoria coberta
4. PORTUGUÊS BRASILEIRO

---

## SUGESTÕES ANTERIORES

```json
[Lista de sugestões anteriores em formato JSON]
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

