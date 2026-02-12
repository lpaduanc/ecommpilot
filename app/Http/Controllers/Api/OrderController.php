<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\SyncedOrder;
use App\Services\BrazilLocationsService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function __construct(
        private BrazilLocationsService $locationsService,
        private PlanLimitService $planLimitService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json([
                'data' => [],
                'total' => 0,
                'last_page' => 1,
            ]);
        }

        // Obter limite de pedidos do plano
        $plan = $user->currentPlan();
        $ordersLimit = $plan?->orders_limit ?? 0;
        $isUnlimited = $plan?->isUnlimited('orders_limit') ?? false;

        // Obter IDs dos pedidos permitidos (mais recentes do mês, limitados ao plano)
        $allowedOrderIds = null;
        $totalOrdersThisMonth = 0;
        $limitReached = false;

        if (! $isUnlimited && $ordersLimit > 0) {
            // Contar total de pedidos do mês
            $totalOrdersThisMonth = SyncedOrder::where('store_id', $store->id)
                ->whereMonth('external_created_at', now()->month)
                ->whereYear('external_created_at', now()->year)
                ->count();

            $limitReached = $totalOrdersThisMonth > $ordersLimit;

            // Obter apenas os IDs dos pedidos mais recentes dentro do limite
            $allowedOrderIds = SyncedOrder::where('store_id', $store->id)
                ->whereMonth('external_created_at', now()->month)
                ->whereYear('external_created_at', now()->year)
                ->orderBy('external_created_at', 'desc')
                ->limit($ordersLimit)
                ->pluck('id');
        }

        $query = SyncedOrder::where('store_id', $store->id)
            ->search($request->input('search'))
            ->orderBy('external_created_at', 'desc');

        // Aplicar limite do plano (apenas pedidos do mês dentro do limite)
        if ($allowedOrderIds !== null) {
            $query->whereIn('id', $allowedOrderIds);
        }

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

        if ($request->filled('start_date')) {
            $query->whereDate('external_created_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('external_created_at', '<=', $request->input('end_date'));
        }

        $orders = $query->paginate($request->input('per_page', 10));

        $response = [
            'data' => OrderResource::collection($orders),
            'total' => $orders->total(),
            'last_page' => $orders->lastPage(),
            'current_page' => $orders->currentPage(),
        ];

        // Adicionar informações do limite se aplicável
        if (! $isUnlimited) {
            $response['limit_info'] = [
                'orders_limit' => $ordersLimit,
                'total_orders_this_month' => $totalOrdersThisMonth,
                'limit_reached' => $limitReached,
                'showing_limited' => $allowedOrderIds !== null,
            ];
        }

        return response()->json($response);
    }

    public function filters(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json([
                'statuses' => [],
                'payment_statuses' => [],
                'coupons' => [],
                'countries' => [],
                'states' => [],
            ]);
        }

        // Retornar todos os status possíveis dos enums com labels
        $statuses = collect(OrderStatus::cases())->map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ])->values()->toArray();

        $paymentStatuses = collect(PaymentStatus::cases())->map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ])->values()->toArray();

        // Obter limite de pedidos do plano
        $plan = $user->currentPlan();
        $ordersLimit = $plan?->orders_limit ?? 0;
        $isUnlimited = $plan?->isUnlimited('orders_limit') ?? false;

        // Query base para coupons e countries (respeitando limite do plano)
        $query = SyncedOrder::where('store_id', $store->id);

        // Aplicar limite do plano (apenas pedidos do mês dentro do limite)
        if (! $isUnlimited && $ordersLimit > 0) {
            $allowedOrderIds = SyncedOrder::where('store_id', $store->id)
                ->whereMonth('external_created_at', now()->month)
                ->whereYear('external_created_at', now()->year)
                ->orderBy('external_created_at', 'desc')
                ->limit($ordersLimit)
                ->pluck('id');

            $query->whereIn('id', $allowedOrderIds);
        }

        // Extrair cupons únicos usando query DISTINCT, excluindo códigos auto-gerados
        $coupons = (clone $query)
            ->whereNotNull('coupon')
            ->selectRaw("DISTINCT coupon->>'code' as coupon_code")
            ->pluck('coupon_code')
            ->filter(function ($code) {
                if (! $code || strlen($code) < 2) {
                    return false;
                }

                // Excluir hashes puros (hex strings com 20+ caracteres)
                if (preg_match('/^[0-9A-F]{20,}$/', $code)) {
                    return false;
                }

                // Excluir cupons ISZICASH auto-gerados (cashback)
                if (preg_match('/^ISZICASH[0-9A-F]{20,}$/', $code)) {
                    return false;
                }

                // Excluir cupons BQ auto-gerados
                if (preg_match('/^BQ\d{6}[0-9A-F]+$/', $code)) {
                    return false;
                }

                // Excluir rascunhos de pedido
                if (str_starts_with($code, 'DRAFT-ORDER-')) {
                    return false;
                }

                return true;
            })
            ->sort()
            ->values()
            ->toArray();

        // Extrair países únicos usando query DISTINCT
        $countries = (clone $query)
            ->whereNotNull('shipping_address')
            ->selectRaw("DISTINCT shipping_address->>'country' as country")
            ->pluck('country')
            ->filter()
            ->sort()
            ->values()
            ->toArray();

        // Get Brazil states from the new service
        // This provides all Brazilian states from IBGE API or fallback
        $states = $this->locationsService->getStates();
        $statesList = array_map(function ($state) {
            return [
                'sigla' => $state['sigla'],
                'nome' => $state['nome'],
            ];
        }, $states);

        return response()->json([
            'statuses' => $statuses,
            'payment_statuses' => $paymentStatuses,
            'coupons' => $coupons,
            'countries' => $countries,
            'states' => $statesList,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        // Obter limite de pedidos do plano
        $plan = $user->currentPlan();
        $ordersLimit = $plan?->orders_limit ?? 0;
        $isUnlimited = $plan?->isUnlimited('orders_limit') ?? false;

        // Obter IDs dos pedidos permitidos
        $allowedOrderIds = null;
        if (! $isUnlimited && $ordersLimit > 0 && $store) {
            $allowedOrderIds = SyncedOrder::where('store_id', $store->id)
                ->whereMonth('external_created_at', now()->month)
                ->whereYear('external_created_at', now()->year)
                ->orderBy('external_created_at', 'desc')
                ->limit($ordersLimit)
                ->pluck('id');
        }

        $query = SyncedOrder::where('store_id', $store?->id ?? 0)
            ->search($request->input('search'))
            ->orderBy('external_created_at', 'desc');

        // Aplicar limite do plano
        if ($allowedOrderIds !== null) {
            $query->whereIn('id', $allowedOrderIds);
        }

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

        if ($request->filled('start_date')) {
            $query->whereDate('external_created_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('external_created_at', '<=', $request->input('end_date'));
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

    public function show(Request $request, SyncedOrder $order): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json(['message' => 'Loja não encontrada.'], 404);
        }

        // Verify order belongs to active store
        if ($order->store_id !== $store->id) {
            return response()->json(['message' => 'Pedido não encontrado.'], 404);
        }

        return response()->json(new OrderResource($order));
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json([
                'total_orders' => 0,
                'orders_by_status' => [],
                'orders_by_payment_status' => [],
                'total_value' => 0,
                'paid_value' => 0,
            ]);
        }

        // Obter limite de pedidos do plano
        $plan = $user->currentPlan();
        $ordersLimit = $plan?->orders_limit ?? 0;
        $isUnlimited = $plan?->isUnlimited('orders_limit') ?? false;

        $query = SyncedOrder::where('store_id', $store->id);

        // Aplicar limite do plano (apenas pedidos do mês dentro do limite)
        if (! $isUnlimited && $ordersLimit > 0) {
            $allowedOrderIds = SyncedOrder::where('store_id', $store->id)
                ->whereMonth('external_created_at', now()->month)
                ->whereYear('external_created_at', now()->year)
                ->orderBy('external_created_at', 'desc')
                ->limit($ordersLimit)
                ->pluck('id');

            $query->whereIn('id', $allowedOrderIds);
        }

        $orders = $query->get();

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
