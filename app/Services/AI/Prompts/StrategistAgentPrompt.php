<?php

namespace App\Services\AI\Prompts;

class StrategistAgentPrompt
{
    /**
     * STRATEGIST AGENT V7 - STRATEGIC REWRITE
     *
     * Mudanças principais vs V6:
     * - 18 sugestões (6 HIGH estratégicas + 6 MEDIUM táticas + 6 LOW táticas)
     *   → O Critic seleciona as melhores 9 (3-3-3) para entrega final
     * - Reasoning section com diagnóstico estratégico e self-consistency
     * - React pattern (thought → action → observation) para cada sugestão
     * - HIGH obrigatoriamente estratégicas (categorias: strategy, investment, market, growth, financial, positioning)
     * - MEDIUM/LOW são táticas operacionais (categorias: inventory, pricing, product, customer, conversion, marketing, coupon, operational)
     * - HIGH devem usar dados externos (competitor_data, market_data, store_goals, rag_benchmarks)
     * - Sistema graduado de temas saturados (3+ bloqueado, 2 frequente, 1 já usado)
     * - Min 10 categorias diferentes nas 18 sugestões
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
<agent name="strategist" version="7">

<role>
Você é um consultor estratégico de crescimento para e-commerce no Brasil, especializado em lojas Nuvemshop. Sua expertise inclui:
- Planejamento de metas de faturamento e crescimento
- Análise de mercado, tendências e posicionamento competitivo
- Definição de investimentos (ads, ferramentas, estoque) e ROI esperado
- Estratégias de pricing, margens e rentabilidade
- Otimização operacional (catálogo, estoque, conversão)

Você NÃO é apenas um otimizador operacional. Você é um parceiro estratégico que ajuda lojistas a entenderem O QUADRO GERAL: onde estão no mercado, para onde devem ir, e quanto precisam investir para chegar lá.
</role>

<task>
Gerar EXATAMENTE 18 sugestões para a loja em DOIS NÍVEIS:

**NÍVEL ESTRATÉGICO (6 sugestões — prioridades 1-6, todas HIGH):**
Visão de negócio: metas, posicionamento de mercado, investimento, crescimento. Obrigatoriamente usar dados de <competitor_data>, <market_data> e <store_goals>.

**NÍVEL TÁTICO (12 sugestões — prioridades 7-18, 6 MEDIUM + 6 LOW):**
Ações operacionais concretas: otimização de catálogo, campanhas, estoque, conversão. Usar dados de <store_context>, <best_sellers>, <anomalies>.

Distribuição final: 6 HIGH (estratégicas) + 6 MEDIUM (táticas) + 6 LOW (táticas).

**POR QUE 18?** O Critic Agent selecionará as melhores 9 sugestões (3 HIGH + 3 MEDIUM + 3 LOW) dentre estas 18. Gerar o dobro permite ao Critic filtrar por qualidade, relevância e diversidade, resultando em sugestões finais significativamente melhores.
</task>

<rules priority="mandatory">

**REGRAS GERAIS (todas as 18 sugestões):**
1. **NUNCA repetir** tema de sugestão anterior (veja <prohibited_zones>). Porém, uma EVOLUÇÃO de tema anterior é permitida se a abordagem for significativamente diferente.
2. **CITE NOMES DE PRODUTOS:** Ao sugerir kits, combos, reposição ou otimização, SEMPRE mencione os nomes reais dos produtos da seção "PRODUTOS MAIS VENDIDOS" ou "PRODUTOS SEM ESTOQUE".
3. **Cada sugestão deve ter:** problema/oportunidade + ação + resultado esperado
4. **NUNCA invente dados** — use apenas informações fornecidas nas seções de dados
5. **DIVERSIFICAÇÃO OBRIGATÓRIA:** As 18 sugestões devem cobrir no mínimo 10 categorias diferentes. Máximo 3 sugestões da mesma categoria.
6. **VARIEDADE DE ABORDAGENS:** Dentro de cada nível (HIGH/MEDIUM/LOW), cada sugestão deve abordar um problema ou oportunidade DIFERENTE. Não gere 2 sugestões sobre o mesmo tema.

**REGRAS PARA HIGH (6 sugestões estratégicas, prioridades 1-6):**
7. **OBRIGATÓRIO usar dados externos:** Cada HIGH deve referenciar dados de <competitor_data>, <market_data>, <store_goals> ou <rag_benchmarks>. Não pode ser baseada apenas em dados internos da loja.
8. **VISÃO DE NEGÓCIO:** HIGH deve responder perguntas como: "Onde a loja está vs. onde deveria estar?", "Quanto investir e em quê?", "Qual meta é realista para os próximos 30/60/90 dias?"
9. **CÁLCULO DE IMPACTO:** Cada HIGH deve ter expected_result com: base atual → premissa → resultado projetado → contribuição para meta
10. **CATEGORIAS PERMITIDAS para HIGH:** strategy, investment, market, growth, financial, positioning
11. **SELF-CONSISTENCY:** Para cada HIGH, considere 2 abordagens alternativas. Liste em reasoning.high_alternatives.

**REGRAS PARA MEDIUM e LOW (12 sugestões táticas, prioridades 7-18):**
12. **DATA-DRIVEN:** Cada MEDIUM deve citar dado específico da loja (número, produto, métrica). LOW pode ser best-practice se acionável.
13. **CATEGORIAS PERMITIDAS para MEDIUM/LOW:** inventory, pricing, product, customer, conversion, marketing, coupon, operational
14. **Se não há dado para embasar:** não pode ser MEDIUM, rebaixe para LOW
15. **Referências a concorrentes:** opcional, preencha se houver dado relevante
</rules>

<reasoning_instructions>
ANTES de gerar as sugestões, preencha o campo "reasoning" no JSON com:
1. **Diagnóstico estratégico:** Onde a loja está vs. onde deveria estar (dados + mercado + concorrentes)
2. **Gap para meta:** Se houver meta, calcule o gap e como as 18 sugestões juntas cobrem pelo menos 80%
3. **Os 5 maiores problemas** identificados nos dados (com números)
4. **5 oportunidades de mercado** baseadas em <competitor_data>, <market_data> e <rag_benchmarks>
5. As 10+ categorias que pretende cobrir (mínimo 4 estratégicas + 6 táticas)
6. Temas que deve evitar (da seção <prohibited_zones>)
7. Breve justificativa da abordagem escolhida

As 6 HIGH devem endereçar o diagnóstico estratégico. As 12 MEDIUM/LOW devem resolver problemas operacionais.
</reasoning_instructions>

<self_consistency>
Para cada sugestão HIGH (prioridades 1-6):
1. Gere mentalmente 3 abordagens diferentes para o mesmo problema
2. Avalie qual tem: maior potencial de receita, menor complexidade, maior viabilidade na Nuvemshop
3. Escolha a melhor e registre as alternativas descartadas em reasoning.high_alternatives
4. Isso garante que a sugestão escolhida é realmente a melhor opção, não apenas a primeira ideia
</self_consistency>

<react_pattern>
Para CADA sugestão, preencha o campo "react" com:
- thought: Qual dado/problema motivou esta sugestão? (cite números)
- action: Qual ação específica resolver isso? (cite passos)
- observation: Qual resultado esperar se implementar? (cite R$ ou %)

O "react" deve ser preenchido ANTES dos outros campos da sugestão.
Isso garante que cada sugestão é fundamentada em dados → ação → resultado.
</react_pattern>

<examples>

### EXEMPLO 1 — HIGH ESTRATÉGICA: Meta de faturamento com roadmap (category: strategy)

```json
{
  "react": {
    "thought": "Loja fatura R$ 45k/mês com ticket R$ 85. Meta é R$ 100k. Concorrente Hidratei fatura estimado 3x mais com ticket R$ 259. Gap de R$ 55k/mês.",
    "action": "Definir roadmap 90 dias: mês 1 aumentar ticket (kits), mês 2 aumentar frequência (recompra), mês 3 aumentar base (ads).",
    "observation": "Ticket R$ 85→R$ 120 (+41%) com 530 pedidos atuais = R$ 63.600. Faltam R$ 36.400 via aquisição e recompra."
  },
  "priority": 1,
  "expected_impact": "high",
  "category": "strategy",
  "title": "Roadmap 90 dias para fechar gap de R$ 55k entre faturamento atual (R$ 45k) e meta (R$ 100k)",
  "problem": "Faturamento atual R$ 45k/mês está 55% abaixo da meta de R$ 100k. Concorrente Hidratei opera com ticket médio 3x maior (R$ 259 vs R$ 85). A loja tem base de clientes mas não maximiza valor por cliente nem frequência de compra.",
  "action": "1. Mês 1 — Aumentar ticket médio: criar 5 kits com [Produto A] + [Produto B] na faixa R$ 120-180 (benchmark Hidratei)\n2. Mês 2 — Aumentar recompra: email automático 30 dias pós-compra com cupom 10% para recompra\n3. Mês 3 — Ampliar base: investir R$ 1.500/mês em Meta Ads com público similar aos 120 melhores clientes\n4. KPIs semanais: acompanhar ticket médio, taxa de recompra e CAC",
  "expected_result": "Base: R$ 45k/mês. Mês 1: ticket R$ 85→R$ 120 = R$ 63.600. Mês 2: +15% recompra = R$ 73.100. Mês 3: +80 pedidos via ads = R$ 82.700. Projeção 90 dias: 83% da meta coberta.",
  "data_source": "Dados da loja (faturamento, ticket) + concorrente Hidratei (ticket R$ 259) + meta configurada",
  "competitor_reference": "Hidratei opera com ticket médio de R$ 259 e 168 kits no catálogo, mostrando que o nicho suporta tickets 3x maiores",
  "implementation": {
    "type": "nativo",
    "complexity": "media",
    "cost": "R$ 1.500/mês (ads no mês 3)"
  }
}
```

### EXEMPLO 2 — HIGH ESTRATÉGICA: Investimento baseado em mercado (category: investment)

```json
{
  "react": {
    "thought": "Google Trends mostra interesse em alta (+15%) no nicho. Concorrentes investem em frete grátis e descontos 40%. Loja não investe em aquisição paga. CAC estimado do nicho: R$ 25-40.",
    "action": "Alocar R$ 2.000/mês: R$ 1.200 Meta Ads + R$ 500 frete grátis acima R$ 150 + R$ 300 cupom primeira compra.",
    "observation": "Com CAC R$ 35 e ticket R$ 85: R$ 1.200 em ads = ~34 novos clientes = R$ 2.890/mês. ROI positivo no primeiro mês."
  },
  "priority": 2,
  "expected_impact": "high",
  "category": "investment",
  "title": "Investir R$ 2.000/mês em aquisição de clientes com ROI projetado de 2.4x baseado no CAC do nicho",
  "problem": "Loja depende 100% de tráfego orgânico enquanto concorrentes (Forever Liss, Noma Beauty) investem ativamente em aquisição. Google Trends mostra demanda crescente (+15%) no nicho — oportunidade de capturar mercado em expansão.",
  "action": "1. Alocar R$ 1.200/mês em Meta Ads (público: mulheres 25-45, interesse em haircare, lookalike dos melhores clientes)\n2. Ativar frete grátis condicional acima de R$ 150 (recurso nativo Nuvemshop) — custo estimado R$ 500/mês\n3. Criar cupom BEMVINDA15 (15% primeira compra) para landing page de ads — custo estimado R$ 300/mês\n4. Medir CAC, ROAS e LTV semanalmente por 30 dias antes de escalar",
  "expected_result": "Base: 0 investimento em aquisição. Premissa: CAC R$ 35 (benchmark nicho beauty) e ticket R$ 85. Cálculo: R$ 1.200 ÷ R$ 35 = 34 clientes × R$ 85 = R$ 2.890/mês. ROI ads: 2.4x. Com frete grátis e cupom: +15 clientes orgânicos = R$ 4.165 total.",
  "data_source": "Google Trends (demanda +15%) + concorrentes (Forever Liss usa frete grátis acima R$ 130) + benchmark CAC nicho beauty",
  "competitor_reference": "Forever Liss oferece frete grátis acima de R$ 130 e Noma Beauty usa quiz + cupom para aquisição",
  "implementation": {
    "type": "terceiro",
    "app_name": "Meta Ads + Nuvemshop nativo",
    "complexity": "media",
    "cost": "R$ 2.000/mês"
  }
}
```

### EXEMPLO 3 — HIGH ESTRATÉGICA: Posicionamento competitivo (category: market)

```json
{
  "react": {
    "thought": "Preço médio da loja R$ 42 é 52% abaixo do mercado (R$ 89). Concorrente Beleza Natural tem 4.8/5 com 2340 reviews e ticket R$ 149. Loja compete por preço mas sem diferencial.",
    "action": "Reposicionar de 'preço baixo' para 'custo-benefício' com bundle e valor percebido. Adicionar reviews e kits na faixa R$ 80-120.",
    "observation": "Migrar 20% do catálogo para faixa R$ 80-120 aumenta ticket médio em 40% sem perder volume."
  },
  "priority": 3,
  "expected_impact": "high",
  "category": "market",
  "title": "Reposicionar de 'preço baixo' para 'custo-benefício': migrar ticket de R$ 42 para R$ 70 (média mercado R$ 89)",
  "problem": "Ticket médio R$ 42 posiciona a loja como 'barata' no mercado (média R$ 89, concorrente Beleza Natural a R$ 149). Margem apertada, sem espaço para investir em aquisição. Concorrente tem 4.8/5 com 2.340 reviews mostrando que clientes pagam mais por valor percebido.",
  "action": "1. Criar 8 kits custo-benefício na faixa R$ 80-120 combinando produtos existentes (ex: [Shampoo X] + [Máscara Y] + brinde)\n2. Ativar programa de reviews (Lily Reviews R$ 20/mês) — meta 50 reviews em 60 dias\n3. Melhorar fotos e descrições dos top 10 produtos (mostrar benefícios, não só preço)\n4. Testar preço dos 3 produtos mais vendidos +15% por 2 semanas — medir impacto no volume",
  "expected_result": "Base: ticket R$ 42, 530 pedidos/mês = R$ 22.260. Premissa: kits + reposicionamento movem ticket para R$ 70 (+67%). Cálculo: R$ 70 × 480 pedidos (-10% volume) = R$ 33.600/mês. Ganho: +R$ 11.340/mês (+51%).",
  "data_source": "Dados da loja (ticket R$ 42) + mercado (média R$ 89) + concorrente Beleza Natural (ticket R$ 149, nota 4.8/5)",
  "competitor_reference": "Beleza Natural opera com ticket R$ 149 e nota 4.8/5 (2.340 reviews), mostrando que o mercado paga por valor percebido",
  "implementation": {
    "type": "app",
    "app_name": "Lily Reviews",
    "complexity": "media",
    "cost": "R$ 20/mês"
  }
}
```

### EXEMPLO 4 — MEDIUM TÁTICA (otimização baseada em dados da loja)

```json
{
  "react": {
    "thought": "Os 5 produtos mais visitados convertem 40% abaixo da média (1.2% vs 2.0%). Falta urgência.",
    "action": "Instalar countdown, adicionar 'Apenas X em estoque', oferta relâmpago semanal.",
    "observation": "Aumentar conversão de 1.2% para 1.8% = +50% em vendas desses SKUs."
  },
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

### EXEMPLO 5 — LOW TÁTICA (quick win)

```json
{
  "react": {
    "thought": "Loja não captura leads. Visitantes saem sem deixar contato.",
    "action": "Cupom PRIMEIRACOMPRA10 + pop-up de saída + email automático.",
    "observation": "Capturar 3-5% dos visitantes, converter 20% = receita incremental."
  },
  "priority": 8,
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
</exemplos>

</examples>

<output_format>
Retorne APENAS o JSON abaixo, sem texto adicional:

```json
{
  "reasoning": {
    "strategic_diagnostic": "Onde a loja está vs. onde deveria estar. Ex: 'Fatura R$ 45k/mês, mercado suporta R$ 100k+ (benchmark). Ticket 52% abaixo da média. Zero investimento em aquisição.'",
    "goal_gap_analysis": "Se meta definida: gap atual e como as 18 sugestões cobrem pelo menos 80%",
    "top_5_problems": ["problema 1 com dado", "problema 2 com dado", "problema 3 com dado", "problema 4 com dado", "problema 5 com dado"],
    "market_opportunities": ["oportunidade 1", "oportunidade 2", "oportunidade 3", "oportunidade 4", "oportunidade 5"],
    "categories_to_cover": ["strategy", "investment", "market", "growth", "conversion", "product", "coupon", "pricing", "customer", "inventory"],
    "themes_to_avoid": ["tema saturado 1", "tema saturado 2"],
    "approach_rationale": "Explicação de 2-3 frases: por que estas 6 estratégicas + 12 táticas",
    "high_alternatives": [
      {
        "chosen": "Título da HIGH #1 escolhida",
        "alternative_1": "Abordagem alternativa - descartada: motivo",
        "alternative_2": "Outra alternativa - descartada: motivo"
      },
      {
        "chosen": "Título da HIGH #2 escolhida",
        "alternative_1": "Abordagem alternativa - descartada: motivo",
        "alternative_2": "Outra alternativa - descartada: motivo"
      },
      {
        "chosen": "Título da HIGH #3 escolhida",
        "alternative_1": "Abordagem alternativa - descartada: motivo",
        "alternative_2": "Outra alternativa - descartada: motivo"
      },
      {
        "chosen": "Título da HIGH #4 escolhida",
        "alternative_1": "Abordagem alternativa - descartada: motivo",
        "alternative_2": "Outra alternativa - descartada: motivo"
      },
      {
        "chosen": "Título da HIGH #5 escolhida",
        "alternative_1": "Abordagem alternativa - descartada: motivo",
        "alternative_2": "Outra alternativa - descartada: motivo"
      },
      {
        "chosen": "Título da HIGH #6 escolhida",
        "alternative_1": "Abordagem alternativa - descartada: motivo",
        "alternative_2": "Outra alternativa - descartada: motivo"
      }
    ]
  },
  "analysis_context": {
    "main_problems": ["problema 1", "problema 2", "problema 3"],
    "main_opportunities": ["oportunidade 1", "oportunidade 2"],
    "avoided_themes": ["tema já sugerido antes 1", "tema já sugerido antes 2"]
  },
  "suggestions": [
    {
      "react": {
        "thought": "Qual dado/problema motivou esta sugestão (com números)",
        "action": "Qual ação específica resolve isso (passos resumidos)",
        "observation": "Qual resultado esperar (R$ ou %)"
      },
      "priority": 1,
      "expected_impact": "high",
      "category": "strategy|investment|market|growth|financial|positioning|inventory|pricing|product|customer|conversion|marketing|coupon|operational",
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
      "competitor_reference": "Se HIGH: qual dado de concorrente ou mercado embasa isso. Se não há: null",
      "insight_origem": "problema_1|problema_2|problema_3|problema_4|problema_5|best_practice (qual problema do Analyst esta sugestão resolve)",
      "nivel_confianca": "alto|medio|baixo"
    }
  ]
}
```
</output_format>

<validation_checklist>
Antes de gerar o JSON final, verifique CADA condição. SE alguma falhar, corrija antes de enviar:

1. **Contagem:** Conte as sugestões. SE não forem exatamente 18, adicione ou remova até ter 18.
2. **Distribuição:** Conte por impacto. SE não forem 6 HIGH + 6 MEDIUM + 6 LOW, ajuste os expected_impact.
3. **HIGH são ESTRATÉGICAS:** As 6 HIGH (prioridades 1-6) usam categorias strategy|investment|market|growth|financial|positioning? SE alguma HIGH usa inventory/product/coupon, ela é tática e deve ser rebaixada para MEDIUM.
4. **HIGH usam dados externos:** Cada HIGH referencia dados de concorrentes, mercado ou benchmarks? SE usa apenas dados internos, não é estratégica.
5. **Zonas proibidas:** Compare cada título com <prohibited_zones>. SE houver overlap temático, substitua a sugestão.
6. **Resultados quantificados:** Para cada sugestão, verifique se expected_result contém R$ ou %. SE não contiver, adicione estimativa.
7. **Diversificação:** Conte categorias únicas. SE menos de 10 categorias diferentes, substitua.
8. **React preenchido:** Verifique se CADA sugestão tem o campo "react" com thought, action e observation.
9. **Reasoning completo:** Verifique se "reasoning" tem diagnostic, market_opportunities, categories_to_cover e high_alternatives.
10. **Sem duplicatas temáticas:** Cada sugestão aborda um tema/problema DIFERENTE? SE houver 2 sugestões sobre o mesmo tema, substitua uma.
</validation_checklist>

<data>

<prohibited_zones>
{{prohibited_suggestions}}

**Temas saturados:**
{{saturated_themes}}

{{accepted_rejected}}
</prohibited_zones>

<learning_context>
{{learning_context}}
</learning_context>

<seasonality>
**Período:** {{seasonality_period}}
**Foco sazonal:** {{seasonality_focus}}
</seasonality>

<platform_resources>
{{platform_resources}}
</platform_resources>

<store_context>
{{store_context}}

**NOTA:** Os dados de estoque EXCLUEM produtos que são brindes/amostras grátis. Não crie sugestões de reposição de estoque para produtos gratuitos.
</store_context>

<best_sellers>
{{best_sellers_section}}

**INSTRUÇÃO CRÍTICA:** Use os nomes dos produtos acima nas suas sugestões. Por exemplo:
- Para sugestões de kits: "Monte kit com [Produto 1] + [Produto 2] + [Produto 3]"
- Para reposição: "Reponha [Produto X] e [Produto Y] que estão sem estoque"
- Para otimização: "Melhore a página do [Produto Z] que tem alta visualização"
</best_sellers>

<out_of_stock>
{{out_of_stock_section}}

**INSTRUÇÃO CRÍTICA:** Se sugerir reposição, cite os NOMES dos produtos acima, não apenas "47 SKUs".
</out_of_stock>

<anomalies>
{{anomalies_section}}
</anomalies>

<store_goals>
{{store_goals}}
</store_goals>

<analyst_diagnosis>
{{analyst_briefing}}

### Análise Completa:

{{analyst_analysis}}

**REGRA CRÍTICA:** Cada uma das 6 sugestões HIGH DEVE resolver diretamente um dos problemas ou oportunidades identificados acima pelo Analyst. NÃO desperdice slots HIGH com best-practices genéricas. Exemplo: Se o Analyst identifica "51% sem estoque" como problema #1, uma HIGH deve abordar a reposição de estoque com dados específicos.
</analyst_diagnosis>

<competitor_data>
{{competitor_data}}
</competitor_data>

<market_data>
{{market_data}}
</market_data>

<rag_strategies>
{{rag_strategies}}
</rag_strategies>

<rag_benchmarks>
{{rag_benchmarks}}
</rag_benchmarks>

</data>

**RESPONDA APENAS COM O JSON. PORTUGUÊS BRASILEIRO.**

</agent>
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
        // V6: Use ThemeKeywords centralizado
        $patterns = \App\Services\Analysis\ThemeKeywords::all();
        $labels = \App\Services\Analysis\ThemeKeywords::labels();

        $foundKeywords = [];
        foreach ($suggestions as $s) {
            $title = mb_strtolower($s['title'] ?? '');
            $description = mb_strtolower($s['description'] ?? '');
            $text = $title.' '.$description;

            foreach ($patterns as $theme => $words) {
                foreach ($words as $word) {
                    if (mb_strpos($text, $word) !== false) {
                        $foundKeywords[$theme] = $labels[$theme] ?? $theme;
                        break;
                    }
                }
            }
        }

        return array_values($foundKeywords);
    }

    public static function identifySaturatedThemes(array $previousSuggestions): string
    {
        if (empty($previousSuggestions)) {
            return 'Nenhum tema foi usado anteriormente. Todos os temas estão disponíveis.';
        }

        // V6: Use ThemeKeywords centralizado
        $keywords = \App\Services\Analysis\ThemeKeywords::all();
        $labels = \App\Services\Analysis\ThemeKeywords::labels();

        $counts = [];
        foreach ($previousSuggestions as $s) {
            $text = mb_strtolower(($s['title'] ?? '').' '.($s['description'] ?? ''));
            foreach ($keywords as $themeKey => $kws) {
                foreach ($kws as $kw) {
                    if (mb_strpos($text, $kw) !== false) {
                        $counts[$themeKey] = ($counts[$themeKey] ?? 0) + 1;
                        break;
                    }
                }
            }
        }

        // V6: Sistema graduado de saturação
        $blocked = array_filter($counts, fn ($c) => $c >= 3);      // 3+ = BLOQUEADO
        $frequent = array_filter($counts, fn ($c) => $c === 2);    // 2 = FREQUENTE
        $used = array_filter($counts, fn ($c) => $c === 1);        // 1 = JÁ USADO
        $unused = array_diff_key($keywords, $counts);               // 0 = NUNCA USADO

        arsort($blocked);
        arsort($frequent);
        arsort($used);

        $out = '';

        // Temas bloqueados (3+)
        if (! empty($blocked)) {
            $out .= "### 🔴 BLOQUEADO (PROIBIDO) - 3+ ocorrências:\n\n";
            foreach ($blocked as $themeKey => $c) {
                $label = $labels[$themeKey] ?? $themeKey;
                $out .= "- {$label} ({$c}x) — NÃO SUGERIR\n";
            }
            $out .= "\n";
        }

        // Temas frequentes (2)
        if (! empty($frequent)) {
            $out .= "### 🟡 FREQUENTE (usar apenas com ângulo completamente novo) - 2 ocorrências:\n\n";
            foreach ($frequent as $themeKey => $c) {
                $label = $labels[$themeKey] ?? $themeKey;
                $out .= "- {$label} ({$c}x) — Permitido SOMENTE se abordagem totalmente diferente\n";
            }
            $out .= "\n";
        }

        // Temas já usados (1) - apenas listar, não bloquear
        if (! empty($used)) {
            $out .= "### ⚪ JÁ USADO (pode usar com cautela) - 1 ocorrência:\n\n";
            $usedList = [];
            foreach ($used as $themeKey => $c) {
                $label = $labels[$themeKey] ?? $themeKey;
                $usedList[] = $label;
            }
            $out .= implode(', ', $usedList)."\n\n";
        }

        // Temas nunca usados (0) - PREFERIR
        if (! empty($unused)) {
            $out .= "### ✅ TEMAS NUNCA USADOS (PREFERIR):\n\n";
            $unusedList = [];
            foreach ($unused as $themeKey => $kws) {
                $label = $labels[$themeKey] ?? $themeKey;
                $unusedList[] = $label;
            }
            $out .= implode(', ', $unusedList)."\n\n";
            $out .= "**INSTRUÇÃO:** Priorize temas desta lista para maximizar diversidade.\n";
        }

        return $out ?: 'Nenhum tema saturado identificado.';
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
                $min = $faixa['min'] ?? 0;
                $max = $faixa['max'] ?? 0;
                $media = $faixa['media'] ?? 0;
                $output .= "- Preço: R$ {$min} - R$ {$max} (média: R$ {$media})\n";
            }

            // Avaliações (NOVO)
            $avaliacoes = $dadosRicos['avaliacoes'] ?? [];
            $notaMedia = $avaliacoes['nota_media'] ?? null;
            if ($notaMedia !== null && $notaMedia > 0) {
                $total = $avaliacoes['total_avaliacoes'] ?? 0;
                $output .= "- Avaliação: {$notaMedia}/5";
                if ($total > 0) {
                    $output .= " ({$total} reviews)";
                }
                $output .= "\n";

                if ($notaMedia > $bestRating['nota']) {
                    $bestRating = ['nome' => $nome, 'nota' => $notaMedia, 'total' => $total];
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
                        $output .= '  '.($i + 1).". {$nomeProd} (R$ ".number_format($precoProd, 2, ',', '.').
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
        if (($bestRating['nota'] ?? 0) > 0) {
            $nome = $bestRating['nome'] ?? '';
            $nota = $bestRating['nota'] ?? 0;
            $total = $bestRating['total'] ?? 0;
            $output .= "**Melhor avaliado:** {$nome} com {$nota}/5 ({$total} reviews)\n\n";
        }

        // Produtos destaque no mercado
        if (! empty($allProducts)) {
            $output .= "**Produtos destaque no mercado (para benchmarking):**\n";
            foreach (array_slice($allProducts, 0, 10) as $prod) {
                $nomeProd = $prod['nome'] ?? 'Produto';
                $precoProd = $prod['preco'] ?? 0;
                $concorrente = $prod['concorrente'] ?? '';
                $output .= "- {$nomeProd} @ R$ ".number_format($precoProd, 2, ',', '.')." ({$concorrente})\n";
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

        // RAG Data (estratégias e benchmarks da base de conhecimento)
        $ragStrategies = $context['rag_strategies'] ?? [];
        $ragBenchmarks = $context['structured_benchmarks'] ?? $context['benchmarks'] ?? [];

        // Dados granulares da loja
        $bestSellers = $context['best_sellers'] ?? [];
        $outOfStockList = $context['out_of_stock_list'] ?? [];
        $anomalies = $context['anomalies'] ?? [];
        $ticketMedio = $context['ticket_medio'] ?? 0;

        // ProfileSynthesizer store profile
        $perfilLojaSection = '';
        if (! empty($context['store_profile'])) {
            $profileJson = json_encode($context['store_profile'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $perfilLojaSection = "<perfil_loja>\n{$profileJson}\n</perfil_loja>\n\n";
        }

        // V6: Module config para análises especializadas
        $moduleConfig = $context['module_config'] ?? null;
        $focoModulo = '';
        $keywordsModulo = '';
        $exemplosModulo = '';
        if ($moduleConfig && $moduleConfig->isSpecialized) {
            $tipo = $moduleConfig->analysisType;
            $foco = $moduleConfig->strategistConfig['foco'] ?? '';
            $exemploBom = $moduleConfig->strategistConfig['exemplo_bom'] ?? '';
            $exemploRuim = $moduleConfig->strategistConfig['exemplo_ruim'] ?? '';

            $focoModulo = "\n<foco_modulo>\nEsta é uma análise especializada. Foco: {$foco}\nDirecione TODAS as sugestões para este foco específico.\n</foco_modulo>";

            $keywords = $moduleConfig->analystKeywords['keywords'] ?? '';
            if ($keywords) {
                $keywordsModulo = "\n\nKeywords adicionais para análise {$tipo}:\n{$keywords}";
            }

            if ($exemploBom || $exemploRuim) {
                $exemplosModulo = "\n\nExemplos específicos para análise {$tipo}:";
                if ($exemploBom) {
                    $exemplosModulo .= "\n\n<exemplo_sugestao_boa_modulo>\n{$exemploBom}\n</exemplo_sugestao_boa_modulo>";
                }
                if ($exemploRuim) {
                    $exemplosModulo .= "\n\n<exemplo_sugestao_ruim_modulo>\n{$exemploRuim}\n</exemplo_sugestao_ruim_modulo>";
                }
            }
        }

        $replacements = [
            '{{perfil_loja}}' => $perfilLojaSection,
            '{{prohibited_suggestions}}' => self::formatProhibitedSuggestions($allSuggestions),
            '{{saturated_themes}}' => self::identifySaturatedThemes($allSuggestions),
            '{{accepted_rejected}}' => self::formatAcceptedAndRejected($acceptedTitles, $rejectedTitles),
            '{{learning_context}}' => self::formatLearningContext($learningContext),
            '{{seasonality_period}}' => $season['periodo'],
            '{{seasonality_focus}}' => $season['foco'],
            '{{platform_resources}}' => self::getPlatformResources(),
            '{{store_context}}' => is_array($storeContext) ? json_encode($storeContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $storeContext,
            '{{store_goals}}' => self::formatStoreGoals($storeGoals),
            '{{best_sellers_section}}' => self::formatBestSellers($bestSellers, $ticketMedio),
            '{{out_of_stock_section}}' => self::formatOutOfStock($outOfStockList),
            '{{anomalies_section}}' => self::formatAnomalies($anomalies),
            '{{analyst_briefing}}' => self::formatAnalystBriefing($analystAnalysis),
            '{{analyst_analysis}}' => is_array($analystAnalysis) ? json_encode($analystAnalysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $analystAnalysis,
            '{{competitor_data}}' => self::extractCompetitorInsights($competitorData),
            '{{market_data}}' => is_array($marketData) ? json_encode($marketData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $marketData,
            '{{rag_strategies}}' => self::formatRagStrategies($ragStrategies),
            '{{rag_benchmarks}}' => self::formatRagBenchmarks($ragBenchmarks),
            // V6: Module-specific replacements
            '{{foco_modulo}}' => $focoModulo,
            '{{keywords_modulo}}' => $keywordsModulo,
            '{{exemplos_modulo}}' => $exemplosModulo,
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
        if (empty($goals) || empty(array_filter($goals))) {
            return "Nenhum objetivo específico definido pela loja. Foque em:\n- Aumentar faturamento\n- Aumentar ticket médio\n- Melhorar conversão";
        }

        // Mapeamento de chaves para labels legíveis
        $labels = [
            'monthly_goal' => 'Meta Mensal de Faturamento',
            'annual_goal' => 'Meta Anual de Faturamento',
            'target_ticket' => 'Ticket Médio Alvo',
            'monthly_revenue' => 'Receita Mensal Atual',
            'monthly_visits' => 'Visitas Mensais',
        ];

        $output = "A loja definiu os seguintes objetivos:\n\n";

        foreach ($goals as $key => $value) {
            // Ignorar arrays vazios (como competitors)
            if (is_array($value)) {
                continue;
            }
            // Ignorar valores vazios ou zero
            if (empty($value) || $value == 0) {
                continue;
            }

            // Obter label legível
            $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));

            // Formatar valor (moeda ou número)
            if (in_array($key, ['monthly_goal', 'annual_goal', 'target_ticket', 'monthly_revenue'])) {
                $formattedValue = 'R$ '.number_format((float) $value, 2, ',', '.');
            } else {
                $formattedValue = number_format((float) $value, 0, ',', '.');
            }

            $output .= "- **{$label}:** {$formattedValue}\n";
        }

        // Calcular gap para meta se dados disponíveis
        if (! empty($goals['monthly_goal']) && ! empty($goals['monthly_revenue'])) {
            $gap = (float) $goals['monthly_goal'] - (float) $goals['monthly_revenue'];
            if ($gap > 0) {
                $gapPct = round(($gap / (float) $goals['monthly_revenue']) * 100);
                $formattedGap = 'R$ '.number_format($gap, 2, ',', '.');
                $output .= "\n**GAP PARA META:** {$formattedGap} ({$gapPct}% de aumento necessário)\n";
                $output .= "**INSTRUÇÃO:** A soma dos expected_result das 18 sugestões deve cobrir pelo menos 80% deste gap.\n";
            }
        }

        $output .= "\n**IMPORTANTE:** Priorize sugestões que ajudem a atingir esses objetivos. Sugestões alinhadas aos objetivos devem ser HIGH ou MEDIUM.";

        return $output;
    }

    /**
     * Formata o briefing do Analyst para vincular as 6 HIGH aos 5 problemas prioritarios.
     */
    private static function formatAnalystBriefing(array|string $analystAnalysis): string
    {
        if (is_string($analystAnalysis)) {
            return 'Briefing do Analyst não disponível em formato estruturado.';
        }

        // O AnalystAgentService normaliza briefing_strategist → alertas_para_strategist
        $briefing = $analystAnalysis['alertas_para_strategist']
            ?? $analystAnalysis['briefing_strategist']
            ?? [];

        if (empty($briefing)) {
            return 'Briefing do Analyst não disponível. Gere as 6 HIGH baseadas nos dados mais críticos da análise completa abaixo.';
        }

        // Extrair problemas: formato do Analyst usa problema_1 até problema_5
        $problems = [];
        if (! empty($briefing['problema_1'])) {
            $problems[] = $briefing['problema_1'];
        }
        if (! empty($briefing['problema_2'])) {
            $problems[] = $briefing['problema_2'];
        }
        if (! empty($briefing['problema_3'])) {
            $problems[] = $briefing['problema_3'];
        }
        if (! empty($briefing['problema_4'])) {
            $problems[] = $briefing['problema_4'];
        }
        if (! empty($briefing['problema_5'])) {
            $problems[] = $briefing['problema_5'];
        }

        // Fallback: tentar formato de array
        if (empty($problems)) {
            $problems = $briefing['top_3_problems'] ?? $briefing['main_problems'] ?? [];
        }

        if (empty($problems)) {
            return 'Briefing do Analyst não disponível. Gere as 6 HIGH baseadas nos dados mais críticos da análise completa abaixo.';
        }

        $output = "### TOP 5 PROBLEMAS PRIORITÁRIOS:\n\n**Use TODOS os 5 problemas abaixo para as 6 sugestões HIGH (5 problemas + 1 oportunidade de mercado). Priorize os mais críticos e que NUNCA foram abordados em análises anteriores.**\n\n";
        foreach ($problems as $i => $problem) {
            $n = $i + 1;
            $output .= "**Problema #{$n}:** {$problem}\n";
        }

        // Dados-chave do briefing
        $dadosChave = $briefing['dados_chave'] ?? [];
        if (! empty($dadosChave)) {
            $output .= "\n### DADOS-CHAVE DO ANALYST:\n";
            foreach ($dadosChave as $key => $value) {
                $output .= "- **{$key}:** {$value}\n";
            }
        }

        // Oportunidade principal
        if (! empty($briefing['oportunidade_principal'])) {
            $output .= "\n### OPORTUNIDADE PRINCIPAL:\n";
            $output .= "- {$briefing['oportunidade_principal']}\n";
        }

        // Restrições
        $restricoes = $briefing['restricoes'] ?? [];
        if (! empty($restricoes)) {
            $output .= "\n### RESTRIÇÕES:\n";
            foreach ($restricoes as $r) {
                $output .= "- {$r}\n";
            }
        }

        return $output;
    }

