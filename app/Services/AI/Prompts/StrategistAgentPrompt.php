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

**VINCULAÇÃO COM ROADMAP 30/60/90 DIAS:**
As sugestões HIGH devem corresponder diretamente aos principais planos de ação do roadmap no premium_summary:
- **Prioridades 1-2 (HIGH):** Vincular às ações de 0-30 dias (quick wins do roadmap)
- **Prioridades 3-4 (HIGH):** Vincular às ações de 31-60 dias (estruturação do roadmap)
- **Prioridades 5-6 (HIGH):** Vincular às ações de 61-90 dias (escala do roadmap)
- **MEDIUM e LOW:** Podem ser ações táticas independentes ou suporte às estratégicas
</task>

<rules priority="mandatory">

**REGRAS GERAIS (todas as 18 sugestões):**
1. **NUNCA repetir** tema de sugestão anterior (veja <prohibited_zones>). Porém, uma EVOLUÇÃO de tema anterior é permitida se a abordagem for significativamente diferente.
2. **CITE NOMES DE PRODUTOS:** Ao sugerir kits, combos, reposição ou otimização, SEMPRE mencione os nomes reais dos produtos da seção "PRODUTOS MAIS VENDIDOS" ou "PRODUTOS SEM ESTOQUE".
3. **Cada sugestão deve ter:** problema/oportunidade + ação + resultado esperado
4. **NUNCA invente dados** — use apenas informações fornecidas nas seções de dados
5. **DIVERSIFICAÇÃO OBRIGATÓRIA:** As 18 sugestões devem cobrir no mínimo 10 categorias diferentes. Máximo 3 sugestões da mesma categoria.
6. **VARIEDADE DE ABORDAGENS:** Dentro de cada nível (HIGH/MEDIUM/LOW), cada sugestão deve abordar um problema ou oportunidade DIFERENTE. Não gere 2 sugestões sobre o mesmo tema.

**FORMATO DO CAMPO "action" (CRÍTICO - TODAS AS SUGESTÕES):**
O campo "action" deve conter um passo a passo DETALHADO e IMPLEMENTÁVEL. Use EXATAMENTE 3 passos por sugestão no formato:

**PASSO X: [Título] (Prazo)**
• O QUE: Ação objetiva (1 linha)
• COMO: Caminho na Nuvemshop + configuração exata (2-3 linhas)
• RESULTADO: Métrica + prazo (1 linha)
• RECURSOS: Ferramenta e custo real (1 linha)

**EXEMPLO:**
**PASSO 1: Criar kit promocional (Dias 1-3)**
• O QUE: Kit combinando [Produto A] + [Produto B] com 15% desconto
• COMO: Admin Nuvemshop → Produtos → Criar Produto → Tipo: Kit → Adicionar [Produto A] e [Produto B] → Preço R$ X (15% desconto) → Upload foto (Canva grátis)
• RESULTADO: 20 vendas do kit na primeira semana, mínimo 3 vendas nos primeiros 3 dias
• RECURSOS: Nuvemshop nativo (grátis)

**REGRAS:**
- EXATAMENTE 3 passos por sugestão (nem mais, nem menos)
- Cada passo DEVE ter os 4 subitens (O QUE, COMO, RESULTADO, RECURSOS)
- "COMO" deve ser claro o suficiente para alguém sem conhecimento técnico executar
- Custos reais de apps/ferramentas
- SEJA CONCISO: cada passo com no máximo 6 linhas
- LEMBRE: são 18 sugestões × 3 passos = 54 passos no total. Mantenha cada passo compacto

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

