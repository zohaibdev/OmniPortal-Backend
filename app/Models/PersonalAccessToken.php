<?php

namespace App\Models;

use App\Services\TenantDatabaseService;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'store_id',
    ];

    /**
     * Get the tokenable model that the access token belongs to.
     * Override to handle tenant database switching for Employee tokens.
     */
    public function tokenable(): MorphTo
    {
        // If this token has a store_id and is for an Employee, configure tenant connection first
        if ($this->store_id && $this->tokenable_type === Employee::class) {
            $store = Store::find($this->store_id);
            
            if ($store && $store->database_name) {
                $tenantService = app(TenantDatabaseService::class);
                $tenantService->configureTenantConnection($store);
                
                // Bind store to container
                app()->instance('current.store', $store);
            }
        }
        
        return $this->morphTo('tokenable');
    }
}
