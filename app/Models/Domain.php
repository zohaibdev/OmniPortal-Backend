<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'domain',
        'type',
        'status',
        'verification_token',
        'verified_at',
        'is_primary',
        'ssl_enabled',
        'ssl_expires_at',
        'verification_error',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'ssl_expires_at' => 'datetime',
        'is_primary' => 'boolean',
        'ssl_enabled' => 'boolean',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_VERIFYING = 'verifying';
    const STATUS_ACTIVE = 'active';
    const STATUS_FAILED = 'failed';

    // Type constants
    const TYPE_SUBDOMAIN = 'subdomain';
    const TYPE_CUSTOM = 'custom';

    /**
     * Get the store that owns the domain.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Check if domain is verified.
     */
    public function isVerified(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->verified_at !== null;
    }

    /**
     * Check if domain is pending verification.
     */
    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_VERIFYING]);
    }

    /**
     * Get DNS verification instructions.
     */
    public function getDnsInstructions(): array
    {
        return [
            'type' => 'TXT',
            'host' => '_omniportal-verification.' . $this->domain,
            'value' => $this->verification_token,
            'ttl' => 3600,
        ];
    }

    /**
     * Get CNAME instructions for pointing domain.
     */
    public function getCnameInstructions(): array
    {
        $baseDomain = config('services.forge.base_domain', 'time-luxe.com');
        
        return [
            'type' => 'CNAME',
            'host' => $this->domain,
            'value' => 'shops.' . $baseDomain,
            'ttl' => 3600,
        ];
    }

    /**
     * Scope for active domains.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for primary domains.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope for custom domains.
     */
    public function scopeCustom($query)
    {
        return $query->where('type', self::TYPE_CUSTOM);
    }
}
