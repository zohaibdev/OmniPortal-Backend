<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\ForgeApiService;
use App\Services\StoreDeploymentService;
use App\Services\StoreBrandingService;
use App\Services\TenantDatabaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteStoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 180; // 3 minutes

    protected int $storeId;
    protected string $storeSlug;
    protected ?string $databaseName;
    protected ?int $forgeSiteId;
    protected ?string $deploymentPath;

    public function __construct(Store $store)
    {
        // Store essential data since the model might be deleted
        $this->storeId = $store->id;
        $this->storeSlug = $store->slug;
        $this->databaseName = $store->database_name;
        $this->forgeSiteId = $store->forge_site_id;
        $this->deploymentPath = $store->deployment_path;
    }

    public function handle(
        ForgeApiService $forgeService,
        TenantDatabaseService $tenantService,
        StoreDeploymentService $deploymentService,
        StoreBrandingService $brandingService
    ): void {
        Log::info('Starting store deletion job', [
            'store_id' => $this->storeId,
            'store_slug' => $this->storeSlug,
        ]);

        $errors = [];

        // Step 1: Delete Forge site
        if ($this->forgeSiteId && $forgeService->isConfigured()) {
            try {
                $this->deleteForgeSite($forgeService);
            } catch (\Exception $e) {
                $errors[] = 'Forge site deletion: ' . $e->getMessage();
                Log::warning('Failed to delete Forge site', [
                    'store_id' => $this->storeId,
                    'forge_site_id' => $this->forgeSiteId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Step 2: Delete tenant database
        if ($this->databaseName) {
            try {
                $this->deleteDatabase($tenantService);
            } catch (\Exception $e) {
                $errors[] = 'Database deletion: ' . $e->getMessage();
                Log::warning('Failed to delete database', [
                    'store_id' => $this->storeId,
                    'database' => $this->databaseName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Step 3: Delete deployment folder
        try {
            $this->deleteDeploymentFolder($deploymentService);
        } catch (\Exception $e) {
            $errors[] = 'Deployment folder deletion: ' . $e->getMessage();
            Log::warning('Failed to delete deployment folder', [
                'store_id' => $this->storeId,
                'error' => $e->getMessage(),
            ]);
        }

        // Step 4: Delete branding folder
        try {
            $this->deleteBrandingFolder($brandingService);
        } catch (\Exception $e) {
            $errors[] = 'Branding folder deletion: ' . $e->getMessage();
            Log::warning('Failed to delete branding folder', [
                'store_id' => $this->storeId,
                'error' => $e->getMessage(),
            ]);
        }

        if (empty($errors)) {
            Log::info('Store deletion completed successfully', [
                'store_id' => $this->storeId,
            ]);
        } else {
            Log::warning('Store deletion completed with some errors', [
                'store_id' => $this->storeId,
                'errors' => $errors,
            ]);
        }
    }

    protected function deleteForgeSite(ForgeApiService $forgeService): void
    {
        Log::info('Deleting Forge site', [
            'store_id' => $this->storeId,
            'forge_site_id' => $this->forgeSiteId,
        ]);

        $forgeService->deleteSiteById($this->forgeSiteId);

        Log::info('Forge site deleted', [
            'store_id' => $this->storeId,
        ]);
    }

    protected function deleteDatabase(TenantDatabaseService $tenantService): void
    {
        Log::info('Deleting tenant database', [
            'store_id' => $this->storeId,
            'database' => $this->databaseName,
        ]);

        $tenantService->dropTenantDatabase($this->databaseName);

        Log::info('Tenant database deleted', [
            'store_id' => $this->storeId,
        ]);
    }

    protected function deleteDeploymentFolder(StoreDeploymentService $deploymentService): void
    {
        Log::info('Deleting deployment folder', [
            'store_id' => $this->storeId,
            'slug' => $this->storeSlug,
        ]);

        // Create a minimal store object for the service
        $store = new Store();
        $store->slug = $this->storeSlug;
        
        $deploymentService->removeStorefront($store);

        // Also try to remove production path if set
        if ($this->deploymentPath && \File::exists($this->deploymentPath)) {
            \File::deleteDirectory($this->deploymentPath);
        }

        Log::info('Deployment folder deleted', [
            'store_id' => $this->storeId,
        ]);
    }

    protected function deleteBrandingFolder(StoreBrandingService $brandingService): void
    {
        Log::info('Deleting branding folder', [
            'store_id' => $this->storeId,
            'slug' => $this->storeSlug,
        ]);

        // Create a minimal store object for the service
        $store = new Store();
        $store->id = $this->storeId;
        $store->slug = $this->storeSlug;
        
        $brandingService->deleteStoreFolder($store);

        Log::info('Branding folder deleted', [
            'store_id' => $this->storeId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Store deletion job failed permanently', [
            'store_id' => $this->storeId,
            'error' => $exception->getMessage(),
        ]);
    }
}
