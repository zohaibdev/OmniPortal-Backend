<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Tenant\MetaField;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Trait HasMetaFields
 * 
 * Provides Shopify-like metafield functionality for any model.
 * Use this trait on models that should support custom meta fields.
 * 
 * @method MorphMany metaFields()
 */
trait HasMetaFields
{
    /**
     * Get all meta fields for this model.
     */
    public function metaFields(): MorphMany
    {
        return $this->morphMany(MetaField::class, 'metafieldable')
            ->orderBy('sort_order')
            ->orderBy('namespace')
            ->orderBy('key');
    }

    /**
     * Get meta fields visible to customers.
     */
    public function customerVisibleMetaFields(): MorphMany
    {
        return $this->metaFields()->where('is_visible_to_customer', true);
    }

    /**
     * Get a single meta field value by namespace and key.
     * 
     * @param string $namespace The namespace (e.g., 'custom', 'shipping')
     * @param string $key The key within the namespace
     * @param mixed $default Default value if not found
     * @return mixed The typed value or default
     */
    public function getMetaField(string $namespace, string $key, $default = null): mixed
    {
        $metaField = $this->metaFields()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->first();

        return $metaField ? $metaField->typed_value : $default;
    }

    /**
     * Get meta field using dot notation (namespace.key).
     * 
     * @param string $fullKey The full key in format "namespace.key"
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function getMeta(string $fullKey, $default = null): mixed
    {
        [$namespace, $key] = $this->parseMetaKey($fullKey);
        return $this->getMetaField($namespace, $key, $default);
    }

    /**
     * Set a meta field value.
     * Creates the meta field if it doesn't exist, updates if it does.
     * 
     * @param string $namespace The namespace
     * @param string $key The key
     * @param mixed $value The value to set
     * @param array $attributes Additional attributes (type, name, description, etc.)
     * @return MetaField
     */
    public function setMetaField(string $namespace, string $key, $value, array $attributes = []): MetaField
    {
        $metaField = $this->metaFields()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->first();

        $data = array_merge([
            'namespace' => $namespace,
            'key' => $key,
            'value' => $value,
            'type' => $attributes['type'] ?? $this->inferMetaType($value),
        ], $attributes);

        if ($metaField) {
            $metaField->update($data);
            return $metaField->fresh();
        }

        return $this->metaFields()->create($data);
    }

    /**
     * Set meta field using dot notation (namespace.key).
     * 
     * @param string $fullKey The full key in format "namespace.key"
     * @param mixed $value The value to set
     * @param array $attributes Additional attributes
     * @return MetaField
     */
    public function setMeta(string $fullKey, $value, array $attributes = []): MetaField
    {
        [$namespace, $key] = $this->parseMetaKey($fullKey);
        return $this->setMetaField($namespace, $key, $value, $attributes);
    }

    /**
     * Set multiple meta fields at once.
     * 
     * @param array $metaFields Array of meta fields [['namespace' => '', 'key' => '', 'value' => '', ...], ...]
     * @return Collection
     */
    public function setMetaFields(array $metaFields): Collection
    {
        $results = collect();

        foreach ($metaFields as $metaField) {
            $namespace = $metaField['namespace'] ?? 'custom';
            $key = $metaField['key'];
            $value = $metaField['value'] ?? null;
            
            unset($metaField['namespace'], $metaField['key'], $metaField['value']);
            
            $results->push($this->setMetaField($namespace, $key, $value, $metaField));
        }

        return $results;
    }

    /**
     * Delete a meta field.
     * 
     * @param string $namespace The namespace
     * @param string $key The key
     * @return bool
     */
    public function deleteMetaField(string $namespace, string $key): bool
    {
        return $this->metaFields()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->delete() > 0;
    }

    /**
     * Delete meta field using dot notation.
     * 
     * @param string $fullKey The full key in format "namespace.key"
     * @return bool
     */
    public function deleteMeta(string $fullKey): bool
    {
        [$namespace, $key] = $this->parseMetaKey($fullKey);
        return $this->deleteMetaField($namespace, $key);
    }

