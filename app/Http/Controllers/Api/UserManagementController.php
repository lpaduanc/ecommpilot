<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserManagementResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class UserManagementController extends Controller
{
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
            $query->where(function ($q) use ($search) {
                $q->whereRaw('name ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('email ILIKE ?', ["%{$search}%"]);
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
                    'analytics.view',
                    'analytics.request',
                    'chat.use',
                ];
            }

            $user->syncPermissions($permissions);

            DB::commit();

            return response()->json([
                'user' => new UserManagementResource($user->load('permissions')),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erro ao criar usuário.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     * SECURITY FIX: Separates admin and client logic to prevent IDOR.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $parentUser = $request->user();

        // Admin pode ver qualquer usuário
        if ($parentUser->role === UserRole::Admin) {
            $user = User::with('permissions')->find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'Usuário não encontrado.',
                ], 404);
            }

            return response()->json([
                'user' => new UserManagementResource($user),
            ]);
        }

        // Cliente só pode ver sub-usuários criados por ele (não outros clientes)
        $user = User::where('parent_user_id', $parentUser->id)
            ->where('id', $id)
            ->with('permissions')
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Usuário não encontrado ou você não tem permissão para visualizá-lo.',
            ], 404);
        }

        return response()->json([
            'user' => new UserManagementResource($user),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $parentUser = $request->user();

        $user = User::where(function ($q) use ($parentUser) {
            $q->where('parent_user_id', $parentUser->id)
                ->orWhere('id', $parentUser->id);
        })
            ->where('id', $id)
            ->first();

        if (! $user) {
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
            $user->syncPermissions($permissions);

            DB::commit();

            return response()->json([
                'user' => new UserManagementResource($user->load('permissions')),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Erro ao atualizar usuário.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $parentUser = $request->user();

        $user = User::where('parent_user_id', $parentUser->id)
            ->where('id', $id)
            ->first();

        if (! $user) {
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
            return response()->json([
                'message' => 'Erro ao remover usuário.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
