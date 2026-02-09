<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
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
        'name',
        'email',
        'password',
        'phone',
        // 'role', // REMOVIDO: Role não deve ser mass assignable (privilege escalation risk)
        'is_active',
        'last_login_at',
        'must_change_password',
        'active_store_id',
        'notification_settings',
        'parent_user_id',
    ];

    /**
     * Campos protegidos contra mass assignment.
     * Role deve ser definido explicitamente apenas por admins.
     */
    protected $guarded = [
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'notification_settings' => 'array',
            'role' => UserRole::class,
        ];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * Get stores accessible to this user.
     * Employees access their parent's stores, clients access their own.
     */
    public function accessibleStores(): HasMany
    {
        $query = $this->isEmployee()
            ? $this->getOwnerUser()->stores()
            : $this->stores();

        return $query->where('sync_status', '!=', \App\Enums\SyncStatus::Disconnected);
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

        return $this->accessibleStores()->latest()->first();
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

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
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

    public function isEmployee(): bool
    {
        return $this->parent_user_id !== null;
    }

    public function getOwnerUser(): self
    {
        if ($this->isEmployee()) {
            return $this->parent ?? $this->parent()->first();
        }

        return $this;
    }

    public function recordLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Check if user has access to a specific store.
     * Admins have access to all stores, clients to their own, employees to their parent's stores.
     */
    public function hasAccessToStore(?int $storeId): bool
    {
        if ($storeId === null) {
            return false;
        }

        // Super admins have access to all stores
        if ($this->hasRole('super_admin')) {
            return true;
        }

        // Admins have access to all stores
        if ($this->isAdmin()) {
            return true;
        }

        // Employees: check if parent user owns the store
        if ($this->isEmployee()) {
            $owner = $this->getOwnerUser();

            return $owner->stores()->where('id', $storeId)->exists();
        }

        // Regular clients: only access to their own stores
        return $this->stores()->where('id', $storeId)->exists();
    }

    // ========== Subscription/Plan Methods ==========

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Retorna a assinatura ativa do usuário.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->where(function ($query) {
                $query->where('status', SubscriptionStatus::Active)
                    ->orWhere(function ($q) {
                        $q->where('status', SubscriptionStatus::Trial)
                            ->where('trial_ends_at', '>', now());
                    });
            })
            ->latest('starts_at')
            ->first();
    }

    /**
     * Retorna o plano atual do usuário.
     */
    public function currentPlan(): ?Plan
    {
        return $this->activeSubscription()?->plan;
    }

    /**
     * Verifica se o usuário tem um plano ativo.
     */
    public function hasActivePlan(): bool
    {
        return $this->activeSubscription() !== null;
    }

    /**
     * Verifica se o usuário está em período de trial.
     */
    public function isOnTrial(): bool
    {
        return $this->activeSubscription()?->isOnTrial() ?? false;
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
