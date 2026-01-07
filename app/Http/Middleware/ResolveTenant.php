<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Services\TenantDatabaseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        private TenantDatabaseService $tenantService
    ) {}

    /**
     * Resolve tenant from subdomain, custom domain, or header
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

        if (!$store->isActive()) {
            return response()->json([
                'message' => 'This store is currently unavailable',
                'error' => 'store_inactive'
            ], 403);
        }

        // Configure tenant database connection
        if ($store->database_name) {
            $this->tenantService->configureTenantConnection($store);
        }

        // Bind store to container for easy access
        app()->instance('current.store', $store);
        $request->merge(['store' => $store]);
        $request->attributes->set('store', $store);

        return $next($request);
    }

    protected function resolveStore(Request $request): ?Store
    {
        // 0. Check for store route parameter (for owner routes like /owner/{store}/categories or /employee/{store}/...)
        if ($store = $request->route('store')) {
            if ($store instanceof Store) {
                return $store;
            }
            // Try to find by ID first, then by slug
            if (is_numeric($store)) {
                return Store::find($store);
            }
            return Store::where('slug', $store)->orWhere('id', $store)->first();
        }

        // 1. Check for store ID in header (for API calls)
        if ($storeId = $request->header('X-Store-ID')) {
            return Store::find($storeId);
        }

        // 2. Check for store slug in header
        if ($storeSlug = $request->header('X-Store-Slug')) {
            return Store::where('slug', $storeSlug)->first();
        }

        // 3. Check subdomain
        $host = $request->getHost();
        $baseDomain = config('services.forge.base_domain', config('app.base_domain', 'time-luxe.com'));
        
        // Extract subdomain if host ends with base domain
        if (str_ends_with($host, '.' . $baseDomain)) {
            $subdomain = str_replace('.' . $baseDomain, '', $host);
            if ($subdomain && !in_array($subdomain, ['www', 'api', 'admin', 'owner'])) {
                $store = Store::where('subdomain', $subdomain)->first();
                if ($store) {
                    return $store;
                }
                // Also try matching by slug
                $store = Store::where('slug', $subdomain)->first();
                if ($store) {
                    return $store;
                }
            }
        }
        
        // Fallback: traditional subdomain detection for any domain
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            $subdomain = $parts[0];
            if (!in_array($subdomain, ['www', 'api', 'admin', 'owner'])) {
                $store = Store::where('subdomain', $subdomain)->first();
                if ($store) {
                    return $store;
                }
            }
        }

        // 4. Check custom domain
        return Store::where('custom_domain', $host)->first();
    }
}