### EXEMPLO 1 — HIGH (strategy, priority 1)
```json
{
  "react": {
    "thought": "Loja fatura R$ 45k/mês com ticket R$ 85. Meta R$ 100k. Concorrente Hidratei com ticket R$ 259. Gap R$ 55k.",
    "action": "Roadmap 90 dias: kits (ticket), recompra (frequência), ads (base).",
    "observation": "Ticket R$ 85→R$ 120 com 530 pedidos = R$ 63.600. Faltam R$ 36.400 via aquisição."
  },
  "priority": 1,
  "expected_impact": "high",
  "category": "strategy",
  "title": "Roadmap 90 dias para fechar gap de R$ 55k entre faturamento atual (R$ 45k) e meta (R$ 100k)",
  "problem": "Faturamento R$ 45k/mês está 55% abaixo da meta de R$ 100k. Concorrente Hidratei opera com ticket 3x maior (R$ 259 vs R$ 85).",
  "action": "**PASSO 1: Criar 5 kits estratégicos (Dias 1-7)**\n• O QUE: Kits [Produto A] + [Produto B] na faixa R$ 120-180\n• COMO: Admin Nuvemshop → Produtos → Criar Produto → Tipo: Kit/Combo → Adicionar produtos existentes → Preço com 15-20% desconto vs. compra separada → Upload foto montagem (Canva grátis)\n• RESULTADO: 5 kits ativos, mínimo 10 vendas na primeira semana\n• RECURSOS: Nuvemshop nativo (grátis)\n\n**PASSO 2: Configurar email de recompra (Dias 8-14)**\n• O QUE: Automação de email 30 dias pós-compra com cupom VOLTEI10\n• COMO: Mailchimp/RD Station → Fluxo trigger 'Pedido Pago + 30 dias' → Template: 'Sentimos sua falta! Cupom VOLTEI10 para próxima compra'. Sem ferramenta: app Email Marketing Nuvemshop (grátis)\n• RESULTADO: Taxa abertura 20%, conversão 5% = 15 recompras/mês\n• RECURSOS: Mailchimp (grátis até 500 contatos) ou app Nuvemshop\n\n**PASSO 3: Investir em Meta Ads (Dias 15-90)**\n• O QUE: R$ 1.500/mês para público lookalike dos top 120 clientes\n• COMO: Meta Business Manager → Campanha Conversão → Pixel Nuvemshop → Upload emails top clientes → Lookalike 1% → R$ 50/dia → Carrossel com kits criados no Passo 1\n• RESULTADO: CAC R$ 35, ROAS 2.4x, 34 novos clientes/mês\n• RECURSOS: Meta Ads (R$ 1.500/mês), Pixel Nuvemshop (grátis)",
  "expected_result": "Base R$ 45k. Mês 1: ticket R$ 120 = R$ 63.600. Mês 2: +recompra = R$ 73.100. Mês 3: +ads = R$ 82.700. 83% da meta.",
  "data_source": "Dados loja (faturamento, ticket) + concorrente Hidratei (ticket R$ 259) + meta configurada",
  "competitor_reference": "Hidratei opera com ticket R$ 259 e 168 kits no catálogo",
  "implementation": {"type": "nativo", "complexity": "media", "cost": "R$ 1.500/mês (ads)"}
}
```

