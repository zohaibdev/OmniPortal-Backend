<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'variants', 'options']);

        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('all') && $request->all == 'true') {
            $products = $query->orderBy('name')->get();
            return response()->json(['products' => $products, 'data' => $products]);
        }

        $products = $query->orderBy('name')->paginate(20);

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:tenant.categories,id',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'stock_quantity' => 'nullable|integer|min:0',
            'track_inventory' => 'nullable',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'status' => 'nullable|in:draft,active,archived',
            'is_featured' => 'nullable',
        ]);

        $slug = $request->slug ?? Str::slug($request->name);
        
        // Ensure unique slug (tenant DB is already scoped to this store)
        $originalSlug = $slug;
        $counter = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products/' . $store->id, 'public');
            $imagePath = '/storage/' . $imagePath;
        }

        $product = Product::create([
            'slug' => $slug,
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id ?: null,
            'price' => $request->price,
            'compare_price' => $request->compare_price,
            'cost_price' => $request->cost_price,
            'sku' => $request->sku,
            'barcode' => $request->barcode,
            'stock_quantity' => $request->stock_quantity ?? 0,
            'track_inventory' => filter_var($request->track_inventory, FILTER_VALIDATE_BOOLEAN),
            'status' => $request->status ?? 'active',
            'is_featured' => filter_var($request->is_featured ?? false, FILTER_VALIDATE_BOOLEAN),
            'image' => $imagePath,
        ]);

        return response()->json([
            'message' => 'Product created',
            'product' => $product->load(['category', 'variants']),
        ], 201);
    }

    public function show(Request $request, string $storeId, int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        
        return response()->json([
            'product' => $product->load(['category', 'variants', 'options']),
        ]);
    }

    public function update(Request $request, string $storeId, int $productId): JsonResponse
    {
        $store = $request->attributes->get('store');
        $product = Product::findOrFail($productId);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $data = $request->except(['image', '_method']);

        // Convert boolean strings
        if (isset($data['track_inventory'])) {
            $data['track_inventory'] = filter_var($data['track_inventory'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($data['status'])) {
            // status is an enum, validate it
            if (!in_array($data['status'], ['draft', 'active', 'archived'])) {
                $data['status'] = 'active';
            }
        }
        if (isset($data['is_featured'])) {
            $data['is_featured'] = filter_var($data['is_featured'], FILTER_VALIDATE_BOOLEAN);
        }

        // Handle category_id
        if (isset($data['category_id']) && $data['category_id'] === '') {
            $data['category_id'] = null;
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($product->image) {
                $oldPath = str_replace('/storage/', '', $product->image);
                Storage::disk('public')->delete($oldPath);
            }
            $imagePath = $request->file('image')->store('products/' . $store->id, 'public');
            $data['image'] = '/storage/' . $imagePath;
        }

        $product->update($data);

        return response()->json([
            'message' => 'Product updated',
            'product' => $product->fresh(['category', 'variants']),
        ]);
    }

    public function destroy(Request $request, string $storeId, int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $product->delete();

        return response()->json(['message' => 'Product deleted']);
    }

    public function storeVariant(Request $request, string $storeId, int $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'sku' => 'nullable|string|max:100',
        ]);

        $data = $request->only(['name', 'sku', 'barcode', 'price', 'compare_price', 'cost_price', 'stock_quantity', 'image', 'is_active', 'sort_order']);
        $data['product_id'] = $product->id;
        
        // Map 'options' to 'option_values' if sent
        if ($request->has('options')) {
            $data['option_values'] = $request->options;
        } elseif ($request->has('option_values')) {
            $data['option_values'] = $request->option_values;
        }

        $variant = ProductVariant::create($data);

        return response()->json([
            'message' => 'Variant created',
            'variant' => $variant,
        ], 201);
    }

    public function updateVariant(Request $request, string $storeId, int $productId, int $variantId): JsonResponse
    {
        $variant = ProductVariant::findOrFail($variantId);
        $variant->update($request->all());

        return response()->json([
            'message' => 'Variant updated',
            'variant' => $variant->fresh(),
        ]);
    }

    public function destroyVariant(Request $request, string $storeId, int $productId, int $variantId): JsonResponse
    {
        $variant = ProductVariant::findOrFail($variantId);
        $variant->delete();

        return response()->json(['message' => 'Variant deleted']);
    }
}
