<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->with(['products' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sort_order')
                    ->with('variants');
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['categories' => $categories]);
    }

    public function category(Request $request, Category $category): JsonResponse
    {
        if (!$category->is_active) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $products = Product::where('category_id', $category->id)
            ->where('is_active', true)
            ->with('variants')
            ->orderBy('sort_order')
            ->paginate(20);

        return response()->json([
            'category' => $category,
            'products' => $products,
        ]);
    }

    public function product(Request $request, Product $product): JsonResponse
    {
        if (!$product->is_active) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json([
            'product' => $product->load(['variants', 'addons', 'category']),
        ]);
    }
}
