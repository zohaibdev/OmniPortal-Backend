<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'phone_number',
        'phone_number_id',
        'waba_id',
        'access_token',
        'status',
        'display_name',
        'quality_rating',
        'messaging_limits',
        'verified_at',
        'last_webhook_at',
        'webhook_verification_token',
        'meta',
    ];

    protected $casts = [
        'messaging_limits' => 'array',
        'meta' => 'array',
        'verified_at' => 'datetime',
        'last_webhook_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'webhook_verification_token',
    ];

    /**
     * Automatically encrypt access token when setting
     */
    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = encrypt($value);
    }

    /**
     * Automatically decrypt access token when getting
     */
    public function getAccessTokenAttribute($value): ?string
    {
        try {
            return $value ? decrypt($value) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set webhook verification token (encrypted)
     */
    public function setWebhookVerificationTokenAttribute($value): void
    {
        $this->attributes['webhook_verification_token'] = $value ? encrypt($value) : null;
    }

    /**
     * Get webhook verification token (decrypted)
     */
    public function getWebhookVerificationTokenAttribute($value): ?string
    {
        try {
            return $value ? decrypt($value) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Store relationship
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Scope to get active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get verified accounts
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    /**
     * Check if account is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if account is verified
     */
    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    /**
     * Mark as verified
     */
    public function markAsVerified(): void
    {
        $this->update([
            'verified_at' => now(),
            'status' => 'active',
        ]);
    }

    /**
     * Update webhook timestamp
     */
    public function updateWebhookTimestamp(): void
    {
        $this->update(['last_webhook_at' => now()]);
    }

    /**
     * Get formatted phone number for display
     */
    public function getFormattedPhoneAttribute(): string
    {
        return '+' . ltrim($this->phone_number, '+');
    }

    /**
     * Get quality rating color
     */
    public function getQualityColorAttribute(): string
    {
        return match ($this->quality_rating) {
            'GREEN' => 'green',
            'YELLOW' => 'yellow',
            'RED' => 'red',
            default => 'gray',
        };
    }
}
