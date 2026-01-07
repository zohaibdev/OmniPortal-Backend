<?php

namespace App\Services;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantDatabaseService
{
    protected ?ForgeApiService $forgeService = null;

    public function __construct()
    {
        // Initialize Forge service for production database management
        if ($this->shouldUseForgeApi()) {
            $this->forgeService = app(ForgeApiService::class);
        }
    }

    /**
     * Determine if we should use Forge API for database operations
     */
    protected function shouldUseForgeApi(): bool
    {
        // Use Forge API in production when configured
        return app()->environment('production') 
            && config('services.forge.api_token') 
            && config('services.forge.server_id');
    }

    /**
     * Create a new tenant database for a store owner
     */
    public function createTenantDatabase(Store $store): bool
    {
        $databaseName = $this->generateDatabaseName($store);
        
        try {
            // Create the database (either locally or via Forge)
            if ($this->shouldUseForgeApi() && $this->forgeService?->isConfigured()) {
                $this->createDatabaseViaForge($store, $databaseName);
            } else {
                $this->createDatabaseLocally($databaseName);
            }
            
            // Update store with database name
            $store->update([
                'database_name' => $databaseName,
                'database_created_at' => now(),
            ]);
            
            // Configure and run migrations
            $this->configureTenantConnection($store);
            $this->runTenantMigrations($store);
            $this->seedTenantDatabase($store);
            
            Log::info("Tenant database created successfully", [
                'store_id' => $store->id,
                'database' => $databaseName,
                'method' => $this->shouldUseForgeApi() ? 'forge_api' : 'local',
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create tenant database", [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            
            // Cleanup on failure
            $this->dropTenantDatabase($databaseName);
            
            throw $e;
        }
    }

    /**
     * Create database locally via direct MySQL connection
     */
    protected function createDatabaseLocally(string $databaseName): void
    {
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        Log::info("Database created locally", ['database' => $databaseName]);
    }

    /**
     * Create database via Forge API
     */
    protected function createDatabaseViaForge(Store $store, string $databaseName): void
    {
        if (!$this->forgeService) {
            throw new \Exception('Forge API service not initialized');
        }

        // Check if database already exists
        if ($this->forgeService->databaseExists($databaseName)) {
            Log::info("Forge database already exists", ['database' => $databaseName]);
            return;
        }

        // Create database via Forge API
        $response = $this->forgeService->createStoreDatabase($store, $databaseName);
        
        if (!isset($response['database'])) {
            throw new \Exception('Failed to create database via Forge API: ' . json_encode($response));
        }

        Log::info("Database created via Forge API", [
            'database' => $databaseName,
            'forge_id' => $response['database']['id'] ?? null,
        ]);
    }

    /**
     * Generate a unique database name for a store
     */
    public function generateDatabaseName(Store $store): string
    {
        $prefix = config('tenant.database_prefix', 'tenant_');
        $slug = Str::slug($store->slug, '_');
        $hash = substr(md5($store->id . config('app.key')), 0, 8);
        
        return $prefix . $slug . '_' . $hash;
    }

    /**
     * Configure the tenant database connection
     */
    public function configureTenantConnection(Store $store): void
    {
        $databaseName = $store->database_name ?? $this->generateDatabaseName($store);
        
        Config::set('database.connections.tenant', [
            'driver' => config('database.connections.mysql.driver', 'mysql'),
            'host' => config('database.connections.mysql.host', '127.0.0.1'),
            'port' => config('database.connections.mysql.port', '3306'),
            'database' => $databaseName,
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
        ]);

        // Purge and reconnect
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    /**
     * Run tenant-specific migrations
     */
    public function runTenantMigrations(Store $store): void
    {
        $this->configureTenantConnection($store);
        
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }

    /**
     * Seed tenant database with initial data
     */
    public function seedTenantDatabase(Store $store): void
    {
        $this->configureTenantConnection($store);
        
        Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => 'Database\\Seeders\\TenantSeeder',
            '--force' => true,
        ]);
    }

    /**
     * Drop a tenant database
     */
    public function dropTenantDatabase(string $databaseName): bool
    {
        try {
            if ($this->shouldUseForgeApi() && $this->forgeService?->isConfigured()) {
                return $this->forgeService->deleteDatabase($databaseName);
            }
            
            // Local deletion
            DB::statement("DROP DATABASE IF EXISTS `{$databaseName}`");
            
            Log::info("Database dropped locally", ['database' => $databaseName]);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to drop tenant database", [
                'database' => $databaseName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete tenant database for a store (with Forge support)
     */
    public function deleteTenantDatabase(Store $store): bool
    {
        if (!$store->database_name) {
            return true;
        }

        try {
            // Try Forge API first if configured
            if ($this->shouldUseForgeApi() && $this->forgeService?->isConfigured()) {
                $result = $this->forgeService->deleteStoreDatabase($store);
            } else {
                $result = $this->dropTenantDatabase($store->database_name);
            }

            if ($result) {
                $store->update([
                    'database_name' => null,
                    'database_created_at' => null,
                ]);

                Log::info("Tenant database deleted", [
                    'store_id' => $store->id,
                    'method' => $this->shouldUseForgeApi() ? 'forge_api' : 'local',
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Failed to delete tenant database", [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if tenant database exists
     */
    public function tenantDatabaseExists(Store $store): bool
    {
        if (!$store->database_name) {
            return false;
        }

        $result = DB::select("SHOW DATABASES LIKE '{$store->database_name}'");
        return count($result) > 0;
    }

    /**
     * Get tenant database connection
     */
    public function getTenantConnection(Store $store): string
    {
        $this->configureTenantConnection($store);
        return 'tenant';
    }

    /**
     * Execute callback within tenant context
     */
    public function withinTenant(Store $store, callable $callback)
    {
        $this->configureTenantConnection($store);
        
        return $callback();
    }

    /**
     * Backup tenant database
     */
    public function backupTenantDatabase(Store $store, string $backupPath): bool
    {
        if (!$store->database_name) {
            return false;
        }

        try {
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');
            $database = $store->database_name;

            $command = sprintf(
                'mysqldump -h %s -P %s -u %s -p%s %s > %s',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                escapeshellarg($backupPath)
            );

            exec($command, $output, $returnCode);

            return $returnCode === 0;
        } catch (\Exception $e) {
            Log::error("Failed to backup tenant database", [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
