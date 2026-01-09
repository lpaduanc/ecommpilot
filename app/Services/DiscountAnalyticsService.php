<?php

namespace App\Services;

use App\Models\Store;
use App\Models\SyncedCoupon;
use App\Models\SyncedOrder;
use Illuminate\Support\Collection;

class DiscountAnalyticsService
{
    /**
     * Calculate discount analytics for all coupons in the store.
     *
     * @param  Store  $store  The store to calculate analytics for
     * @return array Statistics including general stats and per-coupon analytics
     */
    public function calculateDiscountAnalytics(Store $store): array
    {
        // Get all coupons for the store
        $coupons = SyncedCoupon::where('store_id', $store->id)->get();

        // Get all paid orders for the store
        $orders = SyncedOrder::where('store_id', $store->id)
            ->paid()
            ->get();

        // Calculate general statistics
        $generalStats = $this->calculateGeneralStats($orders);

        // Calculate analytics for each coupon
        $couponAnalytics = $this->calculateCouponAnalytics($coupons, $orders, $store);

        return [
            'general' => $generalStats,
            'coupons' => $couponAnalytics,
        ];
    }

    /**
     * Calculate general discount statistics.
     *
     * @param  Collection  $orders  All paid orders
     * @return array General statistics
     */
    private function calculateGeneralStats(Collection $orders): array
    {
        $ordersWithDiscount = $orders->filter(fn ($order) => $order->discount > 0);
        $ordersWithCoupon = $orders->filter(fn ($order) => ! empty($order->coupon));

        $totalRevenue = $orders->sum('total');
        $totalDiscount = $orders->sum('discount');

        return [
            'total_orders' => $orders->count(),
            'orders_with_discount' => $ordersWithDiscount->count(),
            'orders_with_coupon' => $ordersWithCoupon->count(),
            'total_revenue' => (float) $totalRevenue,
            'total_discount' => (float) $totalDiscount,
            'discount_percentage' => $totalRevenue > 0
                ? round(($totalDiscount / ($totalRevenue + $totalDiscount)) * 100, 2)
                : 0,
        ];
    }

    /**
     * Calculate analytics for each coupon.
     *
     * @param  Collection  $coupons  All coupons in the store
     * @param  Collection  $orders  All paid orders
     * @param  Store  $store  The store
     * @return array Array of coupon analytics keyed by coupon ID
     */
    private function calculateCouponAnalytics(Collection $coupons, Collection $orders, Store $store): array
    {
        $analytics = [];

        foreach ($coupons as $coupon) {
            $couponOrders = $this->getOrdersForCoupon($orders, $coupon);

            if ($couponOrders->isEmpty()) {
                $analytics[$coupon->id] = $this->getEmptyAnalytics();

                continue;
            }

            $analytics[$coupon->id] = $this->calculateCouponMetrics($coupon, $couponOrders, $store);
        }

        return $analytics;
    }

    /**
     * Get all orders that used a specific coupon.
     *
     * @param  Collection  $orders  All orders
     * @param  SyncedCoupon  $coupon  The coupon
     * @return Collection Orders that used the coupon
     */
    private function getOrdersForCoupon(Collection $orders, SyncedCoupon $coupon): Collection
    {
        return $orders->filter(function ($order) use ($coupon) {
            if (empty($order->coupon)) {
                return false;
            }

            // Match by coupon ID or code
            $couponData = $order->coupon;
            $matchesId = isset($couponData['id']) && (string) $couponData['id'] === (string) $coupon->external_id;
            $matchesCode = isset($couponData['code']) && strcasecmp($couponData['code'], $coupon->code) === 0;

            return $matchesId || $matchesCode;
        });
    }

