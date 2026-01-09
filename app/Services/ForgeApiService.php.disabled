<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\Log;
use Laravel\Forge\Forge;
use Laravel\Forge\Resources\Site;
use Laravel\Forge\Resources\Database;
use Exception;

class ForgeApiService
{
    protected Forge $forge;
    protected int $serverId;
    protected string $defaultPhpVersion;
    protected string $gitProvider;
    protected string $gitRepository;
    protected string $gitBranch;

    public function __construct()
    {
        $apiToken = config('deployment.forge.api_token');
        
        if (!$apiToken) {
            throw new Exception('Forge API token not configured');
        }

        $this->forge = new Forge($apiToken);
        $this->serverId = (int) config('deployment.forge.server_id');
        $this->defaultPhpVersion = config('deployment.forge.php_version', 'php83');
        $this->gitProvider = config('deployment.forge.git_provider', 'github');
        $this->gitRepository = config('deployment.forge.repository', 'your-org/omniportal-storefront');
        $this->gitBranch = config('deployment.forge.branch', 'main');
    }

    /**
     * Create a new site on Forge server
     */
    public function createSite(Store $store): ?array
    {
        try {
            $domain = $this->getStoreDomain($store);
            
            Log::info('Creating Forge site', [
                'store_id' => $store->id,
                'domain' => $domain,
                'server_id' => $this->serverId,
            ]);

            $site = $this->forge->createSite($this->serverId, [
                'domain' => $domain,
                'project_type' => 'html', // Static site for React SPA
                'directory' => '/dist',
                'isolated' => false,
                'php_version' => $this->defaultPhpVersion,
            ]);

            // Update store with Forge site info
            $store->update([
                'forge_site_id' => $site->id,
                'forge_site_status' => 'created',
                'forge_site_created_at' => now(),
            ]);

            Log::info('Forge site created successfully', [
                'store_id' => $store->id,
                'forge_site_id' => $site->id,
            ]);

            return [
                'id' => $site->id,
                'name' => $site->name,
                'status' => $site->status,
                'directory' => $site->directory,
            ];

        } catch (Exception $e) {
            Log::error('Failed to create Forge site', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);
            
            $store->update(['forge_site_status' => 'failed']);
            
            throw $e;
        }
    }

