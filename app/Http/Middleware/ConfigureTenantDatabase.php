<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Services\TenantDatabaseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureTenantDatabase
{
    public function __construct(
        private TenantDatabaseService $tenantService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $store = $this->resolveStore($request);

        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
                'error' => 'invalid_tenant'
            ], 404);
        }

        if (!$store->database_name) {
            return response()->json([
                'message' => 'Store database not configured',
                'error' => 'database_not_configured'
            ], 500);
        }

        // Configure tenant database connection
        $this->tenantService->configureTenantConnection($store);

        // Bind store to container
        app()->instance('current.store', $store);
        $request->attributes->set('store', $store);

        return $next($request);
    }

    /**
     * Resolve the store from the request
     */
    protected function resolveStore(Request $request): ?Store
    {
        // 1. Check route parameter
        if ($store = $request->route('store')) {
            if ($store instanceof Store) {
                return $store;
            }
            // Try to find by encrypted_id first, then by regular id
            return Store::where('encrypted_id', $store)->first()
                ?? Store::find($store);
        }

        // 2. Check header for store encrypted ID
        if ($storeId = $request->header('X-Store-ID')) {
            return Store::where('encrypted_id', $storeId)->first()
                ?? Store::find($storeId);
        }

        // 3. Check header for store slug
        if ($storeSlug = $request->header('X-Store-Slug')) {
            return Store::where('slug', $storeSlug)->first();
        }

        // 4. Resolve from subdomain
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];
        
        if ($subdomain && $subdomain !== 'www' && $subdomain !== 'api') {
            return Store::where('subdomain', $subdomain)->first();
        }

        // 5. Resolve from custom domain
        return Store::where('custom_domain', $host)->first();
    }
}
