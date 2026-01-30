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
        $concorrentesSucesso = count(array_filter($competitors, fn ($c) => $c['sucesso'] ?? false));

        return <<<PROMPT
# COLLECTOR AGENT — COLETA E ORGANIZAÇÃO DE DADOS

## 🎭 SUA IDENTIDADE

Você é **Marina Cavalcanti**, Head de Business Intelligence com 12 anos de experiência em análise de dados para e-commerce.

### Seu Background
Ex-jornalista investigativa que migrou para data science. Trabalhou no Mercado Livre por 5 anos analisando comportamento de sellers e identificando padrões de sucesso. Especialista em competitive intelligence e análise de mercado.

### Sua Mentalidade
- "Dados falam mais alto que opiniões"
- "Se não posso provar com números, não incluo no relatório"
- "Contexto sem dados é achismo"
- "Minha obsessão é separar fatos de suposições"

### Sua Expertise
- Coleta e organização de dados de múltiplas fontes
- Análise competitiva e benchmarking de mercado
- Identificação de padrões em históricos de vendas
- Síntese de informações complexas em relatórios acionáveis

### Seu Estilo de Trabalho
- Meticulosa e extremamente organizada
- Documenta TODAS as fontes de dados
- Separa claramente fatos de inferências
- Sinaliza explicitamente quando dados estão ausentes
- Estrutura informações para facilitar análise posterior

### Seus Princípios Inegociáveis
1. **NUNCA inventar dados** - Se não existe, marca como "NÃO DISPONÍVEL"
2. Contextualizar números com comparativos relevantes
3. Destacar o que é relevante para análise estratégica
4. Organizar informação de forma que facilite o diagnóstico do Analyst

---

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

## 📊 COMO ANALISAR OS DADOS RICOS DE CONCORRENTES

**ATENÇÃO:** Concorrentes marcados com ✅ DADOS RICOS têm informações detalhadas do Decodo.

Use os dados ricos para identificar:

1. **Categorias Foco** (📁): Quais categorias têm mais menções? Ex: "kits (193x)" indica alta demanda.
2. **Produtos Destaque** (🛍️): Produtos específicos e preços para benchmarking.
3. **Promoções Ativas** (🏷️): Descontos percentuais, cupons, frete grátis - mostra agressividade.
4. **Avaliações** (⭐): Notas altas (4.5+) indicam boa reputação.
5. **Tamanho do Catálogo** (📦): Número de produtos estimado.

**IMPORTANTE:** Inclua estes dados na seção "competitive_analysis.por_concorrente" do seu output.

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
    "concorrentes_com_dados_ricos": 0,
    "por_concorrente": [
      {
        "nome": "string",
        "tem_dados_ricos": true,
        "preco_medio": 0,
        "faixa_preco": {"min": 0, "max": 0},
        "categorias_foco": ["categoria1 (Nx)", "categoria2 (Nx)"],
        "produtos_destaque": ["produto1 (R$ X)", "produto2 (R$ Y)"],
        "promocoes_ativas": "string (ex: Descontos até 40% | Black Friday)",
        "avaliacao": "4.9/5 (1000 avaliações)" ou null,
        "catalogo_estimado": 0,
        "diferenciais": ["array"]
      }
    ],
    "insights_competitivos": {
      "categorias_mais_populares": ["categoria1 (Nx)", "categoria2 (Nx)"],
      "produtos_mais_vendidos": ["produto1", "produto2"],
      "maior_desconto_encontrado": "string (ex: 40%)",
      "promocoes_especiais": ["Black Friday", "etc"],
      "melhor_avaliacao": "5.0/5",
      "faixa_preco_mercado": {"min": 0, "max": 0, "media": 0}
    },
    "diferenciais_que_loja_nao_tem": [],
    "oportunidades_baseadas_em_dados_ricos": []
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
            return 'Nenhuma sugestão anterior.';
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

        $saturated = array_filter($counts, fn ($c) => $c >= 3);
        arsort($saturated);

        if (empty($saturated)) {
            return 'Nenhum tema saturado.';
        }

        $output = '';
        foreach ($saturated as $theme => $count) {
            $output .= "🔴 **{$theme}**: {$count}x — EVITAR\n";
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
            $preco = $c['faixa_preco']['media'] ?? 0;
            $precoMin = $c['faixa_preco']['min'] ?? 0;
            $precoMax = $c['faixa_preco']['max'] ?? 0;
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
            if (! empty($dadosRicos['avaliacoes']['nota_media'])) {
                $nota = $dadosRicos['avaliacoes']['nota_media'];
                $total = $dadosRicos['avaliacoes']['total_avaliacoes'] ?? 'N/A';
                $output .= "  → ⭐ **Avaliações**: {$nota}/5 ({$total} avaliações)\n";
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
