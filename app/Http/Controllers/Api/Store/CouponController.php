<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coupons = Coupon::latest()
            ->paginate(20);

        return response()->json($coupons);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'max_uses_per_customer' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
        ]);

        $coupon = Coupon::create($request->all());

        return response()->json([
            'message' => 'Coupon created',
            'coupon' => $coupon,
        ], 201);
    }

    public function show(Request $request, string $storeId, int $couponId): JsonResponse
    {
        $coupon = Coupon::findOrFail($couponId);
        return response()->json(['coupon' => $coupon]);
    }

    public function update(Request $request, string $storeId, int $couponId): JsonResponse
    {
        $coupon = Coupon::findOrFail($couponId);
        $coupon->update($request->all());

        return response()->json([
            'message' => 'Coupon updated',
            'coupon' => $coupon->fresh(),
        ]);
    }

    public function destroy(Request $request, string $storeId, int $couponId): JsonResponse
    {
        $coupon = Coupon::findOrFail($couponId);
        $coupon->delete();

        return response()->json(['message' => 'Coupon deleted']);
    }
}
