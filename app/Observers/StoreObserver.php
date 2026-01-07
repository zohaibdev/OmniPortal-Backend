<?php

namespace App\Observers;

use App\Models\Store;
use App\Services\IdEncryptionService;
use App\Services\StoreBrandingService;
use App\Services\TenantDatabaseService;
use Illuminate\Support\Facades\Log;

class StoreObserver
{
    public function __construct(
        private TenantDatabaseService $tenantService,
        private IdEncryptionService $encryptionService,
        private StoreBrandingService $brandingService
    ) {}

    /**
     * Handle the Store "created" event.
     */
    public function created(Store $store): void
    {
        // Generate encrypted ID
        if (empty($store->encrypted_id)) {
            $store->encrypted_id = $this->encryptionService->encodeWithType($store->id, 'store');
            $store->saveQuietly();
        }

        // Create store folder for branding/assets first (fast operation)
        try {
            $this->brandingService->createStoreFolder($store);
            
            Log::info('Store branding folder created', [
                'store_id' => $store->id,
                'slug' => $store->slug,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create store branding folder', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Auto-create tenant database if enabled
        // This runs migrations which can take 20-30 seconds
        if (config('tenant.auto_create_database', true)) {
            try {
                // Increase execution time limit for migrations
                set_time_limit(120);
                
                $this->tenantService->createTenantDatabase($store);
                
                Log::info('Tenant database created for store', [
                    'store_id' => $store->id,
                    'database' => $store->database_name,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create tenant database', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
                
                // Mark store as pending setup instead of failing
                $store->updateQuietly(['status' => 'pending']);
            }
        }
    }

    /**
     * Handle the Store "updated" event.
     */
    public function updated(Store $store): void
    {
        // Sync store name to tenant settings if database exists
        if ($store->database_name && $store->isDirty('name')) {
            try {
                $this->tenantService->withinTenant($store, function () use ($store) {
                    \DB::connection('tenant')
                        ->table('settings')
                        ->where('group', 'general')
                        ->where('key', 'store_name')
                        ->update(['value' => $store->name, 'updated_at' => now()]);
                });
            } catch (\Exception $e) {
                Log::warning('Failed to sync store name to tenant', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle the Store "deleted" event.
     */
    public function deleted(Store $store): void
    {
        // Soft delete - don't drop database yet
    }

    /**
     * Handle the Store "force deleted" event.
     */
    public function forceDeleted(Store $store): void
    {
        // Drop tenant database on force delete
        if ($store->database_name) {
            try {
                $this->tenantService->deleteTenantDatabase($store);
                
                Log::info('Tenant database deleted for store', [
                    'store_id' => $store->id,
                    'database' => $store->database_name,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to delete tenant database', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Delete store branding folder
        try {
            $this->brandingService->deleteStoreFolder($store);
            
            Log::info('Store branding folder deleted', [
                'store_id' => $store->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete store branding folder', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
