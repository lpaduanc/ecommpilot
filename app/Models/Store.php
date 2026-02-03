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
        'user_id',
        'platform',
        'external_store_id',
        'name',
        'domain',
        'website_url',
        'email',
        'access_token',
        'refresh_token',
        'token_requires_reconnection',
        'sync_status',
        'auto_analysis_enabled',
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
        'tracking_settings',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'platform' => Platform::class,
            'sync_status' => SyncStatus::class,
            'auto_analysis_enabled' => 'boolean',
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
            'tracking_settings' => 'array',
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

    /**
     * Get suggestions for the store.
     */
    public function suggestions(): HasMany
    {
        return $this->hasMany(Suggestion::class);
    }

    /**
     * Alias for orders() - used by SuggestionImpactAnalysisService.
     */
    public function syncedOrders(): HasMany
    {
        return $this->orders();
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

    /**
     * Check if the store is eligible for automatic analysis.
     * Requires: user has active plan with auto_analysis + store has it enabled + sync completed.
     */
    public function isEligibleForAutoAnalysis(): bool
    {
        // Store must have auto-analysis enabled
        if (! $this->auto_analysis_enabled) {
            return false;
        }

        // Store must have completed sync (has data to analyze)
        if ($this->sync_status !== SyncStatus::Completed) {
            return false;
        }

        // Store must not require reconnection
        if ($this->requiresReconnection()) {
            return false;
        }

        // User must have active plan with auto-analysis benefit
        $user = $this->user;
        if (! $user) {
            return false;
        }

        $plan = $user->currentPlan();
        if (! $plan || ! $plan->has_auto_analysis || ! $plan->has_ai_analysis) {
            return false;
        }

        return true;
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

    /**
     * Retorna a estrutura padrão de tracking settings
     */
    public static function getDefaultTrackingSettings(): array
    {
        return [
            'ga' => [
                'enabled' => false,
                'measurement_id' => '',
            ],
            'gtag' => [
                'enabled' => false,
                'tag_id' => '',
            ],
            'meta_pixel' => [
                'enabled' => false,
                'pixel_id' => '',
            ],
            'clarity' => [
                'enabled' => false,
                'project_id' => '',
            ],
            'hotjar' => [
                'enabled' => false,
                'site_id' => '',
                'snippet_version' => 6,
            ],
        ];
    }

    /**
     * Retorna as configurações de tracking da loja
     */
    public function getTrackingSettings(): array
    {
        $defaults = self::getDefaultTrackingSettings();
        $settings = $this->tracking_settings ?? [];

        return array_replace_recursive($defaults, $settings);
    }

    /**
     * Atualiza uma configuração específica de tracking
     */
    public function updateTrackingSetting(string $provider, array $config): void
    {
        $settings = $this->getTrackingSettings();
        $settings[$provider] = array_merge($settings[$provider] ?? [], $config);

        $this->update(['tracking_settings' => $settings]);
    }

    /**
     * Retorna configurações de tracking formatadas para o frontend
     */
    public function getTrackingConfigForFrontend(): array
    {
        $settings = $this->getTrackingSettings();

        return [
            'ga' => [
                'enabled' => $settings['ga']['enabled'] ?? false,
                'measurementId' => $settings['ga']['measurement_id'] ?? '',
            ],
            'metaPixel' => [
                'enabled' => $settings['meta_pixel']['enabled'] ?? false,
                'pixelId' => $settings['meta_pixel']['pixel_id'] ?? '',
            ],
            'clarity' => [
                'enabled' => $settings['clarity']['enabled'] ?? false,
                'projectId' => $settings['clarity']['project_id'] ?? '',
            ],
            'hotjar' => [
                'enabled' => $settings['hotjar']['enabled'] ?? false,
                'siteId' => $settings['hotjar']['site_id'] ?? '',
                'snippetVersion' => $settings['hotjar']['snippet_version'] ?? 6,
            ],
        ];
    }

    /**
     * Verifica se a loja tem algum tracking configurado
     */
    public function hasTrackingConfigured(): bool
    {
        $settings = $this->getTrackingSettings();

        return ($settings['ga']['enabled'] ?? false)
            || ($settings['meta_pixel']['enabled'] ?? false)
            || ($settings['clarity']['enabled'] ?? false)
            || ($settings['hotjar']['enabled'] ?? false);
    }
}
