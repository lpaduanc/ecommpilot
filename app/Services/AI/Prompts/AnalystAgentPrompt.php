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

        // Sazonalidade
        $mes = (int) date('n');
        $sazonalidade = self::getSeasonalityContext($mes);

        return <<<PROMPT
# ANALYST — DIAGNÓSTICO DA LOJA

## TAREFA
Analisar os dados da loja e produzir um diagnóstico estruturado com:
1. Health Score (0-100)
2. Alertas priorizados
3. 5 oportunidades com potencial de receita
4. Briefing para o Strategist

---

## REGRAS

1. **Health Score:** Calcular baseado nos 5 componentes. Aplicar OVERRIDE se situação crítica.
2. **Alertas:** Apenas problemas reais com dados que comprovem. Não inventar alertas. Máximo 5 alertas, priorizados por severidade.
3. **Oportunidades:** Gerar exatamente 5 oportunidades, cada uma com potencial específico em R$.
4. **Comparação tripla:** Sempre comparar ticket da loja vs benchmark vs concorrentes.
5. **Sazonalidade:** Considerar antes de classificar algo como anomalia.
6. **Classificação Health Score:** critico (0-25), atencao (26-50), saudavel (51-75), excelente (76-100).

---

## CONTEXTO DA LOJA

- **Nome:** {$storeName}
- **Nicho:** {$niche} / {$subcategory}
- **Ticket Médio:** R$ {$ticketMedio}
- **Pedidos/Mês:** {$pedidosMes}
- **Faturamento:** R$ {$faturamentoMes}/mês
- **Período:** {$periodDays} dias

---

## CONTEXTO SAZONAL

{$sazonalidade}

---

## DADOS OPERACIONAIS

### Pedidos
```json
{$orders}
```

### Produtos
```json
{$products}
```

### Estoque
```json
{$inventory}
```

**IMPORTANTE:** Os dados de estoque EXCLUEM produtos que são brindes/amostras grátis (identificados por termos como "brinde", "grátis", "amostra", "gift", etc.). Esses produtos não devem ser considerados em alertas de estoque baixo ou zerado.

### Cupons
```json
{$coupons}
```

---

## HISTÓRICO DA LOJA (para detectar anomalias)

```json
{$historicalData}
```

**Regra:** Variação > 20% vs média histórica = ANOMALIA

---

## DADOS DE MERCADO

{$marketSummary}

---

## DADOS DE CONCORRENTES

{$competitorSummary}

---

## CÁLCULO DO HEALTH SCORE

| Componente | Peso | Como calcular |
|------------|------|---------------|
| Ticket vs Benchmark | 25 pts | ≥100% = 25, 80-99% = 20, 60-79% = 15, <60% = 10 |
| Estoque disponível | 25 pts | ≤10% zerado = 25, 11-20% = 20, 21-35% = 15, >35% = 10 |
| Taxa cancelamento | 15 pts | ≤3% = 15, 4-7% = 12, 8-12% = 8, >12% = 4 |
| Saúde de cupons | 15 pts | uso <50% E impacto <15% = 15, senão proporcional |
| Tendência vendas | 20 pts | crescendo = 20, estável = 15, queda leve = 10, queda forte = 5 |

### OVERRIDE (aplicar após calcular)

**FORÇAR CRÍTICO (máx 25 pts):**
- Estoque zerado > 45%
- Cancelamento > 15%
- Queda vendas > 40%

**LIMITAR A ATENÇÃO (máx 50 pts):**
- Estoque zerado > 35%
- Cancelamento > 10%
- Cupons > 85% das vendas

---

## FEW-SHOT: EXEMPLOS DE DIAGNÓSTICO

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

---

## FORMATO DE SAÍDA

Retorne APENAS o JSON abaixo:

