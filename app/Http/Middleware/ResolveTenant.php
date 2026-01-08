<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\Domain;
use App\Services\TenantDatabaseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Reserved subdomains that cannot be used for stores
     */
    protected array $reservedSubdomains = [
        'www', 'api', 'admin', 'owner', 'app', 'mail', 'smtp', 'ftp',
        'cdn', 'static', 'assets', 'images', 'media', 'staging', 'dev',
        'test', 'demo', 'support', 'help', 'docs', 'blog', 'news',
    ];

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

        // Log store access for analytics
        $this->logStoreAccess($store, $request);

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
            return $this->getCachedStore('id', $storeId);
        }

        // 2. Check for store slug in header
        if ($storeSlug = $request->header('X-Store-Slug')) {
            return $this->getCachedStore('slug', $storeSlug);
        }

        // 3. Check subdomain or custom domain
        $host = $request->getHost();
        
        // Try to get from cache first
        $cacheKey = "store:domain:{$host}";
        $store = Cache::remember($cacheKey, 300, function () use ($host) {
            return $this->resolveStoreByHost($host);
        });

        return $store;
    }

    /**
     * Resolve store by hostname (subdomain or custom domain)
     */
    protected function resolveStoreByHost(string $host): ?Store
    {
        $baseDomain = config('services.forge.base_domain', config('app.base_domain', 'time-luxe.com'));
        
        // 1. Check if it's a subdomain of base domain
        if (str_ends_with($host, '.' . $baseDomain)) {
            $subdomain = str_replace('.' . $baseDomain, '', $host);
            
            if ($subdomain && !in_array($subdomain, $this->reservedSubdomains)) {
                // Try subdomain field first
                $store = Store::where('subdomain', $subdomain)->first();
                if ($store) {
                    return $store;
                }
                
                // Try slug match
                $store = Store::where('slug', $subdomain)->first();
                if ($store) {
                    return $store;
                }
            }
        }
        
        // 2. Traditional subdomain detection for any domain
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            $subdomain = $parts[0];
            if (!in_array($subdomain, $this->reservedSubdomains)) {
                $store = Store::where('subdomain', $subdomain)->first();
                if ($store) {
                    return $store;
                }
            }
        }

        // 3. Check custom domain (exact match)
        $store = Store::where('custom_domain', $host)->first();
        if ($store) {
            return $store;
        }

        // 4. Check domains table for verified custom domains
        $domain = Domain::where('domain', $host)
            ->where('status', 'active')
            ->where('is_verified', true)
            ->first();
        
        if ($domain) {
            return $domain->store;
        }

        // 5. Check if host without www matches
        if (str_starts_with($host, 'www.')) {
            $hostWithoutWww = substr($host, 4);
            
            $store = Store::where('custom_domain', $hostWithoutWww)->first();
            if ($store) {
                return $store;
            }

            $domain = Domain::where('domain', $hostWithoutWww)
                ->where('status', 'active')
                ->where('is_verified', true)
                ->first();
            
            if ($domain) {
                return $domain->store;
            }
        }

        return null;
    }

    /**
     * Get cached store by field
     */
    protected function getCachedStore(string $field, $value): ?Store
    {
        $cacheKey = "store:{$field}:{$value}";
        
        return Cache::remember($cacheKey, 300, function () use ($field, $value) {
            return Store::where($field, $value)->first();
        });
    }

    /**
     * Log store access for analytics
     */
    protected function logStoreAccess(Store $store, Request $request): void
    {
        // Only log in production and for storefront routes
        if (!app()->environment('production')) {
            return;
        }

        // Asynchronously log the access (could dispatch a job)
        try {
            Log::channel('store_access')->info('Store accessed', [
                'store_id' => $store->id,
                'store_slug' => $store->slug,
                'host' => $request->getHost(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 200),
            ]);
        } catch (\Exception $e) {
            // Silently fail - don't break the request
        }
    }

    /**
     * Clear domain cache for a store
     */
    public static function clearStoreCache(Store $store): void
    {
        $baseDomain = config('services.forge.base_domain', 'time-luxe.com');
        
        Cache::forget("store:id:{$store->id}");
        Cache::forget("store:slug:{$store->slug}");
        Cache::forget("store:domain:{$store->slug}.{$baseDomain}");
        
        if ($store->subdomain) {
            Cache::forget("store:domain:{$store->subdomain}.{$baseDomain}");
        }
        
        if ($store->custom_domain) {
            Cache::forget("store:domain:{$store->custom_domain}");
            Cache::forget("store:domain:www.{$store->custom_domain}");
        }
    }
}
