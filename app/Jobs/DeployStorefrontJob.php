<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\StoreDeploymentService;
use App\Services\ThemeService;
use App\Services\ForgeApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeployStorefrontJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;
    public int $timeout = 120;

    public function __construct(
        public Store $store,
        public bool $fullRebuild = false,
        public bool $triggerForgeDeployment = false
    ) {}

    public function handle(
        StoreDeploymentService $deploymentService,
        ThemeService $themeService,
        ForgeApiService $forgeService
    ): void {
        Log::info('Starting storefront deployment', [
            'store_id' => $this->store->id,
            'full_rebuild' => $this->fullRebuild,
        ]);

        try {
            if ($this->fullRebuild) {
                // Remove and redeploy everything
                $deploymentService->rebuildStorefront($this->store);
            } else {
                // Deploy only if not already deployed
                if (!$deploymentService->isDeployed($this->store)) {
                    $deploymentService->deployStorefront($this->store);
                }
            }

            // Deploy theme and save CSS
            $themeService->deployThemeToStore($this->store);
            $themeService->saveThemeCss($this->store);

            // Update store config
            $deploymentService->updateStoreConfig($this->store);

            // Trigger Forge deployment if in production
            if ($this->triggerForgeDeployment && $forgeService->isConfigured() && $this->store->forge_site_id) {
                Log::info('Triggering Forge deployment', ['store_id' => $this->store->id]);
                $forgeService->deploy($this->store);
            }

            // Update last deployed timestamp
            $this->store->update(['last_deployed_at' => now()]);

            Log::info('Storefront deployment completed', [
                'store_id' => $this->store->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Storefront deployment failed', [
                'store_id' => $this->store->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Storefront deployment job failed permanently', [
            'store_id' => $this->store->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
