<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncedProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'external_id',
        'name',
        'description',
        'price',
        'compare_at_price',
        'stock_quantity',
        'sku',
        'images',
        'categories',
        'variants',
        'is_active',
        'external_created_at',
        'external_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'images' => 'array',
            'categories' => 'array',
            'variants' => 'array',
            'is_active' => 'boolean',
            'external_created_at' => 'datetime',
            'external_updated_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function hasLowStock(int $threshold = 10): bool
    {
        return $this->stock_quantity < $threshold;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock_quantity <= 0;
    }

    public function hasDiscount(): bool
    {
        return $this->compare_at_price !== null 
            && $this->compare_at_price > $this->price;
    }

    public function discountPercentage(): ?float
    {
        if (!$this->hasDiscount()) {
            return null;
        }

        return round((1 - ($this->price / $this->compare_at_price)) * 100, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query, int $threshold = 10)
    {
        return $query->where('stock_quantity', '<', $threshold)
            ->where('stock_quantity', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('stock_quantity', '<=', 0);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%");
        });
    }
}

