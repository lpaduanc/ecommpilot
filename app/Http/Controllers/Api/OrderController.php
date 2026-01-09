<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\SyncedOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        if ($request->filled('payment_status')) {
            $query->byPaymentStatus($request->input('payment_status'));
        }

        if ($request->filled('coupon')) {
            $query->byCoupon($request->input('coupon'));
        }

        if ($request->filled('country')) {
            $query->byCountry($request->input('country'));
        }

        if ($request->filled('state')) {
            $query->byState($request->input('state'));
        }

        if ($request->filled('city')) {
            $query->byCity($request->input('city'));
        }

        $orders = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => OrderResource::collection($orders),
            'total' => $orders->total(),
            'last_page' => $orders->lastPage(),
            'current_page' => $orders->currentPage(),
        ]);
    }

    public function filters(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([
                'statuses' => [],
                'coupons' => [],
                'countries' => [],
                'states' => [],
                'cities' => [],
            ]);
        }

        $orders = SyncedOrder::where('store_id', $store->id)->get();

        $statuses = $orders
            ->pluck('status')
            ->filter()
            ->map(fn ($status) => $status->value)
            ->unique()
            ->values()
            ->toArray();

        $coupons = $orders
            ->pluck('coupon')
            ->filter()
            ->map(fn ($coupon) => $coupon['code'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $countries = $orders
            ->pluck('shipping_address')
            ->filter()
            ->map(fn ($address) => $address['country'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $states = $orders
            ->pluck('shipping_address')
            ->filter()
            ->map(fn ($address) => $address['province'] ?? null)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $cities = $orders
            ->pluck('shipping_address')
            ->filter()
            ->map(fn ($address) => $address['city'] ?? null)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return response()->json([
            'statuses' => $statuses,
            'coupons' => $coupons,
            'countries' => $countries,
            'states' => $states,
            'cities' => $cities,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $store = $request->user()->activeStore;

        $query = SyncedOrder::where('store_id', $store?->id ?? 0)
            ->search($request->input('search'))
            ->orderBy('external_created_at', 'desc');

        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        if ($request->filled('payment_status')) {
            $query->byPaymentStatus($request->input('payment_status'));
        }

        if ($request->filled('coupon')) {
            $query->byCoupon($request->input('coupon'));
        }

        if ($request->filled('country')) {
            $query->byCountry($request->input('country'));
        }

        if ($request->filled('state')) {
            $query->byState($request->input('state'));
        }

        if ($request->filled('city')) {
            $query->byCity($request->input('city'));
        }

        $orders = $query->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="pedidos_'.date('Y-m-d_His').'.csv"',
        ];

        return response()->stream(function () use ($orders) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Pedido',
                'Data',
                'Status',
                'Cliente',
                'Email',
                'Telefone',
                'Total Vendido',
                'Itens',
                'Custo',
                'Lucro Bruto',
                'Margem %',
                'Cupom',
                'País',
                'Estado',
                'Cidade',
            ], ';');

            foreach ($orders as $order) {
                fputcsv($handle, [
                    $order->order_number,
                    $order->external_created_at?->format('d/m/Y'),
                    $order->status?->value ?? '-',
                    $order->customer_name,
                    $order->customer_email,
                    $order->customer_phone ?? '-',
                    number_format((float) $order->total, 2, ',', '.'),
                    $order->items_count,
                    number_format($order->calculateCost(), 2, ',', '.'),
                    number_format($order->calculateGrossProfit(), 2, ',', '.'),
                    $order->calculateMargin() !== null ? number_format($order->calculateMargin(), 1, ',', '.').'%' : '-',
                    $order->coupon['code'] ?? '-',
                    $order->shipping_address['country'] ?? '-',
                    $order->shipping_address['province'] ?? '-',
                    $order->shipping_address['city'] ?? '-',
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
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
