<?php

namespace App\Services\AI\Prompts;

class StrategistAgentPrompt
{
    /**
     * STRATEGIST AGENT V5 - REFATORADO
     *
     * Mudanças:
     * - Removida persona fictícia
     * - Adicionados few-shot examples concretos
     * - Prompt reduzido (~50%)
     * - Formato de saída simplificado
     * - Constraints específicos e mensuráveis
     */
    public static function getSeasonalityContext(): array
    {
        $mes = (int) date('n');

        $contextos = [
            1 => ['periodo' => 'PÓS-FESTAS', 'foco' => 'Liquidação, fidelização', 'oportunidades' => ['Queima de estoque', 'Fidelizar clientes do Natal'], 'evitar' => ['Lançamentos premium']],
            2 => ['periodo' => 'CARNAVAL', 'foco' => 'Promoções temáticas', 'oportunidades' => ['Kits temáticos', 'Promoções relâmpago'], 'evitar' => ['Produtos de inverno']],
            3 => ['periodo' => 'DIA DA MULHER', 'foco' => 'Campanhas femininas', 'oportunidades' => ['Kits presenteáveis', 'Promoções especiais'], 'evitar' => ['Produtos masculinos']],
            4 => ['periodo' => 'PÁSCOA', 'foco' => 'Presentes', 'oportunidades' => ['Kits presenteáveis'], 'evitar' => ['Descontos agressivos']],
            5 => ['periodo' => 'DIA DAS MÃES', 'foco' => 'Presentes premium', 'oportunidades' => ['Kits premium', 'Embalagens especiais'], 'evitar' => ['Promoções que desvalorizam']],
            6 => ['periodo' => 'DIA DOS NAMORADOS', 'foco' => 'Presentes casais', 'oportunidades' => ['Kits casais', 'Combos'], 'evitar' => ['Produtos infantis']],
            7 => ['periodo' => 'FÉRIAS', 'foco' => 'Fidelização', 'oportunidades' => ['Assinaturas', 'Programas de pontos'], 'evitar' => ['Esperar Black Friday']],
            8 => ['periodo' => 'DIA DOS PAIS', 'foco' => 'Linha masculina', 'oportunidades' => ['Produtos masculinos', 'Kits pais'], 'evitar' => ['Ignorar público masculino']],
            9 => ['periodo' => 'DIA DO CLIENTE', 'foco' => 'Fidelização', 'oportunidades' => ['Promoções exclusivas', 'Programa pontos'], 'evitar' => ['Grandes descontos (guardar BF)']],
            10 => ['periodo' => 'PRÉ-BLACK FRIDAY', 'foco' => 'Preparação', 'oportunidades' => ['Reposição estoque', 'Aquecimento base'], 'evitar' => ['Queimar promoções antes BF']],
            11 => ['periodo' => 'BLACK FRIDAY', 'foco' => 'Maior evento', 'oportunidades' => ['Descontos agressivos', 'Frete grátis'], 'evitar' => ['Descontos falsos', 'Estoque insuficiente']],
            12 => ['periodo' => 'NATAL', 'foco' => 'Presentes', 'oportunidades' => ['Kits presenteáveis', 'Garantia entrega'], 'evitar' => ['Canibalizar margem']],
        ];

        return $contextos[$mes] ?? $contextos[7];
    }

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

**IMPOSSÍVEL:** Realidade aumentada, IA generativa nativa, Live commerce nativo
RESOURCES;
    }

    public static function formatAcceptedAndRejected(array $accepted, array $rejected): string
    {
        $output = '';

        if (! empty($accepted)) {
            $output .= "**ACEITAS (não repetir tema):**\n";
            foreach ($accepted as $title) {
                $output .= "- {$title}\n";
            }
            $output .= "\n";
        }

        if (! empty($rejected)) {
            $output .= "**REJEITADAS (evitar abordagem):**\n";
            foreach ($rejected as $title) {
                $output .= "- {$title}\n";
            }
            $output .= "\n";
        }

        return $output ?: "Nenhuma sugestão aceita ou rejeitada anteriormente.\n";
    }

    public static function getTemplate(): string
    {
        return <<<'PROMPT'
# STRATEGIST — GERADOR DE SUGESTÕES

## TAREFA
Gerar EXATAMENTE 9 sugestões acionáveis para a loja. Distribuição: 3 HIGH, 3 MEDIUM, 3 LOW.

---

## REGRAS OBRIGATÓRIAS

1. **NUNCA repetir** tema de sugestão anterior (veja ZONAS PROIBIDAS)
2. **HIGH (prioridades 1-3):** Obrigatório citar dado específico (número) da loja ou concorrente
3. **Cada sugestão deve ter:** problema específico + ação específica + resultado esperado com número
4. **Se não há dado para embasar:** não pode ser HIGH, rebaixe para MEDIUM ou LOW

---

## ZONAS PROIBIDAS (NÃO REPETIR)

{{prohibited_suggestions}}

**Temas saturados:**
{{saturated_themes}}

{{accepted_rejected}}

---

## CONTEXTO

**Período:** {{seasonality_period}}
**Foco sazonal:** {{seasonality_focus}}

{{platform_resources}}

---

## DADOS DA LOJA

{{store_context}}

**NOTA:** Os dados de estoque EXCLUEM produtos que são brindes/amostras grátis. Não crie sugestões de reposição de estoque para produtos gratuitos.

---

## ANÁLISE DO ANALYST

{{analyst_analysis}}

---

## DADOS DE CONCORRENTES

{{competitor_data}}

---

## DADOS DE MERCADO

{{market_data}}

---

## FEW-SHOT: EXEMPLOS DE SUGESTÕES BEM ESCRITAS

### EXEMPLO 1 — HIGH (com dado específico)

```json
{
  "priority": 1,
  "expected_impact": "high",
  "category": "inventory",
  "title": "Reativar 8 SKUs parados há 60+ dias que vendiam R$ 3.200/mês",
  "problem": "8 produtos com histórico de venda (R$ 3.200/mês combinado) estão com estoque mas sem vendas há 60 dias. Representam 12% do catálogo ativo.",
  "action": "1. Identificar os 8 SKUs no painel (filtro: estoque > 0, vendas = 0, 60 dias)\n2. Criar banner 'Volta por Demanda' na home\n3. Enviar email para clientes que compraram itens similares\n4. Aplicar desconto progressivo: 10% semana 1, 15% semana 2",
  "expected_result": "Recuperar 60% do histórico = R$ 1.920/mês em receita reativada",
  "data_source": "Dados da loja: 8 SKUs identificados pelo Analyst com vendas zeradas",
  "implementation": {
    "type": "nativo",
    "complexity": "baixa",
    "cost": "R$ 0"
  }
}
```

### EXEMPLO 2 — MEDIUM (otimização baseada em análise)

```json
{
  "priority": 4,
  "expected_impact": "medium",
  "category": "conversion",
  "title": "Adicionar urgência nas páginas dos 5 produtos mais visitados",
  "problem": "Os 5 produtos mais visitados têm taxa de conversão 40% abaixo da média da loja (1.2% vs 2.0%). Falta gatilho de urgência.",
  "action": "1. Instalar app de countdown (CartStack, R$ 30/mês)\n2. Adicionar 'Apenas X em estoque' nos 5 produtos\n3. Criar oferta relâmpago semanal rotativa entre eles",
  "expected_result": "Aumentar conversão desses produtos de 1.2% para 1.8% = +50% em vendas desses SKUs",
  "data_source": "Análise do Analyst: produtos com alto tráfego e baixa conversão",
  "implementation": {
    "type": "app",
    "app_name": "CartStack",
    "complexity": "baixa",
    "cost": "R$ 30/mês"
  }
}
```

### EXEMPLO 3 — LOW (quick win simples)

```json
{
  "priority": 7,
  "expected_impact": "low",
  "category": "coupon",
  "title": "Criar cupom de primeira compra 10% para captura de email",
  "problem": "Loja não tem mecanismo de captura de leads. Visitantes saem sem deixar contato.",
  "action": "1. Criar cupom PRIMEIRACOMPRA10 (10% off, uso único)\n2. Adicionar pop-up de saída oferecendo o cupom em troca do email\n3. Configurar email automático de boas-vindas com o cupom",
  "expected_result": "Capturar 3-5% dos visitantes como leads, converter 20% deles = receita incremental",
  "data_source": "Prática padrão de mercado para e-commerce",
  "implementation": {
    "type": "nativo",
    "complexity": "baixa",
    "cost": "R$ 0"
  }
}
```

---

## FORMATO DE SAÍDA

Retorne APENAS o JSON abaixo, sem texto adicional:

```json
{
  "analysis_context": {
    "main_problems": ["problema 1", "problema 2", "problema 3"],
    "main_opportunities": ["oportunidade 1", "oportunidade 2"],
    "avoided_themes": ["tema já sugerido antes 1", "tema já sugerido antes 2"]
  },
  "suggestions": [
    {
      "priority": 1,
      "expected_impact": "high",
      "category": "inventory|pricing|product|customer|conversion|marketing|coupon|operational",
      "title": "Título específico com número quando possível",
      "problem": "Descrição do problema com dados específicos da loja",
      "action": "Passos numerados e específicos",
      "expected_result": "Resultado esperado com número (R$ ou %)",
      "data_source": "De onde veio o dado que embasa esta sugestão",
      "implementation": {
        "type": "nativo|app|terceiro",
        "app_name": "nome se aplicável ou null",
        "complexity": "baixa|media|alta",
        "cost": "R$ X/mês ou R$ 0"
      },
      "competitor_reference": "Se HIGH: qual dado de concorrente ou mercado embasa isso. Se não há: null"
    }
  ]
}
```

---

## CHECKLIST ANTES DE ENVIAR

- [ ] Exatamente 9 sugestões?
- [ ] 3 HIGH, 3 MEDIUM, 3 LOW?
- [ ] Nenhum tema repetido das ZONAS PROIBIDAS?
- [ ] Toda HIGH tem dado específico (número) no campo problem?
- [ ] Toda sugestão tem expected_result com número?
- [ ] Todas as sugestões são viáveis na Nuvemshop?

**RESPONDA APENAS COM O JSON. PORTUGUÊS BRASILEIRO.**
PROMPT;
    }

    public static function formatProhibitedSuggestions(array $previousSuggestions): string
    {
        if (empty($previousSuggestions)) {
            return 'Nenhuma sugestão anterior.';
        }

        $grouped = [];
        foreach ($previousSuggestions as $s) {
            $cat = $s['category'] ?? 'outros';
            $title = $s['title'] ?? 'Sem título';
            if (! isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            if (! in_array($title, $grouped[$cat])) {
                $grouped[$cat][] = $title;
            }
        }

        $output = 'Total: '.count($previousSuggestions)." sugestões anteriores\n\n";
        foreach ($grouped as $cat => $titles) {
            $output .= "**{$cat}:** ".implode(', ', $titles)."\n";
        }

        return $output;
    }

    public static function identifySaturatedThemes(array $previousSuggestions): string
    {
        if (empty($previousSuggestions)) {
            return 'Nenhum.';
        }

        $keywords = [
            'Quiz' => ['quiz', 'questionário', 'personalizado'],
            'Frete Grátis' => ['frete grátis', 'frete gratuito'],
            'Fidelidade' => ['fidelidade', 'pontos', 'cashback'],
            'Kits' => ['kit', 'combo', 'bundle', 'cronograma'],
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
        arsort($saturated);

        if (empty($saturated)) {
            return 'Nenhum.';
        }

        $out = '';
        foreach ($saturated as $t => $c) {
            $out .= "- {$t} ({$c}x) — NÃO USAR\n";
        }

        return $out;
    }

    public static function extractCompetitorInsights(array $competitors): string
    {
        if (empty($competitors)) {
            return 'Nenhum dado de concorrente disponível.';
        }

        $output = '';
        $allCategories = [];
        $maxDiscount = 0;

        foreach ($competitors as $c) {
            if (! ($c['sucesso'] ?? false)) {
                continue;
            }

            $nome = $c['nome'] ?? 'Concorrente';
            $dadosRicos = $c['dados_ricos'] ?? [];
            $faixa = $c['faixa_preco'] ?? [];

            $output .= "**{$nome}:**\n";

            if (! empty($faixa)) {
                $output .= "- Preço: R$ {$faixa['min']} - R$ {$faixa['max']} (média: R$ {$faixa['media']})\n";
            }

            if (! empty($dadosRicos['categorias'])) {
                $topCats = array_slice($dadosRicos['categorias'], 0, 3);
                $catsStr = implode(', ', array_map(fn ($cat) => "{$cat['nome']} ({$cat['mencoes']}x)", $topCats));
                $output .= "- Categorias foco: {$catsStr}\n";
                foreach ($topCats as $cat) {
                    $allCategories[$cat['nome']] = ($allCategories[$cat['nome']] ?? 0) + $cat['mencoes'];
                }
            }

            if (! empty($dadosRicos['promocoes'])) {
                foreach ($dadosRicos['promocoes'] as $promo) {
                    if (($promo['tipo'] ?? '') === 'desconto_percentual') {
                        $valor = (int) filter_var($promo['valor'] ?? '0', FILTER_SANITIZE_NUMBER_INT);
                        if ($valor > $maxDiscount) {
                            $maxDiscount = $valor;
                        }
                    }
                }
                if ($maxDiscount > 0) {
                    $output .= "- Maior desconto: {$maxDiscount}%\n";
                }
            }

            if (! empty($c['diferenciais'])) {
                $output .= '- Diferenciais: '.implode(', ', $c['diferenciais'])."\n";
            }

            $output .= "\n";
        }

        if (! empty($allCategories)) {
            arsort($allCategories);
            $output .= "**Categorias mais fortes no mercado:**\n";
            $count = 0;
            foreach ($allCategories as $cat => $mentions) {
                if ($count++ >= 3) {
                    break;
                }
                $output .= "- {$cat}: {$mentions} menções\n";
            }
        }

        return $output ?: 'Dados limitados.';
    }

    public static function build(array $context): string
    {
        $template = self::getTemplate();
        $season = self::getSeasonalityContext();

        $storeContext = $context['store_context'] ?? $context['collector_context'] ?? [];
        $analystAnalysis = $context['analyst_analysis'] ?? $context['analysis'] ?? [];
        $externalData = $context['external_data'] ?? [];
        $competitorData = $context['competitor_data'] ?? $externalData['concorrentes'] ?? [];
        $marketData = $context['market_data'] ?? $externalData['dados_mercado'] ?? [];

        $previousSuggestions = $context['previous_suggestions'] ?? [];
        $allSuggestions = isset($previousSuggestions['all']) ? $previousSuggestions['all'] : $previousSuggestions;
        $acceptedTitles = $previousSuggestions['accepted_titles'] ?? [];
        $rejectedTitles = $previousSuggestions['rejected_titles'] ?? [];

        $replacements = [
            '{{prohibited_suggestions}}' => self::formatProhibitedSuggestions($allSuggestions),
            '{{saturated_themes}}' => self::identifySaturatedThemes($allSuggestions),
            '{{accepted_rejected}}' => self::formatAcceptedAndRejected($acceptedTitles, $rejectedTitles),
            '{{seasonality_period}}' => $season['periodo'],
            '{{seasonality_focus}}' => $season['foco'],
            '{{platform_resources}}' => self::getPlatformResources(),
            '{{store_context}}' => is_array($storeContext) ? json_encode($storeContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $storeContext,
            '{{analyst_analysis}}' => is_array($analystAnalysis) ? json_encode($analystAnalysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $analystAnalysis,
            '{{competitor_data}}' => self::extractCompetitorInsights($competitorData),
            '{{market_data}}' => is_array($marketData) ? json_encode($marketData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $marketData,
        ];

        foreach ($replacements as $k => $v) {
            $template = str_replace($k, $v, $template);
        }

        return $template;
    }

    /**
     * Método get() para manter compatibilidade com o pipeline existente.
     */
    public static function get(array $context): string
    {
        return self::build($context);
    }
}
