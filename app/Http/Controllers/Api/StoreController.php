<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function __construct(
        private StoreService $storeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $stores = $request->user()->stores()
            ->withCount(['products', 'orders', 'customers'])
            ->latest()
            ->paginate(20);

        return response()->json($stores);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:stores',
            'domain' => 'nullable|string|max:255|unique:stores',
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
            'cover_image' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string|max:50',
            'settings' => 'nullable|array',
        ]);

        $store = $this->storeService->createStore(
            $request->user(),
            $request->all()
        );

        return response()->json([
            'message' => 'Store created successfully',
            'store' => $store,
        ], 201);
    }

    public function show(Store $store): JsonResponse
    {
        $this->authorize('view', $store);

        $store->load(['categories', 'subscription']);
        $store->loadCount(['products', 'orders', 'customers', 'employees']);

        return response()->json($store);
    }

    public function update(Request $request, Store $store): JsonResponse
    {
        $this->authorize('update', $store);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:stores,slug,' . $store->id,
            'domain' => 'nullable|string|max:255|unique:stores,domain,' . $store->id,
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
            'cover_image' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string|max:50',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'delivery_fee' => 'nullable|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'settings' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $store->update($request->all());

        return response()->json([
            'message' => 'Store updated successfully',
            'store' => $store->fresh(),
        ]);
    }

    public function destroy(Store $store): JsonResponse
    {
        $this->authorize('delete', $store);

        $store->delete();

        return response()->json([
            'message' => 'Store deleted successfully',
        ]);
    }

    public function stats(Store $store): JsonResponse
    {
        $this->authorize('view', $store);

        $stats = $this->storeService->getStats($store);

        return response()->json($stats);
    }

    public function analytics(Request $request, Store $store): JsonResponse
    {
        $this->authorize('view', $store);

        $period = $request->get('period', '30');
        $analytics = $this->storeService->getAnalytics($store, $period);

        return response()->json($analytics);
    }

    public function toggleStatus(Store $store): JsonResponse
    {
        $this->authorize('update', $store);

        $store->update(['is_active' => !$store->is_active]);

        return response()->json([
            'message' => $store->is_active ? 'Store activated' : 'Store deactivated',
            'store' => $store,
        ]);
    }
}
