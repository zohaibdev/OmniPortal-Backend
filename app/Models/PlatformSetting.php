<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class PlatformSetting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'label',
        'description',
        'options',
        'is_public',
        'is_encrypted',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_public' => 'boolean',
            'is_encrypted' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // Setting groups
    public const GROUP_GENERAL = 'general';
    public const GROUP_EMAIL = 'email';
    public const GROUP_STRIPE = 'stripe';
    public const GROUP_STORAGE = 'storage';
    public const GROUP_SEO = 'seo';
    public const GROUP_SECURITY = 'security';
    public const GROUP_NOTIFICATIONS = 'notifications';
    public const GROUP_FEATURES = 'features';

    // Value types
    public const TYPE_STRING = 'string';
    public const TYPE_NUMBER = 'number';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_JSON = 'json';
    public const TYPE_ENCRYPTED = 'encrypted';
    public const TYPE_TEXT = 'text';
    public const TYPE_SELECT = 'select';

    /**
     * Get setting value with caching
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "platform_setting:{$key}";
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }

            return $setting->getTypedValue();
        });
    }

    /**
     * Set setting value
     */
    public static function set(string $key, mixed $value, ?string $group = null): static
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'group' => $group ?? self::GROUP_GENERAL,
            ]
        );

        // Clear cache
        Cache::forget("platform_setting:{$key}");
        Cache::forget('platform_settings_all');

        return $setting;
    }

    /**
     * Get all settings grouped
     */
    public static function getAllGrouped(): array
    {
        return Cache::remember('platform_settings_all', 3600, function () {
            return static::orderBy('group')
                ->orderBy('sort_order')
                ->get()
                ->groupBy('group')
                ->map(function ($settings) {
                    return $settings->mapWithKeys(function ($setting) {
                        return [$setting->key => $setting];
                    });
                })
                ->toArray();
        });
    }

    /**
     * Get settings by group
     */
    public static function getByGroup(string $group): array
    {
        return static::where('group', $group)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->getTypedValue()];
            })
            ->toArray();
    }

    /**
     * Get typed value based on type
     */
    public function getTypedValue(): mixed
    {
        if ($this->value === null) {
            return null;
        }

        return match ($this->type) {
            self::TYPE_BOOLEAN => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_NUMBER => is_numeric($this->value) ? (float) $this->value : 0,
            self::TYPE_JSON => json_decode($this->value, true),
            self::TYPE_ENCRYPTED => $this->is_encrypted ? $this->decryptValue() : $this->value,
            default => $this->value,
        };
    }

    /**
     * Set value with encryption if needed
     */
    public function setValueAttribute($value): void
    {
        if ($this->is_encrypted && $value !== null && $value !== '') {
            $this->attributes['value'] = Crypt::encryptString((string) $value);
        } else {
            $this->attributes['value'] = $value;
        }
    }

    /**
     * Decrypt value
     */
    protected function decryptValue(): ?string
    {
        try {
            return Crypt::decryptString($this->attributes['value']);
        } catch (\Exception $e) {
            return $this->attributes['value'];
        }
    }

    /**
     * Get public settings only (for frontend)
     */
    public static function getPublicSettings(): array
    {
        return static::where('is_public', true)
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->getTypedValue()];
            })
            ->toArray();
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(): void
    {
        Cache::forget('platform_settings_all');
        
        static::all()->each(function ($setting) {
            Cache::forget("platform_setting:{$setting->key}");
        });
    }

    /**
     * Get available groups with labels
     */
    public static function getGroups(): array
    {
        return [
            self::GROUP_GENERAL => 'General',
            self::GROUP_EMAIL => 'Email',
            self::GROUP_STRIPE => 'Stripe & Payments',
            self::GROUP_STORAGE => 'Storage',
            self::GROUP_SEO => 'SEO',
            self::GROUP_SECURITY => 'Security',
            self::GROUP_NOTIFICATIONS => 'Notifications',
            self::GROUP_FEATURES => 'Features',
        ];
    }
}
