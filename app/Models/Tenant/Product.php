<?php

namespace App\Models\Tenant;

use App\Traits\BelongsToTenant;
use App\Traits\HasEncryptedId;
use App\Traits\HasMetaFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToTenant, HasEncryptedId, HasMetaFields, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'short_description',
        'sku',
        'barcode',
        'price',
        'compare_price',
        'cost_price',
        'image',
        'gallery',
        'stock_quantity',
        'low_stock_threshold',
        'track_inventory',
        'allow_backorder',
        'status',
        'is_featured',
        'weight',
        'weight_unit',
        'dimensions',
        'meta_data',
        'nutritional_info',
        'preparation_time',
        'allergens',
        'is_taxable',
        'tax_class',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'dimensions' => 'array',
            'meta_data' => 'array',
            'nutritional_info' => 'array',
            'allergens' => 'array',
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight' => 'decimal:2',
            'track_inventory' => 'boolean',
            'allow_backorder' => 'boolean',
            'is_featured' => 'boolean',
            'is_taxable' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    // Relationships
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

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'product_tags');
    }

    public function addons()
    {
        return $this->belongsToMany(ProductAddon::class, 'product_addon_mappings', 'product_id', 'addon_id')
            ->withPivot(['is_required', 'max_quantity'])
            ->withTimestamps();
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

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('sku', 'like', "%{$term}%");
        });
    }

    // Helpers
    public function isInStock(): bool
    {
        if (!$this->track_inventory) {
            return true;
        }

        return $this->stock_quantity > 0 || $this->allow_backorder;
    }

    public function getDiscountPercentage(): ?float
    {
        if (!$this->compare_price || $this->compare_price <= $this->price) {
            return null;
        }

        return round((($this->compare_price - $this->price) / $this->compare_price) * 100, 1);
    }

    public function incrementViewCount(): void
    {
        $this->increment('views_count');
    }

    public function incrementSalesCount(int $quantity = 1): void
    {
        $this->increment('sales_count', $quantity);
    }
}
