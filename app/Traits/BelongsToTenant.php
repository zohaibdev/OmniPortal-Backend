<?php

namespace App\Traits;

use App\Models\Store;
use App\Services\TenantDatabaseService;

trait BelongsToTenant
{
    /**
     * Get the tenant connection name
     */
    public function getConnectionName(): string
    {
        return 'tenant';
    }

    /**
     * Scope to current tenant
     */
    public function scopeForTenant($query, Store $store)
    {
        app(TenantDatabaseService::class)->configureTenantConnection($store);
        return $query;
    }
}
