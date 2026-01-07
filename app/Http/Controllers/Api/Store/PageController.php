<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pages = Page::orderBy('title')
            ->get();

        return response()->json(['pages' => $pages]);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'is_published' => 'boolean',
        ]);

        $page = Page::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'content' => $request->content,
            'is_published' => $request->is_published ?? false,
        ]);

        return response()->json([
            'message' => 'Page created',
            'page' => $page,
        ], 201);
    }

    public function show(Request $request, string $storeId, int $pageId): JsonResponse
    {
        $page = Page::findOrFail($pageId);
        return response()->json(['page' => $page]);
    }

    public function update(Request $request, string $storeId, int $pageId): JsonResponse
    {
        $page = Page::findOrFail($pageId);
        $page->update([
            ...$request->only(['title', 'content', 'is_published']),
            'slug' => $request->title ? Str::slug($request->title) : $page->slug,
        ]);

        return response()->json([
            'message' => 'Page updated',
            'page' => $page->fresh(),
        ]);
    }

    public function destroy(Request $request, string $storeId, int $pageId): JsonResponse
    {
        $page = Page::findOrFail($pageId);
        $page->delete();

        return response()->json(['message' => 'Page deleted']);
    }
}
