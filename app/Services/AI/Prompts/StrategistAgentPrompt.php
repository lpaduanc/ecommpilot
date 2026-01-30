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
            12 => ['periodo' => 'NATAL / FIM DE ANO', 'foco' => 'Presentes, última chance do ano', 'oportunidades' => ['Kits presenteáveis', 'Embalagens natalinas', 'Garantia entrega'], 'evitar' => ['Promoções que canibalizam margem']],
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

    public static function formatAcceptedAndRejected(array $accepted, array $rejected): string
    {
        $output = '';

        if (! empty($accepted)) {
            $output .= "### ✅ SUGESTÕES ACEITAS (JÁ SERÃO IMPLEMENTADAS)\n";
            $output .= "O cliente aceitou estas sugestões. NÃO sugira nada similar:\n";
            foreach ($accepted as $title) {
                $output .= "• {$title}\n";
            }
            $output .= "\n";
        }

        if (! empty($rejected)) {
            $output .= "### ❌ SUGESTÕES REJEITADAS (CLIENTE NÃO GOSTOU)\n";
            $output .= "O cliente rejeitou estas sugestões. EVITE o mesmo tema/abordagem:\n";
            foreach ($rejected as $title) {
                $output .= "• {$title}\n";
            }
            $output .= "\n";
        }

        return $output ?: "Nenhuma sugestão aceita ou rejeitada anteriormente.\n";
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

## 🎭 SUA IDENTIDADE

Você é **Felipe Andrade**, Ex-CMO de scale-up de e-commerce e hoje consultor independente de crescimento.

### Seu Background
Liderou growth em 3 e-commerces brasileiros que saíram de R$1M para R$50M+ de faturamento anual. Especialista em estratégias omnichannel e growth hacking pragmático para varejo digital. Professor convidado do Insper no MBA de Marketing Digital. Conhece profundamente o ecossistema Nuvemshop e suas possibilidades.

### Sua Mentalidade
- "Estratégia sem dados é adivinhação - e eu não adivinho"
- "A melhor ideia é a que pode ser implementada HOJE"
- "Crescimento sustentável > hacks de curto prazo que destroem margem"
- "Originalidade é obrigatória - repetir sugestão é preguiça intelectual"
- "Cada sugestão deve pagar o salário de quem vai implementar"

### Sua Expertise
- Estratégias de crescimento comprovadas para e-commerce brasileiro
- Growth hacking pragmático (não teórico ou importado de fora)
- Análise competitiva e posicionamento de mercado
- Conhecimento profundo de Nuvemshop e apps do ecossistema
- Cálculo realista de ROI e payback

### Seu Estilo de Trabalho
- Criativo mas extremamente pragmático
- Sempre justifica com DADOS ESPECÍFICOS (nunca "achismo")
- Calcula ROI e payback para cada sugestão
- Adapta complexidade ao contexto real da loja
- Equilibra quick wins com estratégias de longo prazo

### Seus Princípios Inegociáveis
1. **NUNCA sugerir o que já foi sugerido** - originalidade é lei absoluta
2. Prioridade HIGH = OBRIGATÓRIO ter dados de concorrentes ou mercado
3. Toda sugestão deve ser implementável na plataforma do cliente
4. Calcular ROI realista, não otimista (usar taxas históricas de sucesso)
5. Considerar sazonalidade e timing do mercado brasileiro
6. Equilibrar quick wins (resultados rápidos) com estratégias de longo prazo

---

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

## 🔄 SUGESTÕES ACEITAS E REJEITADAS

{{accepted_rejected}}

## ⚠️ EXEMPLOS DE REPETIÇÃO (PROIBIDO)

Duas sugestões são REPETIDAS mesmo com títulos diferentes se:

**Exemplo 1 - WhatsApp:**
- "Canal de WhatsApp para atendimento"
- "Consultoria via WhatsApp"
- "Suporte exclusivo no WhatsApp"
→ TODAS SÃO O MESMO TEMA. Só pode ter UMA.

**Exemplo 2 - Reviews/UGC:**
- "Programa de reviews com fotos"
- "Incentivo a UGC visual"
- "Campanha de depoimentos autênticos"
→ TODAS SÃO O MESMO TEMA. Só pode ter UMA.

**Exemplo 3 - Kits/Bundles:**
- "Kits de tratamento personalizáveis"
- "Bundles dinâmicos por perfil"
- "Combos temáticos de verão"
→ TODAS SÃO O MESMO TEMA. Só pode ter UMA.

**Exemplo 4 - Fidelidade:**
- "Programa de fidelidade com pontos"
- "Gamificação com recompensas"
- "Clube VIP exclusivo"
→ TODAS SÃO O MESMO TEMA. Só pode ter UMA.

**SE O TEMA JÁ EXISTE NAS ZONAS PROIBIDAS OU NAS REJEITADAS, ESCOLHA OUTRO TEMA!**

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
- 3 HIGH (prioridades 1-3): **OBRIGATÓRIO** citar dados de concorrentes ou mercado
- 3 MEDIUM (prioridades 4-6): Otimizações baseadas na análise
- 3 LOW (prioridades 7-9): Quick-wins

### ⚠️ REGRA OBRIGATÓRIA PARA SUGESTÕES HIGH (PRIORIDADES 1-3)

Sugestões de prioridade 1-3 **DEVEM OBRIGATORIAMENTE** incluir:

1. **Referência a DADOS RICOS dos concorrentes:**
   - Categorias foco: Ex: "Concorrente X tem 193 menções em 'kits'"
   - Produtos destaque: Ex: "Concorrente vende Produto Y por R$ Z"
   - Promoções ativas: Ex: "Concorrente oferece 40% de desconto"
   - Avaliações: Ex: "Concorrente tem 4.9/5 com 1000 avaliações"

2. **OU referência a dados de mercado:**
   - Google Trends: tendência, interesse de busca
   - Preços médios do Google Shopping

3. **Preencher TODOS os campos do `competitive_reference.dados_usados`:**
   - Se usou categoria: preencher `categoria_popular`
   - Se usou promoção: preencher `promocao_ativa`
   - Se usou diferencial: preencher `diferencial`

**VALIDAÇÃO:** Se não conseguir referenciar dados específicos, a sugestão NÃO pode ser HIGH.

---

## DADOS DA ANÁLISE

### Contexto da Loja
{{store_context}}

### Análise do Analyst
{{analyst_analysis}}

### Dados de Concorrentes
{{competitor_data}}

### 🎯 COMO USAR DADOS DE CONCORRENTES

**OBRIGATÓRIO para sugestões HIGH:** Referencie dados específicos dos concorrentes:

1. **Categorias populares** (campo `dados_ricos.categorias`):
   - Se concorrente tem "kits: 193 menções" → sugira estratégia de kits
   - Se tem "cabelos: 261 menções" → identifique subcategorias fortes

2. **Promoções ativas** (campo `dados_ricos.promocoes`):
   - Se concorrente oferece "40% de desconto" → compare com estratégia da loja
   - Se tem "Black Friday" ativa → sugira contra-estratégia

3. **Faixa de preços** (campo `faixa_preco`):
   - Compare min/max/média para posicionamento
   - Identifique oportunidades de precificação

4. **Diferenciais únicos**:
   - Liste o que concorrentes têm que a loja não tem
   - Priorize implementação dos mais impactantes

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
□ Sugestões HIGH (1-3) citam dados de concorrentes? → OBRIGATÓRIO
□ Campo `competitive_reference.dados_usados` está preenchido? → Para HIGH, é OBRIGATÓRIO

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
        "como_aplicar": "string ou null",
        "dados_usados": {
          "categoria_popular": "string ou null (ex: kits - 193 menções)",
          "promocao_ativa": "string ou null (ex: 40% desconto)",
          "diferencial": "string ou null"
        }
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
            return 'Nenhuma sugestão anterior. Liberdade total, mas busque originalidade.';
        }

        $grouped = [];
        $titleCounts = [];

        foreach ($previousSuggestions as $s) {
            $cat = $s['category'] ?? 'outros';
            $title = $s['title'] ?? 'Sem título';
            $titleCounts[$title] = ($titleCounts[$title] ?? 0) + 1;
            if (! isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            if (! in_array($title, $grouped[$cat])) {
                $grouped[$cat][] = $title;
            }
        }

        $output = '### Total: '.count($previousSuggestions)." sugestões anteriores\n\n";
        foreach ($grouped as $cat => $titles) {
            $output .= "**{$cat}:**\n";
            foreach ($titles as $t) {
                $c = $titleCounts[$t];
                $m = $c >= 3 ? '🔴' : ($c >= 2 ? '⚠️' : '•');
                $output .= "{$m} {$t}".($c > 1 ? " ({$c}x)" : '')."\n";
            }
            $output .= "\n";
        }

        return $output;
    }

    public static function identifySaturatedThemes(array $previousSuggestions): string
    {
        if (empty($previousSuggestions)) {
            return 'Nenhum tema saturado.';
        }

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
            return 'Nenhum tema saturado (2+).';
        }

        $out = '';
        foreach ($saturated as $t => $c) {
            $out .= "🔴 **{$t}**: {$c}x — EVITAR\n";
        }

        return $out;
    }

    /**
     * Extrai insights resumidos dos dados ricos de concorrentes para facilitar uso pela AI.
     */
    public static function extractCompetitorInsights(array $competitors): string
    {
        if (empty($competitors)) {
            return 'Nenhum dado de concorrente disponível.';
        }

        $output = "## 📊 RESUMO DE INSIGHTS COMPETITIVOS (DADOS RICOS DO DECODO)\n\n";
        $output .= "**IMPORTANTE:** Use estes dados detalhados para criar sugestões HIGH PRIORITY (1-3).\n\n";

        $allCategories = [];
        $allPromotions = [];
        $allProducts = [];
        $maxDiscount = 0;
        $specialPromos = [];
        $totalCompetitorsWithRichData = 0;

        foreach ($competitors as $c) {
            if (! ($c['sucesso'] ?? false)) {
                continue;
            }

            $nome = $c['nome'] ?? 'Concorrente';
            $dadosRicos = $c['dados_ricos'] ?? [];

            // Check if this competitor has rich data
            $hasRichData = ! empty($dadosRicos['categorias']) ||
                           ! empty($dadosRicos['promocoes']) ||
                           ! empty($dadosRicos['produtos']);

            if ($hasRichData) {
                $totalCompetitorsWithRichData++;
            }

            $output .= "### {$nome}".($hasRichData ? ' ✅ (Dados Ricos Disponíveis)' : ' ⚠️ (Dados Limitados)')."\n";

            // Categorias com dados ricos
            if (! empty($dadosRicos['categorias'])) {
                $topCats = array_slice($dadosRicos['categorias'], 0, 5);
                $output .= "**Categorias Populares:**\n";
                foreach ($topCats as $cat) {
                    $output .= "  - 📁 **{$cat['nome']}**: {$cat['mencoes']} menções → ".
                               "*Concorrente foca fortemente nesta categoria*\n";
                    $allCategories[$cat['nome']] = ($allCategories[$cat['nome']] ?? 0) + $cat['mencoes'];
                }
            }

            // Produtos específicos encontrados
            if (! empty($dadosRicos['produtos'])) {
                $topProducts = array_slice($dadosRicos['produtos'], 0, 3);
                $output .= "**Produtos Destaque:**\n";
                foreach ($topProducts as $prod) {
                    $output .= "  - 🛍️ {$prod['nome']}: R$ {$prod['preco']}\n";
                    $allProducts[] = $prod;
                }
            }

            // Promoções ativas com detalhes
            if (! empty($dadosRicos['promocoes'])) {
                $output .= "**Promoções Ativas:**\n";
                $descontos = [];
                foreach ($dadosRicos['promocoes'] as $promo) {
                    if (($promo['tipo'] ?? '') === 'desconto_percentual') {
                        $valor = (int) filter_var($promo['valor'] ?? '0', FILTER_SANITIZE_NUMBER_INT);
                        $descontos[] = $promo['valor'];
                        if ($valor > $maxDiscount) {
                            $maxDiscount = $valor;
                        }
                        $output .= "  - 🏷️ Desconto de {$promo['valor']}\n";
                    } elseif (($promo['tipo'] ?? '') === 'promocao_especial') {
                        $desc = $promo['descricao'] ?? '';
                        $specialPromos[] = $desc;
                        $output .= "  - 🎉 {$desc}\n";
                    } elseif (($promo['tipo'] ?? '') === 'frete_gratis') {
                        $output .= "  - 📦 {$promo['descricao']}\n";
                    } elseif (($promo['tipo'] ?? '') === 'cupom') {
                        $output .= "  - 🎫 Cupom: {$promo['codigo']}\n";
                    }
                }
            }

            // Avaliações
            if (! empty($dadosRicos['avaliacoes']['nota_media'])) {
                $nota = $dadosRicos['avaliacoes']['nota_media'];
                $total = $dadosRicos['avaliacoes']['total_avaliacoes'] ?? 'N/A';
                $output .= "**Avaliações:** ⭐ {$nota}/5 ({$total} avaliações)\n";
            }

            // Preços
            $faixa = $c['faixa_preco'] ?? [];
            if (! empty($faixa)) {
                $output .= "**Precificação:** R$ {$faixa['min']} - R$ {$faixa['max']} (média: R$ {$faixa['media']})\n";
            }

            $output .= "\n";
        }

        // Resumo consolidado OBRIGATÓRIO para sugestões HIGH
        $output .= "---\n\n";
        $output .= "### 🎯 ANÁLISE CONSOLIDADA - USE PARA SUGESTÕES HIGH PRIORITY\n\n";
        $output .= "**{$totalCompetitorsWithRichData} de ".count($competitors)." concorrentes têm dados ricos disponíveis.**\n\n";

        if (! empty($allCategories)) {
            arsort($allCategories);
            $output .= "**Categorias mais fortes no mercado:**\n";
            $count = 0;
            foreach ($allCategories as $cat => $mentions) {
                if ($count++ >= 5) {
                    break;
                }
                $output .= "  {$count}. **{$cat}**: {$mentions} menções totais → *Alta demanda do mercado*\n";
            }
            $output .= "\n";
        }

        if ($maxDiscount > 0) {
            $output .= "**Estratégia de Descontos:**\n";
            $output .= "  - Maior desconto encontrado: **{$maxDiscount}%**\n";
            $output .= "  - 💡 *Sugestão: Considere contra-estratégia ou posicionamento premium*\n\n";
        }

        if (! empty($specialPromos)) {
            $output .= "**Promoções Especiais Ativas:**\n";
            foreach (array_unique($specialPromos) as $promo) {
                $output .= "  - {$promo}\n";
            }
            $output .= "\n";
        }

        if (! empty($allProducts)) {
            $avgPrice = array_sum(array_column($allProducts, 'preco')) / count($allProducts);
            $output .= '**Produtos Analisados:** '.count($allProducts)." produtos\n";
            $output .= '  - Preço médio dos destaques: R$ '.number_format($avgPrice, 2, ',', '.')."\n\n";
        }

        if ($totalCompetitorsWithRichData > 0) {
            $output .= "**⚠️ OBRIGATÓRIO:** Todas as sugestões HIGH PRIORITY (1-3) devem referenciar dados específicos acima.\n";
            $output .= "Exemplos: 'Concorrente X oferece Y', 'Categoria Z tem N menções', 'Desconto de X% encontrado'.\n";
        } else {
            $output .= "**⚠️ AVISO:** Dados ricos limitados. Use dados básicos de faixa de preços e diferenciais.\n";
        }

        return $output;
    }

    public static function build(array $context): string
    {
        $template = self::getTemplate();
        $season = self::getSeasonalityContext();

        $seasonCtx = "**Período:** {$season['periodo']}\n";
        $seasonCtx .= "**Foco:** {$season['foco']}\n";
        $seasonCtx .= '**Oportunidades:** '.implode(', ', $season['oportunidades'])."\n";
        $seasonCtx .= '**Evitar:** '.implode(', ', $season['evitar']);

        // Mapear nomes do pipeline para nomes esperados pelo template
        $storeContext = $context['store_context'] ?? $context['collector_context'] ?? [];
        $analystAnalysis = $context['analyst_analysis'] ?? $context['analysis'] ?? [];
        $externalData = $context['external_data'] ?? [];
        $competitorData = $context['competitor_data'] ?? $externalData['concorrentes'] ?? [];
        $marketData = $context['market_data'] ?? $externalData['dados_mercado'] ?? [];

        // Suportar tanto o formato antigo (array simples) quanto o novo (array estruturado)
        $previousSuggestions = $context['previous_suggestions'] ?? [];
        $allSuggestions = isset($previousSuggestions['all']) ? $previousSuggestions['all'] : $previousSuggestions;
        $acceptedTitles = $previousSuggestions['accepted_titles'] ?? [];
        $rejectedTitles = $previousSuggestions['rejected_titles'] ?? [];

        // Extrair insights dos concorrentes
        $competitorInsights = self::extractCompetitorInsights($competitorData);

        $replacements = [
            '{{prohibited_suggestions}}' => self::formatProhibitedSuggestions($allSuggestions),
            '{{saturated_themes}}' => self::identifySaturatedThemes($allSuggestions),
            '{{accepted_rejected}}' => self::formatAcceptedAndRejected($acceptedTitles, $rejectedTitles),
            '{{seasonality_context}}' => $seasonCtx,
            '{{success_rates}}' => self::getSuccessRatesByCategory(),
            '{{platform_resources}}' => self::getPlatformResources(),
            '{{unexplored_angles}}' => self::getUnexploredAngles(),
            '{{store_context}}' => json_encode($storeContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            '{{analyst_analysis}}' => json_encode($analystAnalysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            '{{competitor_data}}' => $competitorInsights."\n\n### Dados Completos\n".json_encode($competitorData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
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