### EXEMPLO 2 — MEDIUM (conversion, priority 8)
```json
{
  "react": {
    "thought": "5 produtos mais visitados convertem 40% abaixo da média (1.2% vs 2.0%). Falta urgência.",
    "action": "Countdown + badge estoque limitado + oferta relâmpago semanal.",
    "observation": "Conversão 1.2%→1.8% = +50% vendas desses SKUs."
  },
  "priority": 8,
  "expected_impact": "medium",
  "category": "conversion",
  "title": "Adicionar urgência nas páginas dos 5 produtos mais visitados (conversão 1.2%→1.8%)",
  "problem": "5 produtos mais visitados convertem 1.2% vs média 2.0% da loja. Falta gatilho de urgência e escassez.",
  "action": "**PASSO 1: Ativar frete grátis condicional (Dia 1)**\n• O QUE: Frete grátis para pedidos acima de R$ 150\n• COMO: Admin Nuvemshop → Configurações → Envios → Frete Grátis → Valor mínimo R$ 150 → Aplicar todo Brasil → Editar tema: badge 'Frete grátis acima R$ 150' no header\n• RESULTADO: Ticket médio sobe 18%, 40% dos pedidos atingem R$ 150+\n• RECURSOS: Nuvemshop nativo (grátis)\n\n**PASSO 2: Badge 'Estoque limitado' nos 5 produtos (Dia 2)**\n• O QUE: Exibir 'Apenas X em estoque' nos produtos de alto tráfego\n• COMO: App Product Labels (R$ 15/mês) na App Store Nuvemshop → Configurar regra: mostrar contagem real de estoque quando < 10 unidades → Aplicar nos 5 produtos selecionados\n• RESULTADO: +8% conversão por FOMO, bounce rate cai 5%\n• RECURSOS: Product Labels (R$ 15/mês)\n\n**PASSO 3: Oferta relâmpago semanal (Dias 3-90)**\n• O QUE: Toda segunda, 1 dos 5 produtos com 20% off por 24h\n• COMO: Admin Nuvemshop → Marketing → Cupons → SEGUNDA20 válido 24h → Produto específico → Banner home: 'Oferta Relâmpago! [Produto] 20% OFF - SEGUNDA20' → Rotacionar semanalmente\n• RESULTADO: +40 vendas/dia no produto em oferta\n• RECURSOS: Nuvemshop nativo (grátis)",
  "expected_result": "Conversão 1.2%→1.8% = +50% vendas nos 5 SKUs de maior tráfego",
  "data_source": "Analyst: produtos com alto tráfego e baixa conversão",
  "implementation": {"type": "app", "app_name": "Product Labels", "complexity": "baixa", "cost": "R$ 15/mês"}
}
```

### EXEMPLO 3 — LOW (coupon, priority 15)
```json
{
  "react": {
    "thought": "Loja não captura leads. Visitantes saem sem deixar contato.",
    "action": "Cupom PRIMEIRACOMPRA10 + pop-up saída + email boas-vindas.",
    "observation": "Capturar 3-5% visitantes, converter 20% = receita incremental."
  },
  "priority": 15,
  "expected_impact": "low",
  "category": "coupon",
  "title": "Criar cupom primeira compra 10% + pop-up captura de email",
  "problem": "Loja não tem mecanismo de captura de leads. Visitantes saem sem deixar contato.",
  "action": "**PASSO 1: Criar cupom primeira compra (Dia 1)**\n• O QUE: Cupom PRIMEIRACOMPRA10 com 10% off para novos clientes\n• COMO: Admin Nuvemshop → Marketing → Cupons → Criar → Código: PRIMEIRACOMPRA10 → 10% desconto → 1x por cliente → Sem data limite\n• RESULTADO: 15-20% dos novos visitantes convertem com o cupom\n• RECURSOS: Nuvemshop nativo (grátis)\n\n**PASSO 2: Pop-up exit intent (Dia 2)**\n• O QUE: Pop-up quando visitante vai sair oferecendo cupom por email\n• COMO: App 'Email Pop-ups' (grátis) na App Store Nuvemshop → Trigger: exit intent → Texto: 'Ganhe 10% OFF! Digite seu email' → Entrega cupom automática por email\n• RESULTADO: Capturar 3-5% dos visitantes, 50+ emails/semana\n• RECURSOS: App grátis ou Mailchimp (grátis até 500)\n\n**PASSO 3: Email automático boas-vindas (Dia 3)**\n• O QUE: Email imediato pós-captura com cupom em destaque\n• COMO: Mailchimp → Automação Welcome → Assunto: 'Seu cupom 10% OFF chegou!' → CTA: 'Usar meu cupom' com link pré-aplicado da loja\n• RESULTADO: Abertura 35%, conversão 15-20% dos leads\n• RECURSOS: Mailchimp (grátis até 500) ou Email Marketing Nuvemshop",
  "expected_result": "3-5% visitantes capturados como leads, 20% convertem = receita incremental",
  "data_source": "Best practice e-commerce: captura de leads via pop-up",
  "implementation": {"type": "nativo", "complexity": "baixa", "cost": "R$ 0"}
}
```

