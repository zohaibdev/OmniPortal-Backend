<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'sku',
        'barcode',
        'price',
        'compare_price',
        'cost_price',
        'image',
        'gallery',
        'stock_quantity',
        'track_inventory',
        'low_stock_threshold',
        'allow_backorder',
        'weight',
        'weight_unit',
        'dimensions',
        'is_taxable',
        'tax_class',
        'status',
        'is_featured',
        'sort_order',
        'availability_schedule',
        'preparation_time',
        'nutritional_info',
        'allergens',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'dimensions' => 'array',
            'availability_schedule' => 'array',
            'nutritional_info' => 'array',
            'allergens' => 'array',
            'meta' => 'array',
            'meta_data' => 'array',
            'is_featured' => 'boolean',
            'is_taxable' => 'boolean',
            'track_inventory' => 'boolean',
            'allow_backorder' => 'boolean',
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight' => 'decimal:2',
        ];
    }

    // Relationships
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function options()
    {
        return $this->hasMany(ProductOption::class);
    }

    public function addons()
    {
        return $this->belongsToMany(ProductAddon::class, 'product_addon_product');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->where('track_inventory', false)
                ->orWhere('stock_quantity', '>', 0)
                ->orWhere('allow_backorder', true);
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Helpers
    public function isInStock(): bool
    {
        if (!$this->track_inventory) {
            return true;
        }
        return $this->stock_quantity > 0 || $this->allow_backorder;
    }

    public function isLowStock(): bool
    {
        return $this->track_inventory && $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function hasDiscount(): bool
    {
        return $this->compare_price && $this->compare_price > $this->price;
    }

    public function getDiscountPercentage(): float
    {
        if (!$this->hasDiscount()) {
            return 0;
        }
        return round((($this->compare_price - $this->price) / $this->compare_price) * 100);
    }
}