    /**
     * Check if a meta field exists.
     * 
     * @param string $namespace The namespace
     * @param string $key The key
     * @return bool
     */
    public function hasMetaField(string $namespace, string $key): bool
    {
        return $this->metaFields()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->exists();
    }

    /**
     * Check if meta field exists using dot notation.
     * 
     * @param string $fullKey The full key in format "namespace.key"
     * @return bool
     */
    public function hasMeta(string $fullKey): bool
    {
        [$namespace, $key] = $this->parseMetaKey($fullKey);
        return $this->hasMetaField($namespace, $key);
    }

    /**
     * Get all meta fields grouped by namespace.
     * 
     * @return Collection
     */
    public function getMetaFieldsByNamespace(): Collection
    {
        return $this->metaFields->groupBy('namespace');
    }

    /**
     * Get all meta fields in a specific namespace.
     * 
     * @param string $namespace The namespace
     * @return Collection
     */
    public function getMetaFieldsInNamespace(string $namespace): Collection
    {
        return $this->metaFields()->where('namespace', $namespace)->get();
    }

    /**
     * Get meta fields as key-value array.
     * 
     * @param string|null $namespace Filter by namespace (optional)
     * @return array
     */
    public function getMetaFieldsAsArray(?string $namespace = null): array
    {
        $query = $this->metaFields();
        
        if ($namespace) {
            $query->where('namespace', $namespace);
        }

        return $query->get()->mapWithKeys(function ($field) {
            return [$field->full_key => $field->typed_value];
        })->toArray();
    }

    /**
     * Sync meta fields - removes unlisted fields and updates/creates listed ones.
     * 
     * @param array $metaFields Array of meta fields to sync
     * @param string|null $namespace Limit sync to specific namespace
     * @return Collection
     */
    public function syncMetaFields(array $metaFields, ?string $namespace = null): Collection
    {
        // Get keys that should exist after sync
        $newKeys = collect($metaFields)->map(function ($field) {
            return ($field['namespace'] ?? 'custom') . '.' . $field['key'];
        });

        // Delete fields not in the new set
        $query = $this->metaFields();
        if ($namespace) {
            $query->where('namespace', $namespace);
        }

        $query->get()->each(function ($field) use ($newKeys) {
            if (!$newKeys->contains($field->full_key)) {
                $field->delete();
            }
        });

        // Create or update the new fields
        return $this->setMetaFields($metaFields);
    }

    /**
     * Parse a full key into namespace and key parts.
     * 
     * @param string $fullKey The full key (e.g., "custom.color" or just "color")
     * @return array [namespace, key]
     */
    protected function parseMetaKey(string $fullKey): array
    {
        if (str_contains($fullKey, '.')) {
            return explode('.', $fullKey, 2);
        }

        return ['custom', $fullKey];
    }

    /**
     * Infer the meta field type from the value.
     * 
     * @param mixed $value
     * @return string
     */
    protected function inferMetaType($value): string
    {
        if (is_null($value)) {
            return 'string';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'decimal';
        }

        if (is_array($value)) {
            return 'json';
        }

        if (is_string($value)) {
            // Check for specific patterns
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return 'email';
            }

            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return 'url';
            }

            if (preg_match('/^#[a-fA-F0-9]{6}$/', $value)) {
                return 'color';
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return 'date';
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $value)) {
                return 'datetime';
            }

            if (strlen($value) > 255) {
                return 'text';
            }
        }

        return 'string';
    }

    /**
     * Boot the trait - automatically load meta fields with model if needed.
     */
    public static function bootHasMetaFields(): void
    {
        // Optionally auto-delete meta fields when the parent model is deleted
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return; // Don't delete meta fields on soft delete
            }
            
            $model->metaFields()->delete();
        });
    }
}
