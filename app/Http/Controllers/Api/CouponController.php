<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Coupon::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $coupons = $query->latest()->paginate(20);

        return response()->json($coupons);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'code' => 'required|string|max:50',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_customer_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:starts_at',
            'applicable_products' => 'nullable|array',
            'applicable_categories' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        // Check unique code within store
        if (Coupon::where('code', $request->code)->exists()) {
            return response()->json([
                'message' => 'Coupon code already exists',
            ], 422);
        }

        $coupon = Coupon::create($request->all());

        return response()->json([
            'message' => 'Coupon created',
            'coupon' => $coupon,
        ], 201);
    }

    public function show(Request $request, Coupon $coupon): JsonResponse
    {
        return response()->json($coupon);
    }

    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $request->validate([
            'code' => 'sometimes|string|max:50',
            'type' => 'sometimes|in:percentage,fixed',
            'value' => 'sometimes|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'maximum_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_customer_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        $coupon->update($request->all());

        return response()->json([
            'message' => 'Coupon updated',
            'coupon' => $coupon->fresh(),
        ]);
    }

    public function destroy(Request $request, Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return response()->json([
            'message' => 'Coupon deleted',
        ]);
    }

    public function validate(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
            'customer_id' => 'nullable|exists:tenant.customers,id',
        ]);

        $coupon = Coupon::where('code', $request->code)
            ->where('is_active', true)
            ->first();

        if (!$coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid coupon code',
            ]);
        }

        // Check dates
        if ($coupon->starts_at && now()->lt($coupon->starts_at)) {
            return response()->json([
                'valid' => false,
                'message' => 'Coupon not yet active',
            ]);
        }

        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            return response()->json([
                'valid' => false,
                'message' => 'Coupon expired',
            ]);
        }

        // Check minimum order
        if ($coupon->minimum_order && $request->subtotal < $coupon->minimum_order) {
            return response()->json([
                'valid' => false,
                'message' => "Minimum order of {$coupon->minimum_order} required",
            ]);
        }

        // Check usage limit
        if ($coupon->usage_limit && $coupon->times_used >= $coupon->usage_limit) {
            return response()->json([
                'valid' => false,
                'message' => 'Coupon usage limit reached',
            ]);
        }

        // Calculate discount
        $discount = $coupon->type === 'percentage'
            ? $request->subtotal * ($coupon->value / 100)
            : $coupon->value;

        if ($coupon->maximum_discount) {
            $discount = min($discount, $coupon->maximum_discount);
        }

        return response()->json([
            'valid' => true,
            'coupon' => $coupon,
            'discount' => round($discount, 2),
        ]);
    }
}
