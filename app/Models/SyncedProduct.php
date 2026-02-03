<?php

namespace App\Models;

use App\Traits\SafeILikeSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncedProduct extends Model
{
    use HasFactory, SafeILikeSearch, SoftDeletes;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $fillable = [
        'uuid',
        'store_id',
        'external_id',
        'name',
        'description',
        'price',
        'cost',
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
            'uuid' => 'string',
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
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
        if (! $this->hasDiscount()) {
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

    /**
     * Termos que identificam produtos brinde pelo nome.
     * Lista expandida para capturar variações em português e inglês.
     */
    private const GIFT_TERMS = [
        'brinde',
        'brindes',
        'grátis',
        'gratis',
        'gratuito',
        'gift',
        'gifts',
        'amostra',
        'amostras',
        'sample',
        'samples',
        'cortesia',
        'mimo',
        'mimos',
        'bônus',
        'bonus',
        'free',
        'promotional',
        'promo',
        'giveaway',
        'presente grátis',
        'kit brinde',
        'miniatura',
        'sachê',
        'sachet',
        'travel size',
    ];

    /**
     * Verifica se o produto é um brinde baseado no nome.
     */
    public function isGift(): bool
    {
        $nameLower = mb_strtolower($this->name ?? '');

        foreach (self::GIFT_TERMS as $term) {
            if (str_contains($nameLower, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scope para excluir produtos que são brindes.
     */
    public function scopeExcludeGifts($query)
    {
        foreach (self::GIFT_TERMS as $term) {
            $query->whereRaw('LOWER(name) NOT LIKE ?', ["%{$term}%"]);
        }

        return $query;
    }

    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) {
            return $query;
        }

        $sanitized = $this->sanitizeILikeInput($search);
        $pattern = '%'.$sanitized.'%';

        return $query->where(function ($q) use ($pattern) {
            $q->where('name', 'ILIKE', $pattern)
                ->orWhere('sku', 'ILIKE', $pattern);
        });
    }
}
