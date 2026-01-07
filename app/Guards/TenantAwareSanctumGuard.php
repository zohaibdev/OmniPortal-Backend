<?php

namespace App\Guards;

use App\Models\Employee;
use App\Models\Store;
use App\Services\TenantDatabaseService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Laravel\Sanctum\Guard as SanctumGuard;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class TenantAwareSanctumGuard extends SanctumGuard
{
    /**
     * Retrieve the authenticated user for the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function __invoke(Request $request)
    {
        // First check if we can find the user via standard Sanctum guard
        $user = parent::__invoke($request);
        
        if ($user) {
            return $user;
        }
        
        // If not, try to resolve employee token from central DB
        if ($token = $this->getTokenFromRequest($request)) {
            $accessToken = $this->findToken($token);
            
            if ($accessToken && 
                $accessToken->tokenable_type === Employee::class &&
                $accessToken->store_id) {
                
                // Connect to the tenant database
                $store = Store::find($accessToken->store_id);
                
                if ($store && $store->database_name) {
                    $tenantService = app(TenantDatabaseService::class);
                    $tenantService->configureTenantConnection($store);
                    
                    // Now load the employee from tenant database
                    $employee = Employee::on('tenant')->find($accessToken->tokenable_id);
                    
                    if ($employee && $this->isValidAccessToken($accessToken)) {
                        // Update last used timestamp
                        $accessToken->forceFill(['last_used_at' => now()])->save();
                        
                        // Set the current access token on the employee
                        $employee->withAccessToken($accessToken);
                        
                        // Bind store to container
                        app()->instance('current.store', $store);
                        $request->merge(['store' => $store]);
                        $request->attributes->set('store', $store);
                        
                        return $employee;
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get the token from the request.
     */
    protected function getTokenFromRequest(Request $request): ?string
    {
        if (is_callable(Sanctum::$accessTokenRetrievalCallback)) {
            return (string) (Sanctum::$accessTokenRetrievalCallback)($request);
        }

        return $request->bearerToken();
    }
    
    /**
     * Find the token in the central database.
     */
    protected function findToken(string $token): ?PersonalAccessToken
    {
        if (!str_contains($token, '|')) {
            return PersonalAccessToken::where('token', hash('sha256', $token))->first();
        }

        [$id, $token] = explode('|', $token, 2);

        if ($instance = PersonalAccessToken::find($id)) {
            return hash_equals($instance->token, hash('sha256', $token)) ? $instance : null;
        }

        return null;
    }
    
    /**
     * Determine if the provided access token is valid.
     */
    protected function isValidAccessToken(PersonalAccessToken $accessToken): bool
    {
        if (!$accessToken->expires_at) {
            return true;
        }

        return $accessToken->expires_at->isFuture();
    }
}
