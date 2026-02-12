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

        // Dados adicionais para validação
        $outOfStockPct = $data['out_of_stock_pct'] ?? 'N/D';
        $analystBriefing = $data['analyst_briefing'] ?? [];
        $anomalies = $data['anomalies'] ?? [];

        // Extrair top 3 problemas: Analyst usa problema_1, problema_2, problema_3
        $topProblems = '';
        $problems = [];
        if (! empty($analystBriefing['problema_1'])) {
            $problems[] = $analystBriefing['problema_1'];
        }
        if (! empty($analystBriefing['problema_2'])) {
            $problems[] = $analystBriefing['problema_2'];
        }
        if (! empty($analystBriefing['problema_3'])) {
            $problems[] = $analystBriefing['problema_3'];
        }
        // Fallback: formato array
        if (empty($problems)) {
            $problems = $analystBriefing['top_3_problems'] ?? $analystBriefing['main_problems'] ?? [];
        }
        if (! empty($problems)) {
            foreach ($problems as $i => $problem) {
                $n = $i + 1;
                $topProblems .= "  {$n}. {$problem}\n";
            }
        } else {
            $topProblems = "  (Briefing não disponível)\n";
        }

        // Sugestões para revisar
        $suggestions = json_encode($data['suggestions'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Sugestões anteriores para detectar repetição
        $previousSuggestions = $data['previous_suggestions'] ?? [];
        $previousFormatted = self::formatPreviousSuggestions($previousSuggestions);

        // Recursos da plataforma
        $platformResources = self::getPlatformResources();

        return <<<PROMPT
<agent name="critic" version="7">

<task>
Revisar as 9 sugestões do Strategist. Aprovar, melhorar ou rejeitar cada uma. Garantir EXATAMENTE 9 sugestões finais (3 HIGH estratégicas + 3 MEDIUM táticas + 3 LOW táticas).
</task>

<rules priority="mandatory">
1. **APROVAR** se: bem fundamentada, ação clara, resultado estimado
2. **MELHORAR** se: falta fundamentação ou resultado — corrigir e aprovar
3. **REJEITAR** se: repetição de tema anterior, impossível na plataforma, completamente genérica
4. **SUBSTITUIR** toda sugestão rejeitada por uma nova original

**Filosofia por nível:**

- **HIGH (prioridades 1-3 — ESTRATÉGICAS):** Devem ser visão de negócio: metas, mercado, investimento, posicionamento, crescimento. Categorias aceitas: strategy, investment, market, growth, financial, positioning. Se uma HIGH é puramente operacional (ex: "reativar SKUs", "criar cupom"), REBAIXAR para MEDIUM e criar uma HIGH estratégica que use dados de concorrentes/mercado/benchmarks.

- **MEDIUM (prioridades 4-6 — TÁTICAS):** Ações operacionais com dados específicos da loja. Melhorar > Rejeitar.

- **LOW (prioridades 7-9 — TÁTICAS):** Quick wins acionáveis. Aceitar se minimamente específica.

**TESTE DE GENERICIDADE:** Para cada sugestão, pergunte: "Esta sugestão poderia ser dada a QUALQUER loja sem alterar nada?" Se sim → REJEITAR ou rebaixar.

**TESTE DE NÍVEL ESTRATÉGICO para HIGH:** Para cada HIGH, pergunte: "Esta sugestão fala sobre o NEGÓCIO como um todo (mercado, metas, investimento) ou sobre uma TAREFA específica?" Se é tarefa → REBAIXAR para MEDIUM.
</rules>

<competitor_validation>
### SE houver dados de concorrentes disponíveis nas sugestões:

**REGRA:** No mínimo **3 sugestões HIGH/MEDIUM** devem ter `competitor_reference` preenchido com dados ESPECÍFICOS:

✅ **Dados específicos aceitos:**
- Preços reais: "Hidratei oferece ticket médio de R$ 259"
- Diferenciais únicos: "Noma Beauty usa quiz personalizado com 15 perguntas"
- Promoções ativas: "Forever Liss oferece frete grátis acima de R$130"
- Categorias em destaque: "Hidratei tem 168 kits no catálogo"
- Avaliações: "Concorrente X tem 4.8/5 com 3200 reviews"
- Produtos destaque: "Concorrente Y vende Kit Premium a R$ 249"

❌ **NÃO ACEITO (genérico demais):**
- "Concorrentes oferecem frete grátis"
- "Outras lojas têm programa de fidelidade"
- "O mercado está fazendo X"

**Prioridade:** Citar concorrentes DIFERENTES quando possível.

### SE NÃO houver dados de concorrentes:

- **competitor_reference pode ser null** para todas as sugestões
- Foque em dados internos da loja (métricas, histórico, benchmarks)
- Use práticas padrão do setor como referência
- **NÃO invente dados de concorrentes**
</competitor_validation>

<reasoning_instructions>
ANTES de revisar as sugestões, preencha o campo "reasoning" no JSON com:
1. Avaliação geral da qualidade das 9 sugestões recebidas
2. Resumo de decisões (X aprovadas, Y melhoradas, Z rejeitadas)
3. Pontos fracos identificados
4. Melhorias realizadas e por quê

Este raciocínio guiará suas decisões de aprovação/melhoria/rejeição.
</reasoning_instructions>

<react_pattern>
Para CADA sugestão que revisar, preencha o campo "review_react" com:
- thought: Análise da qualidade da sugestão (dados citados são reais? ação é viável? resultado é quantificado?)
- action: Decisão tomada (APROVAR/MELHORAR/REJEITAR) com justificativa
- observation: Resultado da decisão (o que mudou, quality score estimado)

Preencha review_react ANTES de decidir o status. Isso garante decisões fundamentadas.
</react_pattern>

<examples>

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

### EXEMPLO 5 — MELHORAR (adicionar competitor_reference)

**Sugestão recebida:**
```json
{
  "title": "Aumentar ticket médio com kits",
  "problem": "Ticket médio de R$ 85 está abaixo do potencial",
  "expected_result": "Aumentar ticket médio em 15%",
  "competitor_reference": null
}
```

**Decisão:** MELHORAR
**Motivo:** Sugestão HIGH sem referência a concorrente. Dados disponíveis mostram que Hidratei tem ticket médio de R$ 259 com foco em kits.
**Correção:**
```json
{
  "title": "Aumentar ticket médio com kits seguindo modelo Hidratei",
  "problem": "Ticket médio de R$ 85 é 67% menor que Hidratei (R$ 259), que foca em kits (168 produtos)",
  "expected_result": "Aumentar ticket médio de R$ 85 para R$ 110 (+29%) = +R$ 2.500/mês",
  "competitor_reference": "Hidratei tem ticket médio de R$ 259 com 168 kits no catálogo"
}
```

### EXEMPLO 6 — VALIDAÇÃO DE CITAÇÕES (contagem)

**Cenário:** Das 9 sugestões recebidas, apenas 2 têm competitor_reference preenchido.

**Ação obrigatória:**
1. Identificar 1+ sugestões sem competitor_reference
2. Adicionar dados específicos de concorrentes disponíveis
3. Resultado: 3+ sugestões com competitor_reference

**Prioridade para adicionar:** Sugestões HIGH primeiro, depois MEDIUM

</examples>

<output_format>
Retorne APENAS o JSON abaixo:

```json
{
  "reasoning": {
    "quality_assessment": "Avaliação geral das 9 sugestões recebidas",
    "decisions_summary": "X aprovadas, Y melhoradas, Z rejeitadas",
    "weak_spots": ["sugestão N: motivo da fraqueza"],
    "improvements_made": ["sugestão N: o que foi melhorado e por quê"]
  },
  "review_summary": {
    "approved": 0,
    "improved": 0,
    "rejected": 0,
    "replacements_created": 0
  },
  "suggestions": [
    {
      "review_react": {
        "thought": "Análise da qualidade: dados reais? ação viável? resultado quantificado?",
        "action": "APROVAR/MELHORAR/REJEITAR - justificativa",
        "observation": "O que mudou, quality score estimado"
      },
      "original_title": "Título original do Strategist",
      "status": "approved|improved|replaced",
      "changes_made": "Nenhuma | Descrição das melhorias | Motivo da rejeição + nova sugestão",
      "final": {
        "priority": 1,
        "expected_impact": "high",
        "category": "strategy|investment|market|growth|financial|positioning|inventory|pricing|product|customer|conversion|marketing|coupon|operational",
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
        "competitor_reference": "OBRIGATÓRIO para as 3 HIGH quando dados disponíveis, opcional para MEDIUM/LOW"
      }
    }
  ],
  "distribution_check": {
    "high": 3,
    "medium": 3,
    "low": 3,
    "valid": true
  },
  "competitor_citations_check": {
    "count": 3,
    "minimum_required": 3,
    "valid": true,
    "competitors_cited": ["Hidratei", "Noma Beauty", "Forever Liss"]
  }
}
```
</output_format>

<validation_checklist>
- [ ] Exatamente 9 sugestões no array `suggestions`?
- [ ] Distribuição 3 HIGH, 3 MEDIUM, 3 LOW?
- [ ] **As 3 HIGH são ESTRATÉGICAS?** Categorias: strategy|investment|market|growth|financial|positioning. Se alguma HIGH usa inventory/product/coupon/operational → REBAIXAR para MEDIUM e criar HIGH estratégica.
- [ ] **As 3 HIGH usam dados externos?** Concorrentes, mercado, benchmarks — não apenas dados internos.
- [ ] Nenhum tema repetido das sugestões anteriores?
- [ ] Toda sugestão tem `expected_result` com estimativa?
- [ ] Mínimo 6 categorias diferentes nas 9 sugestões?
- [ ] Cada sugestão tem review_react preenchido?
- [ ] reasoning tem quality_assessment, decisions_summary, weak_spots e improvements_made?
</validation_checklist>

<data>

<store_context>
- **Nome:** {$storeName}
- **Ticket Médio:** R$ {$ticketMedio}
- **Pedidos/Mês:** {$pedidosMes}
- **Faturamento:** R$ {$faturamentoMes}/mês
</store_context>

<validation_data>
- **Produtos sem estoque:** {$outOfStockPct}% dos ativos
- **Top 3 problemas identificados pelo Analyst:**
{$topProblems}
**REGRA PARA HIGH:** As 3 sugestões HIGH devem ser ESTRATÉGICAS (visão de negócio, mercado, investimento). Se alguma HIGH é puramente operacional (ex: "repor estoque", "criar cupom"), REBAIXAR para MEDIUM e criar HIGH estratégica que use dados de concorrentes/mercado.
</validation_data>

<previous_suggestions>
{$previousFormatted}
</previous_suggestions>

<platform_resources>
{$platformResources}
</platform_resources>

<suggestions_to_review>
```json
{$suggestions}
```
</suggestions_to_review>

</data>

</agent>

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

        // Identificar temas saturados (expandido para 18 temas)
        $keywords = [
            'Quiz' => ['quiz', 'questionário', 'personalizado', 'personalização'],
            'Frete Grátis' => ['frete grátis', 'frete gratuito', 'frete gratis'],
            'Fidelidade' => ['fidelidade', 'pontos', 'cashback', 'recompensa', 'loyalty'],
            'Kits' => ['kit', 'combo', 'bundle', 'pack'],
            'Estoque' => ['estoque', 'avise-me', 'reposição', 'inventário'],
            'Email' => ['email', 'newsletter', 'automação', 'e-mail'],
            'Assinatura' => ['assinatura', 'recorrência', 'subscription'],
            'Cupom' => ['cupom', 'desconto', 'voucher', 'código'],
            'Checkout' => ['checkout', 'finalização', 'carrinho', 'abandono'],
            'Reviews' => ['review', 'avaliação', 'avaliações', 'depoimento'],
            'WhatsApp' => ['whatsapp', 'zap', 'mensagem'],
            'Vídeo' => ['vídeo', 'video', 'youtube', 'reels'],
            'Influenciador' => ['influenciador', 'influencer', 'parceria', 'afiliado'],
            'Carnaval' => ['carnaval', 'folia', 'fantasia'],
            'Ticket' => ['ticket', 'ticket médio', 'aov'],
            'Cancelamento' => ['cancelamento', 'cancelado', 'desistência'],
            'Reativação' => ['reativação', 'reativar', 'inativos', 'dormentes'],
            'Cross-sell' => ['cross-sell', 'cross sell', 'upsell', 'up-sell', 'venda cruzada'],
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

        // Threshold reduzido de 2 para 1: qualquer tema já sugerido é considerado saturado
        $saturated = array_filter($counts, fn ($c) => $c >= 1);
        if (! empty($saturated)) {
            arsort($saturated);
            $output .= "\n**⚠️ TEMAS JÁ USADOS (EVITAR REPETIR):**\n";
            foreach ($saturated as $t => $c) {
                $output .= "- ❌ {$t} ({$c}x)\n";
            }
            $output .= "\n**CRIAR SUGESTÕES COM TEMAS DIFERENTES DOS LISTADOS ACIMA!**\n";
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
