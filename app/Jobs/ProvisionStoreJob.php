<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\ForgeApiService;
use App\Services\StoreDeploymentService;
use App\Services\TenantDatabaseService;
use App\Services\ThemeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionStoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 300; // 5 minutes

    public function __construct(
        public Store $store,
        public bool $createForgeSite = true,
        public bool $createDatabase = true,
        public bool $deployStorefront = true
    ) {}

    public function handle(
        ForgeApiService $forgeService,
        TenantDatabaseService $tenantService,
        StoreDeploymentService $deploymentService,
        ThemeService $themeService
    ): void {
        Log::info('Starting store provisioning job', [
            'store_id' => $this->store->id,
            'store_slug' => $this->store->slug,
        ]);

        try {
            // Step 1: Create Forge site (if enabled and configured)
            if ($this->createForgeSite && $forgeService->isConfigured()) {
                $this->provisionForgeSite($forgeService);
            }

            // Step 2: Create tenant database (if enabled and not already created)
            if ($this->createDatabase && !$this->store->database_name) {
                $this->provisionDatabase($tenantService);
            }

            // Step 3: Deploy storefront files (if enabled)
            if ($this->deployStorefront) {
                $this->deployStorefrontFiles($deploymentService, $themeService);
            }

            // Step 4: Install SSL certificate (if Forge site was created)
            if ($this->createForgeSite && $forgeService->isConfigured() && $this->store->forge_site_id) {
                $this->installSslCertificate($forgeService);
            }

            // Update store status
            $this->store->update([
                'status' => Store::STATUS_ACTIVE,
                'last_deployed_at' => now(),
            ]);

            Log::info('Store provisioning completed successfully', [
                'store_id' => $this->store->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Store provisioning failed', [
                'store_id' => $this->store->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->store->update([
                'status' => 'provisioning_failed',
                'meta' => array_merge($this->store->meta ?? [], [
                    'provisioning_error' => $e->getMessage(),
                    'provisioning_failed_at' => now()->toIso8601String(),
                ]),
            ]);

            throw $e;
        }
    }

    protected function provisionForgeSite(ForgeApiService $forgeService): void
    {
        Log::info('Creating Forge site', ['store_id' => $this->store->id]);

        $response = $forgeService->createSite($this->store);

        if (isset($response['site']['id'])) {
            $this->store->update([
                'forge_site_id' => $response['site']['id'],
                'forge_site_status' => $response['site']['status'] ?? 'installed',
                'forge_site_created_at' => now(),
                'deployment_path' => $this->getProductionDeploymentPath(),
            ]);

            // Set up deployment script
            $deployScript = $this->getDeploymentScript();
            $forgeService->updateDeploymentScript($this->store, $deployScript);
        }

        Log::info('Forge site created', [
            'store_id' => $this->store->id,
            'forge_site_id' => $response['site']['id'] ?? null,
        ]);
    }

    protected function provisionDatabase(TenantDatabaseService $tenantService): void
    {
        Log::info('Creating tenant database', ['store_id' => $this->store->id]);

        $tenantService->createTenantDatabase($this->store);

        Log::info('Tenant database created', [
            'store_id' => $this->store->id,
            'database' => $this->store->database_name,
        ]);
    }

    protected function deployStorefrontFiles(
        StoreDeploymentService $deploymentService,
        ThemeService $themeService
    ): void {
        Log::info('Deploying storefront files', ['store_id' => $this->store->id]);

        // Deploy base storefront
        $deploymentService->deployStorefront($this->store);

        // Deploy theme
        $themeService->deployThemeToStore($this->store);

        // Save theme CSS
        $themeService->saveThemeCss($this->store);

        Log::info('Storefront deployed', ['store_id' => $this->store->id]);
    }

    protected function installSslCertificate(ForgeApiService $forgeService): void
    {
        try {
            Log::info('Installing SSL certificate', ['store_id' => $this->store->id]);

            // Wait a bit for the site to be fully provisioned
            sleep(10);

            $forgeService->installSslCertificate($this->store);

            $this->store->update([
                'ssl_enabled' => true,
                'ssl_expires_at' => now()->addMonths(3), // Let's Encrypt typical expiry
            ]);

            Log::info('SSL certificate installed', ['store_id' => $this->store->id]);
        } catch (\Exception $e) {
            // SSL installation can fail - don't fail the whole job
            Log::warning('SSL installation failed, will retry later', [
                'store_id' => $this->store->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function getProductionDeploymentPath(): string
    {
        $baseDomain = config('services.forge.base_domain', 'time-luxe.com');
        return '/home/forge/' . $this->store->slug . '.' . $baseDomain;
    }

    protected function getDeploymentScript(): string
    {
        $baseDomain = config('services.forge.base_domain', 'time-luxe.com');
        $storePath = '/home/forge/' . $this->store->slug . '.' . $baseDomain;
        $storefrontBuild = config('deployment.storefront_build_path', '/home/forge/storefront/dist');
        $themesPath = env('THEMES_PATH', '/home/forge/storefront-themes');
        $theme = $this->store->theme ?? 'default';

        return <<<SCRIPT
cd {$storePath}

# Ensure directories exist
mkdir -p public
mkdir -p storage/uploads/images
mkdir -p storage/uploads/documents
mkdir -p storage/logs

# Copy base storefront build
if [ -d "{$storefrontBuild}" ]; then
    cp -r {$storefrontBuild}/* public/
fi

# Copy theme assets
if [ -d "{$themesPath}/{$theme}" ]; then
    mkdir -p public/themes/{$theme}
    cp -r {$themesPath}/{$theme}/* public/themes/{$theme}/
fi

# Set permissions
chown -R forge:forge {$storePath}
chmod -R 755 public
chmod -R 775 storage

echo "Deployment completed for {$this->store->slug}"
SCRIPT;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Store provisioning job failed permanently', [
            'store_id' => $this->store->id,
            'error' => $exception->getMessage(),
        ]);

        $this->store->update([
            'status' => 'provisioning_failed',
        ]);
    }
}
