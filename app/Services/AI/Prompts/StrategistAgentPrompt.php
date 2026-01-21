<?php

namespace App\Services\AI\Prompts;

class StrategistAgentPrompt
{
    /**
     * STRATEGIST AGENT V4 - VERSÃO COMPLETA COM TODAS AS MELHORIAS
     *
     * Melhorias incluídas:
     * [1] Ângulos não explorados (quando temas saturados)
     * [2] Validação de plataforma (nativo vs app)
     * [3] Contexto de sazonalidade
     * [4] Taxas de sucesso históricas por categoria
     * [6] Campo de confiança no output
     * + Proteção contra repetições (zonas proibidas)
     */

    public static function getSeasonalityContext(): array
    {
        $mes = (int) date('n');

        $contextos = [
            1 => ['periodo' => 'PÓS-FESTAS / VERÃO', 'foco' => 'Liquidação, fidelização novos clientes', 'oportunidades' => ['Queima de estoque', 'Fidelizar clientes do Natal', 'Kits verão'], 'evitar' => ['Lançamentos premium', 'Aumento de preços']],
            2 => ['periodo' => 'CARNAVAL / VERÃO', 'foco' => 'Produtos para cabelos expostos', 'oportunidades' => ['Kits pós-sol', 'Tratamentos reparadores', 'Promoções Carnaval'], 'evitar' => ['Produtos de inverno']],
            3 => ['periodo' => 'OUTONO / DIA DA MULHER', 'foco' => 'Campanhas femininas, transição', 'oportunidades' => ['Promoções Dia da Mulher', 'Kits presenteáveis', 'Tratamentos'], 'evitar' => ['Produtos de verão']],
            4 => ['periodo' => 'OUTONO / PÁSCOA', 'foco' => 'Reconstrução pós-verão', 'oportunidades' => ['Cronograma capilar', 'Tratamentos intensivos'], 'evitar' => ['Produtos leves']],
            5 => ['periodo' => 'DIA DAS MÃES', 'foco' => 'Presentes, kits especiais', 'oportunidades' => ['Kits presenteáveis premium', 'Combos especiais', 'Embalagens'], 'evitar' => ['Promoções que desvalorizam']],
            6 => ['periodo' => 'INVERNO / DIA DOS NAMORADOS', 'foco' => 'Hidratação intensa, presentes casais', 'oportunidades' => ['Kits casais', 'Máscaras intensivas', 'Tratamentos inverno'], 'evitar' => ['Proteção solar']],
            7 => ['periodo' => 'INVERNO / FÉRIAS', 'foco' => 'Tratamentos intensivos', 'oportunidades' => ['Cronograma completo', 'Assinaturas', 'Fidelização'], 'evitar' => ['Esperar Black Friday']],
            8 => ['periodo' => 'DIA DOS PAIS / PRÉ-PRIMAVERA', 'foco' => 'Linha masculina', 'oportunidades' => ['Produtos masculinos', 'Kits pais', 'Antecipação tendências'], 'evitar' => ['Ignorar público masculino']],
            9 => ['periodo' => 'PRIMAVERA / DIA DO CLIENTE', 'foco' => 'Renovação, fidelização', 'oportunidades' => ['Lançamentos', 'Promoções Dia do Cliente', 'Programa pontos'], 'evitar' => ['Grandes descontos (guardar BF)']],
            10 => ['periodo' => 'DIA DAS CRIANÇAS / PRÉ-BLACK FRIDAY', 'foco' => 'Linha infantil, preparar BF', 'oportunidades' => ['Produtos kids', 'Reposição estoque', 'Aquecimento base'], 'evitar' => ['Queimar promoções antes BF']],
            11 => ['periodo' => 'BLACK FRIDAY', 'foco' => 'Maior evento de vendas', 'oportunidades' => ['Descontos agressivos', 'Kits exclusivos BF', 'Frete grátis'], 'evitar' => ['Descontos falsos', 'Estoque insuficiente']],
            12 => ['periodo' => 'NATAL / FIM DE ANO', 'foco' => 'Presentes, última chance do ano', 'oportunidades' => ['Kits presenteáveis', 'Embalagens natalinas', 'Garantia entrega'], 'evitar' => ['Promoções que canibalizam margem']]
        ];

        return $contextos[$mes] ?? $contextos[7];
    }

