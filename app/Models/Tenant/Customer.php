<?php

namespace App\Models\Tenant;

use App\Traits\BelongsToTenant;
use App\Traits\HasEncryptedId;
use App\Traits\HasMetaFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant, HasEncryptedId, HasMetaFields, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'avatar',
        'notes',
        'preferences',
        'status',
        'total_spent',
        'orders_count',
        'loyalty_points',
        'loyalty_tier',
        'last_order_at',
        'email_verified_at',
        'verification_token',
        'marketing_consent',
    ];

    protected $hidden = [
        'verification_token',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'marketing_consent' => 'array',
            'total_spent' => 'decimal:2',
            'loyalty_points' => 'decimal:2',
            'date_of_birth' => 'date',
            'last_order_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    // Loyalty tier thresholds
    public const TIER_BRONZE = 'bronze';
    public const TIER_SILVER = 'silver';
    public const TIER_GOLD = 'gold';
    public const TIER_PLATINUM = 'platinum';

    // Relationships
    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function defaultAddress()
    {
        return $this->hasOne(CustomerAddress::class)->where('is_default', true);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByTier($query, string $tier)
    {
        return $query->where('loyalty_tier', $tier);
    }

    // Helpers
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function updateLoyaltyTier(): void
    {
        $tier = match (true) {
            $this->total_spent >= 5000 => self::TIER_PLATINUM,
            $this->total_spent >= 2000 => self::TIER_GOLD,
            $this->total_spent >= 500 => self::TIER_SILVER,
            default => self::TIER_BRONZE,
        };

        if ($tier !== $this->loyalty_tier) {
            $this->update(['loyalty_tier' => $tier]);
        }
    }

    public function addLoyaltyPoints(float $points, ?int $orderId = null, string $description = 'Points earned'): void
    {
        $this->increment('loyalty_points', $points);

        $this->loyaltyTransactions()->create([
            'order_id' => $orderId,
            'type' => 'earned',
            'points' => $points,
            'balance_after' => $this->loyalty_points,
            'description' => $description,
        ]);
    }

    public function redeemLoyaltyPoints(float $points, ?int $orderId = null, string $description = 'Points redeemed'): bool
    {
        if ($this->loyalty_points < $points) {
            return false;
        }

        $this->decrement('loyalty_points', $points);

        $this->loyaltyTransactions()->create([
            'order_id' => $orderId,
            'type' => 'redeemed',
            'points' => -$points,
            'balance_after' => $this->loyalty_points,
            'description' => $description,
        ]);

        return true;
    }

    public function recordOrder(Order $order): void
    {
        $this->increment('orders_count');
        $this->increment('total_spent', $order->total);
        $this->update(['last_order_at' => now()]);
        
        $this->updateLoyaltyTier();
        
        // Award loyalty points (1 point per dollar spent)
        $this->addLoyaltyPoints(
            floor($order->total),
            $order->id,
            "Points for order #{$order->order_number}"
        );
    }
}
