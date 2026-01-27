<?php

namespace App\Models;

use App\Traits\SafeILikeSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncedCustomer extends Model
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
        'email',
        'phone',
        'total_orders',
        'total_spent',
        'external_created_at',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
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

        $sanitized = $this->sanitizeILikeInput($search);
        $pattern = '%'.$sanitized.'%';

        return $query->where(function ($q) use ($pattern) {
            $q->where('name', 'ILIKE', $pattern)
                ->orWhere('email', 'ILIKE', $pattern);
        });
    }
}
