<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DiscountAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function __construct(
        private DiscountAnalyticsService $analyticsService
    ) {}

    /**
     * Get bulk data (stats + coupons + filters) in a single request.
     * This is the recommended endpoint for the frontend.
     */
    public function bulk(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([
                'stats' => $this->getEmptyStats(),
                'coupons' => [
                    'data' => [],
                    'total' => 0,
                    'last_page' => 1,
                    'current_page' => 1,
                    'totals' => $this->getEmptyTotals(),
                ],
                'filters' => [
                    'types' => [],
                    'statuses' => ['active', 'expired', 'invalid'],
                ],
            ]);
        }

        $params = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'type' => $request->input('type'),
            'page' => $request->input('page', 1),
            'per_page' => $request->input('per_page', 10),
            'sort_by' => $request->input('sort_by', 'used'),
            'sort_order' => $request->input('sort_order', 'desc'),
            'period' => $request->input('period', 'yesterday'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];

        return response()->json(
            $this->analyticsService->getBulkData($store, $params)
        );
    }

    /**
     * Get list of coupons with analytics.
     */
    public function index(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([
                'data' => [],
                'total' => 0,
                'last_page' => 1,
                'current_page' => 1,
                'totals' => $this->getEmptyTotals(),
            ]);
        }

        $params = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'type' => $request->input('type'),
            'page' => $request->input('page', 1),
            'per_page' => $request->input('per_page', 10),
            'sort_by' => $request->input('sort_by', 'used'),
            'sort_order' => $request->input('sort_order', 'desc'),
            'period' => $request->input('period', 'yesterday'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ];

        return response()->json(
            $this->analyticsService->getCouponsWithAnalytics($store, $params)
        );
    }

    /**
     * Get general discount statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json($this->getEmptyStats());
        }

        return response()->json(
            $this->analyticsService->getGeneralStats($store)
        );
    }

    /**
     * Get filter options for coupons.
     */
    public function filters(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([
                'types' => [],
                'statuses' => ['active', 'expired', 'invalid'],
            ]);
        }

        return response()->json(
            $this->analyticsService->getFilterOptions($store)
        );
    }

    /**
     * Get empty stats structure.
     */
    private function getEmptyStats(): array
    {
        return [
            'total_orders' => 0,
            'orders_with_discount' => 0,
            'orders_with_coupon' => 0,
            'total_revenue' => 0,
            'total_discount' => 0,
            'discount_percentage' => 0,
            'active_coupons' => 0,
            'expired_coupons' => 0,
            'total_coupons' => 0,
        ];
    }

    /**
     * Get empty totals structure.
     */
    private function getEmptyTotals(): array
    {
        return [
            'revenue_products' => 0,
            'revenue_shipping' => 0,
            'total_revenue' => 0,
            'total_discount' => 0,
            'number_of_orders' => 0,
            'new_customers' => 0,
        ];
    }
}
