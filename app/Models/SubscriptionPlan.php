<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'interval',
        'trial_days',
        'stripe_price_id',
        'features',
        'max_products',
        'max_orders_per_month',
        'max_employees',
        'custom_domain_allowed',
        'pos_enabled',
        'multi_currency_enabled',
        'advanced_analytics',
        'priority_support',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'custom_domain_allowed' => 'boolean',
            'pos_enabled' => 'boolean',
            'multi_currency_enabled' => 'boolean',
            'advanced_analytics' => 'boolean',
            'priority_support' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }
}
