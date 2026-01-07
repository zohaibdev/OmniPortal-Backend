<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['categories' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'parent_id' => 'nullable|exists:tenant.categories,id',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $slug = $request->slug ?? \Illuminate\Support\Str::slug($request->name);
        
        // Ensure unique slug (tenant DB is already scoped to this store)
        $originalSlug = $slug;
        $counter = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $category = Category::create([
            'slug' => $slug,
            ...$request->except('slug'),
        ]);

        return response()->json([
            'message' => 'Category created',
            'category' => $category,
        ], 201);
    }

    public function show(Request $request, string $storeId, int $categoryId): JsonResponse
    {
        $category = Category::findOrFail($categoryId);
        return response()->json(['category' => $category]);
    }

    public function update(Request $request, string $storeId, int $categoryId): JsonResponse
    {
        $category = Category::findOrFail($categoryId);
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $category->update($request->all());

        return response()->json([
            'message' => 'Category updated',
            'category' => $category->fresh(),
        ]);
    }

    public function destroy(Request $request, string $storeId, int $categoryId): JsonResponse
    {
        $category = Category::findOrFail($categoryId);
        $category->delete();

        return response()->json(['message' => 'Category deleted']);
    }
}
