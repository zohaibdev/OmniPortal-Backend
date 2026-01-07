<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetaFieldDefinition extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'resource_type',
        'namespace',
        'key',
        'name',
        'description',
        'type',
        'default_value',
        'validation',
        'options',
        'sort_order',
        'is_visible_to_customer',
        'is_required',
        'is_active',
        'ui_position',
    ];

    protected function casts(): array
    {
        return [
            'default_value' => 'json',
            'validation' => 'array',
            'options' => 'array',
            'sort_order' => 'integer',
            'is_visible_to_customer' => 'boolean',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Resource type constants
     */
    public const RESOURCE_PRODUCT = 'App\\Models\\Tenant\\Product';
    public const RESOURCE_CUSTOMER = 'App\\Models\\Tenant\\Customer';
    public const RESOURCE_ORDER = 'App\\Models\\Tenant\\Order';
    public const RESOURCE_CATEGORY = 'App\\Models\\Tenant\\Category';

    /**
     * Get all supported resource types
     */
    public static function resourceTypes(): array
    {
        return [
            'product' => self::RESOURCE_PRODUCT,
            'customer' => self::RESOURCE_CUSTOMER,
            'order' => self::RESOURCE_ORDER,
            'category' => self::RESOURCE_CATEGORY,
        ];
    }

    /**
     * Get the full key (namespace.key)
     */
    public function getFullKeyAttribute(): string
    {
        return "{$this->namespace}.{$this->key}";
    }

    /**
     * Scope to filter by resource type
     */
    public function scopeForResource($query, string $resourceType)
    {
        // Allow passing simple names like 'product' instead of full class name
        if (!str_contains($resourceType, '\\')) {
            $resourceType = self::resourceTypes()[$resourceType] ?? $resourceType;
        }

        return $query->where('resource_type', $resourceType);
    }

    /**
     * Scope to get only active definitions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by namespace
     */
    public function scopeNamespace($query, string $namespace)
    {
        return $query->where('namespace', $namespace);
    }

    /**
     * Create a meta field instance from this definition
     */
    public function createMetaField(Model $model, $value = null): MetaField
    {
        return $model->metaFields()->create([
            'namespace' => $this->namespace,
            'key' => $this->key,
            'value' => $value ?? $this->default_value,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'validation' => $this->validation,
            'sort_order' => $this->sort_order,
            'is_visible_to_customer' => $this->is_visible_to_customer,
            'is_required' => $this->is_required,
        ]);
    }

    /**
     * Get validation rules for Laravel validator
     */
    public function getLaravelValidationRules(): array
    {
        $rules = [];

        if ($this->is_required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        switch ($this->type) {
            case 'integer':
                $rules[] = 'integer';
                break;
            case 'decimal':
            case 'money':
            case 'weight':
                $rules[] = 'numeric';
                break;
            case 'boolean':
                $rules[] = 'boolean';
                break;
            case 'email':
                $rules[] = 'email';
                break;
            case 'url':
            case 'file':
            case 'image':
                $rules[] = 'url';
                break;
            case 'date':
                $rules[] = 'date';
                break;
            case 'datetime':
                $rules[] = 'date';
                break;
            case 'json':
            case 'dimension':
                $rules[] = 'array';
                break;
            case 'select':
                if ($this->options) {
                    $rules[] = 'in:' . implode(',', array_column($this->options, 'value'));
                }
                break;
            case 'multiselect':
                $rules[] = 'array';
                break;
            case 'rating':
                $rules[] = 'integer';
                $rules[] = 'min:1';
                $rules[] = 'max:5';
                break;
            case 'color':
                $rules[] = 'regex:/^#[a-fA-F0-9]{6}$/';
                break;
        }

        // Add custom validation rules
        if ($this->validation) {
            if (isset($this->validation['min'])) {
                $rules[] = 'min:' . $this->validation['min'];
            }
            if (isset($this->validation['max'])) {
                $rules[] = 'max:' . $this->validation['max'];
            }
            if (isset($this->validation['min_length'])) {
                $rules[] = 'min:' . $this->validation['min_length'];
            }
            if (isset($this->validation['max_length'])) {
                $rules[] = 'max:' . $this->validation['max_length'];
            }
        }

        return $rules;
    }
}
