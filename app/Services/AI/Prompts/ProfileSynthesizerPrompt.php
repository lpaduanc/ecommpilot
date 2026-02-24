<?php

namespace App\Services\AI\Prompts;

class ProfileSynthesizerPrompt
{
    /**
     * PROFILE SYNTHESIZER AGENT
     *
     * Generates a store profile JSON that will be shared with all subsequent agents.
     * Runs BEFORE the Collector and provides foundational context.
     */
    public static function get(array $context): string
    {
        $storeName = $context['store_name'] ?? 'Loja';
        $platformName = $context['platform_name'] ?? 'Nuvemshop';
        $niche = $context['niche'] ?? 'geral';
        $subcategory = $context['subcategory'] ?? 'geral';
        $storeUrl = $context['store_url'] ?? 'N/D';
        $storeStats = json_encode($context['store_stats'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $benchmarks = json_encode($context['structured_benchmarks'] ?? $context['benchmarks'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $currentDate = now()->format('d/m/Y');
        $storeGoals = $context['store_goals'] ?? [];
        $goalsSection = self::formatGoalsForProfile($storeGoals);

        return <<<PROMPT
# PROFILE SYNTHESIZER — PERFIL DA LOJA

<dados_loja>
| Campo | Valor |
|-------|-------|
| Nome | {$storeName} |
| Plataforma | {$platformName} |
| Nicho | {$niche} / {$subcategory} |
| URL | {$storeUrl} |

### Estatísticas
```json
{$storeStats}
```

### Benchmarks do Setor
```json
{$benchmarks}
```

### Objetivos e Tráfego da Loja
{$goalsSection}
</dados_loja>

<persona>
Você é um especialista em e-commerce brasileiro com ampla experiência em análise de lojas online.
</persona>

<instrucoes>
Com base nas informações fornecidas em <dados_loja>, gere um perfil sintetizado da loja.

Regras:
- Use apenas informações que você pode verificar ou inferir com alta confiança dos dados fornecidos.
- Para campos que não podem ser determinados com os dados disponíveis, use "nao_determinado".
- O campo sazonalidade_relevante deve considerar o nicho da loja e a data atual da análise ({$currentDate}).
- **Baseie diferenciais em dados mensuráveis, não em suposições.** Ex: "168 kits no catálogo" (mensuráveis) ao invés de "boa variedade" (subjetivo).
- Se visitas mensais estiverem informadas, considere para classificar maturidade digital: <1.000 (iniciante), 1.000-10.000 (intermediario), >10.000 (avancado) — combine com outros sinais.
- Se meta mensal estiver disponível, use para inferir ambição e validar porte estimado.
- Seja conciso e factual.
</instrucoes>

<regras_anti_alucinacao>
- Use "nao_determinado" para qualquer campo que não pode ser preenchido com confiança.
- Fique à vontade para deixar campos sem resposta ao invés de inventar.
- Baseie todas as suas afirmações exclusivamente nos dados fornecidos em <dados_loja>.
- Quando citar números, eles devem vir diretamente dos dados fornecidos.
- Diferenciais devem ser baseados em dados observáveis (números, funcionalidades, categorias), não em adjetivos subjetivos.
</regras_anti_alucinacao>

<formato_saida>
Retorne EXCLUSIVAMENTE o JSON abaixo, sem texto adicional:

```json
{
  "store_profile": {
    "nome": "nome da loja",
    "url": "url da loja",
    "plataforma": "nuvemshop|shopify|vtex|tray|outro",
    "nicho": "nicho identificado",
    "nicho_detalhado": "sub-nicho se identificável",
    "porte_estimado": "micro|pequeno|medio|grande",
    "maturidade_digital": "iniciante|intermediario|avancado",
    "publico_alvo_estimado": "descrição do público",
    "diferenciais_visiveis": ["diferencial com dado mensurável (ex: '168 kits no catálogo', 'frete grátis acima de R$99')"],
    "sazonalidade_relevante": "descreva eventos sazonais do nicho"
  },
  "contexto_analise": {
    "data_analise": "data atual",
    "eventos_sazonais_proximos": ["lista de datas comerciais próximas"],
    "observacoes_iniciais": "primeiras impressões sobre a loja"
  }
}
```

### Critérios para classificação:
- **porte_estimado:** micro (<R\$10k/mês), pequeno (R\$10-50k), medio (R\$50-200k), grande (>R\$200k)
- **maturidade_digital:** iniciante (poucas integrações, catálogo básico), intermediario (presença online estabelecida), avancado (multi-canal, CRM, automações). Se monthly_visits disponível: <1.000 (iniciante), 1.000-10.000 (intermediario), >10.000 (avancado) — combinar com outros sinais
</formato_saida>

**RESPONDA APENAS COM O JSON. PORTUGUÊS BRASILEIRO.**
PROMPT;
    }

    private static function formatGoalsForProfile(array $goals): string
    {
        $filtered = array_filter($goals, fn ($v) => ! is_array($v) && ! empty($v) && $v != 0);
        if (empty($filtered)) {
            return 'Nenhum objetivo configurado pela loja.';
        }

        $labels = [
            'monthly_goal' => 'Meta Mensal de Faturamento',
            'annual_goal' => 'Meta Anual de Faturamento',
            'target_ticket' => 'Ticket Médio Alvo',
            'monthly_revenue' => 'Receita Mensal Atual',
            'monthly_visits' => 'Visitas Mensais',
        ];

        $currencyKeys = ['monthly_goal', 'annual_goal', 'target_ticket', 'monthly_revenue'];
        $output = '';

        foreach ($filtered as $key => $value) {
            $label = $labels[$key] ?? $key;
            $formatted = in_array($key, $currencyKeys)
                ? 'R$ '.number_format((float) $value, 2, ',', '.')
                : number_format((float) $value, 0, ',', '.');
            $output .= "- {$label}: {$formatted}\n";
        }

        return $output;
    }

    public static function getTemplate(): string
    {
        return <<<'TEMPLATE'
# PROFILE SYNTHESIZER — PERFIL DA LOJA

## TAREFA
Gerar um perfil sintetizado da loja baseado em dados observáveis.

## OUTPUT
JSON com: store_profile (nome, plataforma, nicho, porte, maturidade, público-alvo, diferenciais, sazonalidade) e contexto_analise (data, eventos sazonais, observações).

## REGRA
NUNCA INVENTE DADOS. Se não disponível, use "nao_determinado".

PORTUGUÊS BRASILEIRO
TEMPLATE;
    }
}
