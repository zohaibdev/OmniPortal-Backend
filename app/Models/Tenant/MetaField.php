<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MetaField extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'namespace',
        'key',
        'value',
        'type',
        'name',
        'description',
        'validation',
        'sort_order',
        'is_visible_to_customer',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'validation' => 'array',
            'sort_order' => 'integer',
            'is_visible_to_customer' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    /**
     * Supported meta field types
     */
    public const TYPES = [
        'string' => 'Single line text',
        'text' => 'Multi-line text',
        'rich_text' => 'Rich text (HTML)',
        'integer' => 'Integer number',
        'decimal' => 'Decimal number',
        'boolean' => 'True/False',
        'json' => 'JSON object',
        'date' => 'Date',
        'datetime' => 'Date & Time',
        'url' => 'URL',
        'email' => 'Email',
        'color' => 'Color (hex)',
        'file' => 'File URL',
        'image' => 'Image URL',
        'select' => 'Single select',
        'multiselect' => 'Multi select',
        'rating' => 'Rating (1-5)',
        'dimension' => 'Dimensions (L×W×H)',
        'weight' => 'Weight',
        'money' => 'Money amount',
    ];

    /**
     * Get the parent metafieldable model.
     */
    public function metafieldable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the typed value based on the field type.
     */
    public function getTypedValueAttribute(): mixed
    {
        $value = $this->value;

        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            'integer' => (int) $value,
            'decimal', 'money', 'weight' => (float) $value,
            'boolean' => (bool) $value,
            'date' => \Carbon\Carbon::parse($value)->toDateString(),
            'datetime' => \Carbon\Carbon::parse($value),
            'json', 'dimension', 'multiselect' => is_array($value) ? $value : json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Set the value with proper type casting.
     */
    public function setValueAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['value'] = null;
            return;
        }

        // Store everything as JSON for consistency
        $this->attributes['value'] = json_encode($value);
    }

    /**
     * Get full key (namespace.key format).
     */
    public function getFullKeyAttribute(): string
    {
        return "{$this->namespace}.{$this->key}";
    }

    /**
     * Scope to filter by namespace.
     */
    public function scopeNamespace($query, string $namespace)
    {
        return $query->where('namespace', $namespace);
    }

    /**
     * Scope to filter by key.
     */
    public function scopeKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    /**
     * Scope to get customer-visible fields only.
     */
    public function scopeVisibleToCustomer($query)
    {
        return $query->where('is_visible_to_customer', true);
    }

    /**
     * Validate the value against defined validation rules.
     */
    public function validateValue($value): array
    {
        $errors = [];
        $rules = $this->validation ?? [];

        // Required check
        if ($this->is_required && ($value === null || $value === '')) {
            $errors[] = "The {$this->name} field is required.";
        }

        if ($value === null) {
            return $errors;
        }

        // Type-specific validation
        switch ($this->type) {
            case 'integer':
                if (!is_numeric($value) || (int) $value != $value) {
                    $errors[] = "The {$this->name} field must be an integer.";
                }
                break;

            case 'decimal':
            case 'money':
            case 'weight':
                if (!is_numeric($value)) {
                    $errors[] = "The {$this->name} field must be a number.";
                }
                break;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "The {$this->name} field must be a valid email.";
                }
                break;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = "The {$this->name} field must be a valid URL.";
                }
                break;

            case 'date':
            case 'datetime':
                try {
                    \Carbon\Carbon::parse($value);
                } catch (\Exception $e) {
                    $errors[] = "The {$this->name} field must be a valid date.";
                }
                break;

            case 'color':
                if (!preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
                    $errors[] = "The {$this->name} field must be a valid hex color.";
                }
                break;

            case 'rating':
                if (!is_numeric($value) || $value < 1 || $value > 5) {
                    $errors[] = "The {$this->name} field must be between 1 and 5.";
                }
                break;
        }

        // Custom validation rules
        if (isset($rules['min']) && is_numeric($value) && $value < $rules['min']) {
            $errors[] = "The {$this->name} field must be at least {$rules['min']}.";
        }

        if (isset($rules['max']) && is_numeric($value) && $value > $rules['max']) {
            $errors[] = "The {$this->name} field must not exceed {$rules['max']}.";
        }

        if (isset($rules['min_length']) && strlen($value) < $rules['min_length']) {
            $errors[] = "The {$this->name} field must be at least {$rules['min_length']} characters.";
        }

        if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) {
            $errors[] = "The {$this->name} field must not exceed {$rules['max_length']} characters.";
        }

        if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) {
            $errors[] = "The {$this->name} field format is invalid.";
        }

        return $errors;
    }
}
