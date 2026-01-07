<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MetaField;
use App\Models\Tenant\MetaFieldDefinition;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MetaFieldController extends Controller
{
    /**
     * Supported resource types and their models
     */
    protected array $resourceModels = [
        'product' => Product::class,
        'products' => Product::class,
        'customer' => Customer::class,
        'customers' => Customer::class,
        'order' => Order::class,
        'orders' => Order::class,
    ];

    /**
     * Get all meta field definitions for a resource type.
     */
    public function definitions(Request $request, string $storeId, string $resourceType): JsonResponse
    {
        $modelClass = $this->getModelClass($resourceType);
        
        if (!$modelClass) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid resource type. Supported types: ' . implode(', ', array_keys($this->resourceModels)),
            ], 400);
        }

        $definitions = MetaFieldDefinition::forResource($modelClass)
            ->active()
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $definitions,
        ]);
    }

    /**
     * Create a new meta field definition.
     */
    public function createDefinition(Request $request, string $storeId, string $resourceType): JsonResponse
    {
        $modelClass = $this->getModelClass($resourceType);
        
        if (!$modelClass) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid resource type. Supported types: ' . implode(', ', array_keys($this->resourceModels)),
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'namespace' => 'string|max:100',
            'key' => 'required|string|max:100|regex:/^[a-z][a-z0-9_]*$/',
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'type' => 'required|string|in:' . implode(',', array_keys(MetaField::TYPES)),
            'default_value' => 'nullable',
            'validation' => 'nullable|array',
            'options' => 'nullable|array',
            'sort_order' => 'integer',
            'is_visible_to_customer' => 'boolean',
            'is_required' => 'boolean',
            'ui_position' => 'nullable|string|in:sidebar,main,bottom',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['resource_type'] = $modelClass;
        $data['namespace'] = $data['namespace'] ?? 'custom';

        // Check for duplicate
        $exists = MetaFieldDefinition::where('resource_type', $modelClass)
            ->where('namespace', $data['namespace'])
            ->where('key', $data['key'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => "A meta field with key '{$data['namespace']}.{$data['key']}' already exists for this resource.",
            ], 409);
        }

        $definition = MetaFieldDefinition::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Meta field definition created successfully',
            'data' => $definition,
        ], 201);
    }

    /**
     * Update a meta field definition.
     */
    public function updateDefinition(Request $request, string $storeId, string $resourceType, int $definitionId): JsonResponse
    {
        $definition = MetaFieldDefinition::find($definitionId);

        if (!$definition) {
            return response()->json([
                'success' => false,
                'message' => 'Meta field definition not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:191',
            'description' => 'nullable|string',
            'type' => 'string|in:' . implode(',', array_keys(MetaField::TYPES)),
            'default_value' => 'nullable',
            'validation' => 'nullable|array',
            'options' => 'nullable|array',
            'sort_order' => 'integer',
            'is_visible_to_customer' => 'boolean',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'ui_position' => 'nullable|string|in:sidebar,main,bottom',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $definition->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Meta field definition updated successfully',
            'data' => $definition->fresh(),
        ]);
    }

    /**
     * Delete a meta field definition.
     */
    public function deleteDefinition(Request $request, string $storeId, string $resourceType, int $definitionId): JsonResponse
    {
        $definition = MetaFieldDefinition::find($definitionId);

        if (!$definition) {
            return response()->json([
                'success' => false,
                'message' => 'Meta field definition not found',
            ], 404);
        }

        // Optionally delete all meta fields using this definition
        if ($request->boolean('delete_values', false)) {
            MetaField::where('namespace', $definition->namespace)
                ->where('key', $definition->key)
                ->where('metafieldable_type', $definition->resource_type)
                ->delete();
        }

        $definition->delete();

        return response()->json([
            'success' => true,
            'message' => 'Meta field definition deleted successfully',
        ]);
    }

    /**
     * Get all meta fields for a specific resource instance.
     */
    public function index(Request $request, string $storeId, string $resourceType, int $resourceId): JsonResponse
    {
        $model = $this->findResource($resourceType, $resourceId);

        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($resourceType) . ' not found',
            ], 404);
        }

        $metaFields = $model->metaFields()
            ->when($request->has('namespace'), fn($q) => $q->where('namespace', $request->namespace))
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $metaFields,
        ]);
    }

    /**
     * Get a single meta field.
     */
    public function show(Request $request, string $storeId, string $resourceType, int $resourceId, string $namespaceKey): JsonResponse
    {
        $model = $this->findResource($resourceType, $resourceId);

        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($resourceType) . ' not found',
            ], 404);
        }

        [$namespace, $key] = $this->parseKey($namespaceKey);

        $metaField = $model->metaFields()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->first();

        if (!$metaField) {
            return response()->json([
                'success' => false,
                'message' => 'Meta field not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $metaField,
        ]);
    }

    /**
     * Create or update a meta field for a resource.
     */
    public function store(Request $request, string $storeId, string $resourceType, int $resourceId): JsonResponse
    {
        $model = $this->findResource($resourceType, $resourceId);

        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($resourceType) . ' not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'namespace' => 'string|max:100',
            'key' => 'required|string|max:100',
            'value' => 'nullable',
            'type' => 'string|in:' . implode(',', array_keys(MetaField::TYPES)),
            'name' => 'nullable|string|max:191',
            'description' => 'nullable|string',
            'is_visible_to_customer' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $namespace = $data['namespace'] ?? 'custom';
        $key = $data['key'];
        $value = $data['value'] ?? null;

        unset($data['namespace'], $data['key'], $data['value']);

        $metaField = $model->setMetaField($namespace, $key, $value, $data);

        return response()->json([
            'success' => true,
            'message' => 'Meta field saved successfully',
            'data' => $metaField,
        ], 201);
    }

    /**
     * Set multiple meta fields at once.
     */
    public function storeBulk(Request $request, string $storeId, string $resourceType, int $resourceId): JsonResponse
    {
        $model = $this->findResource($resourceType, $resourceId);

        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($resourceType) . ' not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'meta_fields' => 'required|array|min:1',
            'meta_fields.*.namespace' => 'string|max:100',
            'meta_fields.*.key' => 'required|string|max:100',
            'meta_fields.*.value' => 'nullable',
            'meta_fields.*.type' => 'string|in:' . implode(',', array_keys(MetaField::TYPES)),
            'meta_fields.*.name' => 'nullable|string|max:191',
            'sync' => 'boolean', // If true, removes meta fields not in the list
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $metaFields = $data['meta_fields'];

        if ($data['sync'] ?? false) {
            $results = $model->syncMetaFields($metaFields);
        } else {
            $results = $model->setMetaFields($metaFields);
        }

        return response()->json([
            'success' => true,
            'message' => 'Meta fields saved successfully',
            'data' => $results,
        ]);
    }

    /**
     * Update a specific meta field.
     */
    public function update(Request $request, string $storeId, string $resourceType, int $resourceId, string $namespaceKey): JsonResponse
    {
        $model = $this->findResource($resourceType, $resourceId);

        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($resourceType) . ' not found',
            ], 404);
        }

        [$namespace, $key] = $this->parseKey($namespaceKey);

        $metaField = $model->metaFields()
            ->where('namespace', $namespace)
            ->where('key', $key)
            ->first();

        if (!$metaField) {
            return response()->json([
                'success' => false,
                'message' => 'Meta field not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'value' => 'nullable',
            'type' => 'string|in:' . implode(',', array_keys(MetaField::TYPES)),
            'name' => 'nullable|string|max:191',
            'description' => 'nullable|string',
            'is_visible_to_customer' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $metaField->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Meta field updated successfully',
            'data' => $metaField->fresh(),
        ]);
    }

    /**
     * Delete a meta field.
     */
    public function destroy(Request $request, string $storeId, string $resourceType, int $resourceId, string $namespaceKey): JsonResponse
    {
        $model = $this->findResource($resourceType, $resourceId);

        if (!$model) {
            return response()->json([
                'success' => false,
                'message' => ucfirst($resourceType) . ' not found',
            ], 404);
        }

        [$namespace, $key] = $this->parseKey($namespaceKey);

        $deleted = $model->deleteMetaField($namespace, $key);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Meta field not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Meta field deleted successfully',
        ]);
    }

    /**
     * Get available meta field types.
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => MetaField::TYPES,
        ]);
    }

    /**
     * Get model class from resource type string.
     */
    protected function getModelClass(string $resourceType): ?string
    {
        $type = strtolower($resourceType);
        return $this->resourceModels[$type] ?? null;
    }

    /**
     * Find a resource by type and ID.
     */
    protected function findResource(string $resourceType, int $resourceId)
    {
        $modelClass = $this->getModelClass($resourceType);
        
        if (!$modelClass) {
            return null;
        }

        return $modelClass::find($resourceId);
    }

    /**
     * Parse namespace.key into array.
     */
    protected function parseKey(string $namespaceKey): array
    {
        if (str_contains($namespaceKey, '.')) {
            return explode('.', $namespaceKey, 2);
        }

        return ['custom', $namespaceKey];
    }
}
