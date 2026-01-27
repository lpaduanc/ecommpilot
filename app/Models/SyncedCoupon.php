<?php

namespace App\Models;

use App\Traits\SafeILikeSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncedCoupon extends Model
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
        'code',
        'type',
        'value',
        'valid',
        'used',
        'max_uses',
        'start_date',
        'end_date',
        'min_price',
        'categories',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'value' => 'decimal:2',
            'valid' => 'boolean',
            'used' => 'integer',
            'max_uses' => 'integer',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'min_price' => 'decimal:2',
            'categories' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isActive(): bool
    {
        if (! $this->valid) {
            return false;
        }

        $now = now();

        if ($this->start_date && $now->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && $now->gt($this->end_date)) {
            return false;
        }

        if ($this->max_uses && $this->used >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->end_date && now()->gt($this->end_date);
    }

    public function hasReachedMaxUses(): bool
    {
        return $this->max_uses && $this->used >= $this->max_uses;
    }

    public function scopeValid($query)
    {
        return $query->where('valid', true);
    }

    public function scopeActive($query)
    {
        return $query->where('valid', true)
            ->where(function ($q) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')
                    ->orWhereRaw('used < max_uses');
            });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('end_date')
            ->where('end_date', '<', now());
    }

    public function scopeSearch($query, ?string $search)
    {
        if (empty($search)) {
            return $query;
        }

        $sanitized = $this->sanitizeILikeInput($search);
        $pattern = '%'.$sanitized.'%';

        return $query->where(function ($q) use ($pattern) {
            $q->where('code', 'ILIKE', $pattern);
        });
    }
}
