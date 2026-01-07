<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CmsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pages = CmsPage::orderBy('title')
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

        $page = CmsPage::create([
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

    public function show(Request $request, string $storeId, CmsPage $page): JsonResponse
    {
        return response()->json(['page' => $page]);
    }

    public function update(Request $request, string $storeId, CmsPage $page): JsonResponse
    {
        $page->update([
            ...$request->only(['title', 'content', 'is_published']),
            'slug' => $request->title ? Str::slug($request->title) : $page->slug,
        ]);

        return response()->json([
            'message' => 'Page updated',
            'page' => $page->fresh(),
        ]);
    }

    public function destroy(Request $request, string $storeId, CmsPage $page): JsonResponse
    {
        $page->delete();

        return response()->json(['message' => 'Page deleted']);
    }
}
