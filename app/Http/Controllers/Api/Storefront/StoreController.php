<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        if (!$store || !$store->is_active) {
            return response()->json(['message' => 'Store not found'], 404);
        }

        return response()->json([
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'description' => $store->description,
                'logo' => $store->logo,
                'banner' => $store->banner,
                'address' => $store->address,
                'city' => $store->city,
                'phone' => $store->phone,
                'email' => $store->email,
                'currency' => $store->currency,
                'timezone' => $store->timezone,
                'operating_hours' => $store->operatingHours,
                'settings' => $store->settings->pluck('value', 'key'),
            ],
        ]);
    }
}