    public static function getSuccessRatesByCategory(): string
    {
        return <<<'RATES'
## 📊 TAXAS DE SUCESSO HISTÓRICAS [MELHORIA 4]

| Categoria | Taxa Implementação | Taxa Sucesso | Recomendação |
|-----------|-------------------|--------------|--------------|
| inventory | 78% | 65% | ⭐ ALTA PRIORIDADE |
| pricing | 45% | 72% | Quando implementado, funciona |
| product | 62% | 58% | Kits têm boa adesão |
| customer | 35% | 80% | Difícil mas muito eficaz |
| conversion | 55% | 60% | Resultados moderados |
| marketing | 62% | 48% | Resultado variável |
| coupon | 70% | 45% | Pode viciar cliente |
| operational | 40% | 70% | Requer mudança processo |

**USE:** taxas da coluna "Taxa Sucesso" para calcular ROI conservador
RATES;
    }

    public static function getPlatformResources(): string
    {
        return <<<'RESOURCES'
## 🔧 RECURSOS NUVEMSHOP [MELHORIA 2]

### ✅ NATIVOS (gratuitos)
Cupons, Frete grátis condicional, Avise-me, Produtos relacionados, SEO básico, Checkout transparente

### 📦 APPS (custo mensal)
- Quiz: R$ 30-100/mês (Pregão, Lily AI)
- Fidelidade: R$ 49-150/mês (Fidelizar+)
- Reviews: R$ 20-80/mês (Lily Reviews)
- Carrinho abandonado: R$ 30-100/mês (CartStack)
- Chat/WhatsApp: R$ 0-100/mês (JivoChat)
- Assinatura: R$ 50-150/mês (Vindi)

### ❌ NÃO DISPONÍVEIS
Realidade aumentada, IA generativa nativa, Live commerce nativo

**REGRA:** Sempre verificar viabilidade antes de sugerir!
RESOURCES;
    }

    public static function getUnexploredAngles(): string
    {
        return <<<'ANGLES'
## 💡 ÂNGULOS NÃO EXPLORADOS [MELHORIA 1]

Quando temas comuns (quiz, frete, fidelidade, kits, estoque) estão SATURADOS:

### Aquisição Criativa
1. Programa de Indicação/Referral
2. Parceria com Salões (B2B)
3. Micro-influenciadores do nicho
4. Live Commerce
5. UGC (reviews com fotos)

### Monetização Diferente
6. Precificação Dinâmica
7. Modelo Freemium (amostra + completo)
8. Bundles Personalizados (cliente monta)
9. Pré-venda de Lançamentos
10. Programa de Troca (embalagem vazia)

### Experiência/Engajamento
11. Gamificação (pontos, níveis)
12. Comunidade WhatsApp/Telegram
13. Conteúdo Educativo Premium
14. Consultoria Virtual
15. Desafio de Transformação

### Diferenciação por Valores
16. Sustentabilidade
17. Causa Social
18. Transparência Total
19. Personalização por histórico
20. Atendimento Premium VIP

**USE quando temas tradicionais já foram sugeridos 3+ vezes**
ANGLES;
    }

