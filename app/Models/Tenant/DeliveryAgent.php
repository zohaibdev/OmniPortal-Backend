<?php

namespace App\Models\Tenant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryAgent extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'is_active',
        'current_orders',
        'max_orders',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
            ->whereRaw('current_orders < max_orders');
    }

    // Relationships
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Methods
    public function isAvailable(): bool
    {
        return $this->is_active && $this->current_orders < $this->max_orders;
    }

    public function incrementOrders(): void
    {
        $this->increment('current_orders');
    }

    public function decrementOrders(): void
    {
        if ($this->current_orders > 0) {
            $this->decrement('current_orders');
        }
    }
}
