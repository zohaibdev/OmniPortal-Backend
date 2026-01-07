<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductOptionValue extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'product_option_id',
        'value',
        'price_modifier',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'price_modifier' => 'decimal:2',
        ];
    }

    public function option()
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }
}
