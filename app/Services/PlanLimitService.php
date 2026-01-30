<?php

namespace App\Services;

use App\Models\AnalysisUsage;
use App\Models\Store;
use App\Models\User;

class PlanLimitService
{
    /**
     * Verifica se o usuário pode adicionar uma nova loja.
     */
    public function canAddStore(User $user): bool
    {
        $plan = $user->currentPlan();

        if (! $plan) {
            return false;
        }

        // -1 = ilimitado
        if ($plan->isUnlimited('stores_limit')) {
            return true;
        }

        $currentStores = $user->stores()->count();

        return $currentStores < $plan->stores_limit;
    }

    /**
     * Verifica se o usuário pode solicitar uma nova análise.
     */
    public function canRequestAnalysis(User $user, ?Store $store = null): bool
    {
        $plan = $user->currentPlan();

        if (! $plan) {
            return false;
        }

        // Verificar se ainda tem análises disponíveis hoje
        return $this->getRemainingAnalysesToday($user, $store) > 0;
    }

    /**
     * Verifica se o usuário pode acessar o chat de IA.
     */
    public function canAccessChat(User $user): bool
    {
        // Admins sempre têm acesso
        if ($user->isAdmin()) {
            return true;
        }

        $plan = $user->currentPlan();

        return $plan?->has_ai_chat ?? false;
    }

    /**
     * Verifica se o usuário pode acessar análises IA.
     */
    public function canAccessAnalysis(User $user): bool
    {
        // Admins sempre têm acesso
        if ($user->isAdmin()) {
            return true;
        }

        $plan = $user->currentPlan();

        return $plan?->has_ai_analysis ?? false;
    }

    /**
     * Verifica se o usuário pode acessar dashboards personalizados.
     */
    public function canAccessCustomDashboards(User $user): bool
    {
        // Admins sempre têm acesso
        if ($user->isAdmin()) {
            return true;
        }

        $plan = $user->currentPlan();

        return $plan?->has_custom_dashboards ?? false;
    }

    /**
     * Verifica se o usuário pode usar integrações externas.
     */
    public function canAccessExternalIntegrations(User $user): bool
    {
        // Admins sempre têm acesso
        if ($user->isAdmin()) {
            return true;
        }

        $plan = $user->currentPlan();

        return $plan?->has_external_integrations ?? false;
    }

    /**
     * Verifica se o usuário pode discutir sugestões com IA.
     */
    public function canDiscussSuggestion(User $user): bool
    {
        // Admins sempre têm acesso
        if ($user->isAdmin()) {
            return true;
        }

        $plan = $user->currentPlan();

        return $plan?->has_suggestion_discussion ?? false;
    }

    /**
     * Verifica se o histórico de discussões de sugestões deve ser persistido.
     */
    public function shouldPersistSuggestionHistory(User $user): bool
    {
        // Admins sempre persistem
        if ($user->isAdmin()) {
            return true;
        }

        $plan = $user->currentPlan();

        return $plan?->has_suggestion_history ?? false;
    }

    /**
     * Verifica se o usuário pode acessar o Dashboard de Impacto nas Vendas.
     */
    public function canAccessImpactDashboard(User $user): bool
    {
        // Admins sempre têm acesso
        if ($user->isAdmin()) {
            return true;
        }

        $plan = $user->currentPlan();

        return $plan?->has_impact_dashboard ?? false;
    }

    /**
     * Verifica se o usuário pode adicionar uma nova integração externa.
     */
    public function canAddExternalIntegration(User $user): bool
    {
        $plan = $user->currentPlan();

        if (! $plan || ! $plan->has_external_integrations) {
            return false;
        }

        // -1 = ilimitado
        if ($plan->isUnlimited('external_integrations_limit')) {
            return true;
        }

        $currentIntegrations = $this->getExternalIntegrationsCount($user);

        return $currentIntegrations < $plan->external_integrations_limit;
    }

    /**
     * Retorna o limite de integrações externas do plano.
     */
    public function getExternalIntegrationsLimit(User $user): int
    {
        $plan = $user->currentPlan();

        return $plan?->external_integrations_limit ?? 0;
    }

    /**
     * Retorna o número de integrações externas ativas do usuário.
     */
    public function getExternalIntegrationsCount(User $user): int
    {
        // Placeholder - retorna 0 por enquanto
        // TODO: Implementar contagem real quando houver tabela de integrações
        return 0;
    }

    /**
     * Retorna o limite de pedidos/mês do plano.
     */
    public function getOrdersLimit(User $user): int
    {
        $plan = $user->currentPlan();

        return $plan?->orders_limit ?? 0;
    }

    /**
     * Retorna o limite de lojas do plano.
     */
    public function getStoresLimit(User $user): int
    {
        $plan = $user->currentPlan();

        return $plan?->stores_limit ?? 0;
    }

    /**
     * Retorna o limite de histórico de análises.
     */
    public function getAnalysisHistoryLimit(User $user): int
    {
        $plan = $user->currentPlan();

        return $plan?->analysis_history_limit ?? 0;
    }

    /**
     * Retorna os meses de retenção de dados.
     */
    public function getDataRetentionMonths(User $user): int
    {
        $plan = $user->currentPlan();

        return $plan?->data_retention_months ?? 12;
    }

    /**
     * Retorna quantas análises ainda podem ser feitas hoje.
     */
    public function getRemainingAnalysesToday(User $user, ?Store $store = null): int
    {
        $plan = $user->currentPlan();

        if (! $plan) {
            return 0;
        }

        $storeId = $store?->id ?? $user->active_store_id;

        if (! $storeId) {
            return 0;
        }

        $todayUsage = AnalysisUsage::getTodayUsage($user->id, $storeId);
        $dailyLimit = $plan->analysis_per_day;

        return max(0, $dailyLimit - $todayUsage);
    }

