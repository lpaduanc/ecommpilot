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

        return <<<PROMPT
        Analise os seguintes dados de uma loja e-commerce e forneça recomendações acionáveis:

        DADOS DA LOJA:
        {$dataJson}

        PERÍODO DE ANÁLISE: {$startDate->format('d/m/Y')} a {$endDate->format('d/m/Y')}

        Forneça sua análise no formato JSON especificado nas instruções do sistema.
        PROMPT;
    }

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
        Você é um consultor de e-commerce. Analise os dados e forneça recomendações.

        REGRAS IMPORTANTES:
        1. Responda APENAS com JSON válido, sem texto antes ou depois
        2. Não use markdown (sem ```)
        3. Mantenha descrições CURTAS (máximo 100 caracteres)
        4. Forneça exatamente 5 sugestões
        5. Forneça exatamente 2 alertas
        6. Forneça exatamente 2 oportunidades

        Formato JSON obrigatório:

        {"summary":{"health_score":75,"health_status":"Bom","main_insight":"Resumo curto"},"suggestions":[{"id":"sug1","category":"marketing","priority":"high","title":"Título curto","description":"Descrição curta","expected_impact":"Impacto esperado","action_steps":["Passo 1","Passo 2"],"is_done":false}],"alerts":[{"type":"warning","message":"Mensagem curta"}],"opportunities":[{"title":"Título","potential_revenue":"R$ 5.000","description":"Descrição curta"}]}

        Categorias válidas: marketing, pricing, inventory, product, customer, conversion
        Prioridades válidas: high, medium, low
        Tipos de alerta: warning, danger, info
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
