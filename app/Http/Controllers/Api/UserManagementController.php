<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserManagementResource;
use App\Models\User;
use App\Traits\SafeILikeSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;

class UserManagementController extends Controller
{
    use SafeILikeSearch;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get all users created by this user (and recursively their sub-users)
        $query = User::where(function ($q) use ($user) {
            $q->where('parent_user_id', $user->id)
                ->orWhere('id', $user->id);
        })->with('permissions');

        // Search functionality
        if ($search = $request->input('search')) {
            $sanitized = $this->sanitizeILikeInput($search);
            $pattern = '%'.$sanitized.'%';

            $query->where(function ($q) use ($pattern) {
                $q->where('name', 'ILIKE', $pattern)
                    ->orWhere('email', 'ILIKE', $pattern);
            });
        }

        // Order by created date
        $query->orderBy('created_at', 'desc');

        $users = $query->paginate($request->input('per_page', 10));

        return response()->json([
            'data' => UserManagementResource::collection($users),
            'current_page' => $users->currentPage(),
            'total' => $users->total(),
            'last_page' => $users->lastPage(),
        ]);
    }

    /**
     * List all available permissions.
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::all()->pluck('name')->toArray();

        return response()->json([
            'data' => $permissions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $parentUser = $request->user();

        // Admin não deve usar esta rota - deve usar /api/admin/clients
        if ($parentUser->isAdmin()) {
            return response()->json([
                'message' => 'Administradores devem usar o painel admin para criar clientes.',
            ], 403);
        }

        // Apenas clientes podem criar funcionários
        if (! $parentUser->isClient()) {
            return response()->json([
                'message' => 'Você não tem permissão para criar usuários.',
            ], 403);
        }

        // Funcionários não podem criar sub-usuários
        if ($parentUser->parent_user_id !== null) {
            return response()->json([
                'message' => 'Funcionários não podem criar outros usuários.',
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Create the user (employee/sub-user)
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'role' => UserRole::Client,
                'is_active' => true,
                'parent_user_id' => $parentUser->id,
                'active_store_id' => $parentUser->active_store_id,
            ]);

            // Assign permissions
            $permissions = $request->input('permissions', []);

            // If no permissions specified, give a default set
            if (empty($permissions)) {
                $permissions = [
                    'integrations.manage',
                    'analysis.view',
                    'analysis.request',
                    'chat.use',
                ];
            }

            // Filtrar permissões proibidas para funcionários
            $forbiddenPermissions = ['admin.access'];
            $permissions = array_diff($permissions, $forbiddenPermissions);

            // Garantir que funcionário só recebe permissões que o cliente pai tem
            $parentPermissions = $parentUser->getAllPermissions()->pluck('name')->toArray();
            $permissions = array_intersect($permissions, $parentPermissions);

            $user->syncPermissions($permissions);

            // Assign specific stores to the employee (optional)
            $storeIds = $request->input('store_ids', []);
            if (! empty($storeIds)) {
                $validStoreIds = $parentUser->stores()->whereIn('id', $storeIds)->pluck('id')->toArray();
                $user->assignedStores()->sync($validStoreIds);

                // Set active_store_id to first assigned store
                if (! empty($validStoreIds)) {
                    $user->update(['active_store_id' => $validStoreIds[0]]);
                }
            }

            DB::commit();

            return response()->json([
                'user' => new UserManagementResource($user->load(['permissions', 'assignedStores'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            $errorId = 'err_'.uniqid();
            Log::error('Erro ao criar usuário', [
                'error_id' => $errorId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Erro ao criar usuário.',
                'error_id' => $errorId,
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     * SECURITY FIX: Separates admin and client logic to prevent IDOR.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $parentUser = $request->user();

        // Admin pode ver qualquer usuário
        if ($parentUser->role === UserRole::Admin) {
            $user->load(['permissions', 'assignedStores']);

            return response()->json([
                'user' => new UserManagementResource($user),
            ]);
        }

        // Cliente só pode ver sub-usuários criados por ele (não outros clientes)
        if ($user->parent_user_id !== $parentUser->id) {
            return response()->json([
                'message' => 'Usuário não encontrado ou você não tem permissão para visualizá-lo.',
            ], 404);
        }

        $user->load(['permissions', 'assignedStores']);

        return response()->json([
            'user' => new UserManagementResource($user),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $parentUser = $request->user();

        // Verify ownership
        if ($user->parent_user_id !== $parentUser->id && $user->id !== $parentUser->id) {
            return response()->json([
                'message' => 'Usuário não encontrado.',
            ], 404);
        }

        // Don't allow editing the parent user itself through this endpoint
        if ($user->id === $parentUser->id) {
            return response()->json([
                'message' => 'Você não pode editar seu próprio usuário através desta funcionalidade.',
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Update user data
            $user->update([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
            ]);

            // Update password if provided
            if ($request->filled('password')) {
                $user->update([
                    'password' => Hash::make($request->input('password')),
                ]);
            }

            // Update permissions
            $permissions = $request->input('permissions', []);

            // Filtrar permissões proibidas para funcionários
            $forbiddenPermissions = ['admin.access'];
            $permissions = array_diff($permissions, $forbiddenPermissions);

            // Garantir que funcionário só recebe permissões que o cliente pai tem
            $parentPermissions = $parentUser->getAllPermissions()->pluck('name')->toArray();
            $permissions = array_intersect($permissions, $parentPermissions);

            $user->syncPermissions($permissions);

            // Sync assigned stores if provided
            if ($request->has('store_ids')) {
                $storeIds = $request->input('store_ids', []);
                $validStoreIds = $parentUser->stores()->whereIn('id', $storeIds)->pluck('id')->toArray();
                $user->assignedStores()->sync($validStoreIds);

                // If current active_store_id is no longer assigned, update it
                if (! empty($validStoreIds) && ! in_array($user->active_store_id, $validStoreIds)) {
                    $user->update(['active_store_id' => $validStoreIds[0]]);
                }
            }

            DB::commit();

            return response()->json([
                'user' => new UserManagementResource($user->load(['permissions', 'assignedStores'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $errorId = 'err_'.uniqid();
            Log::error('Erro ao atualizar usuário', [
                'error_id' => $errorId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Erro ao atualizar usuário.',
                'error_id' => $errorId,
            ], 500);
        }
    }

    /**
     * List the parent client's stores for the employee assignment form dropdown.
     */
    public function clientStores(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get the actual owner: employees use their parent, clients use themselves
        $owner = $user->isEmployee() ? $user->getOwnerUser() : $user;

        $stores = $owner->stores()
            ->select('id', 'name', 'platform', 'sync_status')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $stores,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $parentUser = $request->user();

        // Verify ownership
        if ($user->parent_user_id !== $parentUser->id) {
            return response()->json([
                'message' => 'Usuário não encontrado.',
            ], 404);
        }

        try {
            $user->delete();

            return response()->json([
                'message' => 'Usuário removido com sucesso.',
            ]);
        } catch (\Exception $e) {
            $errorId = 'err_'.uniqid();
            Log::error('Erro ao remover usuário', [
                'error_id' => $errorId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Erro ao remover usuário.',
                'error_id' => $errorId,
            ], 500);
        }
    }
}
