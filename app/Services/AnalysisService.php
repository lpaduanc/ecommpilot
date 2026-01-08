<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\Store;
use App\Models\User;
use App\Services\AI\AIManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AnalysisService
{
    private const RATE_LIMIT_MINUTES = 60;

    public function __construct(
        private AIManager $aiManager
    ) {}

    public function canRequestAnalysis(User $user): bool
    {
        $lastAnalysis = Analysis::where('user_id', $user->id)
            ->latest()
            ->first();

        if (! $lastAnalysis) {
            return true;
        }

        return $lastAnalysis->created_at->addMinutes(self::RATE_LIMIT_MINUTES)->isPast();
    }

    public function getNextAvailableAt(User $user): ?Carbon
    {
        $lastAnalysis = Analysis::where('user_id', $user->id)
            ->latest()
            ->first();

        if (! $lastAnalysis) {
            return null;
        }

        $nextAvailable = $lastAnalysis->created_at->addMinutes(self::RATE_LIMIT_MINUTES);

        return $nextAvailable->isFuture() ? $nextAvailable : null;
    }

    public function processAnalysis(Analysis $analysis): void
    {
        $analysis->markAsProcessing();

        try {
            $store = $analysis->store;
            $storeData = $this->prepareStoreData($store, $analysis->period_start, $analysis->period_end);

            $prompt = $this->buildAnalysisPrompt($storeData, $analysis->period_start, $analysis->period_end);

            $messages = [
                ['role' => 'system', 'content' => $this->getSystemPrompt()],
                ['role' => 'user', 'content' => $prompt],
            ];

            $content = $this->aiManager->chat($messages, [
                'temperature' => 0.7,
                'max_tokens' => 8192,
            ]);

            Log::info('AI response received', ['length' => strlen($content)]);
            $data = $this->parseResponse($content);

            $analysis->markAsCompleted($data);
        } catch (\Exception $e) {
            $analysis->markAsFailed();
            throw $e;
        }
    }

    private function prepareStoreData(Store $store, Carbon $startDate, Carbon $endDate): array
    {
        $orders = $store->orders()
            ->whereBetween('external_created_at', [$startDate, $endDate])
            ->get();

        $products = $store->products()->active()->get();
        $customers = $store->customers()->get();

        $totalRevenue = $orders->where('payment_status', 'paid')->sum('total');
        $averageTicket = $orders->count() > 0 ? $totalRevenue / $orders->count() : 0;

        return [
            'store' => [
                'name' => $store->name,
                'domain' => $store->domain,
            ],
            'metrics' => [
                'total_revenue' => $totalRevenue,
                'total_orders' => $orders->count(),
                'average_ticket' => round($averageTicket, 2),
                'total_products' => $products->count(),
                'total_customers' => $customers->count(),
            ],
            'orders_by_status' => $orders->groupBy('status')->map->count(),
            'orders_by_payment' => $orders->groupBy('payment_status')->map->count(),
            'low_stock_products' => $products->filter(fn ($p) => $p->stock_quantity < 10)->count(),
            'out_of_stock_products' => $products->filter(fn ($p) => $p->stock_quantity <= 0)->count(),
            'top_products' => $products->sortByDesc('price')->take(10)->map(fn ($p) => [
                'name' => $p->name,
                'price' => $p->price,
                'stock' => $p->stock_quantity,
            ])->values(),
        ];
    }

    private function buildAnalysisPrompt(array $storeData, Carbon $startDate, Carbon $endDate): string
    {
        $dataJson = json_encode($storeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $periodDays = $startDate->diffInDays($endDate);

        // Calcular contexto da loja
        $revenue = $storeData['metrics']['total_revenue'] ?? 0;
        $products = $storeData['metrics']['total_products'] ?? 0;
        $orders = $storeData['metrics']['total_orders'] ?? 0;

        $storeSize = match (true) {
            $revenue > 50000 => 'grande (alto faturamento)',
            $revenue > 10000 => 'medio',
            default => 'pequeno/iniciante',
        };

        return <<<PROMPT
## CONTEXTO DA ANALISE

- Loja: {$storeData['store']['name']}
- Porte estimado: {$storeSize}
- Periodo analisado: {$startDate->format('d/m/Y')} a {$endDate->format('d/m/Y')} ({$periodDays} dias)
- Total de produtos ativos: {$products}
- Total de pedidos no periodo: {$orders}
- Faturamento no periodo: R$ {$revenue}

## DADOS COMPLETOS DA LOJA

{$dataJson}

## SUA TAREFA

Analise os dados acima e forneca recomendacoes ESPECIFICAS para esta loja.

IMPORTANTE:
- Use os NOMES DOS PRODUTOS que aparecem em "top_products"
- Mencione os NUMEROS EXATOS dos dados (receita, quantidade de pedidos, estoque)
- Calcule impactos baseado nos valores reais (ex: produto X custa R$50 com 10 em estoque = R$500 potencial)
- Se houver produtos com estoque zerado ou baixo, mencione-os pelo nome
- Analise a distribuicao de status dos pedidos para identificar problemas

Forneca sua analise no formato JSON especificado nas instrucoes do sistema.
PROMPT;
    }

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Voce e um consultor senior de e-commerce com 15 anos de experiencia analisando lojas online brasileiras.

## PROCESSO DE ANALISE (siga estas etapas mentalmente)

1. DIAGNOSTICO: Analise os dados e identifique padroes
   - Verifique se ha produtos sem estoque ou com estoque baixo
   - Identifique os produtos mais caros e se estao vendendo
   - Avalie a distribuicao de pedidos por status
   - Calcule metricas como ticket medio

2. PRIORIZACAO: Determine o que e mais urgente
   - Problemas que estao custando dinheiro AGORA (prioridade alta)
   - Oportunidades de crescimento rapido (prioridade media)
   - Otimizacoes de longo prazo (prioridade baixa)

3. RECOMENDACOES: Crie sugestoes ESPECIFICAS e ACIONAVEIS
   - SEMPRE mencione produtos, numeros ou metricas ESPECIFICOS dos dados fornecidos
   - NAO de conselhos genericos - cada sugestao deve ser unica para ESTA loja
   - Cada sugestao deve poder ser executada em menos de 1 semana

## EXEMPLOS DE SUGESTOES

### EXEMPLO RUIM (generico - NAO FACA ISSO):
{
  "title": "Melhore suas campanhas de marketing",
  "description": "Invista em marketing digital para atrair mais clientes",
  "expected_impact": "Aumento nas vendas"
}

### EXEMPLO BOM (especifico - FACA ASSIM):
{
  "title": "Reabastecer 'Camiseta Polo Azul' - produto esgotado",
  "description": "Este produto aparece nos top 10 por preco (R$ 89,90) mas esta com estoque zerado. Reponha estoque para capturar vendas perdidas.",
  "expected_impact": "Potencial de R$ 899 em vendas se vender 10 unidades"
}

### EXEMPLO RUIM (generico):
{
  "title": "Fidelize seus clientes",
  "description": "Crie programas de fidelidade para aumentar recorrencia"
}

### EXEMPLO BOM (especifico):
{
  "title": "Reduzir taxa de pedidos cancelados (atualmente em 15%)",
  "description": "Dos 120 pedidos do periodo, 18 foram cancelados. Isso representa R$ 2.700 em vendas perdidas. Investigue os motivos: prazo de entrega? Pagamento recusado?",
  "expected_impact": "Recuperar ate R$ 1.350 reduzindo cancelamentos pela metade"
}

## FORMATO DE RESPOSTA (JSON estrito)

{
  "summary": {
    "health_score": 0-100,
    "health_status": "Critico|Precisa Atencao|Bom|Excelente",
    "main_insight": "Uma frase de 1-2 linhas com a observacao mais importante sobre a loja"
  },
  "suggestions": [
    {
      "id": "sug1",
      "category": "marketing|pricing|inventory|product|customer|conversion",
      "priority": "high|medium|low",
      "title": "Titulo claro e especifico com dados da loja (max 80 chars)",
      "description": "Descricao detalhada mencionando produtos e numeros especificos dos dados (max 200 chars)",
      "expected_impact": "Impacto estimado em R$ ou % baseado nos dados (max 100 chars)",
      "action_steps": ["Passo 1 concreto", "Passo 2 concreto", "Passo 3 concreto"],
      "is_done": false
    }
  ],
  "alerts": [
    {
      "type": "danger|warning|info",
      "title": "Titulo do alerta",
      "message": "Descricao do problema com dados especificos da loja"
    }
  ],
  "opportunities": [
    {
      "title": "Oportunidade identificada nos dados",
      "potential_revenue": "R$ X.XXX",
      "description": "Como capturar esta oportunidade baseado nos dados da loja"
    }
  ]
}

## REGRAS CRITICAS

1. Responda APENAS com JSON valido, sem texto antes ou depois
2. NAO use markdown (sem ```)
3. Forneca entre 3 e 7 sugestoes, dependendo da quantidade de insights encontrados
4. Forneca entre 1 e 3 alertas (apenas se houver problemas reais nos dados)
5. Forneca entre 1 e 3 oportunidades
6. CADA sugestao DEVE referenciar dados especificos fornecidos (nomes de produtos, valores, quantidades)
7. Se nao houver dados suficientes para uma categoria, NAO invente - pule essa sugestao
8. Calcule valores de impacto baseado nos dados reais (ex: se produto custa R$50 e tem 20 em estoque, potencial = R$1000)

Categorias validas: marketing, pricing, inventory, product, customer, conversion
Prioridades validas: high, medium, low
Tipos de alerta: danger (urgente), warning (atencao), info (informativo)
PROMPT;
    }

    private function parseResponse(string $content): array
    {
        // Check for truncated response (doesn't end with })
        $trimmedContent = trim($content);
        if (! str_ends_with($trimmedContent, '}') && ! str_ends_with($trimmedContent, '```')) {
            Log::error('AI response appears truncated', [
                'length' => strlen($content),
                'last_100_chars' => substr($content, -100),
            ]);
            throw new \RuntimeException(
                'AI response was truncated. The response did not complete. '.
                'This usually means the output token limit was reached.'
            );
        }

        // Step 1: Remove markdown code blocks
        $content = preg_replace('/```json\s*\n?/i', '', $content);
        $content = preg_replace('/\n?```\s*$/i', '', $content);
        $content = preg_replace('/^```\s*\n?/i', '', $content);

        // Step 2: Extract JSON object
        if (preg_match('/\{[\s\S]*\}/s', $content, $matches)) {
            $content = $matches[0];
        }

        // Step 3: Clean control characters
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);

        // Step 4: Normalize whitespace
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace('/,\s*([}\]])/', '$1', $content);
        $content = trim($content);

        // Try to parse
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMessage = json_last_error_msg();
            Log::error('JSON parse error', [
                'error' => $errorMessage,
                'content_length' => strlen($content),
            ]);

            throw new \RuntimeException("Failed to parse AI response: {$errorMessage}");
        }

        // Validate structure
        if (! isset($data['summary']) || ! isset($data['suggestions'])) {
            throw new \RuntimeException('AI response missing required fields (summary or suggestions)');
        }

        return $data;
    }
}
