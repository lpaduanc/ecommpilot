<?php

namespace App\Services\AI\Prompts;

class CriticAgentPrompt
{
    /**
     * CRITIC AGENT V5 - REFATORADO
     *
     * Mudanças:
     * - Removida persona fictícia
     * - Adicionados few-shot examples de aprovação/rejeição/melhoria
     * - Prompt reduzido (~50%)
     * - Formato de saída simplificado e alinhado com StrategistAgentPrompt V5
     * - Constraints específicos e mensuráveis
     */
    public static function getPlatformResources(): string
    {
        return <<<'RESOURCES'
## RECURSOS NUVEMSHOP

**NATIVOS (grátis):** Cupons, Frete grátis condicional, Avise-me, Produtos relacionados, SEO básico

**APPS (custo):**
- Quiz: R$ 30-100/mês (Pregão, Lily AI)
- Fidelidade: R$ 49-150/mês (Fidelizar+)
- Reviews: R$ 20-80/mês (Lily Reviews)
- Carrinho abandonado: R$ 30-100/mês (CartStack)
- Assinatura: R$ 50-150/mês (Vindi)

**IMPOSSÍVEL (rejeitar):** Realidade aumentada, IA generativa nativa, Live commerce nativo
RESOURCES;
    }

    public static function get(array $data): string
    {
        $storeName = $data['store_name'] ?? 'Loja';
        $platform = $data['platform'] ?? 'nuvemshop';
        $ticketMedio = $data['ticket_medio'] ?? 0;
        $pedidosMes = $data['pedidos_mes'] ?? 0;
        $faturamentoMes = $ticketMedio * $pedidosMes;

        // Sugestões para revisar
        $suggestions = json_encode($data['suggestions'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Sugestões anteriores para detectar repetição
        $previousSuggestions = $data['previous_suggestions'] ?? [];
        $previousFormatted = self::formatPreviousSuggestions($previousSuggestions);

        // Recursos da plataforma
        $platformResources = self::getPlatformResources();

        return <<<PROMPT
# CRITIC — REVISOR DE SUGESTÕES

## TAREFA
Revisar as 9 sugestões do Strategist. Aprovar, melhorar ou rejeitar cada uma. Garantir EXATAMENTE 9 sugestões finais (3 HIGH, 3 MEDIUM, 3 LOW).

---

## REGRAS

1. **APROVAR** se: tem dado específico, ação clara, resultado com número, viável na Nuvemshop
2. **MELHORAR** se: falta dado específico, ação vaga, resultado sem número — corrigir e aprovar
3. **REJEITAR** se: repetição de tema anterior, impossível na plataforma, completamente genérica
4. **SUBSTITUIR** toda sugestão rejeitada por uma nova original

**Filosofia:** Melhorar > Rejeitar (exceto repetições e impossíveis)

---

## CONTEXTO DA LOJA

- **Nome:** {$storeName}
- **Ticket Médio:** R$ {$ticketMedio}
- **Pedidos/Mês:** {$pedidosMes}
- **Faturamento:** R$ {$faturamentoMes}/mês

---

## SUGESTÕES ANTERIORES (NÃO REPETIR TEMA)

{$previousFormatted}

---

{$platformResources}

---

## SUGESTÕES PARA REVISAR

```json
{$suggestions}
```

---

## FEW-SHOT: EXEMPLOS DE REVISÃO

### EXEMPLO 1 — APROVAR (sugestão já está boa)

**Sugestão recebida:**
```json
{
  "title": "Reativar 8 SKUs parados há 60+ dias que vendiam R$ 3.200/mês",
  "problem": "8 produtos com histórico de venda estão com estoque mas sem vendas há 60 dias.",
  "expected_result": "Recuperar 60% do histórico = R$ 1.920/mês"
}
```

**Decisão:** APROVAR
**Motivo:** Tem dado específico (8 SKUs, R$ 3.200), ação clara, resultado com número
**Ação:** Manter como está

---

### EXEMPLO 2 — MELHORAR (falta dado específico)

**Sugestão recebida:**
```json
{
  "title": "Criar programa de fidelidade",
  "problem": "Clientes não voltam a comprar",
  "expected_result": "Aumentar recompra"
}
```

**Decisão:** MELHORAR
**Motivo:** Falta dado específico, resultado vago
**Correção:**
```json
{
  "title": "Criar programa de fidelidade para os 120 clientes que compraram 2+ vezes",
  "problem": "Taxa de recompra atual é 8% (120 de 1.500 clientes). Benchmark do setor é 15-20%.",
  "expected_result": "Aumentar taxa de recompra de 8% para 12% = +60 pedidos/mês = R$ 4.800/mês"
}
```

---

### EXEMPLO 3 — REJEITAR (tema já sugerido antes)

**Sugestão recebida:**
```json
{
  "title": "Implementar quiz de personalização de produtos"
}
```

**Decisão:** REJEITAR
**Motivo:** Tema "Quiz" já aparece nas sugestões anteriores (saturado)
**Substituir por:** Nova sugestão com tema diferente

---

### EXEMPLO 4 — REJEITAR (impossível na plataforma)

**Sugestão recebida:**
```json
{
  "title": "Implementar provador virtual com realidade aumentada"
}
```

**Decisão:** REJEITAR
**Motivo:** Realidade aumentada não está disponível na Nuvemshop
**Substituir por:** Alternativa viável (ex: fotos 360°, vídeos de produto)

---

## FORMATO DE SAÍDA

Retorne APENAS o JSON abaixo:

```json
{
  "review_summary": {
    "approved": 0,
    "improved": 0,
    "rejected": 0,
    "replacements_created": 0
  },
  "suggestions": [
    {
      "original_title": "Título original do Strategist",
      "status": "approved|improved|replaced",
      "changes_made": "Nenhuma | Descrição das melhorias | Motivo da rejeição + nova sugestão",
      "final": {
        "priority": 1,
        "expected_impact": "high",
        "category": "inventory|pricing|product|customer|conversion|marketing|coupon|operational",
        "title": "Título final (pode ser igual ao original ou melhorado)",
        "problem": "Problema com dado específico",
        "action": "Passos numerados",
        "expected_result": "Resultado com número (R$ ou %)",
        "data_source": "Fonte do dado",
        "implementation": {
          "type": "nativo|app|terceiro",
          "app_name": "nome ou null",
          "complexity": "baixa|media|alta",
          "cost": "R$ X/mês ou R$ 0"
        },
        "competitor_reference": "dado de concorrente se HIGH, senão null"
      }
    }
  ],
  "distribution_check": {
    "high": 3,
    "medium": 3,
    "low": 3,
    "valid": true
  }
}
```

---

## CHECKLIST ANTES DE ENVIAR

- [ ] Exatamente 9 sugestões no array `suggestions`?
- [ ] Distribuição 3 HIGH, 3 MEDIUM, 3 LOW?
- [ ] Nenhum tema repetido das sugestões anteriores?
- [ ] Todas viáveis na Nuvemshop?
- [ ] Toda sugestão tem `expected_result` com número?
- [ ] Toda HIGH tem dado específico no `problem`?

**RESPONDA APENAS COM O JSON. PORTUGUÊS BRASILEIRO.**
PROMPT;
    }

    private static function formatPreviousSuggestions(array $previousSuggestions): string
    {
        if (empty($previousSuggestions)) {
            return 'Nenhuma sugestão anterior. Todas serão consideradas originais.';
        }

        $grouped = [];
        foreach ($previousSuggestions as $s) {
            $title = $s['title'] ?? 'Sem título';
            $category = $s['category'] ?? 'outros';
            if (! isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            if (! in_array($title, $grouped[$category])) {
                $grouped[$category][] = $title;
            }
        }

        $output = 'Total: '.count($previousSuggestions)." sugestões anteriores\n\n";
        foreach ($grouped as $cat => $titles) {
            $output .= "**{$cat}:** ".implode(', ', $titles)."\n";
        }

        // Identificar temas saturados
        $keywords = [
            'Quiz' => ['quiz', 'questionário', 'personalizado'],
            'Frete Grátis' => ['frete grátis', 'frete gratuito'],
            'Fidelidade' => ['fidelidade', 'pontos', 'cashback'],
            'Kits' => ['kit', 'combo', 'bundle'],
            'Estoque' => ['estoque', 'avise-me', 'reposição'],
            'Email' => ['email', 'newsletter', 'automação'],
            'Assinatura' => ['assinatura', 'recorrência'],
        ];

        $counts = [];
        foreach ($previousSuggestions as $s) {
            $text = mb_strtolower(($s['title'] ?? '').' '.($s['description'] ?? ''));
            foreach ($keywords as $theme => $kws) {
                foreach ($kws as $kw) {
                    if (strpos($text, $kw) !== false) {
                        $counts[$theme] = ($counts[$theme] ?? 0) + 1;
                        break;
                    }
                }
            }
        }

        $saturated = array_filter($counts, fn ($c) => $c >= 2);
        if (! empty($saturated)) {
            arsort($saturated);
            $output .= "\n**TEMAS SATURADOS (NÃO USAR):**\n";
            foreach ($saturated as $t => $c) {
                $output .= "- {$t} ({$c}x)\n";
            }
        }

        return $output;
    }

    /**
     * Método build() para compatibilidade.
     */
    public static function build(array $context): string
    {
        return self::get($context);
    }

    /**
     * Template resumido para referência.
     */
    public static function getTemplate(): string
    {
        return <<<'TEMPLATE'
# CRITIC — REVISOR DE SUGESTÕES

## TAREFA
Revisar 9 sugestões. Aprovar, melhorar ou rejeitar. Garantir 9 finais (3-3-3).

## DECISÕES
- APROVAR: dado específico + ação clara + resultado com número
- MELHORAR: corrigir o que falta e aprovar
- REJEITAR: repetição ou impossível → criar substituta

## OUTPUT
JSON com array de 9 sugestões revisadas.

PORTUGUÊS BRASILEIRO
TEMPLATE;
    }
}
