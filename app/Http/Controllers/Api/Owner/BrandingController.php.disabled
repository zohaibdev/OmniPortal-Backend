<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreBrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BrandingController extends Controller
{
    public function __construct(
        private StoreBrandingService $brandingService
    ) {}

    /**
     * Get store branding configuration
     */
    public function show(Request $request, Store $store): JsonResponse
    {
        $branding = $this->brandingService->getBranding($store);
        $customCss = $this->brandingService->getCustomCss($store);

        return response()->json([
            'success' => true,
            'data' => [
                'branding' => $branding,
                'custom_css' => $customCss,
            ],
        ]);
    }

    /**
     * Update store branding configuration
     */
    public function update(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'colors' => 'sometimes|array',
            'colors.primary' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.primary_hover' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.secondary' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.secondary_hover' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.accent' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.background' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.surface' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.text_primary' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.text_secondary' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'colors.border' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'fonts' => 'sometimes|array',
            'fonts.heading' => 'sometimes|string|max:100',
            'fonts.body' => 'sometimes|string|max:100',
            'layout' => 'sometimes|array',
            'layout.header_style' => 'sometimes|string|in:default,centered,minimal',
            'layout.footer_style' => 'sometimes|string|in:default,minimal,expanded',
            'layout.menu_style' => 'sometimes|string|in:grid,list,cards',
            'layout.product_card_style' => 'sometimes|string|in:default,minimal,detailed',
            'social' => 'sometimes|array',
            'social.facebook' => 'sometimes|nullable|url',
            'social.instagram' => 'sometimes|nullable|url',
            'social.twitter' => 'sometimes|nullable|url',
            'social.tiktok' => 'sometimes|nullable|url',
            'social.youtube' => 'sometimes|nullable|url',
            'seo' => 'sometimes|array',
            'seo.meta_title' => 'sometimes|string|max:70',
            'seo.meta_description' => 'sometimes|string|max:160',
            'seo.keywords' => 'sometimes|string|max:255',
            'custom_css_enabled' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $branding = $this->brandingService->updateBranding($store, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Branding updated successfully',
            'data' => ['branding' => $branding],
        ]);
    }

    /**
     * Upload store logo
     */
    public function uploadLogo(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'type' => 'sometimes|string|in:logo,logo_dark',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $type = $request->input('type', 'logo');
        $path = $this->brandingService->uploadLogo($store, $request->file('logo'), $type);

        if (!$path) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload logo',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logo uploaded successfully',
            'data' => [
                'path' => $path,
                'type' => $type,
            ],
        ]);
    }

    /**
     * Upload favicon
     */
    public function uploadFavicon(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'favicon' => 'required|image|mimes:png,ico,svg|max:512',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = $this->brandingService->uploadFavicon($store, $request->file('favicon'));

        if (!$path) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload favicon',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Favicon uploaded successfully',
            'data' => ['path' => $path],
        ]);
    }

    /**
     * Upload OG image
     */
    public function uploadOgImage(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'og_image' => 'required|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = $this->brandingService->uploadLogo($store, $request->file('og_image'), 'og_image');

        if (!$path) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload OG image',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OG image uploaded successfully',
            'data' => ['path' => $path],
        ]);
    }

    /**
     * Update custom CSS
     */
    public function updateCustomCss(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'css' => 'required|string|max:50000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $success = $this->brandingService->updateCustomCss($store, $request->input('css'));

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update custom CSS',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Custom CSS updated successfully',
        ]);
    }

    /**
     * Reset branding to defaults
     */
    public function reset(Request $request, Store $store): JsonResponse
    {
        // Recreate store folder with defaults
        $this->brandingService->deleteStoreFolder($store);
        $this->brandingService->createStoreFolder($store);

        $branding = $this->brandingService->getBranding($store);

        return response()->json([
            'success' => true,
            'message' => 'Branding reset to defaults',
            'data' => ['branding' => $branding],
        ]);
    }
}
