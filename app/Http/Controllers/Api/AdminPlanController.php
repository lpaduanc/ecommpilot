<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPlanController extends Controller
{
    public function __construct(
        private PlanLimitService $planLimitService
    ) {}

    /**
     * Lista todos os planos.
     */
    public function index(): JsonResponse
    {
        $plans = Plan::orderBy('sort_order')
            ->orderBy('price')
            ->withCount(['subscriptions' => function ($query) {
                $query->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trial]);
            }])
            ->get();

        return response()->json([
            'plans' => $plans,
        ]);
    }

    /**
     * Retorna um plano específico.
     */
    public function show(int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        $subscribersCount = Subscription::where('plan_id', $plan->id)
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trial])
            ->count();

        return response()->json([
            'plan' => $plan,
            'subscribers_count' => $subscribersCount,
        ]);
    }

    /**
     * Cria um novo plano.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:50', 'unique:plans,slug'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'orders_limit' => ['required', 'integer', 'min:-1'],
            'stores_limit' => ['required', 'integer', 'min:-1'],
            'analysis_per_day' => ['required', 'integer', 'min:0'],
            'analysis_history_limit' => ['required', 'integer', 'min:-1'],
            'data_retention_months' => ['required', 'integer', 'min:-1'],
            'has_ai_analysis' => ['boolean'],
            'has_ai_chat' => ['boolean'],
            'has_custom_dashboards' => ['boolean'],
            'has_external_integrations' => ['boolean'],
            'external_integrations_limit' => ['nullable', 'integer', 'min:-1'],
            'features' => ['nullable', 'array'],
        ]);

        $plan = Plan::create($validated);

        return response()->json([
            'message' => 'Plano criado com sucesso.',
            'plan' => $plan,
        ], 201);
    }

    /**
     * Atualiza um plano existente.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:50', Rule::unique('plans')->ignore($plan->id)],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'orders_limit' => ['sometimes', 'integer', 'min:-1'],
            'stores_limit' => ['sometimes', 'integer', 'min:-1'],
            'analysis_per_day' => ['sometimes', 'integer', 'min:0'],
            'analysis_history_limit' => ['sometimes', 'integer', 'min:-1'],
            'data_retention_months' => ['sometimes', 'integer', 'min:-1'],
            'has_ai_analysis' => ['sometimes', 'boolean'],
            'has_ai_chat' => ['sometimes', 'boolean'],
            'has_custom_dashboards' => ['sometimes', 'boolean'],
            'has_external_integrations' => ['sometimes', 'boolean'],
            'external_integrations_limit' => ['nullable', 'integer', 'min:-1'],
            'features' => ['nullable', 'array'],
        ]);

        // Garantir que campos booleanos sejam atualizados mesmo quando false
        if ($request->has('has_ai_analysis')) {
            $validated['has_ai_analysis'] = (bool) $request->input('has_ai_analysis');
        }
        if ($request->has('has_ai_chat')) {
            $validated['has_ai_chat'] = (bool) $request->input('has_ai_chat');
        }
        if ($request->has('has_custom_dashboards')) {
            $validated['has_custom_dashboards'] = (bool) $request->input('has_custom_dashboards');
        }
        if ($request->has('has_external_integrations')) {
            $validated['has_external_integrations'] = (bool) $request->input('has_external_integrations');
        }
        if ($request->has('is_active')) {
            $validated['is_active'] = (bool) $request->input('is_active');
        }

        $plan->update($validated);

        return response()->json([
            'message' => 'Plano atualizado com sucesso.',
            'plan' => $plan->fresh(),
        ]);
    }

    /**
     * Remove um plano.
     */
    public function destroy(int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        // Verificar se há assinaturas ativas
        $activeSubscriptions = $plan->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trial])
            ->count();

        if ($activeSubscriptions > 0) {
            return response()->json([
                'message' => 'Não é possível excluir um plano com assinaturas ativas.',
                'active_subscriptions' => $activeSubscriptions,
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'message' => 'Plano excluído com sucesso.',
        ]);
    }

    /**
     * Atribui um plano a um cliente.
     */
    public function assignToClient(Request $request, int $planId): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'trial_ends_at' => ['nullable', 'date'],
        ]);

        $plan = Plan::findOrFail($planId);
        $user = User::findOrFail($validated['user_id']);

        // Cancelar assinatura ativa atual
        $user->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trial])
            ->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

        // Criar nova assinatura
        $trialEndsAt = $validated['trial_ends_at'] ?? null;
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => $trialEndsAt ? SubscriptionStatus::Trial : SubscriptionStatus::Active,
            'starts_at' => $validated['starts_at'] ?? now(),
            'ends_at' => $validated['ends_at'] ?? null,
            'trial_ends_at' => $trialEndsAt,
        ]);

        return response()->json([
            'message' => 'Plano atribuído com sucesso.',
            'subscription' => $subscription->load('plan'),
        ]);
    }

    /**
     * Remove o plano de um cliente.
     */
    public function removeFromClient(Request $request, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        // Cancelar todas as assinaturas ativas
        $cancelled = $user->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trial])
            ->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

        if ($cancelled === 0) {
            return response()->json([
                'message' => 'Cliente não possui assinatura ativa.',
            ], 422);
        }

        return response()->json([
            'message' => 'Assinatura cancelada com sucesso.',
        ]);
    }

    /**
     * Retorna uso do cliente vs limites do plano.
     */
    public function clientUsage(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        $usage = $this->planLimitService->getUserUsage($user);

        return response()->json($usage);
    }

    /**
     * Retorna a assinatura atual do cliente.
     */
    public function clientSubscription(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        $subscription = $user->activeSubscription();

        if (! $subscription) {
            return response()->json([
                'has_subscription' => false,
                'message' => 'Cliente não possui assinatura ativa.',
            ]);
        }

        return response()->json([
            'has_subscription' => true,
            'subscription' => $subscription->load('plan'),
        ]);
    }

    /**
     * Lista clientes com seus planos.
     */
    public function clientsWithPlans(Request $request): JsonResponse
    {
        $query = User::where('role', 'client')
            ->with(['subscriptions' => function ($q) {
                $q->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trial])
                    ->with('plan')
                    ->latest('starts_at');
            }])
            ->withCount('stores');

        // Filtrar por plano
        if ($request->has('plan_id')) {
            $planId = $request->input('plan_id');
            $query->whereHas('subscriptions', function ($q) use ($planId) {
                $q->where('plan_id', $planId)
                    ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trial]);
            });
        }

        // Filtrar por sem plano
        if ($request->boolean('no_plan')) {
            $query->whereDoesntHave('subscriptions', function ($q) {
                $q->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trial]);
            });
        }

        $clients = $query->orderBy('name')
            ->paginate($request->input('per_page', 20));

        return response()->json($clients);
    }
}
