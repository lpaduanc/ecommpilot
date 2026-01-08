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

        if (! $store) {
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

        if (! $store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        $order = SyncedOrder::where('store_id', $store->id)
            ->where('id', $id)
            ->first();

        if (! $order) {
            return response()->json(['message' => 'Pedido não encontrado.'], 404);
        }

        return response()->json(new OrderResource($order));
    }

    public function stats(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([
                'total_orders' => 0,
                'orders_by_status' => [],
                'orders_by_payment_status' => [],
                'total_value' => 0,
                'paid_value' => 0,
            ]);
        }

        $orders = SyncedOrder::where('store_id', $store->id)->get();

        // Total de pedidos
        $totalOrders = $orders->count();

        // Pedidos por status
        $ordersByStatus = $orders->groupBy(fn ($order) => $order->status?->value ?? 'unknown')
            ->map(fn ($group) => $group->count())
            ->toArray();

        // Pedidos por status de pagamento
        $ordersByPaymentStatus = $orders->groupBy(fn ($order) => $order->payment_status?->value ?? 'unknown')
            ->map(fn ($group) => $group->count())
            ->toArray();

        // Valor total de todos os pedidos
        $totalValue = $orders->sum('total');

        // Valor total de pedidos pagos
        $paidValue = $orders->filter(fn ($order) => $order->isPaid())->sum('total');

        return response()->json([
            'total_orders' => $totalOrders,
            'orders_by_status' => $ordersByStatus,
            'orders_by_payment_status' => $ordersByPaymentStatus,
            'total_value' => (float) $totalValue,
            'paid_value' => (float) $paidValue,
        ]);
    }
}
