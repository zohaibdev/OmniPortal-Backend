<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function validateCart(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:tenant.products,id',
            'items.*.variant_id' => 'nullable|exists:tenant.product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.addons' => 'nullable|array',
        ]);

        $validatedItems = [];
        $total = 0;

        foreach ($request->items as $item) {
            $product = Product::with('variants')->find($item['product_id']);

            if (!$product || !$product->is_active) {
                continue;
            }

            $price = $product->price;

            if (!empty($item['variant_id'])) {
                $variant = $product->variants->find($item['variant_id']);
                if ($variant) {
                    $price = $variant->price;
                }
            }

            $itemTotal = $price * $item['quantity'];
            $total += $itemTotal;

            $validatedItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'variant_id' => $item['variant_id'] ?? null,
                'quantity' => $item['quantity'],
                'price' => $price,
                'total' => $itemTotal,
            ];
        }

        return response()->json([
            'valid' => true,
            'items' => $validatedItems,
            'subtotal' => $total,
        ]);
    }
}
