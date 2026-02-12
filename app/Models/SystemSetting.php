<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'is_sensitive',
    ];

    protected $casts = [
        'is_sensitive' => 'boolean',
    ];

    private const CACHE_PREFIX = 'system_setting:';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX.$key;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            if (! $setting) {
                return $default;
            }

            return $setting->getCastedValue();
        });
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, mixed $value, array $attributes = []): self
    {
        $isSensitive = $attributes['is_sensitive'] ?? false;
        $preparedValue = self::prepareValue($value, $isSensitive);

        $setting = self::updateOrCreate(
            ['key' => $key],
            array_merge($attributes, ['value' => $preparedValue])
        );

        // Clear cache
        Cache::forget(self::CACHE_PREFIX.$key);

        return $setting;
    }

    /**
     * Get all settings by group.
     */
    public static function getByGroup(string $group): array
    {
        return self::where('group', $group)
            ->get()
            ->mapWithKeys(fn ($setting) => [
                $setting->key => $setting->getCastedValue(),
            ])
            ->toArray();
    }

    /**
     * Get the casted value based on type.
     */
    public function getCastedValue(): mixed
    {
        $value = $this->is_sensitive && $this->value
            ? $this->decryptValue($this->value)
            : $this->value;

        return match ($this->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'json', 'array' => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    /**
     * Get the display value (masked for sensitive fields).
     */
    public function getDisplayValue(): mixed
    {
        if ($this->is_sensitive && $this->value) {
            $decrypted = $this->decryptValue($this->value);
            if (strlen($decrypted) > 8) {
                return substr($decrypted, 0, 4).'****'.substr($decrypted, -4);
            }

            return '********';
        }

        return $this->getCastedValue();
    }

    /**
     * Prepare value for storage.
     */
    private static function prepareValue(mixed $value, bool $isSensitive = false): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = json_encode($value);
        } else {
            $value = (string) $value;
        }

        if ($isSensitive && $value) {
            return Crypt::encryptString($value);
        }

        return $value;
    }

    /**
     * Decrypt a sensitive value.
     */
    private function decryptValue(string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            // If the value looks like an encrypted payload (base64 JSON with iv/value/mac)
            // but can't be decrypted, the APP_KEY likely changed. Return empty to force
            // re-configuration instead of returning encrypted gibberish.
            if (str_starts_with($value, 'eyJ')) {
                Log::warning('Failed to decrypt sensitive setting - APP_KEY may have changed', [
                    'key' => $this->key,
                ]);

                return '';
            }

            // If it doesn't look encrypted, it might be a plain text value
            // from before encryption was enabled - return as-is
            return $value;
        }
    }

    /**
     * Clear all settings cache.
     */
    public static function clearCache(): void
    {
        $settings = self::all();
        foreach ($settings as $setting) {
            Cache::forget(self::CACHE_PREFIX.$setting->key);
        }
    }
}
