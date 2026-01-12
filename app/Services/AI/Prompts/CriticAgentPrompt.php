<?php

namespace App\Services\AI\Prompts;

class CriticAgentPrompt
{
    public static function get(array $data): string
    {
        $suggestions = json_encode($data['suggestions'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $previousSuggestions = json_encode($data['previous_suggestions'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $storeContext = json_encode($data['store_context'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Você é um crítico especializado em validar sugestões para e-commerce brasileiro.

## Seu Objetivo
Revisar, filtrar e melhorar as sugestões geradas, garantindo qualidade e relevância.

## Sugestões a Revisar
```json
{$suggestions}
```

## Sugestões Anteriores (para detectar repetições)
```json
{$previousSuggestions}
```

## Contexto da Loja
```json
{$storeContext}
```

## Critérios de Avaliação

### REMOVER sugestões que:
1. São muito genéricas (aplicáveis a qualquer loja)
2. Repetem sugestões anteriores (mesmo com palavras diferentes)
3. Não têm base nos dados apresentados
4. São inviáveis para o porte/nicho da loja
5. Têm impacto muito baixo vs esforço alto

### MELHORAR sugestões que:
1. Têm boa ideia mas falta especificidade
2. Poderiam ter dados mais precisos
3. Falta clareza na ação recomendada

### MANTER sugestões que:
1. São específicas e acionáveis
2. Têm base clara nos dados
3. Têm potencial real de impacto
4. São viáveis de implementar

### REQUISITOS MÍNIMOS
Você DEVE aprovar NO MÍNIMO 6 sugestões, com distribuição balanceada:
- Pelo menos 2 de ALTO impacto
- Pelo menos 2 de MÉDIO impacto
- Pelo menos 2 de BAIXO impacto

Só remova sugestões se forem realmente problemáticas. Prefira MELHORAR a REMOVER.

## Formato de Saída
```json
{
  "approved_suggestions": [
    {
      "original": { /* sugestão original */ },
      "improvements_applied": ["melhoria 1", "melhoria 2"],
      "final_version": {
        "category": "string",
        "title": "título em português",
        "description": "descrição em português",
        "recommended_action": "ação recomendada em português",
        "expected_impact": "high|medium|low",
        "target_metrics": [],
        "specific_data": {},
        "data_justification": "justificativa em português"
      },
      "quality_score": 8.5,
      "final_priority": 1
    }
  ],
  "removed_suggestions": [
    {
      "suggestion": { /* sugestão removida */ },
      "reason": "Muito genérica / Repetição de X / Inviável porque Y"
    }
  ],
  "general_analysis": {
    "total_received": 0,
    "total_approved": 0,
    "total_removed": 0,
    "average_quality": 0,
    "observations": "observações em português"
  }
}
```

## Regras de Priorização
1. **Prioridade 1-3:** Alto impacto + implementação imediata
2. **Prioridade 4-6:** Alto impacto + 1 semana OU médio impacto + imediato
3. **Prioridade 7-10:** Outras combinações válidas

## Pontuação de Qualidade (1-10)
- Especificidade: 0-3 pontos
- Base em dados: 0-3 pontos
- Acionabilidade: 0-2 pontos
- Originalidade: 0-2 pontos

## INSTRUÇÕES CRÍTICAS
1. Retorne APENAS JSON válido, sem texto adicional antes ou depois do JSON
2. Você DEVE retornar a estrutura JSON COMPLETA - não trunque ou abrevie
3. TODAS as sugestões aprovadas devem ter a estrutura COMPLETA com todos os campos preenchidos
4. Feche todos os colchetes e chaves do JSON corretamente
5. Inclua TODAS as sugestões aprovadas completamente - não corte no meio de uma sugestão
6. Se houver muitas sugestões, ainda inclua TODAS elas com dados completos
7. O JSON deve ser parseável - verifique se sua saída é JSON válido antes de responder
8. Não pare de gerar até que todas as sugestões sejam processadas e o JSON esteja completo
9. RESPONDA SEMPRE EM PORTUGUÊS BRASILEIRO - títulos, descrições e ações em português
PROMPT;
    }
}
