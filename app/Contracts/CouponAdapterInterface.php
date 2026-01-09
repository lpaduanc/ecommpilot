<?php

namespace App\Contracts;

/**
 * Interface for adapting external coupon data to internal SyncedCoupon structure.
 *
 * This contract defines the behavior for transforming coupon data from different
 * e-commerce platforms into a normalized format compatible with the SyncedCoupon model.
 */
interface CouponAdapterInterface
{
    /**
     * Transform external coupon data to SyncedCoupon attributes.
     *
     * @param  array  $externalData  Raw coupon data from the external platform API
     * @return array Normalized coupon attributes matching SyncedCoupon fillable fields
     */
    public function transform(array $externalData): array;

    /**
     * Map external coupon type to internal type.
     *
     * @param  string|null  $externalType  External platform coupon type
     * @return string Internal type value (percentage, absolute, shipping)
     */
    public function mapCouponType(?string $externalType): string;
}
