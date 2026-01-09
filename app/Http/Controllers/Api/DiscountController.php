<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CouponResource;
use App\Models\SyncedCoupon;
use App\Services\DiscountAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function __construct(
        private DiscountAnalyticsService $analyticsService
    ) {}

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
            ]);
        }

        $query = SyncedCoupon::where('store_id', $store->id)
            ->search($request->input('search'))
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->active();
            } elseif ($status === 'expired') {
                $query->expired();
            } elseif ($status === 'invalid') {
                $query->where('valid', false);
            }
        }

        // Get all coupons for analytics calculation (before pagination)
        $allCoupons = $query->get();

        // Calculate analytics
        $analyticsData = $this->analyticsService->calculateDiscountAnalytics($store);

        // Attach analytics data to coupons
        foreach ($allCoupons as $coupon) {
            if (isset($analyticsData['coupons'][$coupon->id])) {
                $coupon->analytics = $analyticsData['coupons'][$coupon->id];
            }
        }

        // Apply pagination manually
        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        // Get paginated subset
        $paginatedCoupons = $allCoupons->slice($offset, $perPage)->values();
        $total = $allCoupons->count();
        $lastPage = ceil($total / $perPage);

        return response()->json([
            'data' => CouponResource::collection($paginatedCoupons),
            'total' => $total,
            'last_page' => $lastPage,
            'current_page' => $page,
        ]);
    }

    /**
     * Get general discount statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $store = $request->user()->activeStore;

        if (! $store) {
            return response()->json([
                'total_orders' => 0,
                'orders_with_discount' => 0,
                'orders_with_coupon' => 0,
                'total_revenue' => 0,
                'total_discount' => 0,
                'discount_percentage' => 0,
                'active_coupons' => 0,
                'expired_coupons' => 0,
            ]);
        }

        // Calculate analytics
        $analyticsData = $this->analyticsService->calculateDiscountAnalytics($store);

        // Get coupon counts
        $activeCoupons = SyncedCoupon::where('store_id', $store->id)->active()->count();
        $expiredCoupons = SyncedCoupon::where('store_id', $store->id)->expired()->count();

        return response()->json([
            'total_orders' => $analyticsData['general']['total_orders'],
            'orders_with_discount' => $analyticsData['general']['orders_with_discount'],
            'orders_with_coupon' => $analyticsData['general']['orders_with_coupon'],
            'total_revenue' => $analyticsData['general']['total_revenue'],
            'total_discount' => $analyticsData['general']['total_discount'],
            'discount_percentage' => $analyticsData['general']['discount_percentage'],
            'active_coupons' => $activeCoupons,
            'expired_coupons' => $expiredCoupons,
        ]);
    }
}
