<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\Store;
use App\Observers\StoreObserver;
use App\Services\IdEncryptionService;
use App\Services\TenantDatabaseService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register TenantDatabaseService as singleton
        $this->app->singleton(TenantDatabaseService::class, function ($app) {
            return new TenantDatabaseService();
        });

        // Register IdEncryptionService as singleton
        $this->app->singleton(IdEncryptionService::class, function ($app) {
            return new IdEncryptionService();
        });
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Register model observers
        Store::observe(StoreObserver::class);
        
        // Use custom PersonalAccessToken model that handles tenant database switching
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
