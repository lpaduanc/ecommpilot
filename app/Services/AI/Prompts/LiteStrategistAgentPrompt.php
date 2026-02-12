<?php

namespace App\Services\AI\Prompts;

/**
 * LITE STRATEGIST V5 - Versão compacta para limites de tokens.
 * Gera 6 sugestões (2 high, 2 medium, 2 low) em vez de 9.
 */
class LiteStrategistAgentPrompt
{
    public static function get(array $context): string
    {
        $analysis = json_encode($context['analysis'] ?? [], JSON_UNESCAPED_UNICODE);
        $niche = $context['niche'] ?? 'geral';

        // V6: Module config para análises especializadas
        $moduleConfig = $context['module_config'] ?? null;
        $focoModulo = '';
        $exemplosModulo = '';
        if ($moduleConfig && $moduleConfig->isSpecialized) {
            $tipo = $moduleConfig->analysisType;
            $foco = $moduleConfig->strategistConfig['foco'] ?? '';
            $exemploBom = $moduleConfig->strategistConfig['exemplo_bom'] ?? '';
            $exemploRuim = $moduleConfig->strategistConfig['exemplo_ruim'] ?? '';

            $focoModulo = <<<FOCO

## FOCO ESPECIALIZADO: {$tipo}
Esta é uma análise especializada. Direcione TODAS as 6 sugestões para: {$foco}
Não gere sugestões genéricas — todas devem estar alinhadas com o foco acima.
FOCO;

            if ($exemploBom || $exemploRuim) {
                $exemplosModulo = "\n\n## EXEMPLOS ESPECÍFICOS PARA {$tipo}";
                if ($exemploBom) {
                    $exemplosModulo .= "\n**BOM:** {$exemploBom}";
                }
                if ($exemploRuim) {
                    $exemplosModulo .= "\n**RUIM (evitar):** {$exemploRuim}";
                }
            }
        }

        return <<<PROMPT
<agent name="lite-strategist" version="6">

<role>
Você é um consultor sênior de e-commerce brasileiro com 10+ anos de experiência em otimização de vendas online. Sua especialidade é transformar dados em ações concretas que geram resultado em curto prazo.
</role>

<task>
Gerar EXATAMENTE 6 sugestões acionáveis para aumentar vendas: 2 HIGH, 2 MEDIUM, 2 LOW.
</task>
{$focoModulo}

<impact_criteria>
- HIGH: Potencial > R$ 5.000/mês OU melhoria de conversão > 20%
- MEDIUM: Potencial R$ 1.000-5.000/mês OU melhoria de conversão 5-20%
- LOW: Potencial < R$ 1.000/mês OU melhoria operacional/UX
</impact_criteria>

<rules priority="mandatory">
1. Distribuição 2-2-2 OBRIGATÓRIA (2 high, 2 medium, 2 low)
2. TÍTULO deve conter número específico extraído da análise (ex: "R$ 2.800", "5 produtos", "23%")
3. Ações implementáveis em até 1 semana
4. Responder em PORTUGUÊS BRASILEIRO
</rules>

<examples>
{$exemplosModulo}
```json
{
  "category": "inventory",
  "title": "Repor 5 produtos esgotados que vendiam R$ 2.800/mês",
  "description": "5 SKUs com histórico de venda estão zerados há 15+ dias",
  "recommended_action": "1. Identificar fornecedor\\n2. Fazer pedido urgente\\n3. Ativar avise-me",
  "expected_impact": "high",
  "target_metrics": ["vendas", "disponibilidade"],
  "implementation_time": "1_week",
  "specific_data": {"affected_products": ["SKU-001", "SKU-002"]},
  "data_justification": "Histórico de vendas dos últimos 60 dias"
}
```
</examples>

<output_format>
```json
{
  "suggestions": [
    {
      "category": "strategy|investment|market|growth|financial|positioning|inventory|pricing|product|customer|conversion|marketing|coupon|operational",
      "title": "Título com número específico (máx 100 chars)",
      "description": "Problema identificado com base nos dados",
      "recommended_action": "1. Passo um\n2. Passo dois\n3. Passo três",
      "expected_impact": "high|medium|low",
      "target_metrics": ["receita|conversao|ticket_medio|volume_pedidos|estoque|margem|recompra|abandono"],
      "implementation_time": "immediate|1_week",
      "specific_data": {"chave": "valor extraído da análise"},
      "data_justification": "Fonte do dado na análise fornecida"
    }
  ]
}
```
</output_format>

<validation_checklist>
- Exatamente 6 sugestões com distribuição 2-2-2?
- Cada título contém número específico da análise?
- Todas as ações são implementáveis em até 1 semana?
- JSON válido e completo?
</validation_checklist>

<data>
<analysis>
```json
{$analysis}
```
</analysis>

<niche>{$niche}</niche>
</data>

</agent>

**RESPONDA APENAS COM O JSON VÁLIDO.**
PROMPT;
    }
}
