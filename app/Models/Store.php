<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'platform',
        'external_store_id',
        'name',
        'domain',
        'email',
        'access_token',
        'refresh_token',
        'token_requires_reconnection',
        'sync_status',
        'last_sync_at',
        'metadata',
        'niche',
        'niche_subcategory',
        'monthly_goal',
        'annual_goal',
        'target_ticket',
        'monthly_revenue',
        'monthly_visits',
        'competitors',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'sync_status' => SyncStatus::class,
            'last_sync_at' => 'datetime',
            'metadata' => 'array',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'monthly_goal' => 'decimal:2',
            'annual_goal' => 'decimal:2',
            'target_ticket' => 'decimal:2',
            'monthly_revenue' => 'decimal:2',
            'monthly_visits' => 'integer',
            'competitors' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(SyncedProduct::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SyncedOrder::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(SyncedCustomer::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(SyncedCoupon::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    public function isSyncing(): bool
    {
        return $this->sync_status === SyncStatus::Syncing;
    }

    public function markAsSyncing(): void
    {
        $this->update(['sync_status' => SyncStatus::Syncing]);
    }

    public function markAsSynced(): void
    {
        $this->update([
            'sync_status' => SyncStatus::Completed,
            'last_sync_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['sync_status' => SyncStatus::Failed]);
    }

    public function markAsTokenExpired(): void
    {
        $this->update([
            'sync_status' => SyncStatus::TokenExpired,
            'token_requires_reconnection' => true,
        ]);
    }

    public function requiresReconnection(): bool
    {
        return $this->token_requires_reconnection === true || $this->sync_status === SyncStatus::TokenExpired;
    }

    public function getFormattedGoals(): array
    {
        return [
            'monthly_goal' => $this->monthly_goal,
            'annual_goal' => $this->annual_goal,
            'target_ticket' => $this->target_ticket,
            'monthly_revenue' => $this->monthly_revenue,
            'monthly_visits' => $this->monthly_visits,
            'competitors' => $this->competitors ?? [],
        ];
    }

    public function getNicheLabel(): ?string
    {
        if (! $this->niche) {
            return null;
        }

        return config("niches.niches.{$this->niche}.label", $this->niche);
    }

    public function getSubcategoryLabel(): ?string
    {
        if (! $this->niche || ! $this->niche_subcategory) {
            return null;
        }

        return config("niches.niches.{$this->niche}.subcategories.{$this->niche_subcategory}", $this->niche_subcategory);
    }

    public function hasConfiguredNiche(): bool
    {
        return $this->niche !== null && $this->niche_subcategory !== null;
    }
}
