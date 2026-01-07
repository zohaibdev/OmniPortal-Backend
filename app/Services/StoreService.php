<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Owner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StoreService
{
    /**
     * Default trial days for new stores
     */
    public const TRIAL_DAYS = 7;

    protected ForgeApiService $forgeService;
    protected TenantDatabaseService $tenantService;
    protected StoreDeploymentService $deploymentService;
    protected StoreBrandingService $brandingService;

    public function __construct(
        ForgeApiService $forgeService,
        TenantDatabaseService $tenantService,
        StoreDeploymentService $deploymentService,
        StoreBrandingService $brandingService
    ) {
        $this->forgeService = $forgeService;
        $this->tenantService = $tenantService;
        $this->deploymentService = $deploymentService;
        $this->brandingService = $brandingService;
    }

    /**
     * Create a new store with trial period
     * Handles automatic slug generation
     */
    public function create(Owner $owner, array $data): Store
    {
        $data['owner_id'] = $owner->id;
        
        // Auto-generate slug from name with random suffix (admin doesn't enter manually)
        $data['slug'] = $this->generateUniqueSlug($data['name']);
        $data['subdomain'] = $data['slug']; // Subdomain matches slug
        
        $data['status'] = Store::STATUS_ACTIVE;
        $data['is_active'] = true;
        
        // Set trial period
        $data['trial_ends_at'] = now()->addDays(self::TRIAL_DAYS);
        $data['trial_used'] = true;

        // Create the store record (triggers observer for tenant DB and branding folder)
        return Store::create($data);
    }

    /**
     * Create store with full provisioning (Forge site + deployment)
     */
    public function createWithProvisioning(Owner $owner, array $data): Store
    {
        // Create store record (triggers observer for tenant DB and branding folder)
        $store = $this->create($owner, $data);

        // Create Forge site if configured
        if ($this->forgeService->isConfigured() && config('deployment.auto_create_forge_site', true)) {
            try {
                $this->forgeService->createSite($store);
            } catch (\Exception $e) {
                Log::error('Failed to create Forge site', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Deploy storefront files
        if (config('deployment.auto_deploy_storefront', true)) {
            $this->deploymentService->deployStorefront($store);
        }

        return $store;
    }

    /**
     * Generate unique slug from store name with random suffix
     * Format: store-name-abc123
     */
    protected function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        
        // Add random suffix for uniqueness
        $randomSuffix = Str::lower(Str::random(6));
        $slug = $baseSlug . '-' . $randomSuffix;
        
        // Ensure uniqueness
        while (Store::where('slug', $slug)->exists()) {
            $randomSuffix = Str::lower(Str::random(6));
            $slug = $baseSlug . '-' . $randomSuffix;
        }
        
        return $slug;
    }

    /**
     * Update store
     */
    public function update(Store $store, array $data): Store
    {
        // Don't allow slug changes via normal update
        unset($data['slug']);
        
        if (isset($data['subdomain']) && $data['subdomain'] !== $store->subdomain) {
            $data['subdomain'] = $this->ensureUniqueSubdomain($data['subdomain'], $store->id);
        }

        // Handle custom domain updates
        if (isset($data['custom_domain']) && $data['custom_domain'] !== $store->custom_domain) {
            $this->handleCustomDomainChange($store, $data['custom_domain']);
        }

        $store->update($data);
        
        // Update deployment config if store details changed
        if ($store->wasChanged(['name', 'currency', 'timezone', 'locale', 'custom_domain'])) {
            $this->deploymentService->updateStoreConfig($store);
        }

        return $store->fresh();
    }

    /**
     * Handle custom domain change
     */
    protected function handleCustomDomainChange(Store $store, ?string $newDomain): void
    {
        if (!$this->forgeService->isConfigured()) {
            return;
        }

        try {
            if ($store->custom_domain) {
                $this->forgeService->removeCustomDomain($store, $store->custom_domain);
            }
            if ($newDomain) {
                $this->forgeService->addCustomDomain($store, $newDomain);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update Forge custom domain', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete store completely (hard delete with cleanup)
     */
    public function delete(Store $store): bool
    {
        $storeId = $store->id;
        $storeName = $store->name;
        $databaseName = $store->database_name;
        
        try {
            Log::info('Starting permanent store deletion', [
                'store_id' => $storeId,
                'store_name' => $storeName,
                'database' => $databaseName,
            ]);

            // 1. Delete Forge site (if configured)
            if ($this->forgeService->isConfigured() && $store->forge_site_id) {
                try {
                    $this->forgeService->deleteSite($store);
                    Log::info('Forge site deleted', ['store_id' => $storeId]);
                } catch (\Exception $e) {
                    Log::warning('Failed to delete Forge site', [
                        'store_id' => $storeId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 2. Remove deployment folder (/stores/{slug}/)
            try {
                $this->deploymentService->removeStorefront($store);
                Log::info('Deployment folder deleted', ['store_id' => $storeId]);
            } catch (\Exception $e) {
                Log::warning('Failed to delete deployment folder', [
                    'store_id' => $storeId,
                    'error' => $e->getMessage(),
                ]);
            }

            // 3. Delete branding/storage folders
            try {
                $this->brandingService->deleteStoreFolder($store);
                Log::info('Branding folders deleted', ['store_id' => $storeId]);
            } catch (\Exception $e) {
                Log::warning('Failed to delete branding folders', [
                    'store_id' => $storeId,
                    'error' => $e->getMessage(),
                ]);
            }

            // 4. Drop tenant database
            if ($databaseName) {
                try {
                    $this->tenantService->deleteTenantDatabase($store);
                    Log::info('Tenant database dropped', [
                        'store_id' => $storeId,
                        'database' => $databaseName,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to drop tenant database', [
                        'store_id' => $storeId,
                        'database' => $databaseName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 5. Delete associated domains
            $store->domains()->delete();

            // 6. Force delete store record from database
            $store->forceDelete();

            Log::info('Store permanently deleted', [
                'store_id' => $storeId,
                'store_name' => $storeName,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete store completely', [
                'store_id' => $storeId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Soft delete store
     */
    public function softDelete(Store $store): bool
    {
        $store->update([
            'is_active' => false,
            'status' => Store::STATUS_CLOSED,
        ]);
        $store->delete();
        return true;
    }

    /**
     * Activate store
     */
    public function activate(Store $store): Store
    {
        $store->update([
            'status' => Store::STATUS_ACTIVE,
            'is_active' => true,
        ]);
        return $store;
    }

    /**
     * Suspend store
     */
    public function suspend(Store $store, ?string $reason = null): Store
    {
        $store->update([
            'status' => Store::STATUS_SUSPENDED,
        ]);
        return $store;
    }

    /**
     * Redeploy store frontend
     */
    public function redeploy(Store $store): bool
    {
        return $this->deploymentService->rebuildStorefront($store);
    }

    /**
     * Get store deployment status
     */
    public function getDeploymentStatus(Store $store): array
    {
        return $this->deploymentService->getDeploymentStatus($store);
    }

    /**
     * Get store statistics
     */
    public function getStatistics(Store $store): array
    {
        if (!$store->database_name) {
            return $this->getEmptyStatistics();
        }

        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        return $this->tenantService->withinTenant($store, function () use ($today, $thisMonth) {
            return [
                'total_products' => \DB::connection('tenant')->table('products')->count(),
                'active_products' => \DB::connection('tenant')->table('products')->where('is_active', true)->count(),
                'total_orders' => \DB::connection('tenant')->table('orders')->count(),
                'orders_today' => \DB::connection('tenant')->table('orders')->where('created_at', '>=', $today)->count(),
                'orders_this_month' => \DB::connection('tenant')->table('orders')->where('created_at', '>=', $thisMonth)->count(),
                'total_customers' => \DB::connection('tenant')->table('customers')->count(),
                'total_employees' => \DB::connection('tenant')->table('employees')->count(),
                'revenue_today' => (float) \DB::connection('tenant')->table('orders')
                    ->where('created_at', '>=', $today)
                    ->where('payment_status', 'paid')
                    ->sum('total'),
                'revenue_this_month' => (float) \DB::connection('tenant')->table('orders')
                    ->where('created_at', '>=', $thisMonth)
                    ->where('payment_status', 'paid')
                    ->sum('total'),
            ];
        });
    }

    protected function getEmptyStatistics(): array
    {
        return [
            'total_products' => 0,
            'active_products' => 0,
            'total_orders' => 0,
            'orders_today' => 0,
            'orders_this_month' => 0,
            'total_customers' => 0,
            'total_employees' => 0,
            'revenue_today' => 0,
            'revenue_this_month' => 0,
        ];
    }

    /**
     * Ensure unique slug
     */
    protected function ensureUniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $counter = 1;

        while (Store::where('slug', $slug)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }

    /**
     * Ensure unique subdomain
     */
    protected function ensureUniqueSubdomain(string $subdomain, ?int $excludeId = null): string
    {
        $originalSubdomain = $subdomain;
        $counter = 1;

        while (Store::where('subdomain', $subdomain)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->exists()) {
            $subdomain = $originalSubdomain . $counter++;
        }

        return $subdomain;
    }
}
