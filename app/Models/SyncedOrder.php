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
            $q->where('order_number', 'like', "%{$search}%")
              ->orWhere('customer_name', 'like', "%{$search}%")
              ->orWhere('customer_email', 'like', "%{$search}%");
        });
    }
}

