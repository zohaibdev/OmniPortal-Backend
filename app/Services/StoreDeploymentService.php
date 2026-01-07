<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class StoreDeploymentService
{
    protected string $storefrontBuildPath;
    protected string $storesDeployPath;

    public function __construct()
    {
        $this->storefrontBuildPath = config('deployment.storefront_build_path', base_path('../storefront/dist'));
        $this->storesDeployPath = config('deployment.stores_path', base_path('../stores'));
    }

    /**
     * Deploy store frontend from template build
     */
    public function deployStorefront(Store $store): bool
    {
        try {
            $storePath = $this->getStorePath($store);

            // Ensure stores directory exists
            if (!File::exists($this->storesDeployPath)) {
                File::makeDirectory($this->storesDeployPath, 0755, true);
            }

            // Check if storefront build exists
            if (!File::exists($this->storefrontBuildPath)) {
                Log::warning('Storefront build not found', [
                    'path' => $this->storefrontBuildPath,
                ]);
                // Try alternate paths
                $alternatePaths = [
                    base_path('../storefront/build'),
                    base_path('../storefront/dist'),
                    '/var/www/storefront/dist',
                    config('deployment.storefront_build_path'),
                ];
                
                foreach ($alternatePaths as $path) {
                    if ($path && File::exists($path)) {
                        $this->storefrontBuildPath = $path;
                        break;
                    }
                }
            }

            // Create store directory
            if (!File::exists($storePath)) {
                File::makeDirectory($storePath, 0755, true);
            }

            // Copy build files to store folder
            if (File::exists($this->storefrontBuildPath)) {
                File::copyDirectory($this->storefrontBuildPath, $storePath);
            }

            // Create store-specific config
            $this->createStoreConfig($store, $storePath);

            // Create branding folder structure
            $this->createBrandingStructure($store, $storePath);

            Log::info('Store frontend deployed', [
                'store_id' => $store->id,
                'store_slug' => $store->slug,
                'path' => $storePath,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to deploy store frontend', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Remove store deployment folder
     */
    public function removeStorefront(Store $store): bool
    {
        try {
            $storePath = $this->getStorePath($store);

            if (File::exists($storePath)) {
                File::deleteDirectory($storePath);
                Log::info('Store frontend removed', [
                    'store_id' => $store->id,
                    'path' => $storePath,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to remove store frontend', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Rebuild store frontend with custom branding
     */
    public function rebuildStorefront(Store $store): bool
    {
        try {
            // First remove existing deployment
            $this->removeStorefront($store);

            // Then redeploy
            return $this->deployStorefront($store);
        } catch (\Exception $e) {
            Log::error('Failed to rebuild store frontend', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update store config without full rebuild
     */
    public function updateStoreConfig(Store $store): bool
    {
        try {
            $storePath = $this->getStorePath($store);
            
            if (!File::exists($storePath)) {
                return $this->deployStorefront($store);
            }

            $this->createStoreConfig($store, $storePath);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update store config', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get store deployment path
     */
    public function getStorePath(Store $store): string
    {
        return $this->storesDeployPath . '/' . $store->slug;
    }

    /**
     * Check if store is deployed
     */
    public function isDeployed(Store $store): bool
    {
        return File::exists($this->getStorePath($store));
    }

    /**
     * Create store-specific configuration file
     */
    protected function createStoreConfig(Store $store, string $storePath): void
    {
        $config = [
            'store_id' => $store->id,
            'store_slug' => $store->slug,
            'store_name' => $store->name,
            'api_url' => config('app.url') . '/api',
            'subdomain' => $store->subdomain,
            'custom_domain' => $store->custom_domain,
            'currency' => $store->currency ?? 'USD',
            'timezone' => $store->timezone ?? 'UTC',
            'locale' => $store->locale ?? 'en',
            'branding_path' => '/branding',
            'generated_at' => now()->toISOString(),
        ];

        // Save as JSON config
        File::put(
            $storePath . '/store-config.json',
            json_encode($config, JSON_PRETTY_PRINT)
        );

        // Also create an env.js for runtime config
        $envJs = "window.STORE_CONFIG = " . json_encode($config) . ";";
        File::put($storePath . '/env.js', $envJs);
    }

    /**
     * Create branding folder structure
     */
    protected function createBrandingStructure(Store $store, string $storePath): void
    {
        $brandingPath = $storePath . '/branding';
        
        $directories = [
            $brandingPath,
            $brandingPath . '/images',
            $brandingPath . '/icons',
            $brandingPath . '/css',
        ];

        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }

        // Create default branding config
        $defaultBranding = [
            'store_id' => $store->id,
            'store_name' => $store->name,
            'store_slug' => $store->slug,
            'logo' => null,
            'logo_dark' => null,
            'favicon' => null,
            'colors' => [
                'primary' => '#10B981',
                'primary_hover' => '#059669',
                'secondary' => '#6366F1',
                'secondary_hover' => '#4F46E5',
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
            ],
        ];

        File::put(
            $brandingPath . '/branding.json',
            json_encode($defaultBranding, JSON_PRETTY_PRINT)
        );

        // Create default custom.css
        $defaultCss = ":root {\n  --color-primary: #10B981;\n  --color-primary-hover: #059669;\n}\n";
        File::put($brandingPath . '/css/custom.css', $defaultCss);
    }

    /**
     * Sync branding files from backend storage to store deployment
     */
    public function syncBrandingFiles(Store $store): bool
    {
        try {
            $storePath = $this->getStorePath($store);
            $storagePath = storage_path('app/public/stores/' . $store->slug);

            if (!File::exists($storePath)) {
                Log::warning('Store deployment not found for branding sync', [
                    'store_id' => $store->id,
                ]);
                return false;
            }

            if (File::exists($storagePath)) {
                // Copy images
                $sourceImages = $storagePath . '/images';
                $destImages = $storePath . '/branding/images';
                
                if (File::exists($sourceImages)) {
                    File::copyDirectory($sourceImages, $destImages);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to sync branding files', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get deployment status for store
     */
    public function getDeploymentStatus(Store $store): array
    {
        $storePath = $this->getStorePath($store);
        
        return [
            'deployed' => File::exists($storePath),
            'path' => $storePath,
            'config_exists' => File::exists($storePath . '/store-config.json'),
            'branding_exists' => File::exists($storePath . '/branding'),
            'last_modified' => File::exists($storePath) 
                ? date('Y-m-d H:i:s', File::lastModified($storePath))
                : null,
        ];
    }
}
