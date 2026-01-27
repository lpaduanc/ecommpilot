<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
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

    public function recordLogin(): void
    {
        $this->update(['last_login_at' => now()]);
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
}
