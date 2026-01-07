<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\ProductAddon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductAddonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addons = ProductAddon::orderBy('name')
            ->get();

        return response()->json(['addons' => $addons]);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $addon = ProductAddon::create($request->all());

        return response()->json([
            'message' => 'Addon created',
            'addon' => $addon,
        ], 201);
    }

    public function show(Request $request, string $storeId, int $addonId): JsonResponse
    {
        $addon = ProductAddon::findOrFail($addonId);
        return response()->json(['addon' => $addon]);
    }

    public function update(Request $request, string $storeId, int $addonId): JsonResponse
    {
        $addon = ProductAddon::findOrFail($addonId);
        $addon->update($request->all());

        return response()->json([
            'message' => 'Addon updated',
            'addon' => $addon->fresh(),
        ]);
    }

    public function destroy(Request $request, string $storeId, int $addonId): JsonResponse
    {
        $addon = ProductAddon::findOrFail($addonId);
        $addon->delete();

        return response()->json(['message' => 'Addon deleted']);
    }
}
