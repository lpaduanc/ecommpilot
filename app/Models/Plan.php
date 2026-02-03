<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        // 'price', // REMOVIDO: Price não deve ser mass assignable (manipulação de preços)
        'is_active',
        'sort_order',
        'orders_limit',
        'stores_limit',
        'analysis_per_day',
        'analysis_history_limit',
        'data_retention_months',
        'has_ai_analysis',
        'has_auto_analysis',
        'has_ai_chat',
        'has_suggestion_discussion',
        'has_suggestion_history',
        'has_custom_dashboards',
        'has_external_integrations',
        'has_impact_dashboard',
        'external_integrations_limit',
        'features',
    ];

    /**
     * Campos protegidos contra mass assignment.
     * Price deve ser definido explicitamente apenas por admins.
     */
    protected $guarded = [
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'has_ai_analysis' => 'boolean',
            'has_auto_analysis' => 'boolean',
            'has_ai_chat' => 'boolean',
            'has_suggestion_discussion' => 'boolean',
            'has_suggestion_history' => 'boolean',
            'has_custom_dashboards' => 'boolean',
            'has_external_integrations' => 'boolean',
            'has_impact_dashboard' => 'boolean',
            'features' => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Verifica se um campo de limite é ilimitado.
     */
    public function isUnlimited(string $field): bool
    {
        return $this->{$field} === -1;
    }

    /**
     * Retorna o plano padrão (Starter).
     */
    public static function getDefaultPlan(): ?Plan
    {
        return static::where('slug', 'starter')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Retorna todos os planos ativos ordenados.
     */
    public static function getActivePlans()
    {
        return static::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();
    }

    /**
     * Formata o limite para exibição.
     */
    public function formatLimit(string $field): string
    {
        $value = $this->{$field};

        return $value === -1 ? 'Ilimitado' : number_format($value, 0, ',', '.');
    }
}
