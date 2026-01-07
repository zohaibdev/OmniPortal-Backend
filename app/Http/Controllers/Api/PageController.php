<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pages = Page::orderBy('sort_order')
            ->get();

        return response()->json($pages);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $slug = $request->slug;
        $count = 0;
        // Tenant DB is already scoped to this store
        while (Page::where('slug', $slug)->exists()) {
            $count++;
            $slug = $request->slug . '-' . $count;
        }

        $page = Page::create([
            ...$request->except('slug'),
            'slug' => $slug,
        ]);

        return response()->json([
            'message' => 'Page created',
            'page' => $page,
        ], 201);
    }

    public function show(Request $request, Page $page): JsonResponse
    {
        return response()->json($page);
    }

    public function update(Request $request, Page $page): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255',
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_published' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $page->update($request->all());

        return response()->json([
            'message' => 'Page updated',
            'page' => $page->fresh(),
        ]);
    }

    public function destroy(Request $request, Page $page): JsonResponse
    {
        $page->delete();

        return response()->json([
            'message' => 'Page deleted',
        ]);
    }
}
