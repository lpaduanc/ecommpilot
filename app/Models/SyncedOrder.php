<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncedOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'external_id',
        'order_number',
        'status',
        'payment_status',
        'shipping_status',
        'customer_name',
        'customer_email',
        'customer_phone',
        'subtotal',
        'discount',
        'shipping',
        'total',
        'payment_method',
        'coupon',
        'items',
        'shipping_address',
        'external_created_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping' => 'decimal:2',
            'total' => 'decimal:2',
            'items' => 'array',
            'coupon' => 'array',
            'shipping_address' => 'array',
            'external_created_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }

    public function isCancelled(): bool
    {
        return $this->status === OrderStatus::Cancelled;
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentStatus($query, string $status)
    {
        return $query->where('payment_status', $status);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', PaymentStatus::Paid);
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('external_created_at', [$startDate, $endDate]);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->whereRaw('order_number ILIKE ?', ["%{$search}%"])
                ->orWhereRaw('customer_name ILIKE ?', ["%{$search}%"])
                ->orWhereRaw('customer_email ILIKE ?', ["%{$search}%"]);
        });
    }

    public function scopeByCoupon($query, ?string $couponCode)
    {
        if (empty($couponCode)) {
            return $query;
        }

        return $query->whereJsonContains('coupon->code', $couponCode);
    }

    public function scopeByCountry($query, ?string $country)
    {
        if (empty($country)) {
            return $query;
        }

        return $query->whereJsonContains('shipping_address->country', $country);
    }

    public function scopeByState($query, ?string $state)
    {
        if (empty($state)) {
            return $query;
        }

        return $query->whereJsonContains('shipping_address->province', $state);
    }

    public function scopeByCity($query, ?string $city)
    {
        if (empty($city)) {
            return $query;
        }

        return $query->whereJsonContains('shipping_address->city', $city);
    }

    public function getItemsCountAttribute(): int
    {
        return is_array($this->items) ? count($this->items) : 0;
    }

    public function calculateCost(): float
    {
        if (empty($this->items)) {
            return 0;
        }

        $productIds = collect($this->items)->pluck('product_id')->filter()->unique();

        if ($productIds->isEmpty()) {
            return 0;
        }

        $products = SyncedProduct::whereIn('external_id', $productIds)
            ->where('store_id', $this->store_id)
            ->get()
            ->keyBy('external_id');

        $totalCost = 0;
        foreach ($this->items as $item) {
            $productId = $item['product_id'] ?? null;
            if ($productId) {
                $product = $products->get($productId);
                $cost = $product?->cost ?? 0;
                $totalCost += $cost * ($item['quantity'] ?? 1);
            }
        }

        return round($totalCost, 2);
    }

    public function calculateGrossProfit(): float
    {
        $cost = $this->calculateCost();

        return round((float) $this->total - $cost, 2);
    }

    public function calculateMargin(): ?float
    {
        if ($this->total <= 0) {
            return null;
        }

        $grossProfit = $this->calculateGrossProfit();

        return round(($grossProfit / (float) $this->total) * 100, 1);
    }
}