    /**
     * Delete a site from Forge
     */
    public function deleteSite(int $siteId): bool
    {
        try {
            Log::info('Deleting Forge site', ['site_id' => $siteId]);

            $this->forge->deleteSite($this->serverId, $siteId);

            Log::info('Forge site deleted successfully', ['site_id' => $siteId]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to delete Forge site', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Get site details
     */
    public function getSite(int $siteId): ?Site
    {
        try {
            return $this->forge->site($this->serverId, $siteId);
        } catch (Exception $e) {
            Log::error('Failed to get Forge site', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * List all sites on server
     */
    public function listSites(): array
    {
        try {
            return $this->forge->sites($this->serverId);
        } catch (Exception $e) {
            Log::error('Failed to list Forge sites', [
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Install Git repository on site
     */
    public function installGitRepository(int $siteId, ?string $repository = null, ?string $branch = null): bool
    {
        try {
            $repo = $repository ?? $this->gitRepository;
            $branchName = $branch ?? $this->gitBranch;

            Log::info('Installing Git repository on site', [
                'site_id' => $siteId,
                'repository' => $repo,
                'branch' => $branchName,
            ]);

            $this->forge->installGitRepositoryOnSite($this->serverId, $siteId, [
                'provider' => $this->gitProvider,
                'repository' => $repo,
                'branch' => $branchName,
                'composer' => false,
            ]);

            Log::info('Git repository installed successfully', ['site_id' => $siteId]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to install Git repository', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Update site deployment script
     */
    public function updateDeploymentScript(int $siteId, string $script): bool
    {
        try {
            Log::info('Updating deployment script', ['site_id' => $siteId]);

            $this->forge->updateSiteDeploymentScript($this->serverId, $siteId, $script);

            Log::info('Deployment script updated successfully', ['site_id' => $siteId]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to update deployment script', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Get site deployment script
     */
    public function getDeploymentScript(int $siteId): ?string
    {
        try {
            return $this->forge->siteDeploymentScript($this->serverId, $siteId);
        } catch (Exception $e) {
            Log::error('Failed to get deployment script', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Deploy site
     */
    public function deploySite(int $siteId): bool
    {
        try {
            Log::info('Deploying site', ['site_id' => $siteId]);

            $this->forge->deploySite($this->serverId, $siteId);

            Log::info('Site deployment initiated successfully', ['site_id' => $siteId]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to deploy site', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Get deployment log
     */
    public function getDeploymentLog(int $siteId): ?string
    {
        try {
            return $this->forge->siteDeploymentLog($this->serverId, $siteId);
        } catch (Exception $e) {
            Log::error('Failed to get deployment log', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Enable quick deploy (auto-deploy on push)
     */
    public function enableQuickDeploy(int $siteId): bool
    {
        try {
            Log::info('Enabling quick deploy', ['site_id' => $siteId]);

            $this->forge->enableQuickDeploy($this->serverId, $siteId);

            Log::info('Quick deploy enabled successfully', ['site_id' => $siteId]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to enable quick deploy', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Disable quick deploy
     */
    public function disableQuickDeploy(int $siteId): bool
    {
        try {
            $this->forge->disableQuickDeploy($this->serverId, $siteId);
            return true;
        } catch (Exception $e) {
            Log::error('Failed to disable quick deploy', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Create SSL certificate (Let's Encrypt)
     */
    public function createSslCertificate(int $siteId, array $domains = []): bool
    {
        try {
            Log::info('Creating SSL certificate', [
                'site_id' => $siteId,
                'domains' => $domains,
            ]);

            $this->forge->obtainLetsEncryptCertificate($this->serverId, $siteId, [
                'domains' => $domains,
            ]);

            Log::info('SSL certificate creation initiated', ['site_id' => $siteId]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to create SSL certificate', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Get SSL certificates for a site
     */
    public function getSslCertificates(int $siteId): array
    {
        try {
            return $this->forge->certificates($this->serverId, $siteId);
        } catch (Exception $e) {
            Log::error('Failed to get SSL certificates', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Create environment file for site
     */
    public function updateEnvironmentFile(int $siteId, string $content): bool
    {
        try {
            Log::info('Updating environment file', ['site_id' => $siteId]);

            $this->forge->updateSiteEnvironmentFile($this->serverId, $siteId, $content);

            Log::info('Environment file updated successfully', ['site_id' => $siteId]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to update environment file', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Get environment file content
     */
    public function getEnvironmentFile(int $siteId): ?string
    {
        try {
            return $this->forge->siteEnvironmentFile($this->serverId, $siteId);
        } catch (Exception $e) {
            Log::error('Failed to get environment file', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Create a database on the server
     */
    public function createDatabase(string $name): ?Database
    {
        try {
            Log::info('Creating database', ['name' => $name]);

            $database = $this->forge->createDatabase($this->serverId, [
                'name' => $name,
            ]);

            Log::info('Database created successfully', [
                'name' => $name,
                'id' => $database->id,
            ]);

            return $database;

        } catch (Exception $e) {
            Log::error('Failed to create database', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Delete a database from the server
     */
    public function deleteDatabase(int $databaseId): bool
    {
        try {
            Log::info('Deleting database', ['database_id' => $databaseId]);

            $this->forge->deleteDatabase($this->serverId, $databaseId);

            Log::info('Database deleted successfully', ['database_id' => $databaseId]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to delete database', [
                'database_id' => $databaseId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * List all databases on server
     */
    public function listDatabases(): array
    {
        try {
            return $this->forge->databases($this->serverId);
        } catch (Exception $e) {
            Log::error('Failed to list databases', [
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Create a database user
     */
    public function createDatabaseUser(string $name, string $password, array $databases = []): ?object
    {
        try {
            Log::info('Creating database user', ['name' => $name]);

            $user = $this->forge->createDatabaseUser($this->serverId, [
                'name' => $name,
                'password' => $password,
                'databases' => $databases,
            ]);

            Log::info('Database user created successfully', ['name' => $name]);

            return $user;

        } catch (Exception $e) {
            Log::error('Failed to create database user', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Delete a database user
     */
    public function deleteDatabaseUser(int $userId): bool
    {
        try {
            $this->forge->deleteDatabaseUser($this->serverId, $userId);
            return true;
        } catch (Exception $e) {
            Log::error('Failed to delete database user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Add Nginx redirect rule
     */
    public function createRedirectRule(int $siteId, string $from, string $to, string $type = 'redirect'): bool
    {
        try {
            Log::info('Creating redirect rule', [
                'site_id' => $siteId,
                'from' => $from,
                'to' => $to,
            ]);

            $this->forge->createRedirectRule($this->serverId, $siteId, [
                'from' => $from,
                'to' => $to,
                'type' => $type,
            ]);

            Log::info('Redirect rule created successfully', ['site_id' => $siteId]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to create redirect rule', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Get redirect rules for a site
     */
    public function getRedirectRules(int $siteId): array
    {
        try {
            return $this->forge->redirectRules($this->serverId, $siteId);
        } catch (Exception $e) {
            Log::error('Failed to get redirect rules', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Update Nginx configuration
     */
    public function updateNginxConfiguration(int $siteId, string $content): bool
    {
        try {
            Log::info('Updating Nginx configuration', ['site_id' => $siteId]);

            $this->forge->updateSiteNginxFile($this->serverId, $siteId, $content);

            Log::info('Nginx configuration updated successfully', ['site_id' => $siteId]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to update Nginx configuration', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Get Nginx configuration
     */
    public function getNginxConfiguration(int $siteId): ?string
    {
        try {
            return $this->forge->siteNginxFile($this->serverId, $siteId);
        } catch (Exception $e) {
            Log::error('Failed to get Nginx configuration', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Get server details
     */
    public function getServer(): ?object
    {
        try {
            return $this->forge->server($this->serverId);
        } catch (Exception $e) {
            Log::error('Failed to get server', [
                'server_id' => $this->serverId,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * List all servers
     */
    public function listServers(): array
    {
        try {
            return $this->forge->servers();
        } catch (Exception $e) {
            Log::error('Failed to list servers', [
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Run a command on the server
     */
    public function runCommand(string $command): ?object
    {
        try {
            Log::info('Running command on server', ['command' => $command]);

            $result = $this->forge->executeSiteCommand($this->serverId, null, [
                'command' => $command,
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to run command', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Create a scheduled job (cron)
     */
    public function createScheduledJob(string $command, string $frequency, ?string $user = null): ?object
    {
        try {
            Log::info('Creating scheduled job', [
                'command' => $command,
                'frequency' => $frequency,
            ]);

            $job = $this->forge->createJob($this->serverId, [
                'command' => $command,
                'frequency' => $frequency,
                'user' => $user ?? 'forge',
            ]);

            Log::info('Scheduled job created successfully');

            return $job;

        } catch (Exception $e) {
            Log::error('Failed to create scheduled job', [
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * List scheduled jobs
     */
    public function listScheduledJobs(): array
    {
        try {
            return $this->forge->jobs($this->serverId);
        } catch (Exception $e) {
            Log::error('Failed to list scheduled jobs', [
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Delete a scheduled job
     */
    public function deleteScheduledJob(int $jobId): bool
    {
        try {
            $this->forge->deleteJob($this->serverId, $jobId);
            return true;
        } catch (Exception $e) {
            Log::error('Failed to delete scheduled job', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Create a daemon (worker)
     */
    public function createDaemon(string $command, ?string $directory = null): ?object
    {
        try {
            Log::info('Creating daemon', ['command' => $command]);

            $daemon = $this->forge->createDaemon($this->serverId, [
                'command' => $command,
                'user' => 'forge',
                'directory' => $directory,
            ]);

            Log::info('Daemon created successfully');

            return $daemon;

        } catch (Exception $e) {
            Log::error('Failed to create daemon', [
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * List daemons
     */
    public function listDaemons(): array
    {
        try {
            return $this->forge->daemons($this->serverId);
        } catch (Exception $e) {
            Log::error('Failed to list daemons', [
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Delete a daemon
     */
    public function deleteDaemon(int $daemonId): bool
    {
        try {
            $this->forge->deleteDaemon($this->serverId, $daemonId);
            return true;
        } catch (Exception $e) {
            Log::error('Failed to delete daemon', [
                'daemon_id' => $daemonId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Restart a daemon
     */
    public function restartDaemon(int $daemonId): bool
    {
        try {
            $this->forge->restartDaemon($this->serverId, $daemonId);
            return true;
        } catch (Exception $e) {
            Log::error('Failed to restart daemon', [
                'daemon_id' => $daemonId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Provision complete store infrastructure
     */
    public function provisionStore(Store $store): array
    {
        $result = [
            'site' => null,
            'database' => null,
            'ssl' => false,
            'deployed' => false,
        ];

        try {
            // 1. Create site
            $result['site'] = $this->createSite($store);
            $siteId = $result['site']['id'];

            // 2. Install Git repository
            $this->installGitRepository($siteId);

            // 3. Set up deployment script
            $deployScript = $this->generateDeploymentScript($store);
            $this->updateDeploymentScript($siteId, $deployScript);

            // 4. Set environment variables
            $envContent = $this->generateEnvironmentFile($store);
            $this->updateEnvironmentFile($siteId, $envContent);

            // 5. Deploy the site
            $this->deploySite($siteId);
            $result['deployed'] = true;

            // 6. Enable quick deploy
            $this->enableQuickDeploy($siteId);

            // 7. Create SSL certificate
            $domain = $this->getStoreDomain($store);
            $this->createSslCertificate($siteId, [$domain]);
            $result['ssl'] = true;

            // Update store status
            $store->update([
                'forge_site_status' => 'active',
                'ssl_enabled' => true,
                'last_deployed_at' => now(),
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Failed to provision store', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
                'result' => $result,
            ]);

            throw $e;
        }
    }

    /**
     * Delete complete store infrastructure
     */
    public function deprovisionStore(Store $store): bool
    {
        try {
            if ($store->forge_site_id) {
                $this->deleteSite($store->forge_site_id);
            }

            // Update store
            $store->update([
                'forge_site_id' => null,
                'forge_site_status' => null,
                'forge_site_created_at' => null,
                'ssl_enabled' => false,
                'ssl_expires_at' => null,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to deprovision store', [
                'store_id' => $store->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate deployment script for a store
     */
    protected function generateDeploymentScript(Store $store): string
    {
        $domain = $this->getStoreDomain($store);
        
        return <<<BASH
cd /home/forge/{$domain}
git pull origin {$this->gitBranch}

# Install dependencies
npm ci

# Build with store-specific configuration
VITE_STORE_SLUG={$store->slug} \
VITE_API_URL={$this->getApiUrl()} \
npm run build

# Ensure correct permissions
chown -R forge:forge dist
BASH;
    }

    /**
     * Generate environment file content for a store
     */
    protected function generateEnvironmentFile(Store $store): string
    {
        return <<<ENV
VITE_STORE_SLUG={$store->slug}
VITE_STORE_ID={$store->id}
VITE_API_URL={$this->getApiUrl()}
VITE_STORE_NAME={$store->name}
ENV;
    }

    /**
     * Get the domain for a store
     */
    protected function getStoreDomain(Store $store): string
    {
        // Check for custom domain first
        if ($store->custom_domain) {
            return $store->custom_domain;
        }

        // Use subdomain format
        $baseDomain = config('deployment.base_domain', 'time-luxe.com');
        return "{$store->slug}.{$baseDomain}";
    }

    /**
     * Get the API URL
     */
    protected function getApiUrl(): string
    {
        return config('deployment.api_url', 'https://api.time-luxe.com');
    }

    /**
     * Get the Forge SDK instance
     */
    public function getForgeInstance(): Forge
    {
        return $this->forge;
    }

    /**
     * Set a different server ID (for multi-server setups)
     */
    public function setServerId(int $serverId): self
    {
        $this->serverId = $serverId;
        return $this;
    }

    /**
     * Get current server ID
     */
    public function getServerId(): int
    {
        return $this->serverId;
    }
}
