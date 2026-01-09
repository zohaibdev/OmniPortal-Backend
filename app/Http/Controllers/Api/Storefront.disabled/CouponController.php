<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function apply(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', $request->code)
            ->where('is_active', true)
            ->first();

        if (!$coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid coupon code',
            ], 422);
        }

        // Check if expired
        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return response()->json([
                'valid' => false,
                'message' => 'Coupon has expired',
            ], 422);
        }

        // Check if not yet active
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return response()->json([
                'valid' => false,
                'message' => 'Coupon is not yet active',
            ], 422);
        }

        // Check minimum order amount
        if ($coupon->min_order_amount && $request->subtotal < $coupon->min_order_amount) {
            return response()->json([
                'valid' => false,
                'message' => "Minimum order amount is {$coupon->min_order_amount}",
            ], 422);
        }

        // Check max uses
        if ($coupon->max_uses && $coupon->times_used >= $coupon->max_uses) {
            return response()->json([
                'valid' => false,
                'message' => 'Coupon usage limit reached',
            ], 422);
        }

        // Calculate discount
        $discount = $coupon->type === 'percentage'
            ? $request->subtotal * ($coupon->value / 100)
            : $coupon->value;

        return response()->json([
            'valid' => true,
            'coupon' => [
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
            ],
            'discount' => round($discount, 2),
        ]);
    }
}
