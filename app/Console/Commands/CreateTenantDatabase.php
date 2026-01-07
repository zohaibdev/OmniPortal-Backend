<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\TenantDatabaseService;
use Illuminate\Console\Command;

class CreateTenantDatabase extends Command
{
    protected $signature = 'tenant:create-database 
                            {store? : The store ID or slug}
                            {--all : Create databases for all stores without one}
                            {--force : Force recreation even if database exists}';

    protected $description = 'Create a tenant database for a store';

    public function __construct(
        private TenantDatabaseService $tenantService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->createForAllStores();
        }

        $storeIdentifier = $this->argument('store');

        if (!$storeIdentifier) {
            $this->error('Please provide a store ID/slug or use --all option');
            return self::FAILURE;
        }

        $store = Store::where('id', $storeIdentifier)
            ->orWhere('slug', $storeIdentifier)
            ->orWhere('encrypted_id', $storeIdentifier)
            ->first();

        if (!$store) {
            $this->error("Store not found: {$storeIdentifier}");
            return self::FAILURE;
        }

        return $this->createDatabaseForStore($store);
    }

    private function createForAllStores(): int
    {
        $query = Store::query();

        if (!$this->option('force')) {
            $query->whereNull('database_name');
        }

        $stores = $query->get();

        if ($stores->isEmpty()) {
            $this->info('No stores found that need database creation');
            return self::SUCCESS;
        }

        $this->info("Creating databases for {$stores->count()} stores...");
        $bar = $this->output->createProgressBar($stores->count());

        $success = 0;
        $failed = 0;

        foreach ($stores as $store) {
            try {
                if ($this->option('force') && $store->database_name) {
                    $this->tenantService->deleteTenantDatabase($store);
                }

                $this->tenantService->createTenantDatabase($store);
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("Failed for store {$store->name}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Completed: {$success} success, {$failed} failed");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function createDatabaseForStore(Store $store): int
    {
        if ($store->database_name && !$this->option('force')) {
            $this->warn("Store already has a database: {$store->database_name}");
            
            if (!$this->confirm('Do you want to recreate it?')) {
                return self::SUCCESS;
            }
        }

        try {
            $this->info("Creating database for store: {$store->name}");

            if ($store->database_name && $this->option('force')) {
                $this->warn("Dropping existing database: {$store->database_name}");
                $this->tenantService->deleteTenantDatabase($store);
            }

            $this->tenantService->createTenantDatabase($store);

            $this->info("Database created: {$store->fresh()->database_name}");
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to create database: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
