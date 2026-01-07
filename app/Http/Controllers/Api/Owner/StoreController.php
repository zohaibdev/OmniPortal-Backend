<?php

namespace App\Http\Controllers\Api\Owner;

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
        $owner = $request->user();
        
        $stores = $owner->stores()
            ->latest()
            ->get();

        return response()->json([
            'stores' => $stores,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:stores',
            'description' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string|max:50',
        ]);

        // Auto-generate slug if not provided
        $data = $request->all();
        if (empty($data['slug'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
            // Make sure slug is unique
            $baseSlug = $data['slug'];
            $counter = 1;
            while (Store::where('slug', $data['slug'])->exists()) {
                $data['slug'] = $baseSlug . '-' . $counter;
                $counter++;
            }
        }

        $store = $this->storeService->create(
            $request->user(),
            $data
        );

        return response()->json([
            'message' => 'Store created successfully',
            'store' => $store,
        ], 201);
    }

    public function show(Request $request, Store $store): JsonResponse
    {
        // Verify ownership
        if ($store->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        return response()->json([
            'store' => $store->load(['subscription.plan']),
        ]);
    }

    public function update(Request $request, Store $store): JsonResponse
    {
        // Verify ownership
        if ($store->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string|max:50',
            'logo' => 'nullable|string',
            'banner' => 'nullable|string',
        ]);

        $store->update($request->all());

        return response()->json([
            'message' => 'Store updated successfully',
            'store' => $store->fresh(),
        ]);
    }
}
