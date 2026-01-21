<?php

namespace App\Services\AI\Prompts;

class CollectorAgentPrompt
{
    /**
     * COLLECTOR AGENT V4 - COM LISTA DETALHADA DE SUGESTÕES ANTERIORES
     *
     * Melhorias incluídas:
     * - Seção dedicada "SUGESTÕES ANTERIORES - NÃO REPETIR"
     * - Lista de temas saturados com contagem
     * - Output inclui prohibited_suggestions formatada para Strategist
     */

    public static function get(array $context): string
    {
        $storeName = $context['store_name'] ?? 'Loja';
        $platform = $context['platform'] ?? 'nuvemshop';
        $platformName = $context['platform_name'] ?? 'Nuvemshop';
        $niche = $context['niche'] ?? 'geral';
        $subcategory = $context['subcategory'] ?? 'geral';
        $storeStats = json_encode($context['store_stats'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $previousAnalyses = json_encode($context['previous_analyses'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $benchmarks = json_encode($context['benchmarks'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Processar sugestões anteriores
        $previousSuggestions = $context['previous_suggestions'] ?? [];
        $formattedSuggestions = self::formatPreviousSuggestions($previousSuggestions);
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
        $trendsSucesso = $trendsData['sucesso'] ?? false;

        $precoMedioMercado = $marketData['faixa_preco']['media'] ?? 0;
        $precoMinMercado = $marketData['faixa_preco']['min'] ?? 0;
        $precoMaxMercado = $marketData['faixa_preco']['max'] ?? 0;
        $marketSucesso = $marketData['sucesso'] ?? false;
        $fonteMercado = $marketData['fonte'] ?? 'google_shopping';

        // Formatar concorrentes
        $concorrentesFormatados = self::formatCompetitors($competitors);
        $mediaPrecosConcorrentes = self::calculateAverageCompetitorPrice($competitors);
        $diferenciaisUnicos = self::extractUniqueFeatures($competitors);
        $totalConcorrentes = count($competitors);
        $concorrentesSucesso = count(array_filter($competitors, fn($c) => $c['sucesso'] ?? false));

        return <<<PROMPT
# COLLECTOR AGENT — COLETA E ORGANIZAÇÃO DE DADOS

## SEU PAPEL
Coletar, organizar e sintetizar TODOS os dados disponíveis sobre a loja e o mercado.

## REGRA FUNDAMENTAL
**NUNCA INVENTE DADOS.** Se não disponível, escreva "NÃO DISPONÍVEL".

---

## DADOS DA LOJA

| Campo | Valor |
|-------|-------|
| Nome | {$storeName} |
| Plataforma | {$platformName} |
| Nicho | {$niche} |
| Subcategoria | {$subcategory} |

### Estatísticas
```json
{$storeStats}
```

### Histórico de Análises
```json
{$previousAnalyses}
```

---

## 🚫 SUGESTÕES ANTERIORES - NÃO REPETIR

### Total: {$totalSuggestions} sugestões já dadas para esta loja

### Temas SATURADOS (3+ vezes):
{$saturatedThemes}

### Por Categoria:
{$suggestionsByCategory}

**IMPORTANTE:** Inclua esta lista no seu output para o Strategist usar.

---

### Benchmarks ({$subcategory})
```json
{$benchmarks}
```

---

## DADOS EXTERNOS DE MERCADO

### Google Trends
| Métrica | Valor |
|---------|-------|
| Sucesso | {$trendsSucesso} |
| Tendência | {$tendencia} |
| Interesse | {$interesseBusca}/100 |

### Preços de Mercado ({$fonteMercado})
| Métrica | Valor |
|---------|-------|
| Sucesso | {$marketSucesso} |
| Mínimo | R$ {$precoMinMercado} |
| Máximo | R$ {$precoMaxMercado} |
| Média | R$ {$precoMedioMercado} |

### Concorrentes ({$totalConcorrentes} informados, {$concorrentesSucesso} analisados)
{$concorrentesFormatados}

**Média concorrentes:** R$ {$mediaPrecosConcorrentes}
**Diferenciais:** {$diferenciaisUnicos}

---

## SUA TAREFA

Produza relatório JSON com:

1. **Identificação da Loja**
2. **Resumo Histórico** (5-7 fatos com números)
3. **Padrões de Sucesso** (sugestões completed + successful)
4. **Sugestões a Evitar** (failed ou ignored)
5. **Benchmarks Relevantes**
6. **Posicionamento de Mercado** (tripla comparação)
7. **Análise Competitiva Detalhada**
8. **Gaps Identificados**
9. **Dados Não Disponíveis**
10. **Alertas para o Analyst**

---

## FORMATO DE SAÍDA

```json
{
  "store_identification": {
    "name": "string",
    "niche": "string",
    "subcategory": "string",
    "platform": "string",
    "operation_time_months": 0,
    "total_orders": 0,
    "total_revenue": 0
  },
  "historical_summary": ["fato1", "fato2"],
  "success_patterns": [
    {"suggestion_title": "", "category": "", "what_worked": ""}
  ],
  "suggestions_to_avoid": [
    {"suggestion_title": "", "category": "", "why_failed": "", "status": "failed|ignored"}
  ],
  "prohibited_suggestions": {
    "total": {$totalSuggestions},
    "saturated_themes": ["tema1", "tema2"],
    "by_category": {},
    "all_titles": []
  },
  "relevant_benchmarks": {},
  "market_positioning": {
    "ticket_loja": 0,
    "vs_benchmark": {},
    "vs_mercado": {},
    "vs_concorrentes": {}
  },
  "competitive_analysis": {
    "total_concorrentes": {$totalConcorrentes},
    "por_concorrente": [],
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
    "critical": [],
    "warnings": [],
    "info": []
  }
}
```

---

PORTUGUÊS BRASILEIRO
PROMPT;
    }

    private static function formatPreviousSuggestions(array $suggestions): array
    {
        return [
            'total' => count($suggestions),
            'titles' => array_column($suggestions, 'title'),
        ];
    }

    private static function identifySaturatedThemes(array $suggestions): string
    {
        if (empty($suggestions)) {
            return "Nenhuma sugestão anterior.";
        }

        $keywords = [
            'Quiz/Personalização' => ['quiz', 'questionário', 'personalizado'],
            'Frete Grátis' => ['frete grátis', 'frete gratuito'],
            'Fidelidade' => ['fidelidade', 'pontos', 'cashback'],
            'Kits/Combos' => ['kit', 'combo', 'bundle', 'cronograma'],
            'Estoque' => ['estoque', 'avise-me', 'reposição'],
            'Email' => ['email', 'newsletter', 'automação'],
            'Vídeos' => ['vídeo', 'tutorial'],
            'Assinatura' => ['assinatura', 'recorrência'],
        ];

        $counts = [];
        foreach ($suggestions as $s) {
            $title = mb_strtolower($s['title'] ?? '');
            foreach ($keywords as $theme => $kws) {
                foreach ($kws as $kw) {
                    if (strpos($title, $kw) !== false) {
                        $counts[$theme] = ($counts[$theme] ?? 0) + 1;
                        break;
                    }
                }
            }
        }

        $saturated = array_filter($counts, fn($c) => $c >= 3);
        arsort($saturated);

        if (empty($saturated)) {
            return "Nenhum tema saturado.";
        }

        $output = "";
        foreach ($saturated as $theme => $count) {
            $output .= "🔴 **{$theme}**: {$count}x — EVITAR\n";
        }
        return $output;
    }

    private static function groupByCategory(array $suggestions): string
    {
        if (empty($suggestions)) {
            return "Nenhuma sugestão anterior.";
        }

        $grouped = [];
        foreach ($suggestions as $s) {
            $cat = $s['category'] ?? 'outros';
            $title = $s['title'] ?? 'Sem título';
            if (!isset($grouped[$cat])) $grouped[$cat] = [];
            $grouped[$cat][] = $title;
        }

        $output = "";
        foreach ($grouped as $cat => $titles) {
            $unique = array_unique($titles);
            $output .= "\n**{$cat}** (" . count($unique) . "):\n";
            foreach ($unique as $t) {
                $count = array_count_values($titles)[$t];
                $m = $count >= 3 ? "🔴" : ($count >= 2 ? "⚠️" : "•");
                $output .= "{$m} {$t}" . ($count > 1 ? " ({$count}x)" : "") . "\n";
            }
        }
        return $output;
    }

    private static function formatCompetitors(array $competitors): string
    {
        $output = "";
        foreach ($competitors as $c) {
            if (!($c['sucesso'] ?? false)) continue;
            $nome = $c['nome'] ?? 'Concorrente';
            $preco = $c['faixa_preco']['media'] ?? 0;
            $difs = implode(', ', $c['diferenciais'] ?? []) ?: 'nenhum';
            $output .= "- **{$nome}**: R$ {$preco} | Diferenciais: {$difs}\n";
        }
        return $output ?: 'Nenhum concorrente analisado.';
    }

    private static function calculateAverageCompetitorPrice(array $competitors): float
    {
        $prices = [];
        foreach ($competitors as $c) {
            if (($c['sucesso'] ?? false) && isset($c['faixa_preco']['media'])) {
                $prices[] = $c['faixa_preco']['media'];
            }
        }
        return count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0;
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
# COLLECTOR AGENT

## PAPEL
Coletar e organizar dados sobre a loja e mercado.

## SAÍDA
JSON com: identificação, histórico, benchmarks, posicionamento, análise competitiva, gaps, alertas.

## REGRA
NUNCA INVENTE DADOS.

PORTUGUÊS BRASILEIRO
TEMPLATE;
    }
}
