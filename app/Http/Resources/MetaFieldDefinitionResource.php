<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetaFieldDefinitionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'resource_type' => $this->resource_type,
            'resource_type_name' => $this->getResourceTypeName(),
            'namespace' => $this->namespace,
            'key' => $this->key,
            'full_key' => $this->full_key,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'default_value' => $this->default_value,
            'validation' => $this->validation,
            'options' => $this->options,
            'sort_order' => $this->sort_order,
            'is_visible_to_customer' => $this->is_visible_to_customer,
            'is_required' => $this->is_required,
            'is_active' => $this->is_active,
            'ui_position' => $this->ui_position,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get human-readable resource type name.
     */
    protected function getResourceTypeName(): string
    {
        $parts = explode('\\', $this->resource_type);
        return strtolower(end($parts));
    }
}
