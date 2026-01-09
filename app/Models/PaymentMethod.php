<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'description',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function stores()
    {
        return $this->belongsToMany(
            Store::class,
            'store_payment_methods',
            'payment_method_id',
            'store_id'
        )->withPivot('display_order', 'is_enabled');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOffline($query)
    {
        return $query->where('type', 'offline');
    }

    public function scopeOnline($query)
    {
        return $query->where('type', 'online');
    }
}