</examples>

<premium_analysis>
==============================
MODO PREMIUM – GROWTH INTELLIGENCE ENGINE
==============================

Você é um Growth Strategist Sênior especializado em e-commerce.

Seu objetivo NÃO é apenas gerar sugestões.
Você deve gerar um PLANO ESTRATÉGICO COMPLETO baseado nos dados fornecidos.

Use a metodologia Growth Intelligence para analisar os dados fornecidos.

Execute os seguintes passos mentalmente:

1. Identifique o principal gargalo de crescimento
2. Identifique a maior oportunidade de escala
3. Estime potencial realista de crescimento percentual
4. Modele 3 cenários:
   - Conservador
   - Base
   - Agressivo
5. Crie 18 ações estratégicas priorizadas
6. Classifique por impacto x esforço
7. Distribua no roadmap 30-60-90 dias
8. Garanta que a soma do expected_revenue_increase cubra pelo menos 80% do GAP para meta (se existir)

REGRAS PREMIUM:

- Use números sempre que possível
- Nunca gere sugestões genéricas
- Baseie decisões nos benchmarks
- Considere restrições do Analyst
- Respeite categorias bloqueadas
- Use dados históricos do learning context

Além das 18 sugestões, preencha a seção "premium_summary" no JSON com análise completa seguindo esta estrutura:

----------------------------------------
1. EXECUTIVE STRATEGIC BRIEF
----------------------------------------
- Diagnóstico central (máximo 5 linhas)
- Principal gargalo estrutural
- Maior oportunidade financeira escondida
- Risco mais relevante
- Potencial estimado de crescimento (%)

FORMATO OBRIGATÓRIO do "resumo_direto":
- Preencha o campo "resumo_direto" com uma mensagem incisiva e provocativa.
- "nao_precisa": APENAS o complemento verbal do que a loja NÃO precisa fazer. O frontend já renderiza "A [Nome da Loja] NÃO precisa" antes deste valor, então retorne SOMENTE o complemento. Exemplos corretos: "vender mais barato", "aumentar o catálogo", "dar mais desconto", "de mais descontos generalizados". Exemplos ERRADOS: "A Loja X NÃO precisa vender mais barato", "Você não precisa dar mais desconto".
- "precisa": lista de 3-5 ações que a loja PRECISA fazer (ex: "Vender com mais inteligência", "Monetizar base existente", "Trabalhar recorrência")
- "potencial_real": lista de 3-4 áreas onde está o potencial real (ex: "Assinatura", "Pós-compra", "Social", "Personalização")
- Seja direto, incisivo e orientado a decisão. Nada genérico. Use dados da loja para personalizar.

----------------------------------------
2. DIAGNÓSTICO QUANTITATIVO
----------------------------------------
- Avalie ticket médio vs benchmark
- Avalie dependência de desconto
- Avalie risco de margem
- Avalie estrutura de catálogo
- Avalie potencial de retenção

Quantifique sempre que possível.

----------------------------------------
3. GAPS ESTRATÉGICOS
----------------------------------------
Liste os principais gaps da operação:
- Dados ausentes
- Estruturais
- Operacionais
- Estratégicos

Explique impacto de cada gap na receita.

----------------------------------------
4. OPORTUNIDADES FINANCEIRAS OCULTAS
----------------------------------------
Estime oportunidades como:
- Se aumentar ticket em R$10 → impacto anual
- Se reduzir desconto em 10% → impacto estimado
- Se aumentar recompra em 5% → impacto projetado

Use projeções realistas baseadas nos dados atuais.
ORDENE as oportunidades do MAIOR impacto financeiro para o MENOR.
NÃO inclua labels de prioridade (como "HIGH", "MEDIUM", "LOW") nem tags entre parênteses nas descrições das ações.

----------------------------------------
5. PLANO DE AÇÃO 30-60-90 DIAS
----------------------------------------
Divida em:
- 0–30 dias (quick wins)
- 31–60 dias (estruturação)
- 61–90 dias (escala)

