<?php

namespace Database\Factories;

use App\Enums\AnalysisStatus;
use App\Models\Analysis;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Analysis>
 */
class AnalysisFactory extends Factory
{
    protected $model = Analysis::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'store_id' => Store::factory(),
            'status' => AnalysisStatus::Pending,
            'period_start' => now()->subDays(30),
            'period_end' => now(),
            'credits_used' => 1,
            'summary' => null,
            'suggestions' => null,
            'alerts' => null,
            'opportunities' => null,
            'raw_response' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the analysis is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalysisStatus::Completed,
            'summary' => [
                'health_score' => fake()->numberBetween(50, 100),
                'health_status' => fake()->randomElement(['Excelente', 'Bom', 'Regular', 'Precisa melhorar']),
                'main_insight' => 'Loja com bom desempenho no periodo analisado.',
            ],
            'suggestions' => [
                [
                    'id' => 'sug1',
                    'category' => 'marketing',
                    'priority' => 'high',
                    'title' => 'Investir em marketing digital',
                    'description' => 'Aumentar investimento em anuncios pagos para expandir alcance.',
                    'expected_impact' => 'Aumento de 20% nas vendas',
                    'is_done' => false,
                ],
                [
                    'id' => 'sug2',
                    'category' => 'pricing',
                    'priority' => 'medium',
                    'title' => 'Revisar precificacao',
                    'description' => 'Analisar concorrencia e ajustar precos para melhor competitividade.',
                    'expected_impact' => 'Melhoria na margem de lucro',
                    'is_done' => false,
                ],
            ],
            'alerts' => [
                [
                    'type' => 'warning',
                    'message' => '5 produtos com estoque baixo precisam de atencao.',
                ],
                [
                    'type' => 'danger',
                    'message' => 'Taxa de cancelamento de pedidos acima da media do setor.',
                ],
            ],
            'opportunities' => [
                [
                    'title' => 'Expansao para marketplace',
                    'potential_revenue' => 'R$ 15.000/mes',
                    'description' => 'Vender em marketplaces pode aumentar significativamente o faturamento.',
                ],
            ],
            'raw_response' => json_encode(['processed' => true]),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the analysis is being processed.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalysisStatus::Processing,
        ]);
    }

    /**
     * Indicate that the analysis has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalysisStatus::Failed,
        ]);
    }

    /**
     * Create an analysis for a specific user and store.
     */
    public function forUserAndStore(User $user, Store $store): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);
    }
}
