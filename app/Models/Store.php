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
        'sync_status',
        'last_sync_at',
        'metadata',
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
}