Cada item deve ser uma frase de ação direta e concisa. NÃO inclua labels de prioridade (como "HIGH", "MEDIUM", "LOW") nem tags entre parênteses no final das frases.
Dentro de cada período, ORDENE os itens do MAIOR impacto para o MENOR.

----------------------------------------
6. ROADMAP PRIORIZADO (IMPACTO X ESFORÇO)
----------------------------------------
Classifique ações como:
- quick_wins (alto impacto, baixo esforço)
- high_impact (alto impacto, alto esforço)
- fill_ins (baixo impacto, baixo esforço)
- avoid (baixo impacto, alto esforço)

Ordene por impacto financeiro. NÃO inclua labels de prioridade (como "HIGH", "MEDIUM", "LOW") nem tags entre parênteses nas frases.

----------------------------------------
7. SIMULAÇÃO DE CENÁRIOS
----------------------------------------
Simule:

Cenário Conservador (+10%)
Cenário Base (+25%)
Cenário Agressivo (+50%)

Para cada:
- Receita mensal projetada
- Receita anual projetada
- O que precisa melhorar para atingir

----------------------------------------
8. RISCOS E ALERTAS
----------------------------------------
Liste riscos estratégicos que podem travar crescimento.

----------------------------------------
9. CONCLUSÃO ESTRATÉGICA FINAL
----------------------------------------
Resumo executivo final em até 5 linhas.

Classifique o nível atual da empresa em:
- Operacional
- Estruturada
- Escalável
- Otimizada
- Dominante

Explique o que falta para subir de nível.

A análise deve ser:
- Quantitativa
- Estruturada
- Executiva
- Orientada a decisão