```json
{
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
    // MÁXIMO 5 ALERTAS, ordenados por severidade (critico primeiro)
    {
      "severidade": "critico|atencao|monitorar",
      "tipo": "estoque|cancelamento|pricing|cupons|vendas",
      "titulo": "Descrição curta do problema",
      "dados": "Números específicos que comprovam",
      "impacto": "R$ X/mês ou X% de perda",
      "acao": "O que fazer"
    }
  ],

  "oportunidades": [
    // EXATAMENTE 5 OPORTUNIDADES, ordenadas por potencial (maior primeiro)
    {
      "tipo": "reativacao|upsell|estoque|pricing|conversao",
      "titulo": "Descrição da oportunidade",
      "dados": "Números que embasam",
      "potencial": "R$ X/mês",
      "acao": "Como capturar"
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

  "briefing_strategist": {
    "problema_1": "Principal problema a resolver",
    "problema_2": "Segundo problema",
    "problema_3": "Terceiro problema",
    "oportunidade_principal": "Maior oportunidade identificada",
    "restricoes": ["O que NÃO fazer ou limitações da loja"],
    "dados_chave": {
      "faturamento_mes": 0,
      "ticket_medio": 0,
      "taxa_conversao": 0,
      "estoque_zerado_percent": 0
    }
  }
}
```

---

## CHECKLIST ANTES DE ENVIAR

- [ ] Health Score calculado com os 5 componentes e classificação correta (critico 0-25, atencao 26-50, saudavel 51-75, excelente 76-100)?
- [ ] Override aplicado se situação crítica (estoque >45%, cancelamento >15%, queda >40%)?
- [ ] Máximo 5 alertas, cada um com dados específicos (números)?
- [ ] Exatamente 5 oportunidades, cada uma com potencial em R$?
- [ ] Posicionamento com comparação tripla (benchmark, mercado, concorrentes)?
- [ ] Briefing para Strategist com 3 problemas e restrições?

**RESPONDA APENAS COM O JSON. PORTUGUÊS BRASILEIRO.**
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

        $precoMin = $precos['faixa_preco']['min'] ?? 0;
        $precoMax = $precos['faixa_preco']['max'] ?? 0;
        $precoMedio = $precos['faixa_preco']['media'] ?? 0;

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
     * Resumo dos concorrentes
     */
    private static function summarizeCompetitors(array $competitors): string
    {
        if (empty($competitors)) {
            return 'Nenhum dado de concorrente disponível.';
        }

        $output = '';
        $totalPreco = 0;
        $count = 0;

        foreach ($competitors as $c) {
            if (! ($c['sucesso'] ?? false)) {
                continue;
            }

            $nome = $c['nome'] ?? 'Concorrente';
            $faixa = $c['faixa_preco'] ?? [];
            $diferenciais = $c['diferenciais'] ?? [];
            $dadosRicos = $c['dados_ricos'] ?? [];

            $output .= "**{$nome}:**\n";

            if (! empty($faixa)) {
                $output .= "- Preço: R$ {$faixa['min']} - R$ {$faixa['max']} (média R$ {$faixa['media']})\n";
                $totalPreco += $faixa['media'];
                $count++;
            }

            if (! empty($dadosRicos['categorias'])) {
                $topCats = array_slice($dadosRicos['categorias'], 0, 3);
                $catsStr = implode(', ', array_map(fn ($cat) => "{$cat['nome']} ({$cat['mencoes']}x)", $topCats));
                $output .= "- Categorias foco: {$catsStr}\n";
            }

            if (! empty($dadosRicos['promocoes'])) {
                $maxDesc = 0;
                foreach ($dadosRicos['promocoes'] as $promo) {
                    if (($promo['tipo'] ?? '') === 'desconto_percentual') {
                        $val = (int) filter_var($promo['valor'] ?? '0', FILTER_SANITIZE_NUMBER_INT);
                        if ($val > $maxDesc) {
                            $maxDesc = $val;
                        }
                    }
                }
                if ($maxDesc > 0) {
                    $output .= "- Maior desconto: {$maxDesc}%\n";
                }
            }

            if (! empty($diferenciais)) {
                $output .= '- Diferenciais: '.implode(', ', array_slice($diferenciais, 0, 3))."\n";
            }

            $output .= "\n";
        }

        if ($count > 0) {
            $mediaConcorrentes = round($totalPreco / $count, 2);
            $output .= "**Média de preços dos concorrentes:** R$ {$mediaConcorrentes}\n";
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
