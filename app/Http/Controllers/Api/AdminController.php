<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Store;
use App\Models\SyncedOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboardStats(): JsonResponse
    {
        $totalClients = User::where('role', UserRole::Client)->count();
        $activeClients = User::where('role', UserRole::Client)->where('is_active', true)->count();
        $totalStores = Store::count();
        $totalCreditsUsed = User::where('role', UserRole::Client)->sum('ai_credits');

        // Calculate MRR (mock calculation - in real app, this would come from subscription data)
        $mrr = $activeClients * 99.90;

        // Revenue from stores this month
        $monthlyRevenue = SyncedOrder::whereHas('store.user', function ($q) {
                $q->where('role', UserRole::Client);
            })
            ->whereMonth('external_created_at', now()->month)
            ->whereYear('external_created_at', now()->year)
            ->where('payment_status', 'paid')

            ->sum('total');

        // New clients this month
        $newClientsThisMonth = User::where('role', UserRole::Client)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        return response()->json([
            'total_clients' => $totalClients,
            'active_clients' => $activeClients,
            'inactive_clients' => $totalClients - $activeClients,
            'total_stores' => $totalStores,
            'total_credits_available' => $totalCreditsUsed,
            'mrr' => $mrr,
            'clients_revenue' => $monthlyRevenue,
            'new_clients_this_month' => $newClientsThisMonth,
        ]);
    }

    public function clients(Request $request): JsonResponse
    {
        $query = User::where('role', UserRole::Client)
            ->with(['stores' => function ($q) {
                $q->select('id', 'user_id', 'name', 'platform', 'sync_status', 'last_sync_at');
            }]);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        // Filter by credits
        if ($request->input('credits_filter') === 'low') {
            $query->where('ai_credits', '<', 10);
        } elseif ($request->input('credits_filter') === 'zero') {
            $query->where('ai_credits', 0);
        }

        // Filter by date range
        if ($request->input('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->input('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Sort
        $sortField = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortField, $sortDir);

        $perPage = $request->input('per_page', 20);
        $clients = $query->paginate($perPage);

        // Transform data
        $clients->getCollection()->transform(function ($client) {
            return [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'is_active' => $client->is_active,
                'ai_credits' => $client->ai_credits,
                'stores_count' => $client->stores->count(),
                'stores' => $client->stores->map(fn($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'platform' => $s->platform,
                    'sync_status' => $s->sync_status,
                ]),
                'last_login_at' => $client->last_login_at?->toISOString(),
                'created_at' => $client->created_at->toISOString(),
            ];
        });

        return response()->json($clients);
    }

    public function clientDetail(int $id): JsonResponse
    {
        $client = User::where('role', UserRole::Client)
            ->with(['stores.products', 'stores.orders', 'stores.customers', 'analyses'])
            ->findOrFail($id);

        // Get activity logs
        $activityLogs = ActivityLog::where('user_id', $client->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Calculate stats
        $totalOrders = $client->stores->sum(fn($s) => $s->orders->count());
        $totalRevenue = $client->stores->sum(fn($s) => $s->orders->where('payment_status', 'paid')->sum('total'));
        $totalProducts = $client->stores->sum(fn($s) => $s->products->count());
        $totalCustomers = $client->stores->sum(fn($s) => $s->customers->count());

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'is_active' => $client->is_active,
            'ai_credits' => $client->ai_credits,
            'email_verified_at' => $client->email_verified_at?->toISOString(),
            'last_login_at' => $client->last_login_at?->toISOString(),
            'created_at' => $client->created_at->toISOString(),
            'stores' => $client->stores->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'domain' => $s->domain,
                'platform' => $s->platform,
                'sync_status' => $s->sync_status,
                'last_sync_at' => $s->last_sync_at?->toISOString(),
                'products_count' => $s->products->count(),
                'orders_count' => $s->orders->count(),
                'customers_count' => $s->customers->count(),
            ]),
            'analyses' => $client->analyses->map(fn($a) => [
                'id' => $a->id,
                'status' => $a->status,
                'health_score' => $a->healthScore(),
                'created_at' => $a->created_at->toISOString(),
            ]),
            'activity_logs' => $activityLogs->map(fn($l) => [
                'id' => $l->id,
                'action' => $l->action,
                'description' => $l->description,
                'ip_address' => $l->ip_address,
                'created_at' => $l->created_at->toISOString(),
            ]),
            'stats' => [
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue,
                'total_products' => $totalProducts,
                'total_customers' => $totalCustomers,
                'analyses_count' => $client->analyses->count(),
            ],
        ]);
    }

    public function createClient(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'ai_credits' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
        ]);

        $client = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => UserRole::Client,
            'ai_credits' => $validated['ai_credits'] ?? 10,
            'is_active' => $validated['is_active'] ?? true,
            'email_verified_at' => now(),
        ]);

        $client->assignRole('client');

        ActivityLog::log('admin.client_created', $client, request()->user());

        return response()->json([
            'message' => 'Cliente criado com sucesso.',
            'client' => $client,
        ], 201);
    }

    public function updateClient(Request $request, int $id): JsonResponse
    {
        $client = User::where('role', UserRole::Client)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($client->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'O nome é obrigatório.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
        ]);

        $client->update($validated);

        ActivityLog::log('admin.client_updated', $client, request()->user());

        return response()->json([
            'message' => 'Cliente atualizado com sucesso.',
            'client' => $client,
        ]);
    }

    public function toggleClientStatus(int $id): JsonResponse
    {
        $client = User::where('role', UserRole::Client)->findOrFail($id);

        $client->update(['is_active' => !$client->is_active]);

        $action = $client->is_active ? 'admin.client_activated' : 'admin.client_deactivated';
        ActivityLog::log($action, $client, request()->user());

        return response()->json([
            'message' => $client->is_active ? 'Cliente ativado.' : 'Cliente desativado.',
            'is_active' => $client->is_active,
        ]);
    }

    public function addCredits(Request $request, int $id): JsonResponse
    {
        $client = User::where('role', UserRole::Client)->findOrFail($id);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:10000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'amount.required' => 'A quantidade é obrigatória.',
            'amount.min' => 'A quantidade deve ser pelo menos 1.',
        ]);

        $client->addCredits($validated['amount']);

        ActivityLog::log('admin.credits_added', $client, request()->user(), [
            'amount' => $validated['amount'],
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'message' => "Créditos adicionados com sucesso.",
            'ai_credits' => $client->ai_credits,
        ]);
    }

    public function removeCredits(Request $request, int $id): JsonResponse
    {
        $client = User::where('role', UserRole::Client)->findOrFail($id);

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $amountToRemove = min($validated['amount'], $client->ai_credits);
        $client->decrement('ai_credits', $amountToRemove);

        ActivityLog::log('admin.credits_removed', $client, request()->user(), [
            'amount' => $amountToRemove,
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'message' => "Créditos removidos com sucesso.",
            'ai_credits' => $client->ai_credits,
        ]);
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $client = User::where('role', UserRole::Client)->findOrFail($id);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ], [
            'password.required' => 'A nova senha é obrigatória.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
        ]);

        $client->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => true,
        ]);

        ActivityLog::log('admin.password_reset', $client, request()->user());

        return response()->json([
            'message' => 'Senha redefinida com sucesso.',
        ]);
    }

    public function impersonate(int $id): JsonResponse
    {
        $client = User::where('role', UserRole::Client)->findOrFail($id);

        // Create a temporary token for the client
        $token = $client->createToken('impersonation-token', ['*'], now()->addHour())->plainTextToken;

        ActivityLog::log('admin.impersonated', $client, request()->user());

        return response()->json([
            'message' => 'Sessão de impersonação iniciada.',
            'token' => $token,
            'user' => $client,
        ]);
    }

    public function deleteClient(int $id): JsonResponse
    {
        $client = User::where('role', UserRole::Client)->findOrFail($id);

        // Delete related data
        DB::transaction(function () use ($client) {
            // Delete stores and their data
            foreach ($client->stores as $store) {
                $store->products()->delete();
                $store->orders()->delete();
                $store->customers()->delete();
                $store->analyses()->delete();
                $store->delete();
            }

            // Delete chat conversations
            $client->chatConversations()->each(function ($conv) {
                $conv->messages()->delete();
                $conv->delete();
            });

            // Delete activity logs
            $client->activityLogs()->delete();

            // Delete user
            $client->delete();
        });

        ActivityLog::create([
            'action' => 'admin.client_deleted',
            'description' => "Cliente {$client->email} excluído",
            'user_id' => request()->user()->id,
        ]);

        return response()->json([
            'message' => 'Cliente excluído com sucesso.',
        ]);
    }
}

