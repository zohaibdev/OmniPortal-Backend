<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetaFieldResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'namespace' => $this->namespace,
            'key' => $this->key,
            'full_key' => $this->full_key,
            'value' => $this->typed_value,
            'raw_value' => $this->value,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'validation' => $this->validation,
            'sort_order' => $this->sort_order,
            'is_visible_to_customer' => $this->is_visible_to_customer,
            'is_required' => $this->is_required,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
