<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class StoreBrandingService
{
    /**
     * Base path for store assets in storefront
     */
    protected string $storefrontPath;

    /**
     * Base path for store uploads in backend storage
     */
    protected string $storagePath;

    public function __construct()
    {
        $this->storefrontPath = base_path('../storefront/public/stores');
        $this->storagePath = storage_path('app/public/stores');
    }

    /**
     * Create store folder structure when a new store is created
     */
    public function createStoreFolder(Store $store): bool
    {
        try {
            $storeFolderName = $this->getStoreFolderName($store);
            
            // Create storefront public folder structure
            $storefrontStorePath = $this->storefrontPath . '/' . $storeFolderName;
            
            // Create main directories
            $directories = [
                $storefrontStorePath,
                $storefrontStorePath . '/assets',
                $storefrontStorePath . '/assets/images',
                $storefrontStorePath . '/assets/icons',
                $storefrontStorePath . '/css',
            ];

            foreach ($directories as $dir) {
                if (!File::exists($dir)) {
                    File::makeDirectory($dir, 0755, true);
                }
            }

            // Create default branding.json config file
            $defaultBranding = $this->getDefaultBranding($store);
            File::put(
                $storefrontStorePath . '/branding.json',
                json_encode($defaultBranding, JSON_PRETTY_PRINT)
            );

            // Create default custom.css file
            $defaultCss = $this->generateDefaultCss($defaultBranding);
            File::put($storefrontStorePath . '/css/custom.css', $defaultCss);

            // Create backend storage folder for uploads
            $backendStorePath = $this->storagePath . '/' . $storeFolderName;
            if (!File::exists($backendStorePath)) {
                File::makeDirectory($backendStorePath, 0755, true);
                File::makeDirectory($backendStorePath . '/images', 0755, true);
            }

            Log::info('Store folder created successfully', [
                'store_id' => $store->id,
                'folder' => $storeFolderName,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create store folder', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete store folder when store is force deleted
     */
    public function deleteStoreFolder(Store $store): bool
    {
        try {
            $storeFolderName = $this->getStoreFolderName($store);
            
            // Delete storefront folder
            $storefrontStorePath = $this->storefrontPath . '/' . $storeFolderName;
            if (File::exists($storefrontStorePath)) {
                File::deleteDirectory($storefrontStorePath);
            }

            // Delete backend storage folder
            $backendStorePath = $this->storagePath . '/' . $storeFolderName;
            if (File::exists($backendStorePath)) {
                File::deleteDirectory($backendStorePath);
            }

            Log::info('Store folder deleted', [
                'store_id' => $store->id,
                'folder' => $storeFolderName,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete store folder', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get store branding configuration
     */
    public function getBranding(Store $store): array
    {
        $storeFolderName = $this->getStoreFolderName($store);
        $brandingFile = $this->storefrontPath . '/' . $storeFolderName . '/branding.json';

        if (File::exists($brandingFile)) {
            $content = File::get($brandingFile);
            return json_decode($content, true) ?? $this->getDefaultBranding($store);
        }

        return $this->getDefaultBranding($store);
    }

    /**
     * Update store branding configuration
     */
    public function updateBranding(Store $store, array $branding): array
    {
        $storeFolderName = $this->getStoreFolderName($store);
        $storefrontStorePath = $this->storefrontPath . '/' . $storeFolderName;

        // Ensure folder exists
        if (!File::exists($storefrontStorePath)) {
            $this->createStoreFolder($store);
        }

        // Merge with existing branding
        $currentBranding = $this->getBranding($store);
        $updatedBranding = array_merge($currentBranding, $branding);
        $updatedBranding['updated_at'] = now()->toIso8601String();

        // Save branding.json
        File::put(
            $storefrontStorePath . '/branding.json',
            json_encode($updatedBranding, JSON_PRETTY_PRINT)
        );

        // Regenerate custom.css
        $customCss = $this->generateDefaultCss($updatedBranding);
        File::put($storefrontStorePath . '/css/custom.css', $customCss);

        return $updatedBranding;
    }

    /**
     * Upload store logo
     */
    public function uploadLogo(Store $store, UploadedFile $file, string $type = 'logo'): ?string
    {
        try {
            $storeFolderName = $this->getStoreFolderName($store);
            $storefrontStorePath = $this->storefrontPath . '/' . $storeFolderName;

            // Ensure folder exists
            if (!File::exists($storefrontStorePath . '/assets/images')) {
                File::makeDirectory($storefrontStorePath . '/assets/images', 0755, true);
            }

            // Generate filename
            $extension = $file->getClientOriginalExtension();
            $filename = $type . '_' . time() . '.' . $extension;

            // Save to storefront public folder
            $file->move($storefrontStorePath . '/assets/images', $filename);

            // Return relative path for branding config
            $relativePath = '/stores/' . $storeFolderName . '/assets/images/' . $filename;

            // Update branding config
            $branding = $this->getBranding($store);
            $branding[$type] = $relativePath;
            $this->updateBranding($store, $branding);

            Log::info('Store logo uploaded', [
                'store_id' => $store->id,
                'type' => $type,
                'path' => $relativePath,
            ]);

            return $relativePath;
        } catch (\Exception $e) {
            Log::error('Failed to upload store logo', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Upload favicon
     */
    public function uploadFavicon(Store $store, UploadedFile $file): ?string
    {
        try {
            $storeFolderName = $this->getStoreFolderName($store);
            $storefrontStorePath = $this->storefrontPath . '/' . $storeFolderName;

            // Ensure folder exists
            if (!File::exists($storefrontStorePath . '/assets/icons')) {
                File::makeDirectory($storefrontStorePath . '/assets/icons', 0755, true);
            }

            // Save favicon
            $extension = $file->getClientOriginalExtension();
            $filename = 'favicon.' . $extension;
            $file->move($storefrontStorePath . '/assets/icons', $filename);

            $relativePath = '/stores/' . $storeFolderName . '/assets/icons/' . $filename;

            // Update branding config
            $branding = $this->getBranding($store);
            $branding['favicon'] = $relativePath;
            $this->updateBranding($store, $branding);

            return $relativePath;
        } catch (\Exception $e) {
            Log::error('Failed to upload favicon', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Upload banner/hero image
     */
    public function uploadBannerImage(Store $store, UploadedFile $file, int $index = 0): ?string
    {
        try {
            $storeFolderName = $this->getStoreFolderName($store);
            $storefrontStorePath = $this->storefrontPath . '/' . $storeFolderName;

            if (!File::exists($storefrontStorePath . '/assets/images')) {
                File::makeDirectory($storefrontStorePath . '/assets/images', 0755, true);
            }

            $extension = $file->getClientOriginalExtension();
            $filename = 'banner_' . $index . '_' . time() . '.' . $extension;
            $file->move($storefrontStorePath . '/assets/images', $filename);

            return '/stores/' . $storeFolderName . '/assets/images/' . $filename;
        } catch (\Exception $e) {
            Log::error('Failed to upload banner image', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get store folder name from store slug or ID
     */
    public function getStoreFolderName(Store $store): string
    {
        return $store->slug ?? 'store_' . $store->id;
    }

    /**
     * Get default branding configuration
     */
    protected function getDefaultBranding(Store $store): array
    {
        return [
            'store_id' => $store->id,
            'store_name' => $store->name,
            'store_slug' => $store->slug,
            
            // Logo & Images
            'logo' => null,
            'logo_dark' => null,
            'favicon' => null,
            'og_image' => null,
            
            // Theme Colors
            'colors' => [
                'primary' => '#16a34a',       // Green-600
                'primary_hover' => '#15803d', // Green-700
                'secondary' => '#f97316',     // Orange-500
                'secondary_hover' => '#ea580c', // Orange-600
                'accent' => '#0ea5e9',        // Sky-500
                'background' => '#ffffff',
                'surface' => '#f9fafb',       // Gray-50
                'text_primary' => '#111827',  // Gray-900
                'text_secondary' => '#6b7280', // Gray-500
                'border' => '#e5e7eb',        // Gray-200
                'success' => '#22c55e',
                'warning' => '#f59e0b',
                'error' => '#ef4444',
                'info' => '#3b82f6',
            ],
            
            // Typography
            'fonts' => [
                'heading' => 'Inter',
                'body' => 'Inter',
            ],
            
            // Layout
            'layout' => [
                'header_style' => 'default',   // default, centered, minimal
                'footer_style' => 'default',   // default, minimal, expanded
                'menu_style' => 'grid',        // grid, list, cards
                'product_card_style' => 'default', // default, minimal, detailed
            ],
            
            // Social Links
            'social' => [
                'facebook' => null,
                'instagram' => null,
                'twitter' => null,
                'tiktok' => null,
                'youtube' => null,
            ],
            
            // SEO
            'seo' => [
                'meta_title' => $store->name,
                'meta_description' => $store->description ?? '',
                'keywords' => '',
            ],
            
            // Custom CSS enabled
            'custom_css_enabled' => true,
            
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate CSS from branding configuration
     */
    protected function generateDefaultCss(array $branding): string
    {
        $colors = $branding['colors'] ?? [];
        $fonts = $branding['fonts'] ?? [];

        $css = "/**\n * Custom CSS for {$branding['store_name']}\n * Auto-generated from branding settings\n * Last updated: " . now()->toDateTimeString() . "\n */\n\n";

        // CSS Variables
        $css .= ":root {\n";
        $css .= "  /* Primary Colors */\n";
        $css .= "  --color-primary: " . ($colors['primary'] ?? '#16a34a') . ";\n";
        $css .= "  --color-primary-hover: " . ($colors['primary_hover'] ?? '#15803d') . ";\n";
        $css .= "  --color-secondary: " . ($colors['secondary'] ?? '#f97316') . ";\n";
        $css .= "  --color-secondary-hover: " . ($colors['secondary_hover'] ?? '#ea580c') . ";\n";
        $css .= "  --color-accent: " . ($colors['accent'] ?? '#0ea5e9') . ";\n";
        $css .= "\n  /* Background & Surface */\n";
        $css .= "  --color-background: " . ($colors['background'] ?? '#ffffff') . ";\n";
        $css .= "  --color-surface: " . ($colors['surface'] ?? '#f9fafb') . ";\n";
        $css .= "\n  /* Text Colors */\n";
        $css .= "  --color-text-primary: " . ($colors['text_primary'] ?? '#111827') . ";\n";
        $css .= "  --color-text-secondary: " . ($colors['text_secondary'] ?? '#6b7280') . ";\n";
        $css .= "  --color-border: " . ($colors['border'] ?? '#e5e7eb') . ";\n";
        $css .= "\n  /* Status Colors */\n";
        $css .= "  --color-success: " . ($colors['success'] ?? '#22c55e') . ";\n";
        $css .= "  --color-warning: " . ($colors['warning'] ?? '#f59e0b') . ";\n";
        $css .= "  --color-error: " . ($colors['error'] ?? '#ef4444') . ";\n";
        $css .= "  --color-info: " . ($colors['info'] ?? '#3b82f6') . ";\n";
        $css .= "\n  /* Typography */\n";
        $css .= "  --font-heading: '" . ($fonts['heading'] ?? 'Inter') . "', sans-serif;\n";
        $css .= "  --font-body: '" . ($fonts['body'] ?? 'Inter') . "', sans-serif;\n";
        $css .= "}\n\n";

        // Apply to elements
        $css .= "/* Apply theme colors */\n";
        $css .= "body {\n";
        $css .= "  font-family: var(--font-body);\n";
        $css .= "  background-color: var(--color-background);\n";
        $css .= "  color: var(--color-text-primary);\n";
        $css .= "}\n\n";

        $css .= "h1, h2, h3, h4, h5, h6 {\n";
        $css .= "  font-family: var(--font-heading);\n";
        $css .= "}\n\n";

        $css .= "/* Primary buttons */\n";
        $css .= ".btn-primary, .bg-primary-600 {\n";
        $css .= "  background-color: var(--color-primary) !important;\n";
        $css .= "}\n\n";

        $css .= ".btn-primary:hover, .hover\\:bg-primary-700:hover {\n";
        $css .= "  background-color: var(--color-primary-hover) !important;\n";
        $css .= "}\n\n";

        $css .= ".text-primary-600 {\n";
        $css .= "  color: var(--color-primary) !important;\n";
        $css .= "}\n\n";

        $css .= "/* Custom styles - Add your own below */\n";

        return $css;
    }

    /**
     * Get custom CSS content
     */
    public function getCustomCss(Store $store): string
    {
        $storeFolderName = $this->getStoreFolderName($store);
        $cssFile = $this->storefrontPath . '/' . $storeFolderName . '/css/custom.css';

        if (File::exists($cssFile)) {
            return File::get($cssFile);
        }

        return '';
    }

    /**
     * Update custom CSS
     */
    public function updateCustomCss(Store $store, string $css): bool
    {
        try {
            $storeFolderName = $this->getStoreFolderName($store);
            $storefrontStorePath = $this->storefrontPath . '/' . $storeFolderName;

            if (!File::exists($storefrontStorePath . '/css')) {
                File::makeDirectory($storefrontStorePath . '/css', 0755, true);
            }

            File::put($storefrontStorePath . '/css/custom.css', $css);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update custom CSS', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