    /**
     * Retorna quantas análises foram feitas hoje.
     */
    public function getAnalysesUsedToday(User $user, ?Store $store = null): int
    {
        $storeId = $store?->id ?? $user->active_store_id;

        if (! $storeId) {
            return 0;
        }

        return AnalysisUsage::getTodayUsage($user->id, $storeId);
    }

    /**
     * Registra o uso de uma análise.
     */
    public function recordAnalysisUsage(User $user, Store $store): void
    {
        $usage = AnalysisUsage::getOrCreateForToday($user->id, $store->id);
        $usage->incrementCount();
    }

    /**
     * Retorna os limites do plano do usuário.
     */
    public function getUserLimits(User $user): array
    {
        $plan = $user->currentPlan();

        if (! $plan) {
            return $this->getDefaultLimits();
        }

        return [
            'plan_name' => $plan->name,
            'plan_slug' => $plan->slug,
            'orders_limit' => $plan->orders_limit,
            'stores_limit' => $plan->stores_limit,
            'analysis_per_day' => $plan->analysis_per_day,
            'analysis_history_limit' => $plan->analysis_history_limit,
            'data_retention_months' => $plan->data_retention_months,
            'has_ai_analysis' => $plan->has_ai_analysis,
            'has_ai_chat' => $plan->has_ai_chat,
            'has_suggestion_discussion' => $plan->has_suggestion_discussion,
            'has_suggestion_history' => $plan->has_suggestion_history,
            'has_custom_dashboards' => $plan->has_custom_dashboards,
            'has_external_integrations' => $plan->has_external_integrations,
            'has_impact_dashboard' => $plan->has_impact_dashboard,
            'external_integrations_limit' => $plan->external_integrations_limit,
        ];
    }

    /**
     * Retorna limites padrão para usuários sem plano.
     */
    private function getDefaultLimits(): array
    {
        return [
            'plan_name' => 'Sem Plano',
            'plan_slug' => null,
            'orders_limit' => 0,
            'stores_limit' => 0,
            'analysis_per_day' => 0,
            'analysis_history_limit' => 0,
            'data_retention_months' => 0,
            'has_ai_analysis' => false,
            'has_ai_chat' => false,
            'has_suggestion_discussion' => false,
            'has_suggestion_history' => false,
            'has_custom_dashboards' => false,
            'has_external_integrations' => false,
            'external_integrations_limit' => 0,
        ];
    }

    /**
     * Verifica se usuário excedeu limite de pedidos do mês.
     */
    public function hasExceededOrdersLimit(User $user): bool
    {
        $plan = $user->currentPlan();

        if (! $plan || $plan->isUnlimited('orders_limit')) {
            return false;
        }

        $monthlyOrders = $this->getMonthlyOrdersCount($user);

        return $monthlyOrders >= $plan->orders_limit;
    }

    /**
     * Retorna o número de pedidos do mês atual.
     */
    public function getMonthlyOrdersCount(User $user): int
    {
        return $user->stores()
            ->withCount(['orders' => function ($query) {
                $query->whereMonth('external_created_at', now()->month)
                    ->whereYear('external_created_at', now()->year);
            }])
            ->get()
            ->sum('orders_count');
    }

    /**
     * Retorna o uso atual do usuário vs limites do plano.
     */
    public function getUserUsage(User $user): array
    {
        $plan = $user->currentPlan();

        if (! $plan) {
            return [
                'has_plan' => false,
                'message' => 'Cliente não possui plano ativo.',
            ];
        }

        $storesCount = $user->stores()->count();
        $monthlyOrders = $this->getMonthlyOrdersCount($user);

        $store = $user->activeStore;
        $analysesToday = $store ? $this->getAnalysesUsedToday($user, $store) : 0;
        $externalIntegrations = $this->getExternalIntegrationsCount($user);

        return [
            'has_plan' => true,
            'plan' => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
            ],
            'usage' => [
                'stores' => [
                    'current' => $storesCount,
                    'limit' => $plan->stores_limit,
                    'unlimited' => $plan->isUnlimited('stores_limit'),
                    'percentage' => $plan->isUnlimited('stores_limit') ? 0 : round(($storesCount / $plan->stores_limit) * 100),
                ],
                'orders_this_month' => [
                    'current' => $monthlyOrders,
                    'limit' => $plan->orders_limit,
                    'unlimited' => $plan->isUnlimited('orders_limit'),
                    'percentage' => $plan->isUnlimited('orders_limit') ? 0 : round(($monthlyOrders / $plan->orders_limit) * 100),
                ],
                'analyses_today' => [
                    'current' => $analysesToday,
                    'limit' => $plan->analysis_per_day,
                    'remaining' => max(0, $plan->analysis_per_day - $analysesToday),
                ],
                'external_integrations' => [
                    'current' => $externalIntegrations,
                    'limit' => $plan->external_integrations_limit,
                    'unlimited' => $plan->isUnlimited('external_integrations_limit'),
                    'percentage' => $plan->isUnlimited('external_integrations_limit') ? 0 : round(($externalIntegrations / max(1, $plan->external_integrations_limit)) * 100),
                ],
            ],
            'features' => [
                'has_ai_analysis' => $plan->has_ai_analysis,
                'has_ai_chat' => $plan->has_ai_chat,
                'has_suggestion_discussion' => $plan->has_suggestion_discussion,
                'has_suggestion_history' => $plan->has_suggestion_history,
                'has_custom_dashboards' => $plan->has_custom_dashboards,
                'has_external_integrations' => $plan->has_external_integrations,
                'has_impact_dashboard' => $plan->has_impact_dashboard,
            ],
        ];
    }
}
