<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncedCustomer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'external_id',
        'name',
        'email',
        'phone',
        'total_orders',
        'total_spent',
        'external_created_at',
    ];

    protected function casts(): array
    {
        return [
            'total_orders' => 'integer',
            'total_spent' => 'decimal:2',
            'external_created_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function averageOrderValue(): float
    {
        if ($this->total_orders === 0) {
            return 0;
        }

        return round($this->total_spent / $this->total_orders, 2);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }
}
