<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
        private PlanLimitService $planLimitService
    ) {}

    /**
     * Check if user has access to custom dashboards.
     * Returns error response if access denied, null otherwise.
     */
    private function checkDashboardAccess(Request $request): ?JsonResponse
    {
        $user = $request->user();
        $isLocalEnv = app()->isLocal() || app()->environment('testing', 'dev', 'development');

        if (! $isLocalEnv && ! $this->planLimitService->canAccessCustomDashboards($user)) {
            return response()->json([
                'message' => 'Seu plano não inclui acesso aos dashboards avançados.',
                'upgrade_required' => true,
            ], 403);
        }

        return null;
    }

    public function stats(Request $request): JsonResponse
    {
        if ($response = $this->checkDashboardAccess($request)) {
            return $response;
        }

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
        if ($response = $this->checkDashboardAccess($request)) {
            return $response;
        }

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
        if ($response = $this->checkDashboardAccess($request)) {
            return $response;
        }

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
        if ($response = $this->checkDashboardAccess($request)) {
            return $response;
        }

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
        if ($response = $this->checkDashboardAccess($request)) {
            return $response;
        }

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
        if ($response = $this->checkDashboardAccess($request)) {
            return $response;
        }

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
        if ($response = $this->checkDashboardAccess($request)) {
            return $response;
        }

        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([]);
        }

        $data = $this->dashboardService->getLowStockProducts($store);

        return response()->json($data);
    }

    /**
     * Get all dashboard data in a single request.
     * This endpoint consolidates all dashboard data into one API call with caching.
     */
    public function bulk(Request $request): JsonResponse
    {
        if ($response = $this->checkDashboardAccess($request)) {
            return $response;
        }

        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([
                'has_store' => false,
                'message' => 'Nenhuma loja conectada.',
            ]);
        }

        $filters = $this->getFilters($request);
        $data = $this->dashboardService->getBulkDashboardData($store, $filters);

        return response()->json(array_merge($data, ['has_store' => true]));
    }

    private function getFilters(Request $request): array
    {
        return [
            'period' => $request->input('period', 'yesterday'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'categories' => $request->input('categories'),
            'payment_status' => $request->input('payment_status'),
        ];
    }
}
