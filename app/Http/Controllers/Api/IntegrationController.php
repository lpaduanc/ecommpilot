<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Jobs\SyncStoreDataJob;
use App\Models\ActivityLog;
use App\Models\Store;
use App\Services\Integration\NuvemshopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function __construct(
        private NuvemshopService $nuvemshopService
    ) {}

    public function stores(Request $request): JsonResponse
    {
        $stores = $request->user()
            ->stores()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(StoreResource::collection($stores));
    }

    public function connectNuvemshop(Request $request): RedirectResponse
    {
        $authUrl = $this->nuvemshopService->getAuthorizationUrl(
            $request->user()->id
        );

        return redirect($authUrl);
    }

    public function callbackNuvemshop(Request $request): RedirectResponse
    {
        $code = $request->input('code');
        $userId = $request->input('state');

        if (!$code || !$userId) {
            return redirect('/integrations?error=invalid_callback');
        }

        try {
            $store = $this->nuvemshopService->handleCallback($code, (int) $userId);
            
            ActivityLog::log('store.connected', $store);
            
            // Start initial sync
            SyncStoreDataJob::dispatch($store);

            return redirect('/integrations?success=connected');
        } catch (\Exception $e) {
            return redirect('/integrations?error=' . urlencode($e->getMessage()));
        }
    }

    public function sync(Request $request, int $storeId): JsonResponse
    {
        $store = Store::where('id', $storeId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        if ($store->isSyncing()) {
            return response()->json([
                'message' => 'A sincronização já está em andamento.',
            ], 409);
        }

        SyncStoreDataJob::dispatch($store);
        
        ActivityLog::log('store.sync_started', $store);

        return response()->json([
            'message' => 'Sincronização iniciada.',
            'store' => new StoreResource($store->fresh()),
        ]);
    }

    public function disconnect(Request $request, int $storeId): JsonResponse
    {
        $store = Store::where('id', $storeId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        ActivityLog::log('store.disconnected', $store);

        $store->delete();

        return response()->json([
            'message' => 'Loja desconectada com sucesso.',
        ]);
    }

    public function myStores(Request $request): JsonResponse
    {
        $user = $request->user();
        $stores = $user->stores()
            ->select('id', 'name', 'platform', 'domain', 'sync_status', 'last_sync_at')
            ->orderBy('name')
            ->get();

        $activeStoreId = $user->activeStore?->id;

        return response()->json([
            'stores' => $stores->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'platform' => $s->platform,
                'domain' => $s->domain,
                'sync_status' => $s->sync_status,
                'last_sync_at' => $s->last_sync_at?->toISOString(),
                'is_active' => $s->id === $activeStoreId,
            ]),
            'active_store_id' => $activeStoreId,
        ]);
    }

    public function selectStore(Request $request, int $storeId): JsonResponse
    {
        $user = $request->user();
        
        $store = Store::where('id', $storeId)
            ->where('user_id', $user->id)
            ->first();

        if (!$store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        // Update user's active store
        $user->update(['active_store_id' => $store->id]);

        ActivityLog::log('store.selected', $store);

        return response()->json([
            'message' => 'Loja selecionada com sucesso.',
            'store' => new StoreResource($store),
        ]);
    }
}

