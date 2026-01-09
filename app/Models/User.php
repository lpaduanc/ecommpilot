<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'is_active',
        'last_login_at',
        'must_change_password',
        'ai_credits',
        'active_store_id',
        'notification_settings',
        'parent_user_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'ai_credits' => 'integer',
            'notification_settings' => 'array',
            'role' => UserRole::class,
        ];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function activeStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'active_store_id');
    }

    public function getActiveStoreAttribute()
    {
        // Return the explicitly selected store, or fallback to most recent
        if ($this->active_store_id) {
            return $this->getRelationValue('activeStore');
        }

        return $this->stores()->latest()->first();
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(User::class, 'parent_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    public function hasCredits(int $amount = 1): bool
    {
        return $this->ai_credits >= $amount;
    }

    public function deductCredits(int $amount = 1): bool
    {
        if (! $this->hasCredits($amount)) {
            return false;
        }

        $this->decrement('ai_credits', $amount);

        return true;
    }

    public function addCredits(int $amount): void
    {
        $this->increment('ai_credits', $amount);
    }

    public function recordLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }
}
