<?php

namespace App\Services\AI\Prompts;

use App\Services\Analysis\ThemeKeywords;

class CriticAgentPrompt
{
    /**
     * CRITIC AGENT V7 - SELECTOR
     *
     * Mudanças V7:
     * - Recebe 18 sugestões do Strategist (6 HIGH + 6 MEDIUM + 6 LOW)
     * - Seleciona as melhores 9 (3 HIGH + 3 MEDIUM + 3 LOW)
     * - Critério de seleção por nível: estratégia, data-driven, acionabilidade
     * - Few-shot examples de aprovação/rejeição/melhoria
     * - Constraints específicos e mensuráveis
     */
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

**IMPOSSÍVEL (rejeitar):** Realidade aumentada, IA generativa nativa, Live commerce nativo
RESOURCES;
    }

    public static function get(array $data): string
    {
        $storeName = $data['store_name'] ?? 'Loja';
        $platform = $data['platform'] ?? 'nuvemshop';
        $ticketMedio = $data['ticket_medio'] ?? 0;
        $pedidosMes = $data['pedidos_mes'] ?? 0;
        $faturamentoMes = $ticketMedio * $pedidosMes;

        // Dados adicionais para validação
        $outOfStockPct = $data['out_of_stock_pct'] ?? 'N/D';
        $analystBriefing = $data['analyst_briefing'] ?? [];
        $anomalies = $data['anomalies'] ?? [];

        // V7: Dados detalhados da loja para verificação numérica
        $ordersSummary = $data['orders_summary'] ?? [];
        $productsSummary = $data['products_summary'] ?? [];
        $inventorySummary = $data['inventory_summary'] ?? [];
        $couponsSummary = $data['coupons_summary'] ?? [];
        $storeMetricsSection = self::formatStoreMetrics($ordersSummary, $productsSummary, $inventorySummary, $couponsSummary);

        // V7: Temas saturados separados
        $previousSuggestionsAll = $data['previous_suggestions'] ?? [];
        $allPrevSuggestions = isset($previousSuggestionsAll['all']) ? $previousSuggestionsAll['all'] : $previousSuggestionsAll;
        $saturatedThemesSection = self::formatSaturatedThemes($allPrevSuggestions);

        // Extrair top 5 problemas: Analyst usa problema_1 até problema_5
        $topProblems = '';
        $problems = [];
        if (! empty($analystBriefing['problema_1'])) {
            $problems[] = $analystBriefing['problema_1'];
        }
        if (! empty($analystBriefing['problema_2'])) {
            $problems[] = $analystBriefing['problema_2'];
        }
        if (! empty($analystBriefing['problema_3'])) {
            $problems[] = $analystBriefing['problema_3'];
        }
        if (! empty($analystBriefing['problema_4'])) {
            $problems[] = $analystBriefing['problema_4'];
        }
        if (! empty($analystBriefing['problema_5'])) {
            $problems[] = $analystBriefing['problema_5'];
        }
        // Fallback: formato array
        if (empty($problems)) {
            $problems = $analystBriefing['top_3_problems'] ?? $analystBriefing['main_problems'] ?? [];
        }
        if (! empty($problems)) {
            foreach ($problems as $i => $problem) {
                $n = $i + 1;
                $topProblems .= "  {$n}. {$problem}\n";
            }
        } else {
            $topProblems = "  (Briefing não disponível)\n";
        }

        // Sugestões para revisar
        $suggestions = json_encode($data['suggestions'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        // Sugestões anteriores para detectar repetição
        $previousSuggestions = $data['previous_suggestions'] ?? [];
        $previousFormatted = self::formatPreviousSuggestions($previousSuggestions);

        // Recursos da plataforma
        $platformResources = self::getPlatformResources();

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

        // V6: Module config para análises especializadas
        $moduleConfig = $data['module_config'] ?? null;
        $criteriosModulo = '';
        if ($moduleConfig && $moduleConfig->isSpecialized) {
            $tipo = $moduleConfig->analysisType;
            $criterios = $moduleConfig->criticConfig['criterios_extras'] ?? '';
            if ($criterios) {
                $criteriosModulo = <<<CRIT

<criterios_modulo>
Critérios adicionais de validação para análise {$tipo}:
{$criterios}
</criterios_modulo>
CRIT;
            }
        }

        return <<<PROMPT
<agent name="critic" version="7">

<task>
Revisar as 18 sugestões do Strategist. Seu trabalho é SELECIONAR as melhores 9 sugestões (3 HIGH + 3 MEDIUM + 3 LOW) dentre as 18 recebidas. Para cada nível de impacto (HIGH/MEDIUM/LOW), escolha as 3 melhores e descarte as 3 piores. Você pode melhorar as selecionadas antes de aprovar.
</task>

{$perfilLojaSection}<sugestoes_recebidas>
```json
{$suggestions}
```
</sugestoes_recebidas>

<dados_originais>
## CONTEXTO DA LOJA

- **Nome:** {$storeName}
- **Ticket Médio:** R$ {$ticketMedio}
- **Pedidos/Mês:** {$pedidosMes}
- **Faturamento:** R$ {$faturamentoMes}/mês

## DADOS-CHAVE PARA VALIDAÇÃO

- **Produtos sem estoque:** {$outOfStockPct}% dos ativos
- **Top 5 problemas identificados pelo Analyst:**
{$topProblems}

## SUGESTÕES ANTERIORES (NÃO REPETIR TEMA)

{$previousFormatted}
</dados_originais>

<dados_loja_detalhados>
{$storeMetricsSection}
</dados_loja_detalhados>

<temas_saturados>
{$saturatedThemesSection}
</temas_saturados>

<recursos_plataforma>
{$platformResources}
</recursos_plataforma>

---

<persona>
Você é um Revisor Crítico CÉTICO por natureza. Uma sugestão precisa PROVAR que merece ser aprovada — não basta "parecer boa". Seu trabalho é encontrar falhas, inconsistências numéricas e sugestões genéricas que passariam em qualquer loja. Se todas as sugestões parecem boas, você NÃO está sendo rigoroso o suficiente.
</persona>

<instrucoes_validacao>
## META DE RIGOR

- **Você recebe 18 sugestões e deve selecionar as melhores 9.** Isso significa que EXATAMENTE 9 sugestões serão descartadas. Aproveite para escolher as mais relevantes, específicas e impactantes.
- **Score médio alvo das 9 selecionadas: 7.5-8.5.** Se o score médio ficar acima de 9.0, revise sua avaliação — provavelmente está sendo leniente.
- **Rejeite temas saturados:** Consulte <temas_saturados>. Se um tema aparece 3+ vezes em análises anteriores, REJEITE e substitua por tema novo.
- **Critério de seleção por nível:** Das 6 HIGH, escolha as 3 mais estratégicas e com melhor uso de dados externos. Das 6 MEDIUM, escolha as 3 mais data-driven. Das 6 LOW, escolha as 3 mais acionáveis.

## FRAMEWORK DE VERIFICAÇÃO V1-V6 (OBRIGATÓRIO)

Para CADA sugestão, execute as 6 verificações abaixo. Documente o resultado de cada uma no campo `verificacoes` do output:

- **V1-Números:** Os números citados (ticket médio, quantidade de SKUs, percentuais, faturamento) conferem com <dados_originais> e <dados_loja_detalhados>? Se divergem, corrija.
- **V2-Originalidade:** O tema já aparece em <temas_saturados>? Se sim, REJEITE.
- **V3-Especificidade:** A sugestão poderia ser dada a QUALQUER loja sem alterar nada? Se sim, REJEITE ou torne específica com dados reais da loja.
- **V4-Viabilidade:** A implementação é possível na plataforma conforme <recursos_plataforma>? Qual o custo real?
- **V5-Impacto:** O cálculo de impacto é verificável? Tem base × premissa = resultado? Se faltar, complete.
- **V6-Alinhamento:** A sugestão resolve algum dos 5 problemas do Analyst? (Obrigatório para HIGH - priorize os 3 primeiros)
- **V7-Qualidade dos Passos (CRÍTICO):** O campo "action" tem passos DETALHADOS com formato obrigatório (PASSO X: [Título] (Prazo) + 4 subitens: O QUE, COMO, RESULTADO, RECURSOS)? Se genéricos ou vagos, MELHORE ou REJEITE. NUNCA simplifique passos já detalhados.

## REGRAS

1. **SELECIONAR** (3 por nível): As melhores sugestões que passam em V1-V6, tem dado específico, ação clara, resultado com número
2. **MELHORAR** se necessário: Se uma sugestão selecionada tem potencial mas falta dado específico, ação vaga ou resultado sem número — corrigir antes de aprovar
3. **DESCARTAR** (3 por nível): As sugestões mais fracas, genéricas, repetidas ou com menor impacto
4. **SUBSTITUIR** sugestão descartada APENAS se todas as 6 de um nível forem ruins — criar nova original que passe em V1-V6

**Filosofia por nível:**

- **HIGH (recebe 6, seleciona 3 — ESTRATÉGICAS):** Devem ser visão de negócio: metas, mercado, investimento, posicionamento, crescimento. Categorias aceitas: strategy, investment, market, growth, financial, positioning. Selecione as 3 com melhor uso de dados externos e maior impacto estratégico. Se uma HIGH é puramente operacional, descarte-a.

- **MEDIUM (recebe 6, seleciona 3 — TÁTICAS):** Ações operacionais com dados específicos da loja. Selecione as 3 mais data-driven e com maior impacto mensurável.

- **LOW (recebe 6, seleciona 3 — TÁTICAS):** Quick wins acionáveis. Selecione as 3 mais fáceis de implementar e com resultado mais claro.

**VALIDAÇÃO CRÍTICA: QUALIDADE DOS PASSOS (TODAS AS SUGESTÕES)**

Cada sugestão DEVE ter o campo "action" com passos DETALHADOS seguindo o formato:

**PASSO X: [Título do passo] (Prazo)**
• O QUE: Ação objetiva (1 linha)
• COMO: Caminho na Nuvemshop + configuração exata (2-3 linhas)
• RESULTADO: Métrica + prazo (1 linha)
• RECURSOS: Ferramenta e custo real (1 linha)

**REGRAS DE VALIDAÇÃO DOS PASSOS:**
1. **Se os passos estão DETALHADOS** (seguem o formato acima): PRESERVE. Não simplifique, não resuma, não remova detalhes.
2. **Se os passos estão GENÉRICOS** (ex: "1. Criar kit 2. Divulgar 3. Monitorar"): MELHORE adicionando os 4 subitens obrigatórios OU DESCARTE a sugestão se não conseguir melhorar.
3. **Cada passo deve ter TODOS os 4 subitens** (O QUE, COMO, RESULTADO, RECURSOS). Se faltar algum, complete antes de aprovar.
4. **O subitem "COMO" é o mais importante:** Deve conter instruções tão claras que alguém sem conhecimento técnico consiga executar na Nuvemshop.
5. **Custos reais:** O subitem "RECURSOS" deve mencionar custos exatos de apps/ferramentas (ex: "app X (R$ Y/mês)" ou "Nuvemshop nativo (grátis)").

**EXEMPLO DE AÇÃO REJEITÁVEL:**
```
"action": "1. Criar programa de fidelidade\n2. Divulgar para clientes\n3. Monitorar resultados"
```
**MOTIVO:** Genérico demais. Falta O QUE, COMO (instruções Nuvemshop), RESULTADO, RECURSOS.

**EXEMPLO DE AÇÃO APROVÁVEL:**
```
"action": "**PASSO 1: Instalar app de fidelidade (Dia 1)**\n• O QUE: App Fidelizar+ para programa de pontos\n• COMO: Nuvemshop App Store → Instalar Fidelizar+ (R$ 49/mês, trial 14 dias) → Configurar: 1 ponto = R$ 1 gasto, 100 pontos = R$ 10 desconto → Ativar badge 'Ganhe pontos!' no checkout\n• RESULTADO: 30% dos clientes aderem, 50 cadastrados na 1a semana\n• RECURSOS: Fidelizar+ (R$ 49/mês)"
```

**TESTE DE GENERICIDADE:** Para cada sugestão, pergunte: "Esta sugestão poderia ser dada a QUALQUER loja sem alterar nada?" Se sim → REJEITAR ou rebaixar.

**TESTE DE NÍVEL ESTRATÉGICO para HIGH:** Para cada HIGH, pergunte: "Esta sugestão fala sobre o NEGÓCIO como um todo (mercado, metas, investimento) ou sobre uma TAREFA específica?" Se é tarefa → REBAIXAR para MEDIUM.

<competitor_validation>
### SE houver dados de concorrentes disponíveis nas sugestões:

**REGRA:** No mínimo **2 sugestões HIGH/MEDIUM** devem ter `competitor_reference` preenchido com dados ESPECÍFICOS:

✅ **Dados específicos aceitos:**
- Preços reais: "Hidratei oferece ticket médio de R$ 259"
- Diferenciais únicos: "Noma Beauty usa quiz personalizado com 15 perguntas"
- Promoções ativas: "Forever Liss oferece frete grátis acima de R$130"
- Categorias em destaque: "Hidratei tem 168 kits no catálogo"
- Avaliações: "Concorrente X tem 4.8/5 com 3200 reviews"
- Produtos destaque: "Concorrente Y vende Kit Premium a R$ 249"

❌ **NÃO ACEITO (genérico demais):**
- "Concorrentes oferecem frete grátis"
- "Outras lojas têm programa de fidelidade"
- "O mercado está fazendo X"

**Prioridade:** Citar concorrentes DIFERENTES quando possível.

### SE NÃO houver dados de concorrentes:

- **competitor_reference pode ser null** para todas as sugestões
- Foque em dados internos da loja (métricas, histórico, benchmarks)
- Use práticas padrão do setor como referência
- **NÃO invente dados de concorrentes**
- Use exclusivamente dados de concorrentes presentes em <sugestoes_recebidas> (ou seja, evite criar dados fictícios){$criteriosModulo}
</competitor_validation>

<reasoning_instructions>
ANTES de revisar as sugestões, preencha o campo "reasoning" no JSON com:
1. Avaliação geral da qualidade das 18 sugestões recebidas
2. Critério de seleção: por que escolheu estas 9 e não as outras 9
3. Resumo de decisões (X selecionadas, Y melhoradas, Z descartadas)
4. Pontos fracos identificados nas descartadas
5. Melhorias realizadas nas selecionadas e por quê

Este raciocínio guiará suas decisões de aprovação/melhoria/rejeição.
</reasoning_instructions>

<react_pattern>
Para CADA sugestão que revisar, preencha o campo "review_react" com:
- thought: Análise da qualidade da sugestão (dados citados são reais? ação é viável? resultado é quantificado?)
- action: Decisão tomada (APROVAR/MELHORAR/REJEITAR) com justificativa
- observation: Resultado da decisão (o que mudou, quality score estimado)

Preencha review_react ANTES de decidir o status. Isso garante decisões fundamentadas.
</react_pattern>
</instrucoes_validacao>

<regras_anti_alucinacao>
## REGRAS ANTI-ALUCINAÇÃO

1. **Baseie todas as suas validações exclusivamente nos dados fornecidos** em <dados_originais> e <sugestoes_recebidas>. Quando não houver dados suficientes para validar uma afirmação, sinalize explicitamente: "dado não verificável com as informações disponíveis".
2. **Separe fatos de interpretações:** Ao validar uma sugestão, indique se os números são dados diretos (verificáveis) ou estimativas (não verificáveis).
3. **Quando recalcular impactos,** use os dados de <dados_originais> como fonte de verdade. Se o Strategist usou um número diferente, sinalize a discrepância.
4. **Ao substituir sugestões rejeitadas,** siga as mesmas regras anti-alucinação: use dados reais, identifique fontes, e apresente cálculos verificáveis.
5. **Fique à vontade para marcar sugestões como "dado não verificável"** quando os números citados não puderem ser conferidos com os dados disponíveis.
</regras_anti_alucinacao>

<validacao_factos>
## VALIDAÇÃO DE FATOS OBRIGATÓRIA

Ao revisar cada sugestão do Strategist, execute estas verificações usando <dados_originais> E <dados_loja_detalhados> como fonte de verdade:

1. **Números conferem?** Compare os números citados na sugestão (ticket médio, quantidade de SKUs, percentuais, best-sellers, faturamento) com os dados em <dados_originais> e <dados_loja_detalhados>. Se divergirem, corrija e sinalize.
2. **Tendências de mercado têm fonte?** Se a sugestão afirma algo sobre "tendências" ou "o mercado", verifique se o dado vem dos dados fornecidos ou é afirmação sem fonte. Sinalize afirmações sem fonte verificável.
3. **Classificação data_source correta?** Se a sugestão está marcada como "dado_direto", confirme que o dado realmente existe nos dados fornecidos. Se não existir, reclassifique para "inferencia" ou "best_practice_geral".
4. **Cálculos de impacto verificáveis?** Para sugestões HIGH, verifique se o expected_result mostra base × premissa = resultado. Se faltar alguma parte, complete o cálculo usando dados de <dados_loja_detalhados>.
</validacao_factos>

<exemplos>
## FEW-SHOT: EXEMPLOS DE REVISÃO

<regras_anti_alucinacao>
## REGRAS ANTI-ALUCINAÇÃO

1. **Baseie todas as suas validações exclusivamente nos dados fornecidos** em <dados_originais> e <sugestoes_recebidas>. Quando não houver dados suficientes para validar uma afirmação, sinalize explicitamente: "dado não verificável com as informações disponíveis".
2. **Separe fatos de interpretações:** Ao validar uma sugestão, indique se os números são dados diretos (verificáveis) ou estimativas (não verificáveis).
3. **Quando recalcular impactos,** use os dados de <dados_originais> como fonte de verdade. Se o Strategist usou um número diferente, sinalize a discrepância.
4. **Ao substituir sugestões rejeitadas,** siga as mesmas regras anti-alucinação: use dados reais, identifique fontes, e apresente cálculos verificáveis.
5. **Fique à vontade para marcar sugestões como "dado não verificável"** quando os números citados não puderem ser conferidos com os dados disponíveis.
</regras_anti_alucinacao>

<validacao_factos>
## VALIDAÇÃO DE FATOS OBRIGATÓRIA

Ao revisar cada sugestão do Strategist, execute estas verificações usando <dados_originais> E <dados_loja_detalhados> como fonte de verdade:

1. **Números conferem?** Compare os números citados na sugestão (ticket médio, quantidade de SKUs, percentuais, best-sellers, faturamento) com os dados em <dados_originais> e <dados_loja_detalhados>. Se divergirem, corrija e sinalize.
2. **Tendências de mercado têm fonte?** Se a sugestão afirma algo sobre "tendências" ou "o mercado", verifique se o dado vem dos dados fornecidos ou é afirmação sem fonte. Sinalize afirmações sem fonte verificável.
3. **Classificação data_source correta?** Se a sugestão está marcada como "dado_direto", confirme que o dado realmente existe nos dados fornecidos. Se não existir, reclassifique para "inferencia" ou "best_practice_geral".
4. **Cálculos de impacto verificáveis?** Para sugestões HIGH, verifique se o expected_result mostra base × premissa = resultado. Se faltar alguma parte, complete o cálculo usando dados de <dados_loja_detalhados>.
</validacao_factos>

<exemplos>
## FEW-SHOT: EXEMPLOS DE REVISÃO

### EXEMPLO 1 — APROVAR (sugestão já está boa)

**Sugestão recebida:**
```json
{
  "title": "Reativar 8 SKUs parados há 60+ dias que vendiam R$ 3.200/mês",
  "problem": "8 produtos com histórico de venda estão com estoque mas sem vendas há 60 dias.",
  "expected_result": "Recuperar 60% do histórico = R$ 1.920/mês"
}
```

**Decisão:** APROVAR
**Motivo:** Tem dado específico (8 SKUs, R$ 3.200), ação clara, resultado com número
**Ação:** Manter como está

### EXEMPLO 2 — MELHORAR (falta dado específico E passos genéricos)

**Sugestão recebida:**
```json
{
  "title": "Criar programa de fidelidade",
  "problem": "Clientes não voltam a comprar",
  "action": "1. Contratar app de fidelidade\n2. Configurar pontos\n3. Divulgar programa",
  "expected_result": "Aumentar recompra"
}
```

**Decisão:** MELHORAR
**Motivo:** Falta dado específico, resultado vago, passos GENÉRICOS (falta formato detalhado obrigatório)
**Correção:**
```json
{
  "title": "Criar programa de fidelidade para os 120 clientes que compraram 2+ vezes",
  "problem": "Taxa de recompra atual é 8% (120 de 1.500 clientes). Benchmark do setor é 15-20%.",
  "action": "**PASSO 1: Instalar app Fidelizar+ (Dia 1)**\n• O QUE: App de programa de pontos para recompensar recompra\n• COMO: Nuvemshop App Store → Buscar 'Fidelizar+' → Instalar (R$ 49/mês, trial 14 dias grátis) → Configurar regra: cada R$ 1 gasto = 1 ponto, 100 pontos = R$ 10 desconto → Ativar exibição de pontos no checkout e no email de confirmação\n• RESULTADO ESPERADO: 30% dos clientes ativos (45 clientes) aderem ao programa\n• TEMPO: 1 hora configuração\n• RECURSOS: App Fidelizar+ (R$ 49/mês)\n• INDICADOR: 45 clientes com pontos acumulados na primeira semana\n\n**PASSO 2: Email de divulgação (Dia 2)**\n• O QUE: Comunicar programa aos 1.500 clientes cadastrados\n• COMO: Ferramenta de email Nuvemshop → Criar campanha → Assunto: 'Novo: Ganhe R$ 10 a cada R$ 100 em compras!' → Corpo: explicar regra + CTA 'Fazer nova compra' → Enviar para toda base\n• RESULTADO ESPERADO: Taxa de abertura 25%, 15 pedidos gerados\n• TEMPO: 2 horas criação do email\n• RECURSOS: Email Marketing Nuvemshop (grátis)\n• INDICADOR: 15 pedidos com uso de pontos em 7 dias\n\n**PASSO 3: Acompanhamento mensal (Dias 3-90)**\n• O QUE: Dashboard de métricas do programa\n• COMO: App Fidelizar+ → Relatórios → Exportar mensalmente: clientes com pontos, taxa de resgate, pedidos gerados. Meta: aumentar recompra de 8% para 12% em 60 dias\n• RESULTADO ESPERADO: +60 pedidos/mês de clientes recorrentes\n• TEMPO: 30 min/mês\n• RECURSOS: Dashboard do app (incluso)\n• INDICADOR: Taxa de recompra sobe 1% ao mês",
  "expected_result": "Aumentar taxa de recompra de 8% para 12% = +60 pedidos/mês = R$ 4.800/mês"
}
```

### EXEMPLO 3 — REJEITAR (tema já sugerido antes)

**Sugestão recebida:**
```json
{
  "title": "Implementar quiz de personalização de produtos"
}
```

**Decisão:** REJEITAR
**Motivo:** Tema "Quiz" já aparece nas sugestões anteriores (saturado)
**Substituir por:** Nova sugestão com tema diferente

### EXEMPLO 4 — REJEITAR (impossível na plataforma)

**Sugestão recebida:**
```json
{
  "title": "Implementar provador virtual com realidade aumentada"
}
```

**Decisão:** REJEITAR
**Motivo:** Realidade aumentada não está disponível na Nuvemshop
**Substituir por:** Alternativa viável (ex: fotos 360°, vídeos de produto)

### EXEMPLO 5 — MELHORAR (adicionar competitor_reference)

**Sugestão recebida:**
```json
{
  "title": "Aumentar ticket médio com kits",
  "problem": "Ticket médio de R$ 85 está abaixo do potencial",
  "expected_result": "Aumentar ticket médio em 15%",
  "competitor_reference": null
}
```

**Decisão:** MELHORAR
**Motivo:** Sugestão HIGH sem referência a concorrente. Dados disponíveis mostram que Hidratei tem ticket médio de R$ 259 com foco em kits.
**Correção:**
```json
{
  "title": "Aumentar ticket médio com kits seguindo modelo Hidratei",
  "problem": "Ticket médio de R$ 85 é 67% menor que Hidratei (R$ 259), que foca em kits (168 produtos)",
  "expected_result": "Aumentar ticket médio de R$ 85 para R$ 110 (+29%) = +R$ 2.500/mês",
  "competitor_reference": "Hidratei tem ticket médio de R$ 259 com 168 kits no catálogo"
}
```

### EXEMPLO 6 — VALIDAÇÃO DE CITAÇÕES (contagem)

**Cenário:** Das 18 sugestões recebidas, apenas 1 tem competitor_reference preenchido.

**Ação obrigatória:**
1. Identificar 1+ sugestão sem competitor_reference
2. Adicionar dados específicos de concorrentes disponíveis
3. Resultado: 2+ sugestões com competitor_reference

**Prioridade para adicionar:** Sugestões HIGH primeiro, depois MEDIUM

### EXEMPLO 7 — REJEITAR (passos genéricos demais)

**Sugestão recebida:**
```json
{
  "title": "Otimizar descrições de produtos",
  "problem": "Descrições curtas e sem apelo",
  "action": "1. Reescrever descrições\n2. Adicionar benefícios\n3. Incluir palavras-chave SEO",
  "expected_result": "Melhorar conversão"
}
```

**Decisão:** REJEITAR (ou MELHORAR se houver tempo)
**Motivo:** Passos genéricos demais. Não segue formato obrigatório (falta O QUE, COMO, RESULTADO, RECURSOS). Impossível implementar com essas instruções.
**Substituir por:** Outra sugestão com passos detalhados OU reescrever completamente o campo "action" com o formato correto antes de aprovar.
</exemplos>

<exemplos_contrastivos>
## EXEMPLOS CONTRASTIVOS — BOM vs RUIM

<exemplo_critica_boa>
"SUGESTÃO #3 — DADO_CORRIGIDO: A sugestão afirma que o ticket médio é R$220, mas os dados da loja mostram R$185. Recalculando com o valor correto, o impacto estimado cai de R$5.000 para R$3.800/mês. Recomendo ajustar os números."
**Por que é bom:** identifica erro específico, referencia dado real, recalcula, sugere correção.
</exemplo_critica_boa>

<exemplo_critica_ruim>
"As sugestões estão boas e bem fundamentadas. Recomendo implementar todas."
**Por que é ruim:** não valida dados, não questiona nada, não agrega valor à análise.
</exemplo_critica_ruim>

Siga o padrão da crítica boa. Sempre valide números e questione afirmações.
</exemplos_contrastivos>

<formato_saida>
## FORMATO DE SAÍDA

Retorne APENAS o JSON abaixo:

```json
{
  "reasoning": {
    "quality_assessment": "Avaliação geral das 18 sugestões recebidas",
    "selection_criteria": "Por que estas 9 foram escolhidas e não as outras 9",
    "decisions_summary": "X selecionadas, Y melhoradas, Z descartadas",
    "weak_spots": ["sugestão N: motivo da fraqueza / descarte"],
    "improvements_made": ["sugestão N: o que foi melhorado e por quê"]
  },
  "review_summary": {
    "received": 18,
    "selected": 9,
    "approved": 0,
    "improved": 0,
    "discarded": 0,
    "replacements_created": 0
  },
  "suggestions": [
    {
      "review_react": {
        "thought": "Análise da qualidade: dados reais? ação viável? resultado quantificado?",
        "action": "APROVAR/MELHORAR/REJEITAR - justificativa",
        "observation": "O que mudou, quality score estimado"
      },
      "original_title": "Título original do Strategist",
      "status": "approved|improved|replaced",
      "changes_made": "Nenhuma | Descrição das melhorias | Motivo da substituição",
      "verificacoes": {
        "V1_numeros": {"resultado": "ok|corrigido|nao_verificavel", "detalhe": "Ticket médio confere: R$ 85"},
        "V2_originalidade": {"resultado": "ok|rejeitado", "detalhe": "Tema inédito"},
        "V3_especificidade": {"resultado": "ok|rejeitado|melhorado", "detalhe": "Usa dados específicos da loja"},
        "V4_viabilidade": {"resultado": "ok|rejeitado", "detalhe": "Viável via Nuvemshop nativo"},
        "V5_impacto": {"resultado": "ok|corrigido|nao_verificavel", "detalhe": "120 pedidos × R$85 × 15% = R$1.530/mês"},
        "V6_alinhamento": {"resultado": "ok|nao_aplicavel", "detalhe": "Resolve problema_1 do Analyst"},
        "V7_qualidade_passos": {"resultado": "ok|melhorado|rejeitado", "detalhe": "3 passos com 4 subitens (O QUE, COMO, RESULTADO, RECURSOS)"}
      },
      "verificacao_status": "VERIFICADA|DADO_CORRIGIDO|NAO_VERIFICAVEL",
      "score_qualidade": 8,
      "final": {
        "priority": 1,
        "expected_impact": "high",
        "category": "strategy|investment|market|growth|financial|positioning|inventory|pricing|product|customer|conversion|marketing|coupon|operational",
        "title": "Título final (pode ser igual ao original ou melhorado)",
        "problem": "Problema com dado específico",
        "action": "Passos numerados",
        "expected_result": "Resultado com número (R$ ou %)",
        "data_source": "Fonte do dado",
        "implementation": {
          "type": "nativo|app|terceiro",
          "app_name": "nome ou null",
          "complexity": "baixa|media|alta",
          "cost": "R$ X/mês ou R$ 0"
        },
        "competitor_reference": "OBRIGATÓRIO para HIGH quando dados disponíveis, opcional para MEDIUM/LOW",
        "insight_origem": "problema_1|problema_2|problema_3|problema_4|problema_5|best_practice",
        "nivel_confianca": "alto|medio|baixo"
      }
    }
  ],
  "distribution_check": {
    "high": 3,
    "medium": 3,
    "low": 3,
    "valid": true
  },
  "competitor_citations_check": {
    "count": 2,
    "minimum_required": 2,
    "valid": true,
    "competitors_cited": ["Hidratei", "Noma Beauty"]
  },
  "temas_rejeitados_por_saturacao": ["Quiz", "Frete Grátis"],
  "quality_summary": {
    "total_verificadas": 0,
    "total_corrigidas": 0,
    "total_nao_verificaveis": 0,
    "score_medio": 0
  }
}
```

## CHECKLIST ANTES DE ENVIAR

- [ ] Exatamente 9 sugestões SELECIONADAS no array `suggestions`? (das 18 recebidas, apenas as 9 melhores)
- [ ] Distribuição 3 HIGH, 3 MEDIUM, 3 LOW?
- [ ] **As 3 HIGH selecionadas são ESTRATÉGICAS?** Categorias: strategy|investment|market|growth|financial|positioning. Se alguma HIGH usa inventory/product/coupon/operational → substituir por outra HIGH estratégica das 6 recebidas.
- [ ] **As 3 HIGH usam dados externos?** Concorrentes, mercado, benchmarks — não apenas dados internos.
- [ ] Todos os temas são inéditos em relação às sugestões anteriores?
- [ ] Todas viáveis na Nuvemshop?
- [ ] Toda sugestão tem `expected_result` com número?
- [ ] Toda HIGH tem dado específico no `problem`?
- [ ] **SE houver dados de concorrentes:** mínimo 2 sugestões com competitor_reference específico
- [ ] **SE NÃO houver dados de concorrentes:** competitor_reference pode ser null, foque em dados internos
- [ ] Mínimo 6 categorias diferentes nas 9 sugestões selecionadas?
- [ ] Cada HIGH tem cálculo de impacto (base × premissa = resultado)?
- [ ] Cada sugestão tem review_react preenchido?
- [ ] reasoning tem quality_assessment, selection_criteria, decisions_summary, weak_spots e improvements_made?
- [ ] **CRÍTICO: QUALIDADE DOS PASSOS (TODAS AS 9 SUGESTÕES):** Para CADA sugestão selecionada, verificar se o campo "action" tem:
  - [ ] Formato **PASSO X: [Título] (Prazo)** em negrito
  - [ ] TODOS os 4 subitens em cada passo: O QUE, COMO, RESULTADO, RECURSOS
  - [ ] Subitem "COMO" com instruções específicas para Nuvemshop (caminhos de menu, configurações)
  - [ ] Subitem "RECURSOS" com custos reais (ex: "R$ X/mês" ou "grátis")
  - [ ] Exatamente 3 passos por sugestão
  - [ ] **SE alguma sugestão tem action genérico (ex: "1. Fazer X 2. Fazer Y"), MELHORE com formato detalhado OU DESCARTE**

**RESPONDA APENAS COM O JSON. PORTUGUÊS BRASILEIRO.**
</formato_saida>

</agent>
PROMPT;
    }

    private static function formatPreviousSuggestions(array $previousSuggestions): string
    {
        if (empty($previousSuggestions)) {
            return 'Nenhuma sugestão anterior. Todas serão consideradas originais.';
        }

        $grouped = [];
        foreach ($previousSuggestions as $s) {
            $title = $s['title'] ?? 'Sem título';
            $category = $s['category'] ?? 'outros';
            if (! isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            if (! in_array($title, $grouped[$category])) {
                $grouped[$category][] = $title;
            }
        }

        $output = 'Total: '.count($previousSuggestions)." sugestões anteriores\n\n";
        foreach ($grouped as $cat => $titles) {
            $output .= "**{$cat}:** ".implode(', ', $titles)."\n";
        }

        // Use centralized theme keywords
        $themeKeywords = ThemeKeywords::all();
        $themeLabels = ThemeKeywords::labels();

        // Build keywords map with readable labels
        $keywords = [];
        foreach ($themeKeywords as $themeKey => $keywordList) {
            $label = $themeLabels[$themeKey] ?? ucfirst(str_replace('_', ' ', $themeKey));
            $keywords[$label] = $keywordList;
        }

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

        // Threshold de 3+: temas com 3 ou mais ocorrências são considerados saturados
        $saturated = array_filter($counts, fn ($c) => $c >= 3);
        if (! empty($saturated)) {
            arsort($saturated);
            $output .= "\n**⚠️ TEMAS SATURADOS (REJEITAR):**\n";
            foreach ($saturated as $t => $c) {
                $output .= "- ❌ {$t} ({$c}x)\n";
            }
            $output .= "\n**CRIAR SUGESTÕES COM TEMAS DIFERENTES DOS LISTADOS ACIMA!**\n";
        }

        // Show used themes (1-2x) as a warning but not blocking
        $used = array_filter($counts, fn ($c) => $c >= 1 && $c < 3);
        if (! empty($used)) {
            arsort($used);
            $output .= "\n**⚠️ TEMAS JÁ USADOS (EVITAR SE POSSÍVEL):**\n";
            foreach ($used as $t => $c) {
                $output .= "- ⚠️ {$t} ({$c}x)\n";
            }
        }

        return $output;
    }

    /**
     * V7: Formatar métricas detalhadas da loja para verificação numérica pelo Critic.
     */
    private static function formatStoreMetrics(array $orders, array $products, array $inventory, array $coupons): string
    {
        $output = "## PEDIDOS (período de análise)\n";
        $output .= '- Total de pedidos: '.($orders['total'] ?? 0)."\n";
        $output .= '- Período: '.($orders['period_days'] ?? 15)." dias\n";
        $output .= '- Receita total (pagos): R$ '.number_format($orders['total_revenue'] ?? 0, 2, ',', '.')."\n";
        $output .= '- Ticket médio: R$ '.number_format($orders['average_order_value'] ?? 0, 2, ',', '.')."\n";
        $output .= '- Taxa de cancelamento: '.($orders['cancellation_rate'] ?? 0)."%\n";

        if (! empty($orders['by_payment_status'])) {
            $output .= "- Status de pagamento:\n";
            foreach ($orders['by_payment_status'] as $status => $count) {
                $output .= "  - {$status}: {$count}\n";
            }
        }

        $output .= "\n## PRODUTOS\n";
        $output .= '- Total de produtos: '.($products['total'] ?? 0)."\n";
        $output .= '- Produtos ativos: '.($products['active'] ?? 0)."\n";
        $output .= '- Sem estoque: '.($products['out_of_stock'] ?? 0)."\n";

        if (! empty($products['best_sellers'])) {
            $output .= "- Top produtos mais vendidos:\n";
            foreach (array_slice($products['best_sellers'], 0, 5) as $bs) {
                $name = $bs['name'] ?? 'N/D';
                $qty = $bs['quantity_sold'] ?? 0;
                $rev = number_format($bs['revenue'] ?? 0, 2, ',', '.');
                $stock = $bs['current_stock'] ?? 0;
                $output .= "  - {$name}: {$qty} vendidos, R$ {$rev}, estoque atual: {$stock}\n";
            }
        }

        if (! empty($products['no_sales_period'])) {
            $noSalesCount = is_array($products['no_sales_period']) ? count($products['no_sales_period']) : $products['no_sales_period'];
            $output .= "- Produtos sem vendas no período: {$noSalesCount}\n";
        }

        $output .= "\n## ESTOQUE\n";
        $output .= '- Valor total em estoque: R$ '.number_format($inventory['total_value'] ?? 0, 2, ',', '.')."\n";
        $output .= '- Produtos com estoque baixo: '.($inventory['low_stock_products'] ?? 0)."\n";
        $output .= '- Produtos com excesso de estoque (>100 un.): '.($inventory['excess_stock_products'] ?? 0)."\n";

        $output .= "\n## CUPONS\n";
        if (! empty($coupons)) {
            $output .= '- Total de cupons: '.($coupons['total'] ?? 0)."\n";
            $output .= '- Cupons ativos: '.($coupons['active'] ?? 0)."\n";
            if (isset($coupons['usage_rate'])) {
                $output .= '- Taxa de uso: '.($coupons['usage_rate'] ?? 0)."%\n";
            }
            if (isset($coupons['discount_impact'])) {
                $output .= '- Impacto em desconto: R$ '.number_format($coupons['discount_impact'] ?? 0, 2, ',', '.')."\n";
            }
        } else {
            $output .= "- Dados de cupons não disponíveis\n";
        }

        return $output;
    }

    /**
     * V7: Formatar temas saturados como seção separada para o Critic.
     */
    private static function formatSaturatedThemes(array $previousSuggestions): string
    {
        if (empty($previousSuggestions)) {
            return 'Nenhuma sugestão anterior. Todos os temas são permitidos.';
        }

        // Use centralized theme keywords
        $themeKeywords = ThemeKeywords::all();
        $themeLabels = ThemeKeywords::labels();

        // Build keywords map with readable labels
        $keywords = [];
        foreach ($themeKeywords as $themeKey => $keywordList) {
            $label = $themeLabels[$themeKey] ?? ucfirst(str_replace('_', ' ', $themeKey));
            $keywords[$label] = $keywordList;
        }

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

        $saturated = array_filter($counts, fn ($c) => $c >= 3);
        if (empty($saturated)) {
            $used = array_filter($counts, fn ($c) => $c >= 1 && $c < 3);
            if (empty($used)) {
                return 'Nenhuma sugestão anterior com temas relevantes. Todos os temas são permitidos.';
            }

            return "Nenhum tema saturado (3+ ocorrências). Todos os temas são permitidos.\n\nTemas já usados (1-2x, preferir evitar): ".implode(', ', array_keys($used));
        }

        arsort($saturated);
        $output = "**TEMAS SATURADOS (3+ ocorrências - SCORE AUTOMÁTICO 0, NÃO PASSAM V2-Originalidade):**\n";
        $output .= "Qualquer sugestão que aborde estes temas DEVE receber score_qualidade = 0 e ser DESCARTADA.\n\n";
        foreach ($saturated as $t => $c) {
            $output .= "- {$t} ({$c}x) — REJEITAR AUTOMATICAMENTE e substituir por tema novo\n";
        }

        $allowed = array_filter($counts, fn ($c) => $c >= 1 && $c < 3);
        if (! empty($allowed)) {
            arsort($allowed);
            $output .= "\n**Temas usados 1-2x (permitidos mas evitar se possível):**\n";
            foreach ($allowed as $t => $c) {
                $output .= "- {$t} ({$c}x)\n";
            }
        }

        return $output;
    }

    /**
     * Método build() para compatibilidade.
     */
    public static function build(array $context): string
    {
        return self::get($context);
    }

    /**
     * Template resumido para referência.
     */
    public static function getTemplate(): string
    {
        return <<<'TEMPLATE'
# CRITIC — REVISOR DE SUGESTÕES

## TAREFA
Receber 18 sugestões. Selecionar as 9 melhores (3 HIGH + 3 MEDIUM + 3 LOW).

## DECISÕES
- SELECIONAR: as 3 melhores por nível de impacto
- MELHORAR: corrigir o que falta antes de aprovar
- DESCARTAR: as 3 piores por nível de impacto

## OUTPUT
JSON com array de 9 sugestões selecionadas e revisadas.

PORTUGUÊS BRASILEIRO
TEMPLATE;
    }
}
