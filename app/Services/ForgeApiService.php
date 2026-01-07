<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ForgeApiService
{
    protected string $apiUrl = 'https://forge.laravel.com/api/v1';
    protected ?string $apiToken = null;
    protected ?int $serverId = null;

    public function __construct()
    {
        $this->apiToken = $this->getApiToken();
        $this->serverId = (int) config('services.forge.server_id');
    }

    /**
     * Get Forge API token from file or environment
     */
    protected function getApiToken(): ?string
    {
        // First try environment variable
        if ($envToken = config('services.forge.api_token')) {
            return $envToken;
        }

        // Then try file
        $tokenFile = base_path('../forge-access-token.txt');
        if (file_exists($tokenFile)) {
            return trim(file_get_contents($tokenFile));
        }

        // Try alternate location
        $altTokenFile = base_path('forge-access-token.txt');
        if (file_exists($altTokenFile)) {
            return trim(file_get_contents($altTokenFile));
        }

        Log::warning('Forge API token not found');
        return null;
    }

    /**
     * Check if Forge API is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiToken) && !empty($this->serverId);
    }

    /**
     * Make authenticated request to Forge API
     */
    protected function request(string $method, string $endpoint, array $data = []): array
    {
        if (!$this->apiToken) {
            throw new \Exception('Forge API token not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->{$method}($this->apiUrl . $endpoint, $data);

        if ($response->failed()) {
            Log::error('Forge API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Forge API error: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Create a new site on Forge
     */
    public function createSite(Store $store): array
    {
        $domain = $this->getStoreDomain($store);
        
        Log::info('Creating Forge site', [
            'store_id' => $store->id,
            'domain' => $domain,
        ]);

        $response = $this->request('post', "/servers/{$this->serverId}/sites", [
            'domain' => $domain,
            'project_type' => 'html', // Static site for React SPA
            'directory' => '/public',
            'isolated' => false,
            'php_version' => config('services.forge.php_version', 'php83'),
        ]);

        // Store Forge site ID in store meta
        if (isset($response['site']['id'])) {
            $meta = $store->meta ?? [];
            $meta['forge_site_id'] = $response['site']['id'];
            $store->update(['meta' => $meta]);
        }

        return $response;
    }

    /**
     * Delete a site from Forge
     */
    public function deleteSite(Store $store): bool
    {
        $siteId = $this->getForgeSiteId($store);
        
        if (!$siteId) {
            Log::warning('No Forge site ID found for store', ['store_id' => $store->id]);
            return true; // Consider it deleted if no ID exists
        }

        try {
            $this->request('delete', "/servers/{$this->serverId}/sites/{$siteId}");
            
            Log::info('Forge site deleted', [
                'store_id' => $store->id,
                'site_id' => $siteId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete Forge site', [
                'store_id' => $store->id,
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get site details from Forge
     */
    public function getSite(Store $store): ?array
    {
        $siteId = $this->getForgeSiteId($store);
        
        if (!$siteId) {
            return null;
        }

        try {
            return $this->request('get', "/servers/{$this->serverId}/sites/{$siteId}");
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Install SSL certificate for site
     */
    public function installSslCertificate(Store $store): array
    {
        $siteId = $this->getForgeSiteId($store);
        
        if (!$siteId) {
            throw new \Exception('No Forge site ID found for store');
        }

        return $this->request('post', "/servers/{$this->serverId}/sites/{$siteId}/certificates/letsencrypt", [
            'domains' => [$this->getStoreDomain($store)],
        ]);
    }

    /**
     * Add custom domain to Forge site
     */
    public function addCustomDomain(Store $store, string $customDomain): array
    {
        $siteId = $this->getForgeSiteId($store);
        
        if (!$siteId) {
            throw new \Exception('No Forge site ID found for store');
        }

        // Update site aliases to include custom domain
        return $this->request('put', "/servers/{$this->serverId}/sites/{$siteId}", [
            'aliases' => [$customDomain],
        ]);
    }

    /**
     * Remove custom domain from Forge site
     */
    public function removeCustomDomain(Store $store, string $customDomain): array
    {
        $siteId = $this->getForgeSiteId($store);
        
        if (!$siteId) {
            throw new \Exception('No Forge site ID found for store');
        }

        return $this->request('put', "/servers/{$this->serverId}/sites/{$siteId}", [
            'aliases' => [],
        ]);
    }

    /**
     * Update site web directory (for custom deployments)
     */
    public function updateWebDirectory(Store $store, string $directory): array
    {
        $siteId = $this->getForgeSiteId($store);
        
        if (!$siteId) {
            throw new \Exception('No Forge site ID found for store');
        }

        return $this->request('put', "/servers/{$this->serverId}/sites/{$siteId}", [
            'directory' => $directory,
        ]);
    }

    /**
     * Get deployment script for site
     */
    public function getDeploymentScript(Store $store): ?string
    {
        $siteId = $this->getForgeSiteId($store);
        
        if (!$siteId) {
            return null;
        }

        try {
            $response = $this->request('get', "/servers/{$this->serverId}/sites/{$siteId}/deployment/script");
            return $response['script'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Update deployment script for site
     */
    public function updateDeploymentScript(Store $store, string $script): array
    {
        $siteId = $this->getForgeSiteId($store);
        
        if (!$siteId) {
            throw new \Exception('No Forge site ID found for store');
        }

        return $this->request('put', "/servers/{$this->serverId}/sites/{$siteId}/deployment/script", [
            'content' => $script,
        ]);
    }

    /**
     * Deploy site
     */
    public function deploy(Store $store): array
    {
        $siteId = $this->getForgeSiteId($store);
        
        if (!$siteId) {
            throw new \Exception('No Forge site ID found for store');
        }

        return $this->request('post', "/servers/{$this->serverId}/sites/{$siteId}/deployment/deploy");
    }

    /**
     * Get store domain based on configuration
     */
    protected function getStoreDomain(Store $store): string
    {
        $baseDomain = config('services.forge.base_domain', 'time-luxe.com');
        return $store->slug . '.' . $baseDomain;
    }

    /**
     * Get Forge site ID from store meta
     */
    protected function getForgeSiteId(Store $store): ?int
    {
        return $store->meta['forge_site_id'] ?? null;
    }

    /**
     * List all sites on the server
     */
    public function listSites(): array
    {
        return $this->request('get', "/servers/{$this->serverId}/sites");
    }

    /**
     * Get server details
     */
    public function getServer(): array
    {
        return $this->request('get', "/servers/{$this->serverId}");
    }

    // ==========================================
    // DATABASE MANAGEMENT
    // ==========================================

    /**
     * Create a database on Forge server
     */
    public function createDatabase(string $databaseName): array
    {
        Log::info('Creating Forge database', [
            'database' => $databaseName,
        ]);

        $response = $this->request('post', "/servers/{$this->serverId}/databases", [
            'name' => $databaseName,
            'user' => config('services.forge.database_user', 'forge'),
        ]);

        Log::info('Forge database created', [
            'database' => $databaseName,
            'response' => $response,
        ]);

        return $response;
    }

    /**
     * Delete a database from Forge server
     */
    public function deleteDatabase(string $databaseName): bool
    {
        try {
            // First, find the database ID
            $databaseId = $this->getDatabaseId($databaseName);
            
            if (!$databaseId) {
                Log::warning('Forge database not found for deletion', [
                    'database' => $databaseName,
                ]);
                return true; // Consider it deleted if not found
            }

            $this->request('delete', "/servers/{$this->serverId}/databases/{$databaseId}");
            
            Log::info('Forge database deleted', [
                'database' => $databaseName,
                'database_id' => $databaseId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete Forge database', [
                'database' => $databaseName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * List all databases on the server
     */
    public function listDatabases(): array
    {
        return $this->request('get', "/servers/{$this->serverId}/databases");
    }

    /**
     * Get database ID by name
     */
    public function getDatabaseId(string $databaseName): ?int
    {
        try {
            $response = $this->listDatabases();
            $databases = $response['databases'] ?? [];

            foreach ($databases as $database) {
                if ($database['name'] === $databaseName) {
                    return $database['id'];
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Failed to get database ID', [
                'database' => $databaseName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Check if a database exists on Forge
     */
    public function databaseExists(string $databaseName): bool
    {
        return $this->getDatabaseId($databaseName) !== null;
    }

    /**
     * Create a database for a store
     */
    public function createStoreDatabase(Store $store, string $databaseName): array
    {
        $response = $this->createDatabase($databaseName);

        // Store Forge database ID in store meta
        if (isset($response['database']['id'])) {
            $meta = $store->meta ?? [];
            $meta['forge_database_id'] = $response['database']['id'];
            $store->update(['meta' => $meta]);
        }

        return $response;
    }

    /**
     * Delete a store's database
     */
    public function deleteStoreDatabase(Store $store): bool
    {
        if (!$store->database_name) {
            return true;
        }

        // Try to get ID from meta first
        $databaseId = $store->meta['forge_database_id'] ?? null;

        if ($databaseId) {
            try {
                $this->request('delete', "/servers/{$this->serverId}/databases/{$databaseId}");
                
                Log::info('Forge store database deleted', [
                    'store_id' => $store->id,
                    'database_id' => $databaseId,
                ]);

                return true;
            } catch (\Exception $e) {
                Log::warning('Failed to delete by ID, trying by name', [
                    'database_id' => $databaseId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fall back to deletion by name
        return $this->deleteDatabase($store->database_name);
    }

    // ==========================================
    // DATABASE USERS MANAGEMENT
    // ==========================================

    /**
     * Create a database user on Forge
     */
    public function createDatabaseUser(string $username, string $password, array $databases = []): array
    {
        Log::info('Creating Forge database user', [
            'username' => $username,
        ]);

        return $this->request('post', "/servers/{$this->serverId}/database-users", [
            'name' => $username,
            'password' => $password,
            'databases' => $databases,
        ]);
    }

    /**
     * Delete a database user from Forge
     */
    public function deleteDatabaseUser(int $userId): bool
    {
        try {
            $this->request('delete', "/servers/{$this->serverId}/database-users/{$userId}");
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete Forge database user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * List all database users on the server
     */
    public function listDatabaseUsers(): array
    {
        return $this->request('get', "/servers/{$this->serverId}/database-users");
    }

    /**
     * Update database user's accessible databases
     */
    public function updateDatabaseUserAccess(int $userId, array $databases): array
    {
        return $this->request('put', "/servers/{$this->serverId}/database-users/{$userId}", [
            'databases' => $databases,
        ]);
    }
}
