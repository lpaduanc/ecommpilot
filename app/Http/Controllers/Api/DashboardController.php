<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->activeStore;

        if (! $store) {
            return response()->json([
                'has_store' => false,
                'message' => 'Nenhuma loja conectada.',
            ]);
        }

        $filters = $this->getFilters($request);
        $stats = $this->dashboardService->getStats($store, $filters);

        return response()->json(array_merge($stats, ['has_store' => true]));
    }

    public function revenueChart(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([]);
        }

        $filters = $this->getFilters($request);
        $data = $this->dashboardService->getRevenueChart($store, $filters);

        return response()->json($data);
    }

    public function ordersStatusChart(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([]);
        }

        $filters = $this->getFilters($request);
        $data = $this->dashboardService->getOrdersStatusChart($store, $filters);

        return response()->json($data);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([]);
        }

        $filters = $this->getFilters($request);
        $data = $this->dashboardService->getTopProducts($store, $filters);

        return response()->json($data);
    }

    public function paymentMethodsChart(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([]);
        }

        $filters = $this->getFilters($request);
        $data = $this->dashboardService->getPaymentMethodsChart($store, $filters);

        return response()->json($data);
    }

    public function categoriesChart(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([]);
        }

        $filters = $this->getFilters($request);
        $data = $this->dashboardService->getCategoriesChart($store, $filters);

        return response()->json($data);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([]);
        }

        $data = $this->dashboardService->getLowStockProducts($store);

        return response()->json($data);
    }

    private function getFilters(Request $request): array
    {
        return [
            'period' => $request->input('period', 'last_30_days'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'categories' => $request->input('categories'),
            'payment_status' => $request->input('payment_status'),
        ];
    }
}
