<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Category;
use App\Models\Product;
use App\Models\Page;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        return response()->json([
            'id' => $store->id,
            'name' => $store->name,
            'slug' => $store->slug,
            'description' => $store->description,
            'logo' => $store->logo,
            'cover_image' => $store->cover_image,
            'address' => $store->address,
            'city' => $store->city,
            'phone' => $store->phone,
            'email' => $store->email,
            'currency' => $store->currency,
            'tax_rate' => $store->tax_rate,
            'delivery_fee' => $store->delivery_fee,
            'minimum_order' => $store->minimum_order,
            'settings' => $store->settings,
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->withCount(['products' => fn($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }

    public function products(Request $request): JsonResponse
    {
        $query = Product::where('is_active', true)
            ->with(['category', 'variants', 'options']);

        if ($request->has('category')) {
            $query->whereHas('category', fn($q) => $q->where('slug', $request->category));
        }

        if ($request->has('featured')) {
            $query->where('is_featured', true);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        $products = $query->orderBy('sort_order')
            ->paginate($request->get('per_page', 20));

        return response()->json($products);
    }

    public function product(Request $request, string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)
            ->where('is_active', true)
            ->with(['category', 'variants', 'options'])
            ->first();

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json($product);
    }

    public function banners(Request $request): JsonResponse
    {
        $query = Banner::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });

        if ($request->has('position')) {
            $query->where('position', $request->position);
        }

        $banners = $query->orderBy('sort_order')->get();

        return response()->json($banners);
    }

    public function pages(Request $request): JsonResponse
    {
        $pages = Page::where('is_published', true)
            ->select(['id', 'title', 'slug', 'sort_order'])
            ->orderBy('sort_order')
            ->get();

        return response()->json($pages);
    }

    public function page(Request $request, string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        return response()->json($page);
    }

    public function validateCoupon(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $coupon = \App\Models\Coupon::where('code', $request->code)
            ->where('is_active', true)
            ->first();

        if (!$coupon) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid coupon code',
            ]);
        }

        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            return response()->json([
                'valid' => false,
                'message' => 'Coupon expired',
            ]);
        }

        if ($coupon->minimum_order && $request->subtotal < $coupon->minimum_order) {
            return response()->json([
                'valid' => false,
                'message' => "Minimum order of {$store->currency}{$coupon->minimum_order} required",
            ]);
        }

        $discount = $coupon->type === 'percentage'
            ? $request->subtotal * ($coupon->value / 100)
            : $coupon->value;

        if ($coupon->maximum_discount) {
            $discount = min($discount, $coupon->maximum_discount);
        }

        return response()->json([
            'valid' => true,
            'discount' => round($discount, 2),
            'coupon' => [
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
            ],
        ]);
    }
}
