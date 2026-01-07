<?php

namespace App\Models;

use App\Services\IdEncryptionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Owner extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'encrypted_id',
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'company_name',
        'tax_id',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'status',
        'is_verified',
        'verified_at',
        'settings',
        'meta',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'settings' => 'array',
            'meta' => 'array',
        ];
    }

    // Status constants
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($owner) {
            // Generate and update encrypted ID after creation
            $encryptionService = app(IdEncryptionService::class);
            $owner->encrypted_id = $encryptionService->encodeWithType($owner->id, 'owner');
            $owner->saveQuietly();
        });
    }

    // Relationships
    public function stores()
    {
        return $this->hasMany(Store::class, 'owner_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'owner_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    // Helpers
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function getActiveStoresCount(): int
    {
        return $this->stores()->where('status', Store::STATUS_ACTIVE)->count();
    }
}