    public static function getTemplate(): string
    {
        return <<<'PROMPT'
# STRATEGIST AGENT — GERAÇÃO DE SUGESTÕES ORIGINAIS

## SEU PAPEL
Gerar EXATAMENTE 9 sugestões estratégicas de alta qualidade, TODAS ORIGINAIS.

## DEFINIÇÃO DE REPETIÇÃO
Duas sugestões são REPETIDAS se:
- Têm o mesmo TEMA CENTRAL (quiz, frete, fidelidade, kits, etc.)
- Propõem a MESMA SOLUÇÃO para o mesmo problema
- Diferem apenas em palavras mas a essência é igual

---

## 🚫 ZONAS PROIBIDAS

{{prohibited_suggestions}}

### TEMAS SATURADOS:
{{saturated_themes}}

---

## 📅 CONTEXTO SAZONAL [MELHORIA 3]

{{seasonality_context}}

---

{{success_rates}}

---

{{platform_resources}}

---

{{unexplored_angles}}

---

## DISTRIBUIÇÃO OBRIGATÓRIA
- 3 HIGH (prioridades 1-3): Citar dados externos obrigatório
- 3 MEDIUM (prioridades 4-6): Otimizações
- 3 LOW (prioridades 7-9): Quick-wins

---

## DADOS DA ANÁLISE

### Contexto da Loja
{{store_context}}

### Análise do Analyst
{{analyst_analysis}}

### Dados de Concorrentes
{{competitor_data}}

### Dados de Mercado
{{market_data}}

### Estratégias RAG
{{rag_strategies}}

---

## CHECKLIST ANTES DE FINALIZAR

□ Sugestão aparece em ZONAS PROIBIDAS? → DESCARTE
□ Tema já sugerido antes? → DESCARTE
□ Apenas reformulação? → DESCARTE
□ Faz sentido para o momento sazonal? → Se não, RECONSIDERE
□ É viável na Nuvemshop? → Verificar recursos

---

## FORMATO DE SAÍDA

```json
{
  "originality_check": {
    "prohibited_suggestions_count": <número>,
    "themes_avoided": ["tema1", "tema2"],
    "new_angles_explored": ["ângulo1", "ângulo2"]
  },
  "contexto_analise": {
    "momento_mercado": "string",
    "momento_sazonal": "string",
    "posicao_competitiva": "string",
    "principais_problemas": ["array"],
    "principais_oportunidades": ["array"]
  },
  "suggestions": [
    {
      "priority": 1-9,
      "expected_impact": "high|medium|low",
      "category": "string",
      "title": "string ÚNICO",
      "problem_addressed": "string",
      "description": "string",
      "recommended_action": "passos numerados",
      "data_justification": {
        "fonte": "analyst|mercado|concorrente|benchmark|rag",
        "dado_especifico": "string",
        "conexao": "string"
      },
      "competitive_reference": {
        "concorrente": "string ou null",
        "o_que_faz": "string ou null",
        "como_aplicar": "string ou null"
      },
      "implementation": {
        "platform": "nuvemshop",
        "type": "nativo|app|terceiro|desenvolvimento",
        "app_sugerido": "nome se aplicável",
        "complexity": "baixa|media|alta",
        "cost": "string",
        "tempo_implementacao": "string"
      },
      "roi_estimate": {
        "base": "faturamento mensal",
        "premissa": "usar taxas da tabela",
        "calculo": "fórmula",
        "potencial_mensal": "R$ X/mês",
        "payback": "string"
      },
      "confidence": {
        "score": 0-100,
        "factors": {
          "data_quality": "alta|media|baixa",
          "market_data": "alta|media|baixa",
          "historical_success": "alta|media|baixa"
        }
      },
      "seasonality_fit": {
        "relevante_para_momento": true|false,
        "justificativa": "string"
      },
      "similarity_check": {
        "is_original": true,
        "similar_to_prohibited": null,
        "differentiation": "string"
      },
      "target_metrics": ["array"],
      "riscos": ["array"],
      "quick_win": true|false
    }
  ]
}
```

---

PORTUGUÊS BRASILEIRO
PROMPT;
    }

    public static function formatProhibitedSuggestions(array $previousSuggestions): string
    {
        if (empty($previousSuggestions)) {
            return "Nenhuma sugestão anterior. Liberdade total, mas busque originalidade.";
        }

        $grouped = [];
        $titleCounts = [];

        foreach ($previousSuggestions as $s) {
            $cat = $s['category'] ?? 'outros';
            $title = $s['title'] ?? 'Sem título';
            $titleCounts[$title] = ($titleCounts[$title] ?? 0) + 1;
            if (!isset($grouped[$cat])) $grouped[$cat] = [];
            if (!in_array($title, $grouped[$cat])) $grouped[$cat][] = $title;
        }

        $output = "### Total: " . count($previousSuggestions) . " sugestões anteriores\n\n";
        foreach ($grouped as $cat => $titles) {
            $output .= "**{$cat}:**\n";
            foreach ($titles as $t) {
                $c = $titleCounts[$t];
                $m = $c >= 3 ? "🔴" : ($c >= 2 ? "⚠️" : "•");
                $output .= "{$m} {$t}" . ($c > 1 ? " ({$c}x)" : "") . "\n";
            }
            $output .= "\n";
        }
        return $output;
    }