    /**
     * Calculate metrics for a specific coupon.
     *
     * @param  SyncedCoupon  $coupon  The coupon
     * @param  Collection  $couponOrders  Orders that used the coupon
     * @param  Store  $store  The store
     * @return array Coupon metrics
     */
    private function calculateCouponMetrics(SyncedCoupon $coupon, Collection $couponOrders, Store $store): array
    {
        $numberOfOrders = $couponOrders->count();

        // Calculate revenue (products + shipping)
        $revenueProducts = $couponOrders->sum('subtotal');
        $revenueShipping = $couponOrders->sum('shipping');
        $totalRevenue = $revenueProducts + $revenueShipping;

        // Calculate total discounts
        $totalDiscount = $couponOrders->sum('discount');

        // Calculate percentages and averages
        $discountPercentage = $totalRevenue > 0
            ? round(($totalDiscount / $totalRevenue) * 100, 2)
            : 0;

        $averageDiscountPerOrder = $numberOfOrders > 0
            ? round($totalDiscount / $numberOfOrders, 2)
            : 0;

        $averageTicket = $numberOfOrders > 0
            ? round($totalRevenue / $numberOfOrders, 2)
            : 0;

        // Calculate customer metrics
        $customerMetrics = $this->calculateCustomerMetrics($couponOrders, $store);

        return [
            'revenue_products' => (float) $revenueProducts,
            'revenue_shipping' => (float) $revenueShipping,
            'total_revenue' => (float) $totalRevenue,
            'total_discount' => (float) $totalDiscount,
            'number_of_orders' => $numberOfOrders,
            'discount_percentage' => $discountPercentage,
            'average_discount_per_order' => $averageDiscountPerOrder,
            'average_ticket' => $averageTicket,
            'new_customers' => $customerMetrics['new_customers'],
            'repurchase_rate' => $customerMetrics['repurchase_rate'],
        ];
    }

    /**
     * Calculate customer-related metrics for coupon orders.
     *
     * @param  Collection  $couponOrders  Orders that used the coupon
     * @param  Store  $store  The store
     * @return array Customer metrics
     */
    private function calculateCustomerMetrics(Collection $couponOrders, Store $store): array
    {
        // Get unique customer emails from coupon orders
        $customerEmails = $couponOrders->pluck('customer_email')->filter()->unique();

        if ($customerEmails->isEmpty()) {
            return [
                'new_customers' => 0,
                'repurchase_rate' => 0,
            ];
        }

        // Get all orders for these customers
        $allCustomerOrders = SyncedOrder::where('store_id', $store->id)
            ->paid()
            ->whereIn('customer_email', $customerEmails->toArray())
            ->orderBy('external_created_at', 'asc')
            ->get();

        // Count new customers (first order was with this coupon)
        $newCustomers = 0;
        $customersWithRepurchase = 0;

        foreach ($customerEmails as $email) {
            $customerOrders = $allCustomerOrders->where('customer_email', $email);

            if ($customerOrders->isEmpty()) {
                continue;
            }

            // Get first order for this customer
            $firstOrder = $customerOrders->first();

            // Check if first order used the coupon
            $firstOrderUsedCoupon = $couponOrders->contains(function ($order) use ($firstOrder) {
                return $order->id === $firstOrder->id;
            });

            if ($firstOrderUsedCoupon) {
                $newCustomers++;
            }

            // Check for repurchase (more than 1 order)
            if ($customerOrders->count() > 1) {
                $customersWithRepurchase++;
            }
        }

        // Calculate repurchase rate
        $totalCustomers = $customerEmails->count();
        $repurchaseRate = $totalCustomers > 0
            ? round(($customersWithRepurchase / $totalCustomers) * 100, 2)
            : 0;

        return [
            'new_customers' => $newCustomers,
            'repurchase_rate' => $repurchaseRate,
        ];
    }

    /**
     * Get empty analytics structure.
     *
     * @return array Empty analytics
     */
    private function getEmptyAnalytics(): array
    {
        return [
            'revenue_products' => 0,
            'revenue_shipping' => 0,
            'total_revenue' => 0,
            'total_discount' => 0,
            'number_of_orders' => 0,
            'discount_percentage' => 0,
            'average_discount_per_order' => 0,
            'average_ticket' => 0,
            'new_customers' => 0,
            'repurchase_rate' => 0,
        ];
    }
}
