<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

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

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'marketing_consent' => 'array',
            'date_of_birth' => 'date',
            'last_order_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'total_spent' => 'decimal:2',
            'loyalty_points' => 'decimal:2',
        ];
    }

    // Append computed attributes to JSON
    protected $appends = ['name'];

    // Accessor for full name
    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        // Note: In tenant architecture, customers don't have store_id
        // This is kept for compatibility but won't work as expected
        return null;
    }

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

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    // Update stats after order
    public function updateOrderStats(): void
    {
        $this->orders_count = $this->orders()->count();
        $this->total_spent = $this->orders()->where('payment_status', 'paid')->sum('total');
        $this->last_order_at = $this->orders()->latest()->value('created_at');
        $this->save();
    }
}
