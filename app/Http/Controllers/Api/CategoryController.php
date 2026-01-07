<?php

namespace App\Http\Controllers\Api;

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

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'parent_id' => 'nullable|exists:tenant.categories,id',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        // Ensure slug is unique (tenant DB is already scoped to this store)
        $slug = $request->slug;
        $count = 0;
        while (Category::where('slug', $slug)->exists()) {
            $count++;
            $slug = $request->slug . '-' . $count;
        }

        $category = Category::create([
            ...$request->except('slug'),
            'slug' => $slug,
        ]);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category,
        ], 201);
    }

    public function show(Request $request, Category $category): JsonResponse
    {
        $category->load(['products', 'children']);

        return response()->json($category);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'parent_id' => 'nullable|exists:tenant.categories,id',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        if ($request->has('slug') && $request->slug !== $category->slug) {
            $slug = $request->slug;
            $count = 0;
            while (Category::where('slug', $slug)
                ->where('id', '!=', $category->id)
                ->exists()) {
                $count++;
                $slug = $request->slug . '-' . $count;
            }
            $request->merge(['slug' => $slug]);
        }

        $category->update($request->all());

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category->fresh(),
        ]);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        // Move products to uncategorized or delete
        $category->products()->update(['category_id' => null]);
        $category->children()->update(['parent_id' => null]);

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:tenant.categories,id',
            'categories.*.sort_order' => 'required|integer',
        ]);

        foreach ($request->categories as $item) {
            Category::where('id', $item['id'])
                ->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json([
            'message' => 'Categories reordered successfully',
        ]);
    }
}
