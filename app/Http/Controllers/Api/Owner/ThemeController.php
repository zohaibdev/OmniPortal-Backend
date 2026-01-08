<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\ThemeService;
use App\Jobs\DeployStorefrontJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ThemeController extends Controller
{
    public function __construct(
        private ThemeService $themeService
    ) {}

    /**
     * Get available themes
     */
    public function index(): JsonResponse
    {
        $themes = $this->themeService->getAvailableThemes();
        
        return response()->json([
            'success' => true,
            'data' => ['themes' => $themes],
        ]);
    }

    /**
     * Get store's current theme
     */
    public function show(Store $store): JsonResponse
    {
        $theme = $this->themeService->getStoreTheme($store);
        
        return response()->json([
            'success' => true,
            'data' => ['theme' => $theme],
        ]);
    }

    /**
     * Set store theme
     */
    public function update(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'theme' => 'required|string',
            'config' => 'sometimes|array',
            'deploy' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $this->themeService->setStoreTheme(
                $store,
                $request->input('theme'),
                $request->input('config', [])
            );

            // Optionally trigger deployment
            if ($request->boolean('deploy', false)) {
                DeployStorefrontJob::dispatch($store, true, app()->environment('production'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Theme updated successfully',
                'data' => ['theme' => $this->themeService->getStoreTheme($store)],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update theme configuration
     */
    public function updateConfig(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'colors' => 'sometimes|array',
            'colors.*' => 'sometimes|string',
            'fonts' => 'sometimes|array',
            'fonts.heading' => 'sometimes|string|max:100',
            'fonts.body' => 'sometimes|string|max:100',
            'layout' => 'sometimes|array',
            'layout.header_style' => 'sometimes|string|in:default,centered,minimal',
            'layout.footer_style' => 'sometimes|string|in:default,minimal,expanded',
            'layout.menu_style' => 'sometimes|string|in:grid,list,cards',
            'layout.product_card_style' => 'sometimes|string|in:default,minimal,detailed',
            'features' => 'sometimes|array',
            'deploy' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $config = $request->only(['colors', 'fonts', 'layout', 'features']);
        $newConfig = $this->themeService->updateThemeConfig($store, $config);

        // Regenerate CSS
        $this->themeService->saveThemeCss($store);

        // Optionally trigger deployment
        if ($request->boolean('deploy', false)) {
            DeployStorefrontJob::dispatch($store, false, app()->environment('production'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Theme configuration updated',
            'data' => ['config' => $newConfig],
        ]);
    }

    /**
     * Preview theme CSS
     */
    public function previewCss(Store $store): JsonResponse
    {
        $css = $this->themeService->generateThemeCss($store);
        
        return response()->json([
            'success' => true,
            'data' => ['css' => $css],
        ]);
    }

    /**
     * Reset theme to default
     */
    public function reset(Store $store): JsonResponse
    {
        $this->themeService->resetTheme($store);

        return response()->json([
            'success' => true,
            'message' => 'Theme reset to default',
            'data' => ['theme' => $this->themeService->getStoreTheme($store)],
        ]);
    }

    /**
     * Deploy theme changes
     */
    public function deploy(Store $store): JsonResponse
    {
        DeployStorefrontJob::dispatch($store, true, app()->environment('production'));

        return response()->json([
            'success' => true,
            'message' => 'Theme deployment queued',
        ]);
    }
}
