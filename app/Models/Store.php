<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'description',
        'logo',
        'banner',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'timezone',
        'currency',
        'locale',
        'business_hours',
        'tax_rate',
        'tax_number',
        'subdomain',
        'custom_domain',
        'is_active',
        'is_featured',
        'status',
        'settings',
        'meta',
        'encrypted_id',
        'database_name',
        'database_created_at',
        'trial_ends_at',
        'trial_used',
        // Theme & Deployment fields
        'theme',
        'theme_config',
        'forge_site_id',
        'forge_site_status',
        'forge_site_created_at',
        'deployment_path',
        'last_deployed_at',
        'ssl_enabled',
        'ssl_expires_at',
    ];

    protected $appends = ['on_trial', 'trial_days_remaining', 'trial_expired_status'];

    protected function casts(): array
    {
        return [
            'business_hours' => 'array',
            'settings' => 'array',
            'meta' => 'array',
            'theme_config' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'trial_used' => 'boolean',
            'ssl_enabled' => 'boolean',
            'tax_rate' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'database_created_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'forge_site_created_at' => 'datetime',
            'last_deployed_at' => 'datetime',
            'ssl_expires_at' => 'datetime',
        ];
    }

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';

    // Relationships (Main Database Only)
    public function owner()
    {
        return $this->belongsTo(Owner::class, 'owner_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->latest();
    }

    public function domains()
    {
        return $this->hasMany(Domain::class);
    }

    public function primaryDomain()
    {
        return $this->hasOne(Domain::class)->where('is_primary', true)->where('status', 'active');
    }

    // Note: Products, Orders, Customers, Categories, Pages, Banners, Coupons, Employees, etc.
    // are stored in tenant-specific databases and should be accessed via TenantDatabaseService

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('status', self::STATUS_ACTIVE);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByDomain($query, string $domain)
    {
        return $query->where('subdomain', $domain)
            ->orWhere('custom_domain', $domain);
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->is_active && $this->status === self::STATUS_ACTIVE;
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription !== null;
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function trialExpired(): bool
    {
        return $this->trial_used && $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    public function trialDaysRemaining(): int
    {
        if (!$this->trial_ends_at || $this->trial_ends_at->isPast()) {
            return 0;
        }
        return (int) now()->diffInDays($this->trial_ends_at, false);
    }

    // Accessors for appended attributes
    public function getOnTrialAttribute(): bool
    {
        return $this->onTrial();
    }

    public function getTrialDaysRemainingAttribute(): int
    {
        return $this->trialDaysRemaining();
    }

    public function getTrialExpiredStatusAttribute(): bool
    {
        return $this->trialExpired();
    }

    public function canAccess(): bool
    {
        // Can access if: has active subscription OR is on trial OR is active
        return $this->hasActiveSubscription() || $this->onTrial() || $this->isActive();
    }

    public function getSetting(string $key, $default = null)
    {
        $setting = $this->settings()->where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public function setSetting(string $key, $value, string $group = 'general'): void
    {
        $this->settings()->updateOrCreate(
            ['key' => $key, 'group' => $group],
            ['value' => $value]
        );
    }
}
