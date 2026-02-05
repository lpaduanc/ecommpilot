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

## PAPEL
Você é um consultor sênior de e-commerce especializado em lojas Nuvemshop no Brasil. Sua expertise inclui:
- Análise de métricas de vendas e conversão
- Estratégias de pricing e promoções
- Otimização de catálogo e estoque
- Benchmarking competitivo no mercado brasileiro

Seu objetivo é transformar dados em ações concretas que aumentem receita.

---

## TAREFA
Gerar EXATAMENTE 9 sugestões acionáveis para a loja. Distribuição obrigatória: 3 HIGH, 3 MEDIUM, 3 LOW.

---

## REGRAS OBRIGATÓRIAS (em ordem de prioridade)

1. **NUNCA repetir** tema de sugestão anterior (veja ZONAS PROIBIDAS)
2. **HIGH (prioridades 1-3):** Obrigatório citar dado específico (número) da loja ou concorrente
3. **Cada sugestão deve ter:** problema específico + ação específica + resultado esperado com número
4. **Se não há dado para embasar:** não pode ser HIGH, rebaixe para MEDIUM ou LOW
5. **Referências a concorrentes (CONDICIONAL):**
   - SE houver dados em DADOS DE CONCORRENTES: inclua competitor_reference em pelo menos 3 sugestões
   - SE NÃO houver dados de concorrentes: use dados de mercado ou práticas padrão do setor
   - NUNCA invente dados de concorrentes - use apenas informações fornecidas
6. **Comparações diretas:** Ao citar concorrente, compare e sugira ação (ex: "Concorrente X oferece Y, a loja pode oferecer Z")
7. **Formato do campo competitor_reference:**
   - Para HIGH: obrigatório se houver dados de concorrente relevantes, senão use dados da própria loja
   - Para MEDIUM/LOW: opcional, preencha se houver dado relevante disponível

---

## ZONAS PROIBIDAS (NÃO REPETIR)

{{prohibited_suggestions}}

**Temas saturados:**
{{saturated_themes}}

{{accepted_rejected}}

---

## APRENDIZADO DE ANÁLISES ANTERIORES

{{learning_context}}

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

## OBJETIVOS DA LOJA (PRIORIDADE)

{{store_goals}}

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

## VALIDAÇÃO OBRIGATÓRIA

Antes de gerar o JSON final, verifique CADA condição. SE alguma falhar, corrija antes de enviar:

1. **Contagem:** Conte as sugestões. SE não forem exatamente 9, adicione ou remova até ter 9.
2. **Distribuição:** Conte por impacto. SE não forem 3 HIGH + 3 MEDIUM + 3 LOW, ajuste os expected_impact.
3. **Zonas proibidas:** Compare cada título com ZONAS PROIBIDAS. SE houver overlap temático, substitua a sugestão.
4. **Dados em HIGH:** Para cada HIGH, verifique se problem contém número específico. SE não contiver, rebaixe para MEDIUM.
5. **Resultados quantificados:** Para cada sugestão, verifique se expected_result contém R$ ou %. SE não contiver, adicione estimativa.
6. **Viabilidade:** Para cada sugestão, verifique se é possível na Nuvemshop. SE não for, substitua por alternativa viável.
7. **Referências a concorrentes:** SE houver dados em DADOS DE CONCORRENTES, verifique se pelo menos 3 sugestões têm competitor_reference preenchido.