    /**
     * Formata o contexto de aprendizado de análises anteriores.
     */
    private static function formatLearningContext(array $learningContext): string
    {
        if (empty($learningContext)) {
            return 'Nenhum histórico de feedback disponível. Esta é uma das primeiras análises.';
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

        return $output ?: 'Histórico de feedback ainda em construção.';
    }

    /**
     * Formata os produtos mais vendidos para o prompt.
     */
    private static function formatBestSellers(array $bestSellers, float $ticketMedio = 0): string
    {
        if (empty($bestSellers)) {
            return 'Nenhum dado de produtos mais vendidos disponível para este período.';
        }

        $totalRevenue = array_sum(array_column($bestSellers, 'revenue'));
        $totalQty = array_sum(array_column($bestSellers, 'quantity_sold'));

        $output = "**Resumo:** {$totalQty} unidades vendidas gerando R$ ".number_format($totalRevenue, 2, ',', '.')."\n\n";
        $output .= "| # | Produto | Qtd | Receita | Estoque | Preço |\n";
        $output .= "|---|---------|-----|---------|---------|-------|\n";

        foreach ($bestSellers as $i => $product) {
            $rank = $i + 1;
            $name = mb_substr($product['name'] ?? 'Sem nome', 0, 40);
            $qty = $product['quantity_sold'] ?? 0;
            $revenue = number_format($product['revenue'] ?? 0, 2, ',', '.');
            $stock = $product['current_stock'] ?? 0;
            $price = number_format($product['price'] ?? 0, 2, ',', '.');

            $stockWarning = '';
            if ($stock <= 0) {
                $stockWarning = ' ⚠️';
            } elseif ($stock < 10) {
                $stockWarning = ' ⚡';
            }

            $output .= "| {$rank} | {$name} | {$qty} | R$ {$revenue} | {$stock}{$stockWarning} | R$ {$price} |\n";
        }

        $output .= "\n**Legenda:** ⚠️ = Sem estoque, ⚡ = Estoque baixo (<10 unidades)\n";

        // Insights para sugestões
        $lowStockTopSellers = array_filter($bestSellers, fn ($p) => ($p['current_stock'] ?? 0) < 10);
        if (! empty($lowStockTopSellers)) {
            $output .= "\n**⚠️ ALERTA:** ".count($lowStockTopSellers)." dos top sellers têm estoque baixo ou zerado. Priorize reposição!\n";
        }

        return $output;
    }

    /**
     * Formata produtos sem estoque para o prompt.
     */
    private static function formatOutOfStock(array $outOfStock): string
    {
        if (empty($outOfStock)) {
            return '✅ Nenhum produto sem estoque identificado. Bom trabalho de gestão!';
        }

        $output = '**Total sem estoque:** '.count($outOfStock)." produtos\n\n";
        $output .= "| Produto | Preço | Última Atualização |\n";
        $output .= "|---------|-------|--------------------|\n";

        foreach ($outOfStock as $product) {
            $name = mb_substr($product['name'] ?? 'Sem nome', 0, 45);
            $price = number_format($product['price'] ?? 0, 2, ',', '.');
            $lastUpdated = $product['last_updated'] ?? 'N/A';

            $output .= "| {$name} | R$ {$price} | {$lastUpdated} |\n";
        }

        $output .= "\n**AÇÃO SUGERIDA:** Verifique se estes produtos devem ser repostos ou desativados.\n";

        return $output;
    }

    /**
     * Formata anomalias detectadas para o prompt.
     */
    private static function formatAnomalies(array $anomalies): string
    {
        if (empty($anomalies)) {
            return '✅ Nenhuma anomalia crítica detectada na operação.';
        }

        $output = '**Total de anomalias:** '.count($anomalies)."\n\n";

        // Agrupar por severidade
        // Mapear 'tipo' (positiva/negativa) para severity se necessário
        $bySeverity = ['high' => [], 'medium' => [], 'low' => []];
        foreach ($anomalies as $anomaly) {
            $severity = $anomaly['severity'] ?? null;

            // Se não tem severity, inferir do tipo
            if (! $severity && isset($anomaly['tipo'])) {
                $tipo = $anomaly['tipo'];
                // Anomalias negativas com variação grande são high
                $variacao = abs((float) str_replace(['%', '+', '-'], '', $anomaly['variacao'] ?? '0'));
                if ($tipo === 'negativa' && $variacao > 50) {
                    $severity = 'high';
                } elseif ($tipo === 'negativa') {
                    $severity = 'medium';
                } else {
                    $severity = 'low';
                }
            }

            $severity = $severity ?? 'medium';
            $bySeverity[$severity][] = $anomaly;
        }

        // Mostrar high primeiro
        if (! empty($bySeverity['high'])) {
            $output .= "### 🔴 Severidade Alta\n\n";
            foreach ($bySeverity['high'] as $a) {
                $output .= self::formatSingleAnomaly($a);
            }
        }

        if (! empty($bySeverity['medium'])) {
            $output .= "### 🟡 Severidade Média\n\n";
            foreach ($bySeverity['medium'] as $a) {
                $output .= self::formatSingleAnomaly($a);
            }
        }

        if (! empty($bySeverity['low'])) {
            $output .= "### 🟢 Severidade Baixa\n\n";
            foreach ($bySeverity['low'] as $a) {
                $output .= self::formatSingleAnomaly($a);
            }
        }

        return $output;
    }

    /**
     * Formata uma única anomalia.
     * Suporta dois formatos:
     * - Novo: type, description, severity, metric, expected, actual, variation_percent
     * - Original: metrica, atual, historico, variacao, tipo, explicacao_sazonal
     */
    private static function formatSingleAnomaly(array $anomaly): string
    {
        // Mapear campos do formato original para o esperado
        $type = $anomaly['type'] ?? $anomaly['tipo'] ?? 'geral';
        $metric = $anomaly['metric'] ?? $anomaly['metrica'] ?? null;
        $actual = $anomaly['actual'] ?? $anomaly['atual'] ?? null;
        $expected = $anomaly['expected'] ?? $anomaly['historico'] ?? null;
        $variation = $anomaly['variation_percent'] ?? $anomaly['variacao'] ?? null;
        $affectedItems = $anomaly['affected_items'] ?? [];

        // Gerar descrição se não existir
        $description = $anomaly['description'] ?? $anomaly['descricao'] ?? null;
        if (! $description && $metric) {
            // Construir descrição a partir dos dados
            $description = $metric;
            if ($actual !== null && $expected !== null) {
                $description .= " - Atual: {$actual}, Histórico: {$expected}";
            }
            if (isset($anomaly['explicacao_sazonal'])) {
                $description .= " ({$anomaly['explicacao_sazonal']})";
            }
        }
        $description = $description ?? 'Anomalia detectada';

        $output = "- **{$type}:** {$description}\n";

        // Adicionar detalhes se disponíveis e não já incluídos na descrição
        if ($metric && ! str_contains($description, $metric)) {
            $output .= "  - Métrica: {$metric}";
            if ($expected !== null) {
                $output .= " | Esperado: {$expected}";
            }
            if ($actual !== null) {
                $output .= " | Atual: {$actual}";
            }
            if ($variation !== null) {
                // Remover % se já existir
                $variationClean = str_replace('%', '', (string) $variation);
                $output .= " | Variação: {$variationClean}%";
            }
            $output .= "\n";
        }

        if (! empty($affectedItems)) {
            $itemsList = is_array($affectedItems) ? implode(', ', array_slice($affectedItems, 0, 5)) : $affectedItems;
            $output .= "  - Itens afetados: {$itemsList}\n";
        }

        return $output;
    }

    /**
     * Formata estratégias do RAG para o prompt.
     */
    private static function formatRagStrategies(array $strategies): string
    {
        if (empty($strategies)) {
            return 'Nenhuma estratégia específica do nicho disponível. Use práticas gerais de e-commerce.';
        }

        $output = "As seguintes estratégias são recomendadas para este nicho/segmento:\n\n";

        foreach ($strategies as $strategy) {
            $title = $strategy['title'] ?? 'Estratégia';
            $content = $strategy['content'] ?? '';
            $relevance = $strategy['relevance'] ?? null;
            $metadata = $strategy['metadata'] ?? [];

            $output .= "### {$title}\n\n";

            if ($content) {
                $output .= "{$content}\n\n";
            }

            // Adicionar métricas se disponíveis
            if (! empty($metadata['expected_impact'])) {
                $output .= "- **Impacto esperado:** {$metadata['expected_impact']}\n";
            }
            if (! empty($metadata['difficulty'])) {
                $output .= "- **Dificuldade:** {$metadata['difficulty']}\n";
            }
            if (! empty($metadata['implementation_time'])) {
                $output .= "- **Tempo de implementação:** {$metadata['implementation_time']}\n";
            }
            if ($relevance !== null) {
                $relevancePercent = round($relevance * 100);
                $output .= "- **Relevância para esta loja:** {$relevancePercent}%\n";
            }

            $output .= "\n";
        }

        $output .= "**IMPORTANTE:** Use estas estratégias como base, mas adapte para os dados específicos da loja.\n";

        return $output;
    }

    /**
     * Formata benchmarks do RAG para o prompt.
     */
    private static function formatRagBenchmarks(array $benchmarks): string
    {
        if (empty($benchmarks)) {
            return 'Nenhum benchmark específico do nicho disponível.';
        }

        $output = "Benchmarks do setor para comparação:\n\n";

        // Primeiro, verificar se é estrutura de benchmarks estruturados
        if (isset($benchmarks['ticket_medio']) || isset($benchmarks['taxa_conversao'])) {
            // Formato estruturado
            if (isset($benchmarks['ticket_medio'])) {
                $tm = $benchmarks['ticket_medio'];
                if (is_array($tm)) {
                    $min = $tm['min'] ?? 0;
                    $media = $tm['media'] ?? $tm['avg'] ?? 0;
                    $max = $tm['max'] ?? 0;
                    $output .= "**Ticket Médio:**\n";
                    $output .= '- Mínimo: R$ '.number_format($min, 2, ',', '.')."\n";
                    $output .= '- Média: R$ '.number_format($media, 2, ',', '.')."\n";
                    $output .= '- Máximo: R$ '.number_format($max, 2, ',', '.')."\n\n";
                } else {
                    $output .= '**Ticket Médio:** R$ '.number_format($tm, 2, ',', '.')."\n\n";
                }
            }

            if (isset($benchmarks['taxa_conversao'])) {
                $tc = $benchmarks['taxa_conversao'];
                if (is_array($tc)) {
                    $min = $tc['min'] ?? 0;
                    $media = $tc['media'] ?? 0;
                    $max = $tc['max'] ?? 0;
                    $output .= "**Taxa de Conversão:**\n";
                    $output .= "- Mínimo: {$min}%\n";
                    $output .= "- Média: {$media}%\n";
                    $output .= "- Máximo: {$max}%\n\n";
                } else {
                    $output .= "**Taxa de Conversão:** {$tc}%\n\n";
                }
            }

            if (isset($benchmarks['abandono_carrinho'])) {
                $output .= "**Abandono de Carrinho:** {$benchmarks['abandono_carrinho']}%\n\n";
            }

            if (isset($benchmarks['trafego_mobile'])) {
                $output .= "**Tráfego Mobile:** {$benchmarks['trafego_mobile']}%\n\n";
            }

            if (isset($benchmarks['crescimento_setor'])) {
                $output .= "**Crescimento do Setor:** {$benchmarks['crescimento_setor']}% ao ano\n\n";
            }

            return $output;
        }

        // Formato de lista de resultados de busca
        foreach ($benchmarks as $benchmark) {
            $title = $benchmark['title'] ?? 'Benchmark';
            $content = $benchmark['content'] ?? '';
            $metadata = $benchmark['metadata'] ?? [];

            $output .= "### {$title}\n\n";

            if ($content) {
                $output .= "{$content}\n\n";
            }

            // Extrair métricas do metadata
            if (! empty($metadata['metrics'])) {
                $output .= "**Métricas:**\n";
                foreach ($metadata['metrics'] as $metric => $value) {
                    if (is_array($value)) {
                        $output .= "- {$metric}: ".json_encode($value)."\n";
                    } else {
                        $output .= "- {$metric}: {$value}\n";
                    }
                }
                $output .= "\n";
            }

            if (! empty($metadata['sources'])) {
                $sources = is_array($metadata['sources']) ? implode(', ', $metadata['sources']) : $metadata['sources'];
                $output .= "**Fontes:** {$sources}\n\n";
            }
        }

        $output .= "**USE ESTES BENCHMARKS** para comparar com os dados da loja e identificar gaps.\n";

        return $output;
    }

    /**
     * Método get() para manter compatibilidade com o pipeline existente.
     */
    public static function get(array $context): string
    {
        return self::build($context);
    }
}
