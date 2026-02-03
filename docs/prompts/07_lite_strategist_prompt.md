# LITE STRATEGIST PROMPT — SUGESTÕES RÁPIDAS (V5)

## TAREFA
Gerar 6 sugestões acionáveis para aumentar vendas: 2 HIGH, 2 MEDIUM, 2 LOW.

## REGRAS
1. Distribuição 2-2-2 obrigatória
2. Cada sugestão com dado específico (número)
3. Ações implementáveis em até 1 semana
4. PORTUGUÊS BRASILEIRO

---

## ANÁLISE DA LOJA

```json
[Análise da loja em formato JSON]
```

## NICHO

[Nicho da loja]

---

## EXEMPLO DE SUGESTÃO BEM ESCRITA

```json
{
  "category": "inventory",
  "title": "Repor 5 produtos esgotados que vendiam R$ 2.800/mês",
  "description": "5 SKUs com histórico de venda estão zerados há 15+ dias",
  "recommended_action": "1. Identificar fornecedor\n2. Fazer pedido urgente\n3. Ativar avise-me",
  "expected_impact": "high",
  "target_metrics": ["vendas", "disponibilidade"],
  "implementation_time": "1_week",
  "specific_data": {"affected_products": ["SKU-001", "SKU-002"]},
  "data_justification": "Histórico de vendas dos últimos 60 dias"
}
```

---

## FORMATO DE SAÍDA

```json
{
  "suggestions": [
    {
      "category": "inventory|coupon|product|marketing|operational|customer|conversion|pricing",
      "title": "Título com número específico (máx 100 chars)",
      "description": "Problema identificado",
      "recommended_action": "Passos numerados",
      "expected_impact": "high|medium|low",
      "target_metrics": [],
      "implementation_time": "immediate|1_week|1_month",
      "specific_data": {},
      "data_justification": "Fonte do dado"
    }
  ]
}
```

**EXATAMENTE 6 sugestões: 2 HIGH, 2 MEDIUM, 2 LOW.**

**RESPONDA APENAS COM O JSON. PORTUGUÊS BRASILEIRO.**

