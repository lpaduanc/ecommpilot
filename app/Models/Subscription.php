<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'cancelled_at',
        'payment_gateway',
        'external_subscription_id',
        'payment_metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'payment_metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Verifica se a assinatura está ativa.
     */
    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    /**
     * Verifica se a assinatura está em período de teste.
     */
    public function isOnTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trial
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Verifica se a assinatura dá acesso ao sistema.
     */
    public function hasAccess(): bool
    {
        return $this->isActive() || $this->isOnTrial();
    }

    /**
     * Verifica se a assinatura expirou.
     */
    public function isExpired(): bool
    {
        if ($this->ends_at && $this->ends_at->isPast()) {
            return true;
        }

        if ($this->isOnTrial() === false && $this->status === SubscriptionStatus::Trial) {
            return true;
        }

        return $this->status === SubscriptionStatus::Expired;
    }

    /**
     * Cancela a assinatura.
     */
    public function cancel(): void
    {
        $this->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Escopo para assinaturas ativas.
     */
    public function scopeActive($query)
    {
        return $query->where('status', SubscriptionStatus::Active);
    }

    /**
     * Escopo para assinaturas com acesso (ativas ou em trial válido).
     */
    public function scopeWithAccess($query)
    {
        return $query->where(function ($q) {
            $q->where('status', SubscriptionStatus::Active)
                ->orWhere(function ($q2) {
                    $q2->where('status', SubscriptionStatus::Trial)
                        ->where('trial_ends_at', '>', now());
                });
        });
    }
}
