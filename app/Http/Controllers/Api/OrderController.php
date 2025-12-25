<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\SyncedOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (!$store) {
            return response()->json([
                'data' => [],
                'total' => 0,
                'last_page' => 1,
            ]);
        }

        $query = SyncedOrder::where('store_id', $store->id)
            ->search($request->input('search'))
            ->orderBy('external_created_at', 'desc');

        if ($request->has('status') && $request->input('status')) {
            $query->byStatus($request->input('status'));
        }

        if ($request->has('payment_status') && $request->input('payment_status')) {
            $query->byPaymentStatus($request->input('payment_status'));
        }

        $orders = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => OrderResource::collection($orders),
            'total' => $orders->total(),
            'last_page' => $orders->lastPage(),
            'current_page' => $orders->currentPage(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (!$store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        $order = SyncedOrder::where('store_id', $store->id)
            ->where('id', $id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Pedido não encontrado.'], 404);
        }

        return response()->json(new OrderResource($order));
    }
}

