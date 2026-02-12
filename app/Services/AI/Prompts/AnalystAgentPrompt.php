<?php

namespace App\Services\AI\Prompts;

class AnalystAgentPrompt
{
    /**
     * ANALYST AGENT V5 - REFATORADO
     *
     * Mudanças:
     * - Removida persona fictícia
     * - Adicionados few-shot examples de diagnóstico
     * - Prompt reduzido (~50%)
     * - Formato de saída simplificado
     * - Mantido: Health Score, Override, Sazonalidade, Comparação tripla
     */
    public static function get(array $data): string
    {
        $storeName = $data['store_name'] ?? 'Loja';
        $niche = $data['niche'] ?? 'geral';
        $subcategory = $data['subcategory'] ?? 'geral';
        $periodDays = $data['period_days'] ?? 15;
        $ticketMedio = $data['ticket_medio'] ?? 0;
        $pedidosMes = $data['pedidos_mes'] ?? 0;
        $faturamentoMes = $ticketMedio * $pedidosMes;

        // Dados operacionais
        $orders = json_encode($data['orders_summary'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $products = json_encode($data['products_summary'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $inventory = json_encode($data['inventory_summary'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $coupons = json_encode($data['coupons_summary'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Histórico para detectar anomalias
        $historicalData = json_encode($data['historical_metrics'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Dados externos
        $externalData = $data['external_data'] ?? [];
        $competitors = $externalData['concorrentes'] ?? [];
        $marketData = $externalData['dados_mercado'] ?? [];

        // Processar concorrentes
        $competitorSummary = self::summarizeCompetitors($competitors);
        $marketSummary = self::summarizeMarket($marketData, $ticketMedio);

        // Gerar comparativo loja vs concorrentes (Mudança 11)
        $categoriasFoco = $data['products_summary']['top_categories'] ?? [];
        $promocoesAtivas = $data['coupons_summary']['registered_active'] ?? 0;
        $comparativoLojaConcorrentes = self::generateComparativo($ticketMedio, $pedidosMes, $categoriasFoco, $promocoesAtivas, $competitors);

        // Sazonalidade
        $mes = (int) date('n');
        $sazonalidade = self::getSeasonalityContext($mes);

        // Benchmarks do RAG
        $benchmarks = $data['benchmarks'] ?? [];
        $structuredBenchmarks = $data['structured_benchmarks'] ?? [];
        $benchmarkSummary = self::summarizeBenchmarks($structuredBenchmarks, $benchmarks, $ticketMedio);

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

        // V6: Sugestões anteriores para anti-repetição de diagnósticos
        $previousSuggestions = $data['previous_suggestions'] ?? [];
        $allSuggestions = isset($previousSuggestions['all']) ? $previousSuggestions['all'] : $previousSuggestions;
        $previousSuggestionsSection = self::formatPreviousSuggestionsForAnalyst($allSuggestions);

        // V6: Module config para análises especializadas
        $moduleConfig = $data['module_config'] ?? null;
        $keywordsModulo = '';
        $exemplosModulo = '';
        if ($moduleConfig && $moduleConfig->isSpecialized) {
            $tipo = $moduleConfig->analysisType;
            $keywords = $moduleConfig->analystKeywords['keywords'] ?? '';
            $foco = $moduleConfig->analystKeywords['foco_analise'] ?? '';
            $keywordsModulo = "\n\nKeywords adicionais para análise {$tipo}:\n{$keywords}\n\nDirecionamento específico:\n{$foco}";
            $exemplosModulo = "\n\nExemplos adicionais específicos para análise {$tipo}:\nAplique o mesmo padrão dos exemplos acima, mas com foco em métricas e indicadores de {$tipo}.";
        }

        return <<<PROMPT
<agent name="analyst" version="7">

<task>
Analisar os dados da loja e produzir um diagnóstico estruturado com:
1. Health Score (0-100)
2. Alertas priorizados
3. 5 oportunidades com potencial de receita
4. Briefing para o Strategist com 5 problemas (causas raiz)
</task>

<rules priority="mandatory">
1. **Health Score:** Calcular baseado nos 5 componentes. Aplicar OVERRIDE se situação crítica.
2. **Alertas:** Gere apenas alertas baseados em problemas reais comprovados com dados numéricos de <operational_data> (ou seja, evite criar alertas sem evidência nos dados). Máximo 5 alertas, priorizados por severidade.
3. **Oportunidades:** Gerar exatamente 5 oportunidades, cada uma com potencial específico em R$.
4. **Comparação tripla:** Sempre comparar ticket da loja vs benchmark vs concorrentes.
5. **Sazonalidade:** Consulte <seasonality> antes de classificar variações como anomalias.
6. **Classificação Health Score:** critico (0-25), atencao (26-50), saudavel (51-75), excelente (76-100).

7. **Evitar repetição entre análises:** Consulte <previous_suggestions>.
   Se os problemas que você identificaria já foram sugeridos 3+ vezes em
   análises anteriores, APROFUNDE a análise para encontrar CAUSAS RAIZ
   de segundo nível que ainda não foram abordadas.
   Exemplo: Se "estoque zerado" já foi reportado 15 vezes, não reporte
   "estoque zerado" novamente. Em vez disso, investigue POR QUE o estoque
   continua zerado (falha na previsão? fornecedor? capital? decisão
   estratégica de descontinuar?) e reporte ESSA causa raiz.

8. **Identificação de causa raiz:** Não liste apenas sintomas no briefing_strategist.
   - RUIM: "Estoque está baixo" (isso é um sintoma)
   - BOM: "55% dos produtos ativos estão sem estoque, concentrados em kits de alto valor (R$200+). Isso sugere falha na previsão de demanda para produtos compostos."
   Os 5 problemas do briefing devem ser CAUSAS RAIZ, não sintomas, e DIFERENTES entre si.
</rules>

<health_score_calculation>
| Componente | Peso | Como calcular |
|------------|------|---------------|
| Ticket vs Benchmark | 25 pts | ≥100% = 25, 80-99% = 20, 60-79% = 15, <60% = 10 |
| Estoque disponível | 25 pts | ≤10% zerado = 25, 11-20% = 20, 21-35% = 15, >35% = 10 |
| Taxa cancelamento | 15 pts | ≤3% = 15, 4-7% = 12, 8-12% = 8, >12% = 4 |
| Saúde de cupons | 15 pts | uso <50% E impacto <15% = 15, senão proporcional |
| Tendência vendas | 20 pts | crescendo = 20, estável = 15, queda leve = 10, queda forte = 5 |

### OVERRIDE (aplicar após calcular — gradual)

**PENALIZAÇÃO GRADUAL (ao invés de cortes abruptos):**

Estoque zerado:
- 20-30%: penalizar -10 pontos do score calculado
- 30-40%: penalizar -20 pontos (mínimo 30)
- 40-50%: penalizar -30 pontos (mínimo 20)
- >50%: forçar máximo 15 pontos (crítico)

Taxa de cancelamento:
- 8-12%: penalizar -10 pontos
- 12-18%: penalizar -20 pontos (mínimo 25)
- >18%: forçar máximo 15 pontos (crítico)

Queda de vendas vs histórico:
- 20-30%: penalizar -5 pontos
- 30-45%: penalizar -15 pontos (mínimo 25)
- >45%: forçar máximo 20 pontos (crítico)

Dependência de cupons:
- 60-75% das vendas com cupom: penalizar -5 pontos
- 75-90%: penalizar -15 pontos (mínimo 30)
- >90%: penalizar -25 pontos (mínimo 20)

**IMPORTANTE:** Aplique TODAS as penalizações acumuladas, mas nunca abaixo do mínimo mais restritivo.
**Exemplo:** Score calculado = 65, estoque zerado 35% (-20), cancelamento 11% (-10) → 65 - 20 - 10 = 35, classificação: atenção.
</health_score_calculation>

<reasoning_instructions>
ANTES de gerar o diagnóstico, preencha o campo "reasoning" no JSON com:
1. Avaliação da qualidade e completude dos dados recebidos
2. Métricas-chave identificadas com classificação (bom/ruim/neutro)
3. Anomalias detectadas com thresholds usados
4. Detalhamento do cálculo do Health Score (componente por componente + overrides)

Este raciocínio guiará a geração do diagnóstico completo.
</reasoning_instructions>

<keywords_foco>
Organize sua análise priorizando os seguintes aspectos quando houver dados disponíveis:
conversão, abandono de carrinho, ticket médio, margem, CAC, funil de vendas,
tráfego por canal, taxa de recompra, sazonalidade, performance mobile vs desktop{$keywordsModulo}
</keywords_foco>

<anti_hallucination_rules>
1. **Baseie todas as afirmações exclusivamente nos dados fornecidos** em <operational_data>, <historical> e <benchmarks>. Quando não houver dados suficientes para uma conclusão, escreva explicitamente: "dados insuficientes para esta análise".
2. **Separe fatos de interpretações:** Fatos vêm diretamente dos dados fornecidos. Interpretações são inferências suas — identifique-as como tal.
3. **Quando citar números,** eles devem vir diretamente dos dados fornecidos. Identifique a origem (ex: "conforme dados de pedidos", "segundo benchmarks do setor").
4. **Se um dado não estiver disponível,** registre a limitação — use campos como `dados_insuficientes` para listar áreas sem dados.
5. **Fique à vontade para dizer que não tem informação suficiente** para qualquer seção do JSON de saída.
</anti_hallucination_rules>

<classification_format>
Para cada alerta e oportunidade que gerar, inclua internamente a classificação:
- **fonte:** "dado_direto" (extraído dos dados) | "inferencia" (sua interpretação dos dados) | "benchmark_geral" (comparação com padrões do setor)
- **confianca:** "alta" (dado direto verificável) | "media" (inferência lógica) | "baixa" (estimativa ou benchmark genérico)

Exemplos de classificação:
- "42% dos SKUs sem estoque" → fonte: dado_direto, confianca: alta
- "Isso indica perda estimada de R$ 4.200/mês" → fonte: inferencia, confianca: media
- "Acima da média do setor de moda (estimada em 65%)" → fonte: benchmark_geral, confianca: media

**REGRA:** Alertas com confianca "baixa" devem ser classificados como severidade máxima "monitorar" (nunca "critico"). Oportunidades com confianca "baixa" devem ter potencial apresentado como faixa (ex: "R$ 1.000-3.000/mês") ao invés de número exato.
</classification_format>

<examples>

### EXEMPLO 1 — Alerta crítico bem escrito

```json
{
  "tipo": "estoque",
  "severidade": "critico",
  "titulo": "42% dos produtos ativos estão sem estoque",
  "dados": "84 de 200 SKUs com estoque = 0. Inclui 3 dos 10 mais vendidos.",
  "impacto": "Perda estimada de R$ 4.200/mês (baseado no histórico desses SKUs)",
  "acao": "Repor estoque dos 3 top sellers em até 7 dias"
}
```

### EXEMPLO 2 — Oportunidade bem escrita

```json
{
  "tipo": "reativacao_clientes",
  "titulo": "Reativar 180 clientes inativos há 90+ dias",
  "dados": "180 clientes compraram 2+ vezes mas estão inativos há 90 dias. Ticket médio histórico: R$ 120.",
  "potencial": "Se 15% voltarem = 27 pedidos × R$ 120 = R$ 3.240/mês",
  "acao": "Campanha de email com cupom exclusivo 10% para retorno"
}
```

### EXEMPLO 3 — Health Score com override

```json
{
  "score_calculado": 68,
  "override_aplicado": true,
  "motivo_override": "Estoque zerado em 47% dos SKUs ativos",
  "score_final": 25,
  "classificacao": "critico"
}
```

### EXEMPLO 4 — Análise BOM vs RUIM

**BOM:**
"A taxa de conversão da loja é 1.2%, calculada a partir dos dados fornecidos (145 vendas / 12.083 visitantes). Comparando com o benchmark informado para o nicho de moda feminina (~2.5%), a loja opera 52% abaixo do esperado. O principal gargalo identificado nos dados é a página de checkout, onde 73% dos carrinhos são abandonados."
- **Por que é bom:** usa dados reais da loja, faz cálculo verificável, compara com benchmark identificando a fonte, aponta gargalo específico.

**RUIM:**
"A loja tem uma conversão baixa e precisa melhorar. O mercado de e-commerce está em crescimento e a loja deveria aproveitar melhor as oportunidades disponíveis."
- **Por que é ruim:** não cita números, não referencia dados, faz afirmação genérica sobre "o mercado", não aponta causa específica.

Siga o padrão do exemplo bom. Evite o padrão do exemplo ruim.{$exemplosModulo}
</examples>

<output_format>
Retorne APENAS o JSON abaixo:

```json
{
  "reasoning": {
    "data_quality": "Avaliação da qualidade e completude dos dados recebidos",
    "key_metrics": ["métrica 1: valor (bom/ruim/neutro)", "métrica 2: valor"],
    "anomalies_detected": ["anomalia 1 com threshold", "anomalia 2"],
    "score_calculation": "Componente1: X pts + Componente2: Y pts + ... = Total. Override: -Z pts. Final: W"
  },

  "resumo_executivo": "2-3 frases: saúde geral, problema principal, oportunidade principal",

  "health_score": {
    "score_calculado": 0,
    "componentes": {
      "ticket_vs_benchmark": {"pontos": 0, "detalhe": "X% do benchmark"},
      "estoque_disponivel": {"pontos": 0, "detalhe": "X% zerado"},
      "taxa_cancelamento": {"pontos": 0, "detalhe": "X%"},
      "saude_cupons": {"pontos": 0, "detalhe": "X% uso, Y% impacto"},
      "tendencia_vendas": {"pontos": 0, "detalhe": "crescendo|estável|queda"}
    },
    "override_aplicado": false,
    "motivo_override": null,
    "score_final": 0,
    "classificacao": "critico|atencao|saudavel|excelente"
  },

  "alertas": [
    {
      "severidade": "critico|atencao|monitorar",
      "tipo": "estoque|cancelamento|pricing|cupons|vendas",
      "titulo": "Descrição curta do problema",
      "dados": "Números específicos que comprovam",
      "impacto": "R$ X/mês ou X% de perda",
      "causa_raiz": "Análise de POR QUE isso está acontecendo, não apenas O QUE",
      "acao": "O que fazer",
      "fonte": "dado_direto|inferencia|benchmark_geral",
      "confianca": "alta|media|baixa"
    }
  ],

  "oportunidades": [
    {
      "tipo": "reativacao|upsell|estoque|pricing|conversao",
      "titulo": "Descrição da oportunidade",
      "dados": "Números que embasam",
      "potencial": "R$ X/mês",
      "acao": "Como capturar",
      "fonte": "dado_direto|inferencia|benchmark_geral",
      "confianca": "alta|media|baixa",
      "ja_sugerido_antes": false
    }
  ],

  "posicionamento": {
    "ticket_loja": 0,
    "vs_benchmark": {"valor": 0, "diferenca": "+X% ou -X%"},
    "vs_mercado": {"valor": 0, "diferenca": "+X% ou -X%"},
    "vs_concorrentes": {"valor": 0, "diferenca": "+X% ou -X%"},
    "interpretacao": "Loja está acima/abaixo/dentro do mercado porque..."
  },

  "anomalias": [
    {
      "metrica": "nome",
      "atual": 0,
      "historico": 0,
      "variacao": "+X% ou -X%",
      "tipo": "positiva|negativa",
      "explicacao_sazonal": "É ou não explicado pela sazonalidade"
    }
  ],

  "comparativo_concorrentes": {
    "ticket_medio": {
      "loja": 0,
      "concorrentes_media": 0,
      "gap": "+X% ou -X%",
      "insight": "Loja está acima/abaixo porque..."
    },
    "categorias": {
      "loja_foco": ["categoria1", "categoria2"],
      "concorrentes_foco": ["categoria1", "categoria2"],
      "oportunidade": "Categoria que concorrentes têm e loja não"
    },
    "promocoes": {
      "loja_tipos": ["tipo1", "tipo2"],
      "concorrentes_tipos": ["tipo1", "tipo2", "tipo3"],
      "gap": "Concorrentes usam X tipos de promoção que loja não usa"
    },
    "avaliacoes": {
      "melhor_concorrente": "Nome (nota/5)",
      "insight": "Se loja deve investir em programa de reviews"
    }
  },

  "briefing_strategist": {
    "problema_1": "Causa raiz #1 — detalhada, com dados, diferente dos problemas anteriores",
    "problema_2": "Causa raiz #2 — diferente do problema_1",
    "problema_3": "Causa raiz #3 — diferente dos dois anteriores",
    "problema_4": "Causa raiz #4 — diferente dos três anteriores",
    "problema_5": "Causa raiz #5 — diferente dos quatro anteriores",
    "oportunidade_principal": "Maior oportunidade não explorada",
    "restricoes": ["O que NÃO fazer ou limitações da loja"],
    "temas_ja_saturados": ["temas que já foram sugeridos 3+ vezes em análises anteriores"],
    "dados_chave": {
      "faturamento_mes": 0,
      "ticket_medio": 0,
      "taxa_conversao": 0,
      "estoque_zerado_percent": 0,
      "uso_cupons_percent": 0
    }
  },
  "dados_insuficientes": ["áreas onde não há dados suficientes para análise completa"]
}
```
</output_format>

<validation_checklist>
- [ ] Health Score calculado com os 5 componentes e classificação correta (critico 0-25, atencao 26-50, saudavel 51-75, excelente 76-100)?
- [ ] Penalizações graduais aplicadas conforme OVERRIDE (estoque, cancelamento, queda de vendas, dependência de cupons)?
- [ ] Máximo 5 alertas, cada um com dados específicos (números)?
- [ ] Exatamente 5 oportunidades, cada uma com potencial em R$?
- [ ] Posicionamento com comparação tripla (benchmark, mercado, concorrentes)?
- [ ] Comparativo de concorrentes preenchido (ticket, categorias, promoções, avaliações)?
- [ ] Briefing para Strategist com 5 problemas (causas raiz, não sintomas) e restrições?
- [ ] reasoning preenchido com data_quality, key_metrics, anomalies_detected e score_calculation?
</validation_checklist>

<data>

- **Nome:** {$storeName}
- **Nicho:** {$niche} / {$subcategory}
- **Ticket Médio:** R$ {$ticketMedio}
- **Pedidos/Mês:** {$pedidosMes}
- **Faturamento:** R$ {$faturamentoMes}/mês
- **Período:** {$periodDays} dias
</store_context>

<seasonality>
{$sazonalidade}
</seasonality>

<operational_data>
<orders>
```json
{$orders}
```
</orders>

<products>
```json
{$products}
```
</products>

<inventory>
```json
{$inventory}
```

**IMPORTANTE:** Os dados de estoque EXCLUEM produtos que são brindes/amostras grátis (identificados por termos como "brinde", "grátis", "amostra", "gift", etc.). Esses produtos não devem ser considerados em alertas de estoque baixo ou zerado.
</inventory>

<coupons>
```json
{$coupons}
```
</coupons>
</operational_data>

<historical>
```json
{$historicalData}
```

**Regra:** Variação > 20% vs média histórica = ANOMALIA
</historical>

<market_data>
{$marketSummary}
</market_data>

<competitors>
{$competitorSummary}
</competitors>

<competitor_comparison>
{$comparativoLojaConcorrentes}

**IMPORTANTE:** Use estes dados para preencher a seção `comparativo_concorrentes` no JSON de saída.
</competitor_comparison>

<benchmarks>
{$benchmarkSummary}
</benchmarks>

<previous_suggestions>
{$previousSuggestionsSection}
</previous_suggestions>

</data>

**RESPONDA APENAS COM O JSON. PORTUGUÊS BRASILEIRO.**

</agent>
PROMPT;
    }

    /**
     * Resumo dos dados de mercado
     */
    private static function summarizeMarket(array $marketData, float $ticketLoja): string
    {
        $trends = $marketData['google_trends'] ?? [];
        $precos = $marketData['precos_mercado'] ?? [];

        $tendencia = $trends['tendencia'] ?? 'não disponível';
        $interesse = $trends['interesse_busca'] ?? 0;

        $faixaPreco = $precos['faixa_preco'] ?? [];
        $precoMin = $faixaPreco['min'] ?? 0;
        $precoMax = $faixaPreco['max'] ?? 0;
        $precoMedio = $faixaPreco['media'] ?? 0;

        $posicao = 'não calculado';
        if ($precoMedio > 0 && $ticketLoja > 0) {
            $ratio = $ticketLoja / $precoMedio;
            $diff = round(($ratio - 1) * 100);
            $posicao = $diff >= 0 ? "+{$diff}% vs mercado" : "{$diff}% vs mercado";
        }

        return <<<MARKET
**Google Trends:** Tendência {$tendencia}, interesse {$interesse}/100

**Preços de Mercado:** R$ {$precoMin} - R$ {$precoMax} (média R$ {$precoMedio})

**Posição da Loja:** {$posicao}
MARKET;
    }

    /**
     * Resumo dos concorrentes (versão expandida com todos os dados)
     */
    private static function summarizeCompetitors(array $competitors): string
    {
        if (empty($competitors)) {
            return 'Nenhum dado de concorrente disponível.';
        }

        $output = '';
        $totalPreco = 0;
        $count = 0;
        $allCategories = [];
        $allPromos = [];

        foreach ($competitors as $c) {
            if (! ($c['sucesso'] ?? false)) {
                continue;
            }

            $nome = $c['nome'] ?? 'Concorrente';
            $faixa = $c['faixa_preco'] ?? [];
            $diferenciais = $c['diferenciais'] ?? [];
            $dadosRicos = $c['dados_ricos'] ?? [];

            $output .= "**{$nome}:**\n";

            // Preços
            if (! empty($faixa)) {
                $min = $faixa['min'] ?? 0;
                $max = $faixa['max'] ?? 0;
                $media = $faixa['media'] ?? 0;
                $output .= "- Preço: R$ {$min} - R$ {$max} (média R$ {$media})\n";
                $totalPreco += $media;
                $count++;
            }

            // Avaliações (NOVO - dados que estavam sendo ignorados)
            $avaliacoes = $dadosRicos['avaliacoes'] ?? [];
            if (! empty($avaliacoes['nota_media'])) {
                $notaMedia = $avaliacoes['nota_media'];
                $totalAvaliacoes = $avaliacoes['total_avaliacoes'] ?? 0;
                $output .= "- Avaliação: {$notaMedia}/5";
                if ($totalAvaliacoes > 0) {
                    $output .= " ({$totalAvaliacoes} reviews)";
                }
                $output .= "\n";
            }

            // Categorias
            if (! empty($dadosRicos['categorias'])) {
                $topCats = array_slice($dadosRicos['categorias'], 0, 3);
                $catsStr = implode(', ', array_map(fn ($cat) => "{$cat['nome']} ({$cat['mencoes']}x)", $topCats));
                $output .= "- Categorias foco: {$catsStr}\n";

                // Agregar categorias para análise geral
                foreach ($dadosRicos['categorias'] as $cat) {
                    $catNome = $cat['nome'] ?? 'outros';
                    $allCategories[$catNome] = ($allCategories[$catNome] ?? 0) + ($cat['mencoes'] ?? 1);
                }
            }

            // Promoções detalhadas (NOVO - antes só pegava maior desconto)
            if (! empty($dadosRicos['promocoes'])) {
                $promosFormatted = [];
                foreach ($dadosRicos['promocoes'] as $promo) {
                    $tipo = $promo['tipo'] ?? 'outro';
                    $allPromos[$tipo] = ($allPromos[$tipo] ?? 0) + 1;

                    if ($tipo === 'desconto_percentual') {
                        $valor = $promo['valor'] ?? '';
                        $promosFormatted[] = "Desconto {$valor}";
                    } elseif ($tipo === 'cupom') {
                        $codigo = $promo['codigo'] ?? '';
                        $promosFormatted[] = "Cupom: {$codigo}";
                    } elseif ($tipo === 'frete_gratis') {
                        $promosFormatted[] = 'Frete grátis';
                    } elseif ($tipo === 'promocao_especial') {
                        $descricao = $promo['descricao'] ?? 'Promoção especial';
                        $promosFormatted[] = $descricao;
                    }
                }
                if (! empty($promosFormatted)) {
                    $output .= '- Promoções: '.implode(', ', array_slice($promosFormatted, 0, 4))."\n";
                }
            }

            // Diferenciais
            if (! empty($diferenciais)) {
                $output .= '- Diferenciais: '.implode(', ', array_slice($diferenciais, 0, 4))."\n";
            }

            // Produtos estimados
            if (! empty($c['produtos_estimados'])) {
                $output .= "- Produtos no catálogo: ~{$c['produtos_estimados']}\n";
            }

            // Top produtos do concorrente (NOVO - dados que estavam sendo ignorados)
            $produtos = $dadosRicos['produtos'] ?? [];
            if (! empty($produtos)) {
                $topProdutos = array_slice($produtos, 0, 5);
                $output .= "- Produtos destaque:\n";
                foreach ($topProdutos as $i => $prod) {
                    $nomeProd = $prod['nome'] ?? $prod['name'] ?? 'Produto';
                    $precoProd = $prod['preco'] ?? $prod['price'] ?? 0;
                    $precoFormatado = is_numeric($precoProd) ? 'R$ '.number_format($precoProd, 2, ',', '.') : $precoProd;
                    $rank = $i + 1;
                    $output .= "  {$rank}. {$nomeProd} ({$precoFormatado})\n";
                }
            }

            $output .= "\n";
        }

        // Resumo agregado
        if ($count > 0) {
            $mediaConcorrentes = round($totalPreco / $count, 2);
            $output .= "---\n";
            $output .= "**RESUMO AGREGADO ({$count} concorrentes):**\n";
            $output .= "- Ticket médio: R$ {$mediaConcorrentes}\n";

            // Top categorias do mercado
            if (! empty($allCategories)) {
                arsort($allCategories);
                $topMarketCats = array_slice($allCategories, 0, 5, true);
                $catsMarket = implode(', ', array_map(fn ($k, $v) => "{$k} ({$v})", array_keys($topMarketCats), $topMarketCats));
                $output .= "- Categorias mais fortes no mercado: {$catsMarket}\n";
            }

            // Tipos de promoção mais usados
            if (! empty($allPromos)) {
                arsort($allPromos);
                $promosMarket = implode(', ', array_map(fn ($k, $v) => "{$k} ({$v}x)", array_keys($allPromos), $allPromos));
                $output .= "- Tipos de promoção: {$promosMarket}\n";
            }
        }

        return $output ?: 'Dados limitados.';
    }

    /**
     * Contexto de sazonalidade
     */
    private static function getSeasonalityContext(int $mes): string
    {
        $contextos = [
            1 => '**Janeiro (Pós-Festas):** Queda de 20-30% é NORMAL. Não classificar como anomalia.',
            2 => '**Fevereiro (Carnaval):** Vendas voláteis. Pico antes, queda durante feriado.',
            3 => '**Março:** Mês de normalização. Bom para comparação histórica.',
            4 => '**Abril (Páscoa):** Leve alta em kits presenteáveis.',
            5 => '**Maio (Dia das Mães):** ALTA TEMPORADA. +30-50% esperado. Queda após = normal.',
            6 => '**Junho (Namorados):** Pico no início do mês, depois estabiliza.',
            7 => '**Julho (Férias):** Queda de 10-15% é normal (férias escolares).',
            8 => '**Agosto (Dia dos Pais):** Leve alta. Preparação para Q4.',
            9 => '**Setembro (Dia do Cliente):** Promoções moderadas. Preparação para Black Friday.',
            10 => '**Outubro (Pré-BF):** Consumidores segurando compras. Queda é estratégica.',
            11 => '**Novembro (Black Friday):** MAIOR MÊS. +50-100% esperado.',
            12 => '**Dezembro (Natal):** ALTA TEMPORADA até dia 20. Queda após Natal = normal.',
        ];

        return $contextos[$mes] ?? 'Mês sem sazonalidade específica identificada.';
    }

    /**
     * Resumo dos benchmarks do RAG
     */
    private static function summarizeBenchmarks(array $structured, array $raw, float $ticketLoja): string
    {
        $output = '';

        // Dados estruturados (prioritários)
        if (! empty($structured)) {
            // Ticket médio
            if (isset($structured['ticket_medio'])) {
                $tm = $structured['ticket_medio'];
                if (is_array($tm)) {
                    $min = $tm['min'] ?? 0;
                    $media = $tm['media'] ?? $tm['avg'] ?? 0;
                    $max = $tm['max'] ?? 0;
                    $output .= "**Ticket Médio do Setor:**\n";
                    $output .= '- Mínimo: R$ '.number_format($min, 2, ',', '.')."\n";
                    $output .= '- Média: R$ '.number_format($media, 2, ',', '.')."\n";
                    $output .= '- Máximo: R$ '.number_format($max, 2, ',', '.')."\n";

                    // Comparação com a loja
                    if ($media > 0 && $ticketLoja > 0) {
                        $diff = round((($ticketLoja / $media) - 1) * 100);
                        $posicao = $diff >= 0 ? "+{$diff}%" : "{$diff}%";
                        $output .= "- **Loja vs Benchmark:** {$posicao}\n";
                    }
                    $output .= "\n";
                } else {
                    $output .= '**Ticket Médio do Setor:** R$ '.number_format($tm, 2, ',', '.')."\n\n";
                }
            }

            // Taxa de conversão
            if (isset($structured['taxa_conversao'])) {
                $tc = $structured['taxa_conversao'];
                if (is_array($tc)) {
                    $min = $tc['min'] ?? 0;
                    $media = $tc['media'] ?? 0;
                    $max = $tc['max'] ?? 0;
                    $output .= "**Taxa de Conversão do Setor:**\n";
                    $output .= "- Mínimo: {$min}%\n";
                    $output .= "- Média: {$media}%\n";
                    $output .= "- Máximo: {$max}%\n\n";
                } else {
                    $output .= "**Taxa de Conversão do Setor:** {$tc}%\n\n";
                }
            }

            // Abandono de carrinho
            if (isset($structured['abandono_carrinho'])) {
                $output .= "**Abandono de Carrinho (benchmark):** {$structured['abandono_carrinho']}%\n\n";
            }

            // Tráfego mobile
            if (isset($structured['trafego_mobile'])) {
                $output .= "**Tráfego Mobile (benchmark):** {$structured['trafego_mobile']}%\n\n";
            }

            // Crescimento do setor
            if (isset($structured['crescimento_setor'])) {
                $output .= "**Crescimento do Setor:** {$structured['crescimento_setor']}% ao ano\n\n";
            }

            // Fonte dos dados
            if (isset($structured['benchmark_source'])) {
                $output .= "**Fonte:** {$structured['benchmark_source']}\n\n";
            }
        }

        // Dados brutos (complementares)
        if (! empty($raw) && empty($output)) {
            $output .= "**Benchmarks Disponíveis:**\n\n";
            foreach (array_slice($raw, 0, 3) as $benchmark) {
                $title = $benchmark['title'] ?? 'Benchmark';
                $content = $benchmark['content'] ?? '';
                $output .= "### {$title}\n{$content}\n\n";
            }
        }

        if (empty($output)) {
            return "Nenhum benchmark específico disponível para este nicho. Use médias gerais de e-commerce:\n- Ticket Médio: R$ 250-350\n- Taxa de Conversão: 1.5-2.5%\n- Abandono de Carrinho: 65-75%\n- Tráfego Mobile: 70-80%";
        }

        $output .= "**IMPORTANTE:** Use estes benchmarks para calcular o componente 'Ticket vs Benchmark' do Health Score.\n";

        return $output;
    }

    /**
     * Gera comparativo estruturado loja vs concorrentes (Mudança 11).
     */
    private static function generateComparativo(float $ticketLoja, int $pedidosMes, array $categoriasFoco, int $promocoesAtivas, array $competitors): string
    {
        if (empty($competitors)) {
            return "Nenhum dado de concorrente disponível para comparação.\n";
        }

        $competitorsValidos = array_filter($competitors, fn ($c) => $c['sucesso'] ?? false);
        if (empty($competitorsValidos)) {
            return "Dados de concorrentes não processados com sucesso.\n";
        }

        $output = '';

        // 1. Comparativo de Ticket Médio
        $ticketsConcorrentes = [];
        $avaliacoesConcorrentes = [];
        $categoriasConcorrentes = [];
        $promocoesConcorrentes = 0;

        foreach ($competitorsValidos as $c) {
            $faixa = $c['faixa_preco'] ?? [];
            $media = $faixa['media'] ?? null;
            if ($media !== null && $media > 0) {
                $ticketsConcorrentes[] = [
                    'nome' => $c['nome'] ?? 'Concorrente',
                    'ticket' => $media,
                ];
            }

            $dadosRicos = $c['dados_ricos'] ?? [];
            $avaliacoes = $dadosRicos['avaliacoes'] ?? [];
            $notaMedia = $avaliacoes['nota_media'] ?? null;
            if ($notaMedia !== null && $notaMedia > 0) {
                $avaliacoesConcorrentes[] = [
                    'nome' => $c['nome'] ?? 'Concorrente',
                    'nota' => $notaMedia,
                    'total' => $avaliacoes['total_avaliacoes'] ?? 0,
                ];
            }

            // Categorias
            foreach ($dadosRicos['categorias'] ?? [] as $cat) {
                $catNome = $cat['nome'] ?? 'outros';
                $categoriasConcorrentes[$catNome] = ($categoriasConcorrentes[$catNome] ?? 0) + ($cat['mencoes'] ?? 1);
            }

            // Promoções
            $promocoesConcorrentes += count($dadosRicos['promocoes'] ?? []);
        }

        // Ticket Médio
        if (! empty($ticketsConcorrentes)) {
            $ticketMediaConcorrentes = array_sum(array_column($ticketsConcorrentes, 'ticket')) / count($ticketsConcorrentes);
            $diffTicket = $ticketLoja > 0 ? round((($ticketLoja / $ticketMediaConcorrentes) - 1) * 100) : 0;
            $diffStr = $diffTicket >= 0 ? "+{$diffTicket}%" : "{$diffTicket}%";

            $output .= "### Ticket Médio\n\n";
            $output .= "| | Valor |\n";
            $output .= "|---|------|\n";
            $output .= '| **Sua Loja** | R$ '.number_format($ticketLoja, 2, ',', '.')." |\n";
            $output .= '| **Média Concorrentes** | R$ '.number_format($ticketMediaConcorrentes, 2, ',', '.')." |\n";
            $output .= "| **Diferença** | {$diffStr} |\n\n";

            if ($diffTicket < -20) {
                $output .= "⚠️ **ALERTA:** Ticket 20%+ abaixo da concorrência. Considere upsell/kits.\n\n";
            } elseif ($diffTicket > 20) {
                $output .= "✅ **POSITIVO:** Ticket acima da concorrência. Pode indicar melhor posicionamento.\n\n";
            }
        }

        // Avaliações
        if (! empty($avaliacoesConcorrentes)) {
            $output .= "### Avaliações\n\n";
            $output .= "| Concorrente | Nota | Reviews |\n";
            $output .= "|-------------|------|--------|\n";
            usort($avaliacoesConcorrentes, fn ($a, $b) => ($b['nota'] ?? 0) <=> ($a['nota'] ?? 0));
            foreach ($avaliacoesConcorrentes as $av) {
                $nome = $av['nome'] ?? '';
                $nota = $av['nota'] ?? 0;
                $total = $av['total'] ?? 0;
                $output .= "| {$nome} | {$nota}/5 | {$total} |\n";
            }
            $output .= "\n**INSIGHT:** Se sua loja não tem programa de reviews, considere implementar.\n\n";
        }

        // Categorias
        if (! empty($categoriasConcorrentes)) {
            arsort($categoriasConcorrentes);
            $topCategorias = array_slice($categoriasConcorrentes, 0, 5, true);

            $output .= "### Categorias Foco dos Concorrentes\n\n";
            foreach ($topCategorias as $cat => $mencoes) {
                $output .= "- **{$cat}** ({$mencoes} menções)\n";
            }
            $output .= "\n";

            // Oportunidades
            $catsConcorrentes = array_keys($topCategorias);
            $catsLoja = is_array($categoriasFoco) ? array_keys($categoriasFoco) : [];
            $oportunidades = array_diff($catsConcorrentes, $catsLoja);
            if (! empty($oportunidades)) {
                $output .= '**Oportunidade:** Concorrentes focam em categorias que você não tem: '.implode(', ', array_slice($oportunidades, 0, 3))."\n\n";
            }
        }

        // Promoções
        $mediaPromosConcorrentes = count($competitorsValidos) > 0 ? round($promocoesConcorrentes / count($competitorsValidos)) : 0;
        $output .= "### Promoções\n\n";
        $output .= "| | Quantidade |\n";
        $output .= "|---|------|\n";
        $output .= "| **Sua Loja** | {$promocoesAtivas} promoções ativas |\n";
        $output .= "| **Média Concorrentes** | {$mediaPromosConcorrentes} promoções |\n\n";

        if ($promocoesAtivas < $mediaPromosConcorrentes * 0.5) {
            $output .= "⚠️ **ALERTA:** Você tem bem menos promoções que a concorrência.\n\n";
        }

        return $output;
    }

    /**
     * Formata sugestões anteriores para o Analyst, destacando temas saturados
     * para que o diagnóstico evite repetir os mesmos problemas.
     */
    private static function formatPreviousSuggestionsForAnalyst(array $suggestions): string
    {
        if (empty($suggestions)) {
            return 'Nenhuma sugestão anterior. Esta é uma das primeiras análises desta loja.';
        }

        $total = count($suggestions);
        $output = "**Total:** {$total} sugestões já dadas em análises anteriores\n\n";

        // Identificar temas saturados
        $keywords = [
            'Estoque/Reposição' => ['estoque', 'avise-me', 'reposição', 'inventário', 'repor'],
            'Kits/Combos' => ['kit', 'combo', 'bundle', 'pack', 'cronograma'],
            'Cupom/Desconto' => ['cupom', 'desconto', 'voucher', 'código promocional'],
            'Email Marketing' => ['email', 'newsletter', 'automação', 'e-mail marketing'],
            'Frete Grátis' => ['frete grátis', 'frete gratuito', 'frete condicional'],
            'Fidelidade/Pontos' => ['fidelidade', 'pontos', 'cashback', 'recompensa'],
            'Quiz/Personalização' => ['quiz', 'questionário', 'personalizado'],
            'Checkout/Conversão' => ['checkout', 'carrinho', 'abandono', 'conversão'],
            'Ticket Médio' => ['ticket médio', 'aov', 'valor médio'],
            'Upsell/Cross-sell' => ['upsell', 'cross-sell', 'venda cruzada'],
            'Reativação' => ['reativação', 'reativar', 'clientes inativos', 'win-back'],
            'Reviews/Avaliações' => ['review', 'avaliação', 'depoimento', 'prova social'],
            'Assinatura' => ['assinatura', 'recorrência', 'subscription'],
        ];

        $counts = [];
        foreach ($suggestions as $s) {
            $text = mb_strtolower(($s['title'] ?? '').($s['description'] ?? ''));
            foreach ($keywords as $theme => $kws) {
                foreach ($kws as $kw) {
                    if (mb_strpos($text, $kw) !== false) {
                        $counts[$theme] = ($counts[$theme] ?? 0) + 1;

                        break;
                    }
                }
            }
        }

        arsort($counts);

        // Temas saturados (3+ vezes)
        $saturated = array_filter($counts, fn ($c) => $c >= 3);
        if (! empty($saturated)) {
            $output .= "### TEMAS SATURADOS (já sugeridos 3+ vezes — NÃO diagnosticar o mesmo problema)\n\n";
            foreach ($saturated as $theme => $count) {
                $output .= "- **{$theme}**: {$count}x — APROFUNDE para encontrar a CAUSA RAIZ por trás deste tema\n";
            }
            $output .= "\n**REGRA:** Se o problema mais óbvio é um tema saturado acima, NÃO o reporte.\n";
            $output .= "Em vez disso, investigue POR QUE esse problema persiste apesar de já ter sido\n";
            $output .= "sugerido múltiplas vezes, e reporte essa causa raiz mais profunda.\n\n";
        }

        // Temas usados (1-2 vezes)
        $used = array_filter($counts, fn ($c) => $c >= 1 && $c < 3);
        if (! empty($used)) {
            $output .= "### Temas já usados (1-2x — evitar se possível)\n\n";
            foreach ($used as $theme => $count) {
                $output .= "- {$theme}: {$count}x\n";
            }
            $output .= "\n";
        }

        if (empty($saturated) && empty($used)) {
            $output .= "Nenhum tema saturado identificado.\n";
        }

        return $output;
    }

    /**
     * Template resumido
     */
    public static function getTemplate(): string
    {
        return <<<'TEMPLATE'
# ANALYST — DIAGNÓSTICO

## TAREFA
Analisar dados e produzir: Health Score, Alertas, Oportunidades, Briefing.

## OUTPUT
JSON com health_score, alertas, oportunidades, posicionamento, briefing_strategist.

PORTUGUÊS BRASILEIRO
TEMPLATE;
    }

    /**
     * Método build() para compatibilidade.
     */
    public static function build(array $context): string
    {
        return self::get($context);
    }
}
