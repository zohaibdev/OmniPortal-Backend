<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'variants', 'options']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        $products = $query->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($request->get('per_page', 20));

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:tenant.categories,id',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'track_stock' => 'boolean',
            'allow_backorder' => 'boolean',
            'images' => 'nullable|array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'preparation_time' => 'nullable|integer|min:0',
            'calories' => 'nullable|integer|min:0',
            'allergens' => 'nullable|array',
            'tags' => 'nullable|array',
            'variants' => 'nullable|array',
            'options' => 'nullable|array',
        ]);

        // Ensure unique slug (tenant DB is already scoped to this store)
        $slug = $request->slug;
        $count = 0;
        while (Product::where('slug', $slug)->exists()) {
            $count++;
            $slug = $request->slug . '-' . $count;
        }

        $product = Product::create([
            ...$request->except(['slug', 'variants', 'options']),
            'slug' => $slug,
        ]);

        // Create variants
        if ($request->has('variants')) {
            foreach ($request->variants as $variant) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    ...$variant,
                ]);
            }
        }

        // Create options
        if ($request->has('options')) {
            foreach ($request->options as $option) {
                ProductOption::create([
                    'product_id' => $product->id,
                    ...$option,
                ]);
            }
        }

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product->load(['category', 'variants', 'options']),
        ], 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $product->load(['category', 'variants', 'options']);

        return response()->json($product);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:tenant.categories,id',
            'price' => 'sometimes|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'track_stock' => 'boolean',
            'allow_backorder' => 'boolean',
            'images' => 'nullable|array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'preparation_time' => 'nullable|integer|min:0',
            'calories' => 'nullable|integer|min:0',
            'allergens' => 'nullable|array',
            'tags' => 'nullable|array',
        ]);

        $product->update($request->except(['variants', 'options']));

        // Update variants if provided
        if ($request->has('variants')) {
            $product->variants()->delete();
            foreach ($request->variants as $variant) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    ...$variant,
                ]);
            }
        }

        // Update options if provided
        if ($request->has('options')) {
            $product->options()->delete();
            foreach ($request->options as $option) {
                ProductOption::create([
                    'product_id' => $product->id,
                    ...$option,
                ]);
            }
        }

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->fresh(['category', 'variants', 'options']),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    public function toggleStatus(Request $request, Product $product): JsonResponse
    {
        $product->update(['is_active' => !$product->is_active]);

        return response()->json([
            'message' => $product->is_active ? 'Product activated' : 'Product deactivated',
            'product' => $product,
        ]);
    }

    public function updateStock(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'stock_quantity' => 'required|integer|min:0',
            'variant_id' => 'nullable|exists:tenant.product_variants,id',
        ]);

        if ($request->has('variant_id')) {
            $variant = ProductVariant::find($request->variant_id);
            if ($variant && $variant->product_id === $product->id) {
                $variant->update(['stock_quantity' => $request->stock_quantity]);
            }
        } else {
            $product->update(['stock_quantity' => $request->stock_quantity]);
        }

        return response()->json([
            'message' => 'Stock updated successfully',
            'product' => $product->fresh(['variants']),
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:tenant.products,id',
        ]);

        Product::whereIn('id', $request->ids)
            ->delete();

        return response()->json([
            'message' => 'Products deleted successfully',
        ]);
    }
}
