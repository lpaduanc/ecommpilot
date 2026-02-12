<?php

namespace App\Services\AI\Prompts;

use App\Services\Analysis\ThemeKeywords;

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

        // V7: Dados detalhados da loja para verificação numérica
        $ordersSummary = $data['orders_summary'] ?? [];
        $productsSummary = $data['products_summary'] ?? [];
        $inventorySummary = $data['inventory_summary'] ?? [];
        $couponsSummary = $data['coupons_summary'] ?? [];
        $storeMetricsSection = self::formatStoreMetrics($ordersSummary, $productsSummary, $inventorySummary, $couponsSummary);

        // V7: Temas saturados separados
        $previousSuggestionsAll = $data['previous_suggestions'] ?? [];
        $allPrevSuggestions = isset($previousSuggestionsAll['all']) ? $previousSuggestionsAll['all'] : $previousSuggestionsAll;
        $saturatedThemesSection = self::formatSaturatedThemes($allPrevSuggestions);

        // Extrair top 5 problemas: Analyst usa problema_1 até problema_5
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
        if (! empty($analystBriefing['problema_4'])) {
            $problems[] = $analystBriefing['problema_4'];
        }
        if (! empty($analystBriefing['problema_5'])) {
            $problems[] = $analystBriefing['problema_5'];
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

        // ProfileSynthesizer store profile
        $perfilLojaSection = '';
        if (! empty($data['store_profile'])) {
            $profileJson = json_encode($data['store_profile'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $perfilLojaSection = <<<SECTION
<perfil_loja>
{$profileJson}
</perfil_loja>

SECTION;
        }

        // V6: Module config para análises especializadas
        $moduleConfig = $data['module_config'] ?? null;
        $criteriosModulo = '';
        if ($moduleConfig && $moduleConfig->isSpecialized) {
            $tipo = $moduleConfig->analysisType;
            $criterios = $moduleConfig->criticConfig['criterios_extras'] ?? '';
            if ($criterios) {
                $criteriosModulo = <<<CRIT

<criterios_modulo>
Critérios adicionais de validação para análise {$tipo}:
{$criterios}
</criterios_modulo>
CRIT;
            }
        }

        return <<<PROMPT
# CRITIC — REVISOR DE SUGESTÕES

{$perfilLojaSection}<sugestoes_recebidas>
```json
{$suggestions}
```
</sugestoes_recebidas>

<dados_originais>
## CONTEXTO DA LOJA

- **Nome:** {$storeName}
- **Ticket Médio:** R$ {$ticketMedio}
- **Pedidos/Mês:** {$pedidosMes}
- **Faturamento:** R$ {$faturamentoMes}/mês

## DADOS-CHAVE PARA VALIDAÇÃO

- **Produtos sem estoque:** {$outOfStockPct}% dos ativos
- **Top 5 problemas identificados pelo Analyst:**
{$topProblems}

## SUGESTÕES ANTERIORES (NÃO REPETIR TEMA)

{$previousFormatted}
</dados_originais>

<dados_loja_detalhados>
{$storeMetricsSection}
</dados_loja_detalhados>

<temas_saturados>
{$saturatedThemesSection}
</temas_saturados>

<recursos_plataforma>
{$platformResources}
</recursos_plataforma>

---

<persona>
Você é um Revisor Crítico CÉTICO por natureza. Uma sugestão precisa PROVAR que merece ser aprovada — não basta "parecer boa". Seu trabalho é encontrar falhas, inconsistências numéricas e sugestões genéricas que passariam em qualquer loja. Se todas as sugestões parecem boas, você NÃO está sendo rigoroso o suficiente.
</persona>

<instrucoes_validacao>
## TAREFA
Revisar as 12 sugestões do Strategist. Aprovar, melhorar ou rejeitar cada uma. Garantir EXATAMENTE 12 sugestões finais (4 HIGH, 4 MEDIUM, 4 LOW).

## META DE RIGOR

- **Mínimo 3 rejeições por análise.** Se TUDO parece bom, você não está sendo rigoroso o suficiente.
- **Score médio alvo: 7.0-8.0.** Se o score médio ficar acima de 9.0, revise sua avaliação — provavelmente está sendo leniente.
- **Rejeite temas saturados:** Consulte <temas_saturados>. Se um tema aparece 3+ vezes em análises anteriores, REJEITE e substitua por tema novo.

## FRAMEWORK DE VERIFICAÇÃO V1-V6 (OBRIGATÓRIO)

Para CADA sugestão, execute as 6 verificações abaixo. Documente o resultado de cada uma no campo `verificacoes` do output:

- **V1-Números:** Os números citados (ticket médio, quantidade de SKUs, percentuais, faturamento) conferem com <dados_originais> e <dados_loja_detalhados>? Se divergem, corrija.
- **V2-Originalidade:** O tema já aparece em <temas_saturados>? Se sim, REJEITE.
- **V3-Especificidade:** A sugestão poderia ser dada a QUALQUER loja sem alterar nada? Se sim, REJEITE ou torne específica com dados reais da loja.
- **V4-Viabilidade:** A implementação é possível na plataforma conforme <recursos_plataforma>? Qual o custo real?
- **V5-Impacto:** O cálculo de impacto é verificável? Tem base × premissa = resultado? Se faltar, complete.
- **V6-Alinhamento:** A sugestão resolve algum dos 5 problemas do Analyst? (Obrigatório para HIGH - priorize os 3 primeiros)

## REGRAS

1. **APROVAR** se: passa em V1-V6, tem dado específico, ação clara, resultado com número
2. **MELHORAR** se: falta dado específico, ação vaga, resultado sem número — corrigir e aprovar
3. **REJEITAR** se: falha em V2 (tema saturado), V3 (genérica demais), V4 (inviável), ou V6 (HIGH sem alinhamento)
4. **SUBSTITUIR** toda sugestão rejeitada por uma nova original que passe em V1-V6

**Filosofia por nível:**
- **HIGH:** Exigência MÁXIMA. Se não tem dado específico da loja + cálculo de impacto + ação em passos + vinculação com problema do Analyst → REJEITAR e substituir.
- **MEDIUM:** Melhorar > Rejeitar. Aceitar com correções se tiver potencial.
- **LOW:** Aceitar se acionável. Rejeitar APENAS se completamente genérica.

**REGRA DE VINCULAÇÃO:** As 4 sugestões HIGH devem resolver os 5 problemas do Analyst identificados em <dados_originais>, priorizando os 3 primeiros/mais críticos. Se alguma HIGH não endereçar nenhum dos problemas do Analyst, REJEITAR e substituir por sugestão que endereça.

## VALIDAÇÃO: CITAÇÕES DE CONCORRENTES (CONDICIONAL)

### SE houver dados de concorrentes disponíveis nas sugestões:

**REGRA:** No mínimo **4 sugestões HIGH/MEDIUM** devem ter `competitor_reference` preenchido com dados ESPECÍFICOS:

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
- Use exclusivamente dados de concorrentes presentes em <sugestoes_recebidas> (ou seja, evite criar dados fictícios){$criteriosModulo}
</instrucoes_validacao>

<regras_anti_alucinacao>
## REGRAS ANTI-ALUCINAÇÃO

1. **Baseie todas as suas validações exclusivamente nos dados fornecidos** em <dados_originais> e <sugestoes_recebidas>. Quando não houver dados suficientes para validar uma afirmação, sinalize explicitamente: "dado não verificável com as informações disponíveis".
2. **Separe fatos de interpretações:** Ao validar uma sugestão, indique se os números são dados diretos (verificáveis) ou estimativas (não verificáveis).
3. **Quando recalcular impactos,** use os dados de <dados_originais> como fonte de verdade. Se o Strategist usou um número diferente, sinalize a discrepância.
4. **Ao substituir sugestões rejeitadas,** siga as mesmas regras anti-alucinação: use dados reais, identifique fontes, e apresente cálculos verificáveis.
5. **Fique à vontade para marcar sugestões como "dado não verificável"** quando os números citados não puderem ser conferidos com os dados disponíveis.
</regras_anti_alucinacao>

<validacao_factos>
## VALIDAÇÃO DE FATOS OBRIGATÓRIA

Ao revisar cada sugestão do Strategist, execute estas verificações usando <dados_originais> E <dados_loja_detalhados> como fonte de verdade:

1. **Números conferem?** Compare os números citados na sugestão (ticket médio, quantidade de SKUs, percentuais, best-sellers, faturamento) com os dados em <dados_originais> e <dados_loja_detalhados>. Se divergirem, corrija e sinalize.
2. **Tendências de mercado têm fonte?** Se a sugestão afirma algo sobre "tendências" ou "o mercado", verifique se o dado vem dos dados fornecidos ou é afirmação sem fonte. Sinalize afirmações sem fonte verificável.
3. **Classificação data_source correta?** Se a sugestão está marcada como "dado_direto", confirme que o dado realmente existe nos dados fornecidos. Se não existir, reclassifique para "inferencia" ou "best_practice_geral".
4. **Cálculos de impacto verificáveis?** Para sugestões HIGH, verifique se o expected_result mostra base × premissa = resultado. Se faltar alguma parte, complete o cálculo usando dados de <dados_loja_detalhados>.
</validacao_factos>

<exemplos>
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

---

### EXEMPLO 6 — VALIDAÇÃO DE CITAÇÕES (contagem)

**Cenário:** Das 12 sugestões recebidas, apenas 2 têm competitor_reference preenchido.

**Ação obrigatória:**
1. Identificar 2+ sugestões sem competitor_reference
2. Adicionar dados específicos de concorrentes disponíveis
3. Resultado: 4+ sugestões com competitor_reference

**Prioridade para adicionar:** Sugestões HIGH primeiro, depois MEDIUM
</exemplos>

<exemplos_contrastivos>
## EXEMPLOS CONTRASTIVOS — BOM vs RUIM

<exemplo_critica_boa>
"SUGESTÃO #3 — DADO_CORRIGIDO: A sugestão afirma que o ticket médio é R$220, mas os dados da loja mostram R$185. Recalculando com o valor correto, o impacto estimado cai de R$5.000 para R$3.800/mês. Recomendo ajustar os números."
**Por que é bom:** identifica erro específico, referencia dado real, recalcula, sugere correção.
</exemplo_critica_boa>

<exemplo_critica_ruim>
"As sugestões estão boas e bem fundamentadas. Recomendo implementar todas."
**Por que é ruim:** não valida dados, não questiona nada, não agrega valor à análise.
</exemplo_critica_ruim>

Siga o padrão da crítica boa. Sempre valide números e questione afirmações.
</exemplos_contrastivos>

<formato_saida>
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
      "verificacoes": {
        "V1_numeros": {"resultado": "ok|corrigido|nao_verificavel", "detalhe": "Ticket médio confere: R$ 85"},
        "V2_originalidade": {"resultado": "ok|rejeitado", "detalhe": "Tema inédito"},
        "V3_especificidade": {"resultado": "ok|rejeitado|melhorado", "detalhe": "Usa dados específicos da loja"},
        "V4_viabilidade": {"resultado": "ok|rejeitado", "detalhe": "Viável via Nuvemshop nativo"},
        "V5_impacto": {"resultado": "ok|corrigido|nao_verificavel", "detalhe": "120 pedidos × R$85 × 15% = R$1.530/mês"},
        "V6_alinhamento": {"resultado": "ok|nao_aplicavel", "detalhe": "Resolve problema_1 do Analyst"}
      },
      "verificacao_status": "VERIFICADA|DADO_CORRIGIDO|NAO_VERIFICAVEL",
      "score_qualidade": 8,
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
        "competitor_reference": "OBRIGATÓRIO para as 3 HIGH quando dados disponíveis, opcional para MEDIUM/LOW",
        "insight_origem": "problema_1|problema_2|problema_3|problema_4|problema_5|best_practice",
        "nivel_confianca": "alto|medio|baixo"
      }
    }
  ],
  "distribution_check": {
    "high": 4,
    "medium": 4,
    "low": 4,
    "valid": true
  },
  "competitor_citations_check": {
    "count": 4,
    "minimum_required": 4,
    "valid": true,
    "competitors_cited": ["Hidratei", "Noma Beauty", "Forever Liss", "Outro Concorrente"]
  },
  "temas_rejeitados_por_saturacao": ["Quiz", "Frete Grátis"],
  "quality_summary": {
    "total_verificadas": 0,
    "total_corrigidas": 0,
    "total_nao_verificaveis": 0,
    "score_medio": 0
  }
}
```

## CHECKLIST ANTES DE ENVIAR

- [ ] Exatamente 12 sugestões no array `suggestions`?
- [ ] Distribuição 4 HIGH, 4 MEDIUM, 4 LOW?
- [ ] Todos os temas são inéditos em relação às sugestões de <dados_originais>?
- [ ] Todas viáveis na Nuvemshop?
- [ ] Toda sugestão tem `expected_result` com número?
- [ ] Toda HIGH tem dado específico no `problem`?
- [ ] **SE houver dados de concorrentes:** mínimo 4 sugestões com competitor_reference específico
- [ ] **SE NÃO houver dados de concorrentes:** competitor_reference pode ser null, foque em dados internos
- [ ] As 4 HIGH resolvem problemas do Analyst (priorizando os 3 primeiros dos 5 problemas identificados)?
- [ ] Mínimo 6 categorias diferentes nas 12 sugestões?
- [ ] Cada HIGH tem cálculo de impacto (base × premissa = resultado)?

**RESPONDA APENAS COM O JSON. PORTUGUÊS BRASILEIRO.**
</formato_saida>
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

        // Use centralized theme keywords
        $themeKeywords = ThemeKeywords::all();
        $themeLabels = ThemeKeywords::labels();

        // Build keywords map with readable labels
        $keywords = [];
        foreach ($themeKeywords as $themeKey => $keywordList) {
            $label = $themeLabels[$themeKey] ?? ucfirst(str_replace('_', ' ', $themeKey));
            $keywords[$label] = $keywordList;
        }

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

        // Threshold de 3+: temas com 3 ou mais ocorrências são considerados saturados
        $saturated = array_filter($counts, fn ($c) => $c >= 3);
        if (! empty($saturated)) {
            arsort($saturated);
            $output .= "\n**⚠️ TEMAS SATURADOS (REJEITAR):**\n";
            foreach ($saturated as $t => $c) {
                $output .= "- ❌ {$t} ({$c}x)\n";
            }
            $output .= "\n**CRIAR SUGESTÕES COM TEMAS DIFERENTES DOS LISTADOS ACIMA!**\n";
        }

        // Show used themes (1-2x) as a warning but not blocking
        $used = array_filter($counts, fn ($c) => $c >= 1 && $c < 3);
        if (! empty($used)) {
            arsort($used);
            $output .= "\n**⚠️ TEMAS JÁ USADOS (EVITAR SE POSSÍVEL):**\n";
            foreach ($used as $t => $c) {
                $output .= "- ⚠️ {$t} ({$c}x)\n";
            }
        }

        return $output;
    }

    /**
     * V7: Formatar métricas detalhadas da loja para verificação numérica pelo Critic.
     */
    private static function formatStoreMetrics(array $orders, array $products, array $inventory, array $coupons): string
    {
        $output = "## PEDIDOS (período de análise)\n";
        $output .= '- Total de pedidos: '.($orders['total'] ?? 0)."\n";
        $output .= '- Período: '.($orders['period_days'] ?? 15)." dias\n";
        $output .= '- Receita total (pagos): R$ '.number_format($orders['total_revenue'] ?? 0, 2, ',', '.')."\n";
        $output .= '- Ticket médio: R$ '.number_format($orders['average_order_value'] ?? 0, 2, ',', '.')."\n";
        $output .= '- Taxa de cancelamento: '.($orders['cancellation_rate'] ?? 0)."%\n";

        if (! empty($orders['by_payment_status'])) {
            $output .= "- Status de pagamento:\n";
            foreach ($orders['by_payment_status'] as $status => $count) {
                $output .= "  - {$status}: {$count}\n";
            }
        }

        $output .= "\n## PRODUTOS\n";
        $output .= '- Total de produtos: '.($products['total'] ?? 0)."\n";
        $output .= '- Produtos ativos: '.($products['active'] ?? 0)."\n";
        $output .= '- Sem estoque: '.($products['out_of_stock'] ?? 0)."\n";

        if (! empty($products['best_sellers'])) {
            $output .= "- Top produtos mais vendidos:\n";
            foreach (array_slice($products['best_sellers'], 0, 5) as $bs) {
                $name = $bs['name'] ?? 'N/D';
                $qty = $bs['quantity_sold'] ?? 0;
                $rev = number_format($bs['revenue'] ?? 0, 2, ',', '.');
                $stock = $bs['current_stock'] ?? 0;
                $output .= "  - {$name}: {$qty} vendidos, R$ {$rev}, estoque atual: {$stock}\n";
            }
        }

        if (! empty($products['no_sales_period'])) {
            $noSalesCount = is_array($products['no_sales_period']) ? count($products['no_sales_period']) : $products['no_sales_period'];
            $output .= "- Produtos sem vendas no período: {$noSalesCount}\n";
        }

        $output .= "\n## ESTOQUE\n";
        $output .= '- Valor total em estoque: R$ '.number_format($inventory['total_value'] ?? 0, 2, ',', '.')."\n";
        $output .= '- Produtos com estoque baixo: '.($inventory['low_stock_products'] ?? 0)."\n";
        $output .= '- Produtos com excesso de estoque (>100 un.): '.($inventory['excess_stock_products'] ?? 0)."\n";

        $output .= "\n## CUPONS\n";
        if (! empty($coupons)) {
            $output .= '- Total de cupons: '.($coupons['total'] ?? 0)."\n";
            $output .= '- Cupons ativos: '.($coupons['active'] ?? 0)."\n";
            if (isset($coupons['usage_rate'])) {
                $output .= '- Taxa de uso: '.($coupons['usage_rate'] ?? 0)."%\n";
            }
            if (isset($coupons['discount_impact'])) {
                $output .= '- Impacto em desconto: R$ '.number_format($coupons['discount_impact'] ?? 0, 2, ',', '.')."\n";
            }
        } else {
            $output .= "- Dados de cupons não disponíveis\n";
        }

        return $output;
    }

    /**
     * V7: Formatar temas saturados como seção separada para o Critic.
     */
    private static function formatSaturatedThemes(array $previousSuggestions): string
    {
        if (empty($previousSuggestions)) {
            return 'Nenhuma sugestão anterior. Todos os temas são permitidos.';
        }

        // Use centralized theme keywords
        $themeKeywords = ThemeKeywords::all();
        $themeLabels = ThemeKeywords::labels();

        // Build keywords map with readable labels
        $keywords = [];
        foreach ($themeKeywords as $themeKey => $keywordList) {
            $label = $themeLabels[$themeKey] ?? ucfirst(str_replace('_', ' ', $themeKey));
            $keywords[$label] = $keywordList;
        }

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

        $saturated = array_filter($counts, fn ($c) => $c >= 3);
        if (empty($saturated)) {
            $used = array_filter($counts, fn ($c) => $c >= 1 && $c < 3);
            if (empty($used)) {
                return 'Nenhuma sugestão anterior com temas relevantes. Todos os temas são permitidos.';
            }

            return "Nenhum tema saturado (3+ ocorrências). Todos os temas são permitidos.\n\nTemas já usados (1-2x, preferir evitar): ".implode(', ', array_keys($used));
        }

        arsort($saturated);
        $output = "**TEMAS SATURADOS (3+ ocorrências - REJEITAR sugestões com estes temas):**\n";
        foreach ($saturated as $t => $c) {
            $output .= "- {$t} ({$c}x) — REJEITAR e substituir por tema novo\n";
        }

        $allowed = array_filter($counts, fn ($c) => $c >= 1 && $c < 3);
        if (! empty($allowed)) {
            arsort($allowed);
            $output .= "\n**Temas usados 1-2x (permitidos mas evitar se possível):**\n";
            foreach ($allowed as $t => $c) {
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
Revisar 12 sugestões. Aprovar, melhorar ou rejeitar. Garantir 12 finais (4-4-4).

## DECISÕES
- APROVAR: dado específico + ação clara + resultado com número
- MELHORAR: corrigir o que falta e aprovar
- REJEITAR: repetição ou impossível → criar substituta

## OUTPUT
JSON com array de 12 sugestões revisadas.

PORTUGUÊS BRASILEIRO
TEMPLATE;
    }
}