    public static function identifySaturatedThemes(array $previousSuggestions): string
    {
        if (empty($previousSuggestions)) return "Nenhum tema saturado.";

        $keywords = [
            'Quiz/Personalização' => ['quiz', 'questionário', 'personalizado'],
            'Frete Grátis' => ['frete grátis', 'frete gratuito'],
            'Fidelidade' => ['fidelidade', 'pontos', 'cashback'],
            'Kits/Combos' => ['kit', 'combo', 'bundle', 'cronograma'],
            'Estoque' => ['estoque', 'avise-me', 'reposição'],
            'Email' => ['email', 'newsletter', 'automação'],
            'Vídeos' => ['vídeo', 'tutorial', 'youtube'],
            'Assinatura' => ['assinatura', 'recorrência'],
        ];

        $counts = [];
        foreach ($previousSuggestions as $s) {
            $text = mb_strtolower(($s['title'] ?? '') . ' ' . ($s['description'] ?? ''));
            foreach ($keywords as $theme => $kws) {
                foreach ($kws as $kw) {
                    if (strpos($text, $kw) !== false) {
                        $counts[$theme] = ($counts[$theme] ?? 0) + 1;
                        break;
                    }
                }
            }
        }

        $saturated = array_filter($counts, fn($c) => $c >= 3);
        arsort($saturated);

        if (empty($saturated)) return "Nenhum tema saturado (3+).";

        $out = "";
        foreach ($saturated as $t => $c) {
            $out .= "🔴 **{$t}**: {$c}x — EVITAR\n";
        }
        return $out;
    }

    public static function build(array $context): string
    {
        $template = self::getTemplate();
        $season = self::getSeasonalityContext();

        $seasonCtx = "**Período:** {$season['periodo']}\n";
        $seasonCtx .= "**Foco:** {$season['foco']}\n";
        $seasonCtx .= "**Oportunidades:** " . implode(', ', $season['oportunidades']) . "\n";
        $seasonCtx .= "**Evitar:** " . implode(', ', $season['evitar']);

        // Mapear nomes do pipeline para nomes esperados pelo template
        $storeContext = $context['store_context'] ?? $context['collector_context'] ?? [];
        $analystAnalysis = $context['analyst_analysis'] ?? $context['analysis'] ?? [];
        $externalData = $context['external_data'] ?? [];
        $competitorData = $context['competitor_data'] ?? $externalData['concorrentes'] ?? [];
        $marketData = $context['market_data'] ?? $externalData['dados_mercado'] ?? [];

        $replacements = [
            '{{prohibited_suggestions}}' => self::formatProhibitedSuggestions($context['previous_suggestions'] ?? []),
            '{{saturated_themes}}' => self::identifySaturatedThemes($context['previous_suggestions'] ?? []),
            '{{seasonality_context}}' => $seasonCtx,
            '{{success_rates}}' => self::getSuccessRatesByCategory(),
            '{{platform_resources}}' => self::getPlatformResources(),
            '{{unexplored_angles}}' => self::getUnexploredAngles(),
            '{{store_context}}' => json_encode($storeContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            '{{analyst_analysis}}' => json_encode($analystAnalysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            '{{competitor_data}}' => json_encode($competitorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            '{{market_data}}' => json_encode($marketData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            '{{rag_strategies}}' => json_encode($context['rag_strategies'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ];

        foreach ($replacements as $k => $v) {
            $template = str_replace($k, $v, $template);
        }

        return $template;
    }

    /**
     * Método get() para manter compatibilidade com o pipeline existente.
     * Redireciona para o novo método build().
     */
    public static function get(array $context): string
    {
        return self::build($context);
    }
}
