<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $banners = Banner::orderBy('sort_order')
            ->get();

        return response()->json($banners);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'image' => 'required|string',
            'link' => 'nullable|string|max:500',
            'position' => 'nullable|in:hero,sidebar,footer',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $banner = Banner::create($request->all());

        return response()->json([
            'message' => 'Banner created',
            'banner' => $banner,
        ], 201);
    }

    public function show(Request $request, Banner $banner): JsonResponse
    {
        return response()->json($banner);
    }

    public function update(Request $request, Banner $banner): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'image' => 'sometimes|string',
            'link' => 'nullable|string|max:500',
            'position' => 'nullable|in:hero,sidebar,footer',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $banner->update($request->all());

        return response()->json([
            'message' => 'Banner updated',
            'banner' => $banner->fresh(),
        ]);
    }

    public function destroy(Request $request, Banner $banner): JsonResponse
    {
        $banner->delete();

        return response()->json([
            'message' => 'Banner deleted',
        ]);
    }
}
