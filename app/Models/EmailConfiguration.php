<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'identifier',
        'provider',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'encrypted:array',
    ];

    /**
     * Scope to get only active configurations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get configuration by identifier.
     */
    public function scopeByIdentifier($query, string $identifier)
    {
        return $query->where('identifier', $identifier);
    }

    /**
     * Get from_address from settings.
     */
    public function getFromAddressAttribute(): ?string
    {
        return $this->settings['from_address'] ?? null;
    }

    /**
     * Get from_name from settings.
     */
    public function getFromNameAttribute(): ?string
    {
        return $this->settings['from_name'] ?? null;
    }
}