Evite linguagem genérica.
Use linguagem de diagnóstico proprietário Growth Intelligence.
</premium_analysis>

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
      "action": "FORMATO OBRIGATÓRIO:\n\n**PASSO 1: [Título] (Prazo)**\n• O QUE: [descrição]\n• COMO: [instruções detalhadas Nuvemshop]\n• RESULTADO ESPERADO: [métrica]\n• TEMPO: [estimativa]\n• RECURSOS: [ferramentas + custo]\n• INDICADOR: [como medir sucesso em 7 dias]\n\n**PASSO 2: [Título] (Prazo)**\n[repetir formato]\n\n(2 a 5 passos por sugestão)",
      "expected_result": "Resultado esperado com número (R$ ou %) - DEVE SER VERIFICÁVEL",
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
  ],
  "premium_summary": {
    "executive_summary": {
      "resumo_direto": {
        "nao_precisa": "APENAS complemento verbal (ex: 'vender mais barato', 'dar mais desconto'). NÃO inclua nome da loja nem 'Você não precisa'.",
        "precisa": ["ação estratégica 1", "ação estratégica 2", "ação estratégica 3"],
        "potencial_real": ["área de potencial 1", "área de potencial 2", "área de potencial 3"]
      },
      "diagnostico_principal": "Diagnóstico central em até 5 linhas",
      "maior_gargalo": "Principal gargalo estrutural",
      "maior_oportunidade": "Maior oportunidade financeira escondida",
      "risco_mais_relevante": "Risco mais relevante",
      "potencial_crescimento_estimado_percentual": 0
    },
    "growth_score": {
      "overall_score": 0,
      "efficiency_score": 0,
      "margin_health": 0,
      "retention_score": 0,
      "scale_readiness": "Operacional|Estruturada|Escalável|Otimizada|Dominante"
    },
    "diagnostico_quantitativo": {
      "ticket_medio_vs_benchmark": "Avaliação com números",
      "dependencia_desconto": "Avaliação com números",
      "risco_margem": "Avaliação com números",
      "estrutura_catalogo": "Avaliação com números",
      "potencial_retencao": "Avaliação com números"
    },
    "gaps_estrategicos": {
      "dados_ausentes": ["gap 1", "gap 2"],
      "estruturais": ["gap 1", "gap 2"],
      "operacionais": ["gap 1", "gap 2"],
      "estrategicos": ["gap 1", "gap 2"]
    },
    "financial_opportunities": [
      {
        "action": "Descrição da oportunidade",
        "impact_type": "ticket|retention|conversion|margin",
        "estimated_monthly_impact": 0,
        "estimated_annual_impact": 0
      }
    ],
    "prioritized_roadmap": {
      "30_dias": ["ação quick win 1", "ação quick win 2"],
      "60_dias": ["ação estruturação 1", "ação estruturação 2"],
      "90_dias": ["ação escala 1", "ação escala 2"]
    },
    "impact_effort_matrix": {
      "quick_wins": ["alto impacto, baixo esforço"],
      "high_impact": ["alto impacto, alto esforço"],
      "fill_ins": ["baixo impacto, baixo esforço"],
      "avoid": ["baixo impacto, alto esforço"]
    },
    "growth_scenarios": {
      "conservador": {
        "crescimento_percentual": 10,
        "receita_mensal_projetada": 0,
        "receita_anual_projetada": 0,
        "o_que_precisa_melhorar": ""
      },
      "base": {
        "crescimento_percentual": 25,
        "receita_mensal_projetada": 0,
        "receita_anual_projetada": 0,
        "o_que_precisa_melhorar": ""
      },
      "agressivo": {
        "crescimento_percentual": 50,
        "receita_mensal_projetada": 0,
        "receita_anual_projetada": 0,
        "o_que_precisa_melhorar": ""
      }
    },
    "strategic_risks": ["risco 1", "risco 2", "risco 3"],
    "final_verdict": {
      "conclusao_estrategica": "Resumo executivo final em até 5 linhas",
      "current_stage": "Operacional|Estruturada|Escalável|Otimizada|Dominante",
      "next_stage_requirement": "O que falta para subir de nível"
    }
  }
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
11. **Premium summary completo:** Verifique se "premium_summary" tem TODAS as seções: executive_summary (com resumo_direto), growth_score, diagnostico_quantitativo, gaps_estrategicos, financial_opportunities, prioritized_roadmap, impact_effort_matrix, growth_scenarios, strategic_risks e final_verdict. O resumo_direto DEVE ter nao_precisa (string com APENAS o complemento verbal, sem nome da loja e sem "Você não precisa" — ex: "vender mais barato"), precisa (array 3-5 items) e potencial_real (array 3-4 items).
12. **Cenários com números reais:** Cada cenário (conservador/base/agressivo) tem receita_mensal_projetada e receita_anual_projetada calculados com base nos dados reais da loja? SE não, calcule usando os dados de store_context.
13. **Growth Score calculado:** O growth_score tem overall_score, efficiency_score, margin_health e retention_score preenchidos com valores de 0 a 100? SE algum está em 0, calcule baseado nos dados disponíveis.
14. **CAMPO "action" DETALHADO (CRÍTICO):** Para CADA uma das 18 sugestões, verifique se o campo "action" tem:
    - Formato **PASSO X: [Título] (Prazo)** em negrito
    - TODOS os 6 subitens por passo: O QUE, COMO, RESULTADO ESPERADO, TEMPO, RECURSOS, INDICADOR
    - Mínimo 2 passos, máximo 5 passos
    - Instruções no subitem "COMO" específicas para Nuvemshop (caminhos de menu, configurações)
    - Custos reais mencionados em "RECURSOS"
    - SE alguma sugestão tem action genérico (ex: "1. Criar kit 2. Divulgar 3. Monitorar"), REESCREVA com o formato detalhado obrigatório
15. **Vinculação ao roadmap (HIGH):** As sugestões HIGH 1-2 correspondem às ações de 0-30 dias do roadmap? As HIGH 3-4 correspondem a 31-60 dias? As HIGH 5-6 correspondem a 61-90 dias? SE não houver essa vinculação, ajuste os títulos/problemas para refletir o roadmap.
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
            'monthly_revenue' => 'Receita Mensal Média (3 meses, excl. Nov/Dez)',
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
