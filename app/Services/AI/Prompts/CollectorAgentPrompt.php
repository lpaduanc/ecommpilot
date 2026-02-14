<?php

namespace App\Services\AI\Prompts;

class CollectorAgentPrompt
{
    /**
     * COLLECTOR AGENT V5 - REFATORADO
     *
     * Mudanças:
     * - Removida persona fictícia
     * - Adicionados few-shot examples
     * - Prompt reduzido (~40%)
     * - Foco em dados estruturados para o pipeline
     */
    public static function get(array $context): string
    {
        $storeName = $context['store_name'] ?? 'Loja';
        $platformName = $context['platform_name'] ?? 'Nuvemshop';
        $niche = $context['niche'] ?? 'geral';
        $subcategory = $context['subcategory'] ?? 'geral';
        $storeStats = json_encode($context['store_stats'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $previousAnalyses = json_encode($context['previous_analyses'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $benchmarks = json_encode($context['benchmarks'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Processar sugestões anteriores
        $previousSuggestions = $context['previous_suggestions'] ?? [];
        $saturatedThemes = self::identifySaturatedThemes($previousSuggestions);
        $suggestionsByCategory = self::groupByCategory($previousSuggestions);
        $totalSuggestions = count($previousSuggestions);

        // Dados externos
        $externalData = $context['external_data'] ?? [];
        $trendsData = $externalData['dados_mercado']['google_trends'] ?? [];
        $marketData = $externalData['dados_mercado']['precos_mercado'] ?? [];
        $competitors = $externalData['concorrentes'] ?? [];

        $tendencia = $trendsData['tendencia'] ?? 'nao_disponivel';
        $interesseBusca = $trendsData['interesse_busca'] ?? 0;

        $faixaPreco = $marketData['faixa_preco'] ?? [];
        $precoMedioMercado = $faixaPreco['media'] ?? 0;
        $precoMinMercado = $faixaPreco['min'] ?? 0;
        $precoMaxMercado = $faixaPreco['max'] ?? 0;

        // Formatar concorrentes
        $concorrentesFormatados = self::formatCompetitors($competitors);
        $mediaPrecosConcorrentes = self::calculateAverageCompetitorPrice($competitors);
        $totalConcorrentes = count($competitors);
        $concorrentesSucesso = count(array_filter($competitors, fn ($c) => $c['sucesso'] ?? false));

        // Learning Context (V5 - feedback de análises anteriores)
        $learningContext = $context['learning_context'] ?? [];
        $learningSection = self::formatLearningContext($learningContext);

        // V6: Module config para análises especializadas
        $moduleConfig = $context['module_config'] ?? null;
        $focoModulo = '';
        if ($moduleConfig && $moduleConfig->isSpecialized) {
            $tipo = $moduleConfig->analysisType;
            $prioridade = $moduleConfig->collectorFocus['dados_prioridade'] ?? '';
            $metricas = implode(', ', $moduleConfig->collectorFocus['metricas_obrigatorias'] ?? []);
            $focoModulo = <<<FOCO

<foco_modulo>
Esta é uma análise especializada do tipo: {$tipo}
Priorize a coleta dos seguintes dados: {$prioridade}
Métricas obrigatórias para este tipo de análise: {$metricas}
Se alguma métrica obrigatória não estiver disponível, registre em data_quality.missing_data.
</foco_modulo>
FOCO;
        }

        // ProfileSynthesizer store profile
        $perfilLojaSection = '';
        if (! empty($context['store_profile'])) {
            $profileJson = json_encode($context['store_profile'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $perfilLojaSection = <<<SECTION
<perfil_loja>
{$profileJson}
</perfil_loja>

SECTION;
        }

        return <<<PROMPT
<agent name="collector" version="6">

<role>
Você é um Analista de Dados Sênior especializado em e-commerce brasileiro. Seu trabalho é extrair, organizar e sintetizar informações de múltiplas fontes para alimentar o próximo estágio do pipeline de análise.
</role>

<task>
Coletar, organizar e sintetizar dados da loja e mercado para o Analyst.
</task>

<rules priority="mandatory">
1. **NUNCA INVENTE DADOS** — Se não disponível, escreva "NÃO DISPONÍVEL"
2. **Números específicos** — Valores monetários com 2 decimais (R$ 142.50), percentuais com 1 decimal (4.2%), inteiros sem decimais (1247 pedidos)
3. **Separar fatos de inferências** — Dados vs interpretações
4. **Incluir sugestões proibidas** — Para o Strategist não repetir
5. **Marcar problemas recorrentes** — Se um problema apareceu em 3+ análises anteriores, marque `recorrente: true` em `alerts_for_analyst`
</rules>

<focus_indicators>
## INDICADORES PRIORITÁRIOS PARA COLETA

Ao analisar os dados da loja, priorize a coleta destes indicadores:
- **Estoque:** % produtos sem estoque, produtos ativos com estoque crítico, valor parado em estoque
- **Vendas:** Ticket médio vs benchmark, tendência de receita, taxa de cancelamento
- **Cupons:** Taxa de uso, impacto no faturamento, cupons inativos
- **Produtos:** Best-sellers com risco de ruptura, produtos sem vendas no período, concentração de vendas
- **Competitividade:** Preço vs concorrentes, diferenciais ausentes, oportunidades de mercado
- **Recorrência:** Quais problemas já foram identificados antes e quantas vezes
</focus_indicators>
{$focoModulo}

<anti_hallucination_rules>
## REGRAS ANTI-ALUCINAÇÃO

1. **Baseie todas as afirmações exclusivamente nos dados fornecidos** em <dados_loja>, <dados_mercado> e <concorrentes>. Quando não houver dados suficientes para uma conclusão, escreva explicitamente: "dados insuficientes para esta análise".
2. **Separe fatos de interpretações:** Fatos vêm diretamente dos dados fornecidos. Interpretações são inferências suas — identifique-as como tal.
3. **Quando citar números,** eles devem vir diretamente dos dados fornecidos. Identifique a origem (ex: "conforme dados de pedidos", "segundo benchmarks do setor").
4. **Se um dado não estiver disponível,** liste-o em `data_not_available` — use "NÃO DISPONÍVEL" em vez de estimar ou inventar.
5. **Fique à vontade para dizer que não tem informação suficiente** para qualquer seção do JSON de saída.
</anti_hallucination_rules>

<examples>
## FEW-SHOT: EXEMPLOS DE COLETA

### EXEMPLO 1 — Resumo histórico bem escrito

```json
{
  "historical_summary": [
    "Loja opera há 18 meses com 1.247 pedidos totais",
    "Ticket médio atual R$ 142, 8% abaixo do benchmark (R$ 154)",
    "Taxa de cancelamento 4.2%, dentro do aceitável (<5%)",
    "Última análise há 32 dias identificou problema de estoque",
    "3 sugestões implementadas com sucesso (email, frete, kits)"
  ]
}
```

### EXEMPLO 2 — Análise competitiva com dados ricos

```json
{
  "por_concorrente": [
    {
      "nome": "Beleza Natural",
      "tem_dados_ricos": true,
      "preco_medio": 89.90,
      "categorias_foco": ["kits (193x)", "hidratação (87x)", "cachos (54x)"],
      "produtos_destaque": ["Kit Cronograma (R$ 149)", "Máscara 1kg (R$ 79)"],
      "promocoes_ativas": "Descontos até 40% | Frete grátis acima R$ 99",
      "avaliacao": "4.8/5 (2.340 avaliações)",
      "diferenciais": ["cashback 5%", "clube de assinatura", "amostras grátis"]
    }
  ],
  "diferenciais_que_loja_nao_tem": ["cashback", "clube de assinatura"],
  "oportunidades": ["Implementar programa de fidelidade similar ao concorrente"]
}
```

### EXEMPLO 3 — Alerta bem estruturado (com rastreamento de recorrência)

```json
{
  "alerts_for_analyst": {
    "critical": [
      {"descricao": "42% dos SKUs ativos estão sem estoque (84 de 200)", "dados": "84/200 ativos", "recorrente": true, "vezes_reportado": 4}
    ],
    "warnings": [
      {"descricao": "Ticket médio caiu 12% nos últimos 30 dias", "dados": "R$ 142 → R$ 125", "recorrente": false, "vezes_reportado": 1},
      {"descricao": "3 dos 10 produtos mais vendidos estão esgotados", "dados": "Kit Cronograma, Máscara 1kg, Sérum", "recorrente": true, "vezes_reportado": 3}
    ],
    "info": [
      {"descricao": "Tendência de busca do nicho está em alta (+15%)", "dados": "Google Trends", "recorrente": false, "vezes_reportado": 0},
      {"descricao": "Concorrente principal lançou promoção de 40%", "dados": "Beleza Natural", "recorrente": false, "vezes_reportado": 0}
    ]
  }
}
```
**Nota:** Alertas com `recorrente: true` indicam que o Analyst deve propor ABORDAGENS DIFERENTES para este problema, pois as anteriores não funcionaram.
</examples>

<output_format>
```json
{
  "store_identification": {
    "name": "{$storeName}",
    "niche": "{$niche}",
    "subcategory": "{$subcategory}",
    "platform": "{$platformName}",
    "operation_time_months": 0,
    "total_orders": 0,
    "total_revenue": 0
  },
  "historical_summary": ["fato1 com número", "fato2 com número", "fato3", "fato4", "fato5", "fato6 (opcional)", "fato7 (opcional)"],
  "success_patterns": [
    {"title": "título", "category": "categoria", "what_worked": "o que funcionou"}
  ],
  "suggestions_to_avoid": [
    {"title": "título", "category": "categoria", "why_failed": "motivo"}
  ],
  "prohibited_suggestions": {
    "total": {$totalSuggestions},
    "saturated_themes": [],
    "by_category": {},
    "all_titles": []
  },
  "relevant_benchmarks": {},
  "market_positioning": {
    "ticket_loja": 0,
    "vs_benchmark": {"valor": 0, "diferenca": "+X% ou -X%"},
    "vs_mercado": {"valor": 0, "diferenca": "+X% ou -X%"},
    "vs_concorrentes": {"valor": 0, "diferenca": "+X% ou -X%"}
  },
  "competitive_analysis": {
    "total_concorrentes": {$totalConcorrentes},
    "por_concorrente": [],
    "insights": {
      "categorias_populares": [],
      "maior_desconto": "X%",
      "faixa_preco": {"min": 0, "max": 0, "media": 0}
    },
    "diferenciais_que_loja_nao_tem": [],
    "oportunidades": []
  },
  "identified_gaps": [],
  "data_not_available": [],
  "market_context": {
    "tendencia": "{$tendencia}",
    "interesse": {$interesseBusca}
  },
  "alerts_for_analyst": {
    "critical": [
      {"descricao": "string", "dados": "evidência numérica", "recorrente": false, "vezes_reportado": 0}
    ],
    "warnings": [
      {"descricao": "string", "dados": "evidência numérica", "recorrente": false, "vezes_reportado": 0}
    ],
    "info": [
      {"descricao": "string", "dados": "evidência numérica", "recorrente": false, "vezes_reportado": 0}
    ]
  },
  "data_quality": {
    "completeness": "alta|media|baixa",
    "missing_data": ["lista de dados que não foram encontrados — deve corresponder a data_not_available"],
    "confidence_notes": "Observações sobre a qualidade/confiabilidade dos dados coletados"
  }
}
```
</output_format>

<validation_checklist>
- [ ] Resumo histórico com 5-7 fatos e números?
- [ ] Sugestões anteriores listadas para evitar repetição?
- [ ] Posicionamento com comparação tripla (benchmark, mercado, concorrentes)?
- [ ] Alertas categorizados (critical, warnings, info)?
- [ ] Dados não disponíveis listados?
</validation_checklist>

<instruction_summary>
**RESPONDA APENAS COM O JSON. PORTUGUÊS BRASILEIRO.**
</instruction_summary>

<data>

{$perfilLojaSection}<store_info>
| Campo | Valor |
|-------|-------|
| Nome | {$storeName} |
| Plataforma | {$platformName} |
| Nicho | {$niche} / {$subcategory} |
</store_info>

<store_stats>
```json
{$storeStats}
```
</store_stats>

<previous_analyses>
```json
{$previousAnalyses}
```
</previous_analyses>

<benchmarks category="{$subcategory}">
```json
{$benchmarks}
```
</benchmarks>

<previous_suggestions total="{$totalSuggestions}">
### Temas Saturados:
{$saturatedThemes}

### Por Categoria:
{$suggestionsByCategory}
</previous_suggestions>

<market_data>
**Google Trends:** Tendência {$tendencia}, interesse {$interesseBusca}/100

**Preços:** R$ {$precoMinMercado} - R$ {$precoMaxMercado} (média R$ {$precoMedioMercado})
</market_data>

<competitors analyzed="{$concorrentesSucesso}/{$totalConcorrentes}">
{$concorrentesFormatados}

**Média concorrentes:** R$ {$mediaPrecosConcorrentes}
</competitors>

<learning_context>
{$learningSection}
</learning_context>

</data>

</agent>
PROMPT;
    }

    private static function identifySaturatedThemes(array $suggestions): string
    {
        if (empty($suggestions)) {
            return 'Nenhuma sugestão anterior.';
        }

        $keywords = [
            'Quiz/Personalização' => ['quiz', 'questionário', 'personalizado', 'personalização'],
            'Frete Grátis' => ['frete grátis', 'frete gratuito', 'frete gratis'],
            'Fidelidade' => ['fidelidade', 'pontos', 'cashback', 'recompensa', 'loyalty'],
            'Kits/Combos' => ['kit', 'combo', 'bundle', 'pack', 'cronograma'],
            'Estoque' => ['estoque', 'avise-me', 'reposição', 'inventário'],
            'Email' => ['email', 'newsletter', 'automação', 'e-mail'],
            'Vídeos' => ['vídeo', 'video', 'tutorial', 'youtube', 'reels'],
            'Assinatura' => ['assinatura', 'recorrência', 'subscription'],
            'Cupom' => ['cupom', 'desconto', 'voucher', 'código'],
            'Checkout' => ['checkout', 'finalização', 'carrinho', 'abandono'],
            'Reviews' => ['review', 'avaliação', 'avaliações', 'depoimento'],
            'WhatsApp' => ['whatsapp', 'zap', 'mensagem'],
            'Influenciador' => ['influenciador', 'influencer', 'parceria', 'afiliado'],
            'Ticket' => ['ticket', 'ticket médio', 'aov'],
            'Reativação' => ['reativação', 'reativar', 'inativos', 'dormentes'],
            'Cross-sell' => ['cross-sell', 'cross sell', 'upsell', 'up-sell', 'venda cruzada'],
        ];

        $counts = [];
        foreach ($suggestions as $s) {
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
        arsort($saturated);

        if (empty($saturated)) {
            return 'Nenhum tema saturado.';
        }

        $output = '';
        foreach ($saturated as $theme => $count) {
            $output .= "🔴 **{$theme}**: {$count}x — PROIBIDO (resultará em REJEIÇÃO pelo Critic)\n";
        }

        return $output;
    }

    private static function groupByCategory(array $suggestions): string
    {
        if (empty($suggestions)) {
            return 'Nenhuma sugestão anterior.';
        }

        $grouped = [];
        foreach ($suggestions as $s) {
            $cat = $s['category'] ?? 'outros';
            $title = $s['title'] ?? 'Sem título';
            if (! isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $title;
        }

        $output = '';
        foreach ($grouped as $cat => $titles) {
            $unique = array_unique($titles);
            $output .= "\n**{$cat}** (".count($unique)."):\n";
            foreach ($unique as $t) {
                $count = array_count_values($titles)[$t];
                $m = $count >= 3 ? '🔴' : ($count >= 2 ? '⚠️' : '•');
                $output .= "{$m} {$t}".($count > 1 ? " ({$count}x)" : '')."\n";
            }
        }

        return $output;
    }

    private static function formatCompetitors(array $competitors): string
    {
        $output = '';
        $competitorsWithRichData = 0;

        foreach ($competitors as $c) {
            if (! ($c['sucesso'] ?? false)) {
                continue;
            }
            $nome = $c['nome'] ?? 'Concorrente';
            $faixa = $c['faixa_preco'] ?? [];
            $preco = $faixa['media'] ?? 0;
            $precoMin = $faixa['min'] ?? 0;
            $precoMax = $faixa['max'] ?? 0;
            $difs = implode(', ', $c['diferenciais'] ?? []) ?: 'nenhum';

            // Check if has rich data
            $dadosRicos = $c['dados_ricos'] ?? [];
            $hasRichData = ! empty($dadosRicos['categorias']) ||
                           ! empty($dadosRicos['promocoes']) ||
                           ! empty($dadosRicos['produtos']);

            if ($hasRichData) {
                $competitorsWithRichData++;
            }

            $richDataBadge = $hasRichData ? '✅ DADOS RICOS' : '⚠️';

            $output .= "- **{$nome}** {$richDataBadge}: R$ {$preco} (min: R$ {$precoMin}, max: R$ {$precoMax}) | Diferenciais: {$difs}\n";

            // Categorias populares (DADOS RICOS)
            if (! empty($dadosRicos['categorias'])) {
                $topCats = array_slice($dadosRicos['categorias'], 0, 5);
                $catsStr = implode(', ', array_map(fn ($cat) => "{$cat['nome']} ({$cat['mencoes']}x)", $topCats));
                $output .= "  → 📁 **Categorias Foco**: {$catsStr}\n";
            }

            // Produtos específicos (DADOS RICOS)
            if (! empty($dadosRicos['produtos'])) {
                $topProds = array_slice($dadosRicos['produtos'], 0, 3);
                $prodsStr = implode(', ', array_map(fn ($p) => "{$p['nome']} (R$ {$p['preco']})", $topProds));
                $output .= "  → 🛍️ **Produtos Destaque**: {$prodsStr}\n";
            }

            // Promoções ativas (DADOS RICOS)
            if (! empty($dadosRicos['promocoes'])) {
                $promos = self::summarizePromotions($dadosRicos['promocoes']);
                $output .= "  → 🏷️ **Promoções**: {$promos}\n";
            }

            // Avaliações (DADOS RICOS)
            $avaliacoes = $dadosRicos['avaliacoes'] ?? [];
            $notaMedia = $avaliacoes['nota_media'] ?? null;
            if ($notaMedia !== null && $notaMedia > 0) {
                $total = $avaliacoes['total_avaliacoes'] ?? 'N/A';
                $output .= "  → ⭐ **Avaliações**: {$notaMedia}/5 ({$total} avaliações)\n";
            }

            // Quantidade de produtos
            $produtosEst = $c['produtos_estimados'] ?? 0;
            if ($produtosEst > 0) {
                $output .= "  → 📦 **Catálogo**: ~{$produtosEst} produtos\n";
            }
        }

        $totalCompetitors = count(array_filter($competitors, fn ($c) => $c['sucesso'] ?? false));
        if ($competitorsWithRichData > 0) {
            $output = "**{$competitorsWithRichData}/{$totalCompetitors} concorrentes com DADOS RICOS (Decodo)**\n\n".$output;
        }

        return $output ?: 'Nenhum concorrente analisado.';
    }

    private static function summarizePromotions(array $promocoes): string
    {
        $descontos = [];
        $especiais = [];

        foreach ($promocoes as $promo) {
            if (($promo['tipo'] ?? '') === 'desconto_percentual') {
                $descontos[] = $promo['valor'] ?? '';
            } elseif (($promo['tipo'] ?? '') === 'promocao_especial') {
                $especiais[] = $promo['descricao'] ?? '';
            }
        }

        $parts = [];
        if (! empty($descontos)) {
            $descontosUnicos = array_unique($descontos);
            rsort($descontosUnicos); // Maiores primeiro
            $parts[] = 'Descontos até '.$descontosUnicos[0];
        }
        if (! empty($especiais)) {
            $parts[] = implode(', ', array_unique($especiais));
        }

        return implode(' | ', $parts) ?: 'Nenhuma identificada';
    }

    private static function calculateAverageCompetitorPrice(array $competitors): float
    {
        $prices = [];
        foreach ($competitors as $c) {
            if (($c['sucesso'] ?? false)) {
                $faixa = $c['faixa_preco'] ?? [];
                $media = $faixa['media'] ?? null;
                if ($media !== null && $media > 0) {
                    $prices[] = $media;
                }
            }
        }

        return count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0;
    }

    /**
     * Formata o contexto de aprendizado para o Collector (Mudança 13).
     */
    private static function formatLearningContext(array $learningContext): string
    {
        if (empty($learningContext)) {
            return 'Nenhum histórico de feedback disponível. Esta é uma das primeiras análises desta loja.';
        }

        $output = '';

        // Taxa de sucesso por categoria
        $categoryRates = $learningContext['category_success_rates'] ?? [];
        if (! empty($categoryRates)) {
            $output .= "### Taxas de Sucesso por Categoria\n\n";
            $output .= "| Categoria | Taxa de Sucesso | Total Implementadas |\n";
            $output .= "|-----------|-----------------|---------------------|\n";
            foreach ($categoryRates as $category => $stats) {
                $rate = $stats['success_rate'] ?? 0;
                $total = $stats['total_implemented'] ?? 0;
                $emoji = $rate >= 70 ? '✅' : ($rate >= 40 ? '⚠️' : '❌');
                $output .= "| {$emoji} {$category} | {$rate}% | {$total} |\n";
            }
            $output .= "\n**INSIGHT:** Priorize categorias com >70% de sucesso para sugestões HIGH.\n\n";
        }

        // Casos de sucesso
        $successCases = $learningContext['success_cases'] ?? [];
        if (! empty($successCases)) {
            $output .= "### Casos de Sucesso Recentes\n\n";
            foreach ($successCases as $case) {
                $title = $case['title'] ?? 'Sem título';
                $category = $case['category'] ?? 'geral';
                $impact = $case['metrics_impact'] ?? null;
                $output .= "- ✅ **{$title}** ({$category})";
                if ($impact) {
                    $impactStr = is_array($impact) ? json_encode($impact) : $impact;
                    $output .= " → Impacto: {$impactStr}";
                }
                $output .= "\n";
            }
            $output .= "\n**INSIGHT:** Esses temas funcionam bem para este cliente. Considere variações.\n\n";
        }

        // Casos de falha
        $failureCases = $learningContext['failure_cases'] ?? [];
        if (! empty($failureCases)) {
            $output .= "### Padrões de Falha (EVITAR)\n\n";
            foreach ($failureCases as $case) {
                $title = $case['title'] ?? 'Sem título';
                $category = $case['category'] ?? 'geral';
                $reason = $case['failure_reason'] ?? 'Não informado';
                $output .= "- ❌ **{$title}** ({$category}): {$reason}\n";
            }
            $output .= "\n**INSIGHT:** Evitar temas similares ou abordar de forma completamente diferente.\n\n";
        }

        // Categorias bloqueadas
        $blockedCategories = $learningContext['blocked_categories'] ?? [];
        if (! empty($blockedCategories)) {
            $output .= "### ⛔ CATEGORIAS BLOQUEADAS (3+ rejeições)\n\n";
            foreach ($blockedCategories as $category => $count) {
                $output .= "- 🚫 **{$category}** ({$count} rejeições consecutivas)\n";
            }
            $output .= "\n**REGRA CRÍTICA:** NÃO gerar sugestões nestas categorias.\n\n";
        }

        return $output ?: 'Histórico de feedback ainda em construção.';
    }

    private static function extractUniqueFeatures(array $competitors): string
    {
        $features = [];
        foreach ($competitors as $c) {
            if ($c['sucesso'] ?? false) {
                $features = array_merge($features, $c['diferenciais'] ?? []);
            }
        }

        return implode(', ', array_unique($features)) ?: 'nenhum';
    }

    public static function getTemplate(): string
    {
        return <<<'TEMPLATE'
# COLLECTOR — COLETA DE DADOS

## TAREFA
Coletar e organizar dados da loja e mercado para o Analyst.

## OUTPUT
JSON com: identificação, histórico, benchmarks, posicionamento, análise competitiva, alertas.

## REGRA
NUNCA INVENTE DADOS. Se não disponível, escreva "NÃO DISPONÍVEL".

PORTUGUÊS BRASILEIRO
TEMPLATE;
    }
}
