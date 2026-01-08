<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ThemeService
{
    /**
     * Path to central theme folder
     */
    protected string $themesPath;

    /**
     * Path to storefront build
     */
    protected string $storefrontBuildPath;

    /**
     * Available themes
     */
    protected array $availableThemes = [
        'default' => [
            'name' => 'Default',
            'description' => 'Clean and modern default theme',
            'preview' => '/themes/default/preview.png',
        ],
        'minimal' => [
            'name' => 'Minimal',
            'description' => 'Simple and minimalistic design',
            'preview' => '/themes/minimal/preview.png',
        ],
        'elegant' => [
            'name' => 'Elegant',
            'description' => 'Sophisticated and refined look',
            'preview' => '/themes/elegant/preview.png',
        ],
        'bold' => [
            'name' => 'Bold',
            'description' => 'Strong colors and typography',
            'preview' => '/themes/bold/preview.png',
        ],
        'classic' => [
            'name' => 'Classic',
            'description' => 'Timeless and traditional style',
            'preview' => '/themes/classic/preview.png',
        ],
    ];

    public function __construct()
    {
        // Production paths on Forge server
        $this->themesPath = env('THEMES_PATH', '/home/forge/storefront-themes');
        $this->storefrontBuildPath = config('deployment.storefront_build_path', base_path('../OmniPortal-Storefront/dist'));
        
        // Local development fallback
        if (!app()->environment('production') && !File::exists($this->themesPath)) {
            $this->themesPath = base_path('../storefront-themes');
        }
    }

    /**
     * Get available themes
     */
    public function getAvailableThemes(): array
    {
        return $this->availableThemes;
    }

    /**
     * Get theme details
     */
    public function getTheme(string $themeId): ?array
    {
        return $this->availableThemes[$themeId] ?? null;
    }

    /**
     * Get store's current theme
     */
    public function getStoreTheme(Store $store): array
    {
        $themeId = $store->theme ?? 'default';
        $theme = $this->availableThemes[$themeId] ?? $this->availableThemes['default'];
        
        return array_merge($theme, [
            'id' => $themeId,
            'config' => $store->theme_config ?? $this->getDefaultThemeConfig(),
        ]);
    }

    /**
     * Set store theme
     */
    public function setStoreTheme(Store $store, string $themeId, array $config = []): bool
    {
        if (!isset($this->availableThemes[$themeId])) {
            throw new \InvalidArgumentException("Theme '{$themeId}' not found");
        }

        $store->update([
            'theme' => $themeId,
            'theme_config' => array_merge($this->getDefaultThemeConfig(), $config),
        ]);

        return true;
    }

    /**
     * Update theme configuration for store
     */
    public function updateThemeConfig(Store $store, array $config): array
    {
        $currentConfig = $store->theme_config ?? $this->getDefaultThemeConfig();
        $newConfig = array_merge($currentConfig, $config);
        
        $store->update(['theme_config' => $newConfig]);
        
        return $newConfig;
    }

    /**
     * Deploy theme to store folder
     * Copies base React build + theme assets to store deployment folder
     */
    public function deployThemeToStore(Store $store): bool
    {
        try {
            $themeId = $store->theme ?? 'default';
            $storePath = $this->getStoreDeploymentPath($store);
            
            Log::info('Deploying theme to store', [
                'store_id' => $store->id,
                'theme' => $themeId,
                'path' => $storePath,
            ]);

            // Ensure store deployment path exists
            if (!File::exists($storePath)) {
                File::makeDirectory($storePath, 0755, true);
            }

            // Copy base React storefront build
            if (File::exists($this->storefrontBuildPath)) {
                File::copyDirectory($this->storefrontBuildPath, $storePath . '/public');
            }

            // Copy theme assets if they exist
            $themePath = $this->themesPath . '/' . $themeId;
            if (File::exists($themePath)) {
                // Copy theme CSS
                if (File::exists($themePath . '/css')) {
                    File::copyDirectory($themePath . '/css', $storePath . '/public/themes/' . $themeId);
                }
                
                // Copy theme components/overrides
                if (File::exists($themePath . '/assets')) {
                    File::copyDirectory($themePath . '/assets', $storePath . '/public/assets');
                }
            }

            // Create theme.json config file
            $this->createThemeConfigFile($store, $storePath);

            // Create storage directories
            $this->createStorageStructure($storePath);

            $store->update(['last_deployed_at' => now()]);

            Log::info('Theme deployed successfully', [
                'store_id' => $store->id,
                'theme' => $themeId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to deploy theme', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get store deployment path based on environment
     */
    public function getStoreDeploymentPath(Store $store): string
    {
        if (app()->environment('production')) {
            $baseDomain = config('services.forge.base_domain', 'time-luxe.com');
            return '/home/forge/' . $store->slug . '.' . $baseDomain;
        }
        
        return config('deployment.stores_path', base_path('../stores')) . '/' . $store->slug;
    }

    /**
     * Create theme config file in store deployment
     */
    protected function createThemeConfigFile(Store $store, string $storePath): void
    {
        $themeConfig = [
            'store_id' => $store->id,
            'store_slug' => $store->slug,
            'store_name' => $store->name,
            'theme' => $store->theme ?? 'default',
            'theme_config' => $store->theme_config ?? $this->getDefaultThemeConfig(),
            'api_url' => config('deployment.api_url', config('app.url')),
            'subdomain' => $store->subdomain,
            'custom_domain' => $store->custom_domain,
            'currency' => $store->currency ?? 'USD',
            'timezone' => $store->timezone ?? 'UTC',
            'locale' => $store->locale ?? 'en',
            'generated_at' => now()->toIso8601String(),
        ];

        File::put(
            $storePath . '/theme.json',
            json_encode($themeConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Create storage structure for store
     */
    protected function createStorageStructure(string $storePath): void
    {
        $directories = [
            $storePath . '/storage',
            $storePath . '/storage/uploads',
            $storePath . '/storage/uploads/images',
            $storePath . '/storage/uploads/documents',
            $storePath . '/storage/logs',
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }
    }

    /**
     * Get default theme configuration
     */
    public function getDefaultThemeConfig(): array
    {
        return [
            'colors' => [
                'primary' => '#4F46E5',
                'primary_hover' => '#4338CA',
                'secondary' => '#10B981',
                'secondary_hover' => '#059669',
                'accent' => '#F59E0B',
                'background' => '#FFFFFF',
                'surface' => '#F9FAFB',
                'text_primary' => '#111827',
                'text_secondary' => '#6B7280',
                'border' => '#E5E7EB',
            ],
            'fonts' => [
                'heading' => 'Inter',
                'body' => 'Inter',
            ],
            'layout' => [
                'header_style' => 'default',
                'footer_style' => 'default',
                'menu_style' => 'grid',
                'product_card_style' => 'default',
                'sidebar_position' => 'left',
                'container_width' => 'max-w-7xl',
            ],
            'features' => [
                'show_search' => true,
                'show_cart_icon' => true,
                'show_social_links' => true,
                'enable_dark_mode' => false,
                'sticky_header' => true,
            ],
        ];
    }

    /**
     * Generate custom CSS from theme config
     */
    public function generateThemeCss(Store $store): string
    {
        $config = $store->theme_config ?? $this->getDefaultThemeConfig();
        $colors = $config['colors'] ?? [];
        $fonts = $config['fonts'] ?? [];

        $css = ":root {\n";
        
        // Color variables
        foreach ($colors as $key => $value) {
            $varName = str_replace('_', '-', $key);
            $css .= "  --color-{$varName}: {$value};\n";
        }
        
        // Font variables
        if (!empty($fonts['heading'])) {
            $css .= "  --font-heading: '{$fonts['heading']}', sans-serif;\n";
        }
        if (!empty($fonts['body'])) {
            $css .= "  --font-body: '{$fonts['body']}', sans-serif;\n";
        }
        
        $css .= "}\n\n";
        
        // Body styles
        $css .= "body {\n";
        $css .= "  font-family: var(--font-body);\n";
        $css .= "  background-color: var(--color-background);\n";
        $css .= "  color: var(--color-text-primary);\n";
        $css .= "}\n\n";
        
        // Heading styles
        $css .= "h1, h2, h3, h4, h5, h6 {\n";
        $css .= "  font-family: var(--font-heading);\n";
        $css .= "}\n";

        return $css;
    }

    /**
     * Save custom CSS to store deployment
     */
    public function saveThemeCss(Store $store): bool
    {
        try {
            $storePath = $this->getStoreDeploymentPath($store);
            $css = $this->generateThemeCss($store);
            
            $cssDir = $storePath . '/public/css';
            if (!File::exists($cssDir)) {
                File::makeDirectory($cssDir, 0755, true);
            }
            
            File::put($cssDir . '/theme.css', $css);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to save theme CSS', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Reset store theme to default
     */
    public function resetTheme(Store $store): bool
    {
        $store->update([
            'theme' => 'default',
            'theme_config' => $this->getDefaultThemeConfig(),
        ]);

        return $this->deployThemeToStore($store);
    }
}
