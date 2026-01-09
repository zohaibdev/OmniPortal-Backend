<?php

namespace App\Models\Tenant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'type',
        'instructions',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // Type constants
    public const TYPE_OFFLINE = 'offline';
    public const TYPE_ONLINE = 'online';

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    // Relationships
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