**RESPONDA APENAS COM O JSON. PORTUGUÊS BRASILEIRO.**
PROMPT;
    }

    public static function formatProhibitedSuggestions(array $previousSuggestions): string
    {
        if (empty($previousSuggestions)) {
            return 'Nenhuma sugestão anterior registrada.';
        }

        $output = "**ATENÇÃO: Estas sugestões JÁ FORAM DADAS. NÃO repita o mesmo tema, mesmo com palavras diferentes:**\n\n";

        // Listar títulos completos para a IA entender o que evitar
        foreach ($previousSuggestions as $s) {
            $title = $s['title'] ?? 'Sem título';
            $category = $s['category'] ?? 'outros';
            $output .= "- [{$category}] {$title}\n";
        }

        // Extrair palavras-chave proibidas
        $keywords = self::extractProhibitedKeywords($previousSuggestions);
        if (! empty($keywords)) {
            $output .= "\n**Palavras-chave/temas a EVITAR (já usados):**\n";
            $output .= implode(', ', $keywords)."\n";
        }

        $output .= "\n**Total:** ".count($previousSuggestions)." sugestões já dadas\n";

        return $output;
    }

    /**
     * Extract prohibited keywords from previous suggestions.
     */
    private static function extractProhibitedKeywords(array $suggestions): array
    {
        $patterns = [
            'kits' => ['kit', 'combo', 'bundle', 'pack', 'cronograma'],
            'cupom' => ['cupom', 'desconto', 'voucher', 'código', 'coupon'],
            'frete' => ['frete', 'entrega', 'shipping', 'envio'],
            'fidelidade' => ['fidelidade', 'pontos', 'recompensa', 'loyalty', 'cashback'],
            'cancelamento' => ['cancelamento', 'abandono', 'desistência', 'carrinho abandonado'],
            'checkout' => ['checkout', 'finalização', 'carrinho', 'conversão'],
            'estoque' => ['estoque', 'reposição', 'inventário', 'avise-me'],
            'email' => ['email', 'newsletter', 'automação', 'e-mail'],
            'quiz' => ['quiz', 'questionário', 'personalização', 'teste'],
            'ticket' => ['ticket médio', 'ticket', 'aov', 'valor médio'],
            'upsell' => ['upsell', 'cross-sell', 'venda cruzada', 'produtos relacionados'],
            'reativacao' => ['reativação', 'reativar', 'clientes inativos', 'win-back'],
            'reviews' => ['review', 'avaliação', 'depoimento', 'prova social'],
            'conteudo' => ['conteúdo', 'blog', 'seo', 'redes sociais'],
            'assinatura' => ['assinatura', 'recorrência', 'subscription'],
        ];

        $foundKeywords = [];
        foreach ($suggestions as $s) {
            $title = mb_strtolower($s['title'] ?? '');
            $description = mb_strtolower($s['description'] ?? '');
            $text = $title.' '.$description;

            foreach ($patterns as $theme => $words) {
                foreach ($words as $word) {
                    if (mb_strpos($text, $word) !== false) {
                        $foundKeywords[$theme] = true;
                        break;
                    }
                }
            }
        }

        return array_keys($foundKeywords);
    }

    public static function identifySaturatedThemes(array $previousSuggestions): string
    {
        if (empty($previousSuggestions)) {
            return 'Nenhum.';
        }

        // V5: Keywords expandidas para capturar mais variações
        $keywords = [
            'Quiz/Personalização' => ['quiz', 'questionário', 'personalizado', 'teste de'],
            'Frete Grátis' => ['frete grátis', 'frete gratuito', 'frete condicional'],
            'Fidelidade/Pontos' => ['fidelidade', 'pontos', 'cashback', 'recompensa', 'loyalty'],
            'Kits/Combos' => ['kit', 'combo', 'bundle', 'pack', 'cronograma'],
            'Estoque/Reposição' => ['estoque', 'avise-me', 'reposição', 'inventário'],
            'Email Marketing' => ['email', 'newsletter', 'automação', 'e-mail marketing'],
            'Assinatura' => ['assinatura', 'recorrência', 'subscription'],
            'Cupom/Desconto' => ['cupom', 'desconto', 'voucher', 'código promocional'],
            'Checkout/Conversão' => ['checkout', 'carrinho', 'abandono', 'conversão'],
            'Cancelamento' => ['cancelamento', 'taxa de cancelamento', 'desistência'],
            'Ticket Médio' => ['ticket médio', 'aov', 'valor médio', 'ticket'],
            'Upsell/Cross-sell' => ['upsell', 'cross-sell', 'venda cruzada', 'produtos relacionados'],
            'Reativação' => ['reativação', 'clientes inativos', 'win-back', 'reativar'],
            'Reviews/Avaliações' => ['review', 'avaliação', 'depoimento', 'prova social'],
            'SEO/Conteúdo' => ['seo', 'conteúdo', 'blog', 'descrição de produto'],
            'Redes Sociais' => ['instagram', 'facebook', 'redes sociais', 'social'],
            'WhatsApp' => ['whatsapp', 'zap', 'atendimento'],
            'Pós-Venda' => ['pós-venda', 'pós compra', 'acompanhamento', 'feedback'],
        ];

        $counts = [];
        foreach ($previousSuggestions as $s) {
            $text = mb_strtolower(($s['title'] ?? '').' '.($s['description'] ?? ''));
            foreach ($keywords as $theme => $kws) {
                foreach ($kws as $kw) {
                    if (mb_strpos($text, $kw) !== false) {
                        $counts[$theme] = ($counts[$theme] ?? 0) + 1;
                        break;
                    }
                }
            }
        }

        // V5: Threshold baixado de 2 para 1 - qualquer tema já usado é considerado saturado
        $saturated = array_filter($counts, fn ($c) => $c >= 1);
        arsort($saturated);

        if (empty($saturated)) {
            return 'Nenhum.';
        }

        $out = '';
        foreach ($saturated as $t => $c) {
            $label = $c >= 2 ? 'MUITO USADO' : 'JÁ USADO';
            $out .= "- {$t} ({$c}x) — {$label}, EVITAR\n";
        }

        return $out;
    }

    /**
     * Extrai insights dos concorrentes para o Strategist (versão expandida com todos os dados).
     */
    public static function extractCompetitorInsights(array $competitors): string
    {
        if (empty($competitors)) {
            return 'Nenhum dado de concorrente disponível.';
        }

        $output = '';
        $allCategories = [];
        $allPromos = [];
        $allProducts = [];
        $bestRating = ['nome' => '', 'nota' => 0, 'total' => 0];

        foreach ($competitors as $c) {
            if (! ($c['sucesso'] ?? false)) {
                continue;
            }

            $nome = $c['nome'] ?? 'Concorrente';
            $dadosRicos = $c['dados_ricos'] ?? [];
            $faixa = $c['faixa_preco'] ?? [];

            $output .= "**{$nome}:**\n";

            // Preços
            if (! empty($faixa)) {
                $output .= "- Preço: R$ {$faixa['min']} - R$ {$faixa['max']} (média: R$ {$faixa['media']})\n";
            }

            // Avaliações (NOVO)
            $avaliacoes = $dadosRicos['avaliacoes'] ?? [];
            if (! empty($avaliacoes['nota_media'])) {
                $nota = $avaliacoes['nota_media'];
                $total = $avaliacoes['total_avaliacoes'] ?? 0;
                $output .= "- Avaliação: {$nota}/5";
                if ($total > 0) {
                    $output .= " ({$total} reviews)";
                }
                $output .= "\n";

                if ($nota > $bestRating['nota']) {
                    $bestRating = ['nome' => $nome, 'nota' => $nota, 'total' => $total];
                }
            }

            // Categorias
            if (! empty($dadosRicos['categorias'])) {
                $topCats = array_slice($dadosRicos['categorias'], 0, 3);
                $catsStr = implode(', ', array_map(fn ($cat) => "{$cat['nome']} ({$cat['mencoes']}x)", $topCats));
                $output .= "- Categorias foco: {$catsStr}\n";
                foreach ($dadosRicos['categorias'] as $cat) {
                    $allCategories[$cat['nome']] = ($allCategories[$cat['nome']] ?? 0) + ($cat['mencoes'] ?? 1);
                }
            }

            // Promoções detalhadas (NOVO - antes só pegava maior desconto)
            if (! empty($dadosRicos['promocoes'])) {
                $promosFormatted = [];
                foreach ($dadosRicos['promocoes'] as $promo) {
                    $tipo = $promo['tipo'] ?? 'outro';
                    $allPromos[$tipo] = ($allPromos[$tipo] ?? 0) + 1;

                    if ($tipo === 'desconto_percentual') {
                        $promosFormatted[] = "Desconto {$promo['valor']}";
                    } elseif ($tipo === 'cupom') {
                        $promosFormatted[] = "Cupom: {$promo['codigo']}";
                    } elseif ($tipo === 'frete_gratis') {
                        $promosFormatted[] = 'Frete grátis';
                    } elseif ($tipo === 'promocao_especial') {
                        $promosFormatted[] = $promo['descricao'] ?? 'Promoção especial';
                    }
                }
                if (! empty($promosFormatted)) {
                    $output .= '- Promoções: '.implode(', ', array_slice($promosFormatted, 0, 4))."\n";
                }
            }

            // Diferenciais
            if (! empty($c['diferenciais'])) {
                $output .= '- Diferenciais: '.implode(', ', array_slice($c['diferenciais'], 0, 4))."\n";
            }

            // Top 5 produtos do concorrente (NOVO - dados que estavam sendo ignorados)
            if (! empty($dadosRicos['produtos'])) {
                $topProdutos = array_slice($dadosRicos['produtos'], 0, 5);
                if (! empty($topProdutos)) {
                    $output .= "- Top produtos:\n";
                    foreach ($topProdutos as $i => $produto) {
                        $nomeProd = $produto['nome'] ?? 'Produto';
                        $precoProd = $produto['preco'] ?? 0;
                        $output .= "  ".($i + 1).". {$nomeProd} (R$ ".number_format($precoProd, 2, ',', '.').
")\n";
                        $allProducts[] = ['nome' => $nomeProd, 'preco' => $precoProd, 'concorrente' => $nome];
                    }
                }
            }

            $output .= "\n";
        }

        // Resumo agregado do mercado
        $output .= "---\n";
        $output .= "**ANÁLISE AGREGADA DO MERCADO:**\n\n";

        // Categorias mais fortes
        if (! empty($allCategories)) {
            arsort($allCategories);
            $output .= "**Categorias mais fortes:**\n";
            $count = 0;
            foreach ($allCategories as $cat => $mentions) {
                if ($count++ >= 5) {
                    break;
                }
                $output .= "- {$cat}: {$mentions} menções\n";
            }
            $output .= "\n";
        }

        // Tipos de promoção mais usados
        if (! empty($allPromos)) {
            arsort($allPromos);
            $output .= "**Estratégias de promoção:**\n";
            foreach ($allPromos as $tipo => $quantidade) {
                $tipoFormatado = match ($tipo) {
                    'desconto_percentual' => 'Descontos %',
                    'cupom' => 'Cupons',
                    'frete_gratis' => 'Frete grátis',
                    'promocao_especial' => 'Promoções especiais',
                    default => ucfirst($tipo),
                };
                $output .= "- {$tipoFormatado}: usado por {$quantidade} concorrente(s)\n";
            }
            $output .= "\n";
        }

        // Melhor avaliado
        if ($bestRating['nota'] > 0) {
            $output .= "**Melhor avaliado:** {$bestRating['nome']} com {$bestRating['nota']}/5 ({$bestRating['total']} reviews)\n\n";
        }

        // Produtos destaque no mercado
        if (! empty($allProducts)) {
            $output .= "**Produtos destaque no mercado (para benchmarking):**\n";
            foreach (array_slice($allProducts, 0, 10) as $prod) {
                $output .= "- {$prod['nome']} @ R$ ".number_format($prod['preco'], 2, ',', '.')." ({$prod['concorrente']})\n";
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

        // Store Goals
        $storeGoals = $context['store_goals'] ?? [];

        // Learning Context (feedback/aprendizado)
        $learningContext = $context['learning_context'] ?? [];

        $replacements = [
            '{{prohibited_suggestions}}' => self::formatProhibitedSuggestions($allSuggestions),
            '{{saturated_themes}}' => self::identifySaturatedThemes($allSuggestions),
            '{{accepted_rejected}}' => self::formatAcceptedAndRejected($acceptedTitles, $rejectedTitles),
            '{{learning_context}}' => self::formatLearningContext($learningContext),
            '{{seasonality_period}}' => $season['periodo'],
            '{{seasonality_focus}}' => $season['foco'],
            '{{platform_resources}}' => self::getPlatformResources(),
            '{{store_context}}' => is_array($storeContext) ? json_encode($storeContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $storeContext,
            '{{store_goals}}' => self::formatStoreGoals($storeGoals),
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
     * Formata os objetivos da loja para o prompt.
     */
    private static function formatStoreGoals(array $goals): string
    {
        if (empty($goals)) {
            return "Nenhum objetivo específico definido pela loja. Foque em:\n- Aumentar faturamento\n- Aumentar ticket médio\n- Melhorar conversão";
        }

        $output = "A loja definiu os seguintes objetivos:\n\n";
        foreach ($goals as $goal) {
            if (is_array($goal)) {
                $nome = $goal['goal'] ?? $goal['name'] ?? $goal['objetivo'] ?? 'Objetivo';
                $meta = $goal['target'] ?? $goal['meta'] ?? '';
                $output .= "- **{$nome}**";
                if ($meta) {
                    $output .= ": {$meta}";
                }
                $output .= "\n";
            } else {
                $output .= "- {$goal}\n";
            }
        }

        $output .= "\n**IMPORTANTE:** Priorize sugestões que ajudem a atingir esses objetivos. Sugestões alinhadas aos objetivos devem ser HIGH ou MEDIUM.";

        return $output;
    }

    /**
     * Formata o contexto de aprendizado de análises anteriores.
     */
    private static function formatLearningContext(array $learningContext): string
    {
        if (empty($learningContext)) {
            return "Nenhum histórico de feedback disponível. Esta é uma das primeiras análises.";
        }

        $output = '';

        // Taxa de sucesso por categoria
        $categoryRates = $learningContext['category_success_rates'] ?? [];
        if (! empty($categoryRates)) {
            $output .= "### Taxas de Sucesso por Categoria\n\n";
            $output .= "| Categoria | Taxa de Sucesso | Total |\n";
            $output .= "|-----------|-----------------|-------|\n";
            foreach ($categoryRates as $category => $stats) {
                $rate = $stats['success_rate'] ?? 0;
                $total = $stats['total_implemented'] ?? 0;
                $output .= "| {$category} | {$rate}% | {$total} |\n";
            }
            $output .= "\n**REGRA DE PRIORIZAÇÃO:**\n";
            $output .= "- Categorias com >70% sucesso: podem ser HIGH\n";
            $output .= "- Categorias com 40-70% sucesso: MEDIUM\n";
            $output .= "- Categorias com <40% sucesso: rebaixar para LOW ou evitar\n\n";
        }

        // Casos de sucesso
        $successCases = $learningContext['success_cases'] ?? [];
        if (! empty($successCases)) {
            $output .= "### Casos de Sucesso Recentes\n\n";
            $output .= "Sugestões que funcionaram bem para este cliente:\n\n";
            foreach ($successCases as $case) {
                $title = $case['title'] ?? 'Sem título';
                $category = $case['category'] ?? 'geral';
                $impact = $case['metrics_impact'] ?? null;
                $impactStr = $impact ? ' - Impacto: '.json_encode($impact) : '';
                $output .= "- ✅ **{$title}** ({$category}){$impactStr}\n";
            }
            $output .= "\n**INSIGHT:** Esses temas funcionam bem. Considere variações ou evoluções.\n\n";
        }

        // Casos de falha
        $failureCases = $learningContext['failure_cases'] ?? [];
        if (! empty($failureCases)) {
            $output .= "### Padrões de Falha (EVITAR)\n\n";
            $output .= "Sugestões que NÃO funcionaram:\n\n";
            foreach ($failureCases as $case) {
                $title = $case['title'] ?? 'Sem título';
                $category = $case['category'] ?? 'geral';
                $reason = $case['failure_reason'] ?? 'Não informado';
                $output .= "- ❌ **{$title}** ({$category}): {$reason}\n";
            }
            $output .= "\n**INSIGHT:** Evitar temas similares ou abordar de forma completamente diferente.\n\n";
        }

        // Sugestões por status
        $byStatus = $learningContext['suggestions_by_status'] ?? [];

        // Em andamento
        $inProgress = $byStatus['in_progress'] ?? [];
        if (! empty($inProgress)) {
            $output .= "### Sugestões Em Andamento\n\n";
            $output .= "O cliente está trabalhando nestas sugestões:\n\n";
            foreach ($inProgress as $s) {
                $output .= "- 🔄 {$s['title']} ({$s['category']})\n";
            }
            $output .= "\n**REGRA:** NÃO sugerir nada similar até conclusão.\n\n";
        }

        // Rejeitadas
        $rejected = $byStatus['rejected'] ?? [];
        if (! empty($rejected)) {
            $output .= "### Sugestões Rejeitadas pelo Cliente\n\n";
            foreach (array_slice($rejected, 0, 5) as $s) {
                $output .= "- ⛔ {$s['title']} ({$s['category']})\n";
            }
            $output .= "\n**INSIGHT:** Cliente não se interessou. Evitar temas similares.\n\n";
        }

        // Categorias bloqueadas por múltiplas rejeições
        $blockedCategories = $learningContext['blocked_categories'] ?? [];
        if (! empty($blockedCategories)) {
            $output .= "### ⛔ CATEGORIAS BLOQUEADAS (3+ rejeições)\n\n";
            $output .= "**REGRA CRÍTICA:** As seguintes categorias foram rejeitadas 3+ vezes pelo cliente. NÃO gerar sugestões nestas categorias:\n\n";
            foreach ($blockedCategories as $category => $count) {
                $output .= "- 🚫 **{$category}** ({$count} rejeições)\n";
            }
            $output .= "\n";
        }

        return $output ?: "Histórico de feedback ainda em construção.";
    }

    /**
     * Método get() para manter compatibilidade com o pipeline existente.
     */
    public static function get(array $context): string
    {
        return self::build($context);
    }
}
