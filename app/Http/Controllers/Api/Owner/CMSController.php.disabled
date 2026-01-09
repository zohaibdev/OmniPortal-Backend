<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CMSController extends Controller
{
    /**
     * Get CMS overview (pages, banners, menus)
     */
    public function overview(Store $store): JsonResponse
    {
        $data = [
            'pages' => [],
            'banners' => [],
            'menu_items' => [],
        ];

        if ($store->database_name) {
            try {
                app(\App\Services\TenantDatabaseService::class)->configureTenantConnection($store);
                
                $data['pages'] = DB::connection('tenant')
                    ->table('pages')
                    ->select(['id', 'title', 'slug', 'status', 'is_published', 'created_at'])
                    ->orderBy('sort_order')
                    ->get();

                $data['banners'] = DB::connection('tenant')
                    ->table('banners')
                    ->select(['id', 'title', 'image', 'is_active', 'position', 'sort_order'])
                    ->orderBy('sort_order')
                    ->get();

                // Get categories as menu items
                $data['menu_items'] = DB::connection('tenant')
                    ->table('categories')
                    ->where('is_active', true)
                    ->select(['id', 'name', 'slug', 'parent_id', 'sort_order', 'image'])
                    ->orderBy('sort_order')
                    ->get();
            } catch (\Exception $e) {
                // Database might not exist yet
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get navigation menu configuration
     */
    public function getMenu(Store $store): JsonResponse
    {
        $menuConfig = $store->settings['menu'] ?? $this->getDefaultMenuConfig();
        
        $menuItems = [];
        if ($store->database_name) {
            try {
                app(\App\Services\TenantDatabaseService::class)->configureTenantConnection($store);
                
                // Get pages for menu
                $pages = DB::connection('tenant')
                    ->table('pages')
                    ->where('is_published', true)
                    ->where('show_in_menu', true)
                    ->select(['id', 'title', 'slug', 'sort_order'])
                    ->orderBy('sort_order')
                    ->get();

                // Get categories for menu
                $categories = DB::connection('tenant')
                    ->table('categories')
                    ->where('is_active', true)
                    ->whereNull('parent_id')
                    ->select(['id', 'name', 'slug', 'sort_order'])
                    ->orderBy('sort_order')
                    ->get();

                $menuItems = [
                    'pages' => $pages,
                    'categories' => $categories,
                ];
            } catch (\Exception $e) {
                // Database might not exist yet
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'config' => $menuConfig,
                'items' => $menuItems,
            ],
        ]);
    }

    /**
     * Update navigation menu configuration
     */
    public function updateMenu(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array',
            'items.*.id' => 'required|string',
            'items.*.label' => 'required|string|max:100',
            'items.*.type' => 'required|string|in:page,category,link,dropdown',
            'items.*.url' => 'nullable|string',
            'items.*.target' => 'sometimes|string|in:_self,_blank',
            'items.*.children' => 'sometimes|array',
            'style' => 'sometimes|string|in:horizontal,vertical,mega',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $settings = $store->settings ?? [];
        $settings['menu'] = [
            'items' => $request->input('items'),
            'style' => $request->input('style', 'horizontal'),
            'updated_at' => now()->toIso8601String(),
        ];
        
        $store->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'Menu updated successfully',
            'data' => ['menu' => $settings['menu']],
        ]);
    }

    /**
     * Get footer configuration
     */
    public function getFooter(Store $store): JsonResponse
    {
        $footerConfig = $store->settings['footer'] ?? $this->getDefaultFooterConfig($store);

        return response()->json([
            'success' => true,
            'data' => ['footer' => $footerConfig],
        ]);
    }

    /**
     * Update footer configuration
     */
    public function updateFooter(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'columns' => 'sometimes|array',
            'columns.*.title' => 'sometimes|string|max:100',
            'columns.*.links' => 'sometimes|array',
            'copyright' => 'sometimes|string|max:500',
            'show_social' => 'sometimes|boolean',
            'show_payment_icons' => 'sometimes|boolean',
            'custom_html' => 'sometimes|nullable|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $settings = $store->settings ?? [];
        $settings['footer'] = array_merge(
            $settings['footer'] ?? $this->getDefaultFooterConfig($store),
            $request->only(['columns', 'copyright', 'show_social', 'show_payment_icons', 'custom_html'])
        );
        $settings['footer']['updated_at'] = now()->toIso8601String();
        
        $store->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'Footer updated successfully',
            'data' => ['footer' => $settings['footer']],
        ]);
    }

    /**
     * Get homepage configuration
     */
    public function getHomepage(Store $store): JsonResponse
    {
        $homepageConfig = $store->settings['homepage'] ?? $this->getDefaultHomepageConfig();

        return response()->json([
            'success' => true,
            'data' => ['homepage' => $homepageConfig],
        ]);
    }

    /**
     * Update homepage configuration
     */
    public function updateHomepage(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sections' => 'required|array',
            'sections.*.type' => 'required|string|in:hero,featured_products,categories,testimonials,about,cta,custom',
            'sections.*.enabled' => 'required|boolean',
            'sections.*.order' => 'required|integer',
            'sections.*.config' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $settings = $store->settings ?? [];
        $settings['homepage'] = [
            'sections' => $request->input('sections'),
            'updated_at' => now()->toIso8601String(),
        ];
        
        $store->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'Homepage updated successfully',
            'data' => ['homepage' => $settings['homepage']],
        ]);
    }

    /**
     * Get SEO settings
     */
    public function getSeo(Store $store): JsonResponse
    {
        $seoConfig = $store->settings['seo'] ?? $this->getDefaultSeoConfig($store);

        return response()->json([
            'success' => true,
            'data' => ['seo' => $seoConfig],
        ]);
    }

    /**
     * Update SEO settings
     */
    public function updateSeo(Request $request, Store $store): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'meta_title' => 'sometimes|string|max:70',
            'meta_description' => 'sometimes|string|max:160',
            'meta_keywords' => 'sometimes|string|max:255',
            'og_title' => 'sometimes|string|max:100',
            'og_description' => 'sometimes|string|max:200',
            'og_image' => 'sometimes|nullable|string',
            'twitter_card' => 'sometimes|string|in:summary,summary_large_image',
            'google_analytics_id' => 'sometimes|nullable|string|max:50',
            'facebook_pixel_id' => 'sometimes|nullable|string|max:50',
            'robots' => 'sometimes|string|in:index,noindex',
            'canonical_url' => 'sometimes|nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $settings = $store->settings ?? [];
        $settings['seo'] = array_merge(
            $settings['seo'] ?? $this->getDefaultSeoConfig($store),
            $request->all()
        );
        $settings['seo']['updated_at'] = now()->toIso8601String();
        
        $store->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'message' => 'SEO settings updated successfully',
            'data' => ['seo' => $settings['seo']],
        ]);
    }

    // Helper methods for default configs

    protected function getDefaultMenuConfig(): array
    {
        return [
            'items' => [
                ['id' => 'home', 'label' => 'Home', 'type' => 'link', 'url' => '/', 'target' => '_self'],
                ['id' => 'menu', 'label' => 'Menu', 'type' => 'link', 'url' => '/menu', 'target' => '_self'],
                ['id' => 'about', 'label' => 'About', 'type' => 'page', 'url' => '/about', 'target' => '_self'],
                ['id' => 'contact', 'label' => 'Contact', 'type' => 'page', 'url' => '/contact', 'target' => '_self'],
            ],
            'style' => 'horizontal',
        ];
    }

    protected function getDefaultFooterConfig(Store $store): array
    {
        return [
            'columns' => [
                [
                    'title' => 'Quick Links',
                    'links' => [
                        ['label' => 'Home', 'url' => '/'],
                        ['label' => 'Menu', 'url' => '/menu'],
                        ['label' => 'About Us', 'url' => '/about'],
                        ['label' => 'Contact', 'url' => '/contact'],
                    ],
                ],
                [
                    'title' => 'Contact Info',
                    'links' => [
                        ['label' => $store->email ?? 'email@example.com', 'url' => 'mailto:' . ($store->email ?? '')],
                        ['label' => $store->phone ?? '', 'url' => 'tel:' . ($store->phone ?? '')],
                    ],
                ],
            ],
            'copyright' => '© ' . date('Y') . ' ' . $store->name . '. All rights reserved.',
            'show_social' => true,
            'show_payment_icons' => true,
        ];
    }

    protected function getDefaultHomepageConfig(): array
    {
        return [
            'sections' => [
                ['type' => 'hero', 'enabled' => true, 'order' => 1, 'config' => []],
                ['type' => 'featured_products', 'enabled' => true, 'order' => 2, 'config' => ['limit' => 8]],
                ['type' => 'categories', 'enabled' => true, 'order' => 3, 'config' => []],
                ['type' => 'about', 'enabled' => false, 'order' => 4, 'config' => []],
                ['type' => 'testimonials', 'enabled' => false, 'order' => 5, 'config' => []],
                ['type' => 'cta', 'enabled' => true, 'order' => 6, 'config' => []],
            ],
        ];
    }

    protected function getDefaultSeoConfig(Store $store): array
    {
        return [
            'meta_title' => $store->name,
            'meta_description' => $store->description ?? 'Welcome to ' . $store->name,
            'meta_keywords' => '',
            'og_title' => $store->name,
            'og_description' => $store->description ?? '',
            'og_image' => $store->logo,
            'twitter_card' => 'summary_large_image',
            'google_analytics_id' => null,
            'facebook_pixel_id' => null,
            'robots' => 'index',
        ];
    }
}
