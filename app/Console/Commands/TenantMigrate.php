<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\TenantDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantMigrate extends Command
{
    protected $signature = 'tenant:migrate 
                            {store? : The store ID or slug}
                            {--all : Run migrations for all stores}
                            {--fresh : Drop all tables and re-run migrations}
                            {--seed : Seed the database after migrations}
                            {--force : Force running in production}';

    protected $description = 'Run migrations for tenant databases';

    public function __construct(
        private TenantDatabaseService $tenantService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->migrateAllStores();
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

        return $this->migrateStore($store);
    }

    private function migrateAllStores(): int
    {
        $stores = Store::whereNotNull('database_name')->get();

        if ($stores->isEmpty()) {
            $this->info('No stores with databases found');
            return self::SUCCESS;
        }

        $this->info("Running migrations for {$stores->count()} stores...");
        $bar = $this->output->createProgressBar($stores->count());

        $success = 0;
        $failed = 0;

        foreach ($stores as $store) {
            try {
                $this->migrateStore($store, false);
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

    private function migrateStore(Store $store, bool $showOutput = true): int
    {
        if (!$store->database_name) {
            if ($showOutput) {
                $this->error("Store does not have a database configured");
            }
            return self::FAILURE;
        }

        try {
            $this->tenantService->configureTenantConnection($store);

            if ($showOutput) {
                $this->info("Running migrations for store: {$store->name}");
            }

            $command = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

            Artisan::call($command, [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => $this->option('force') || true,
            ]);

            if ($showOutput) {
                $this->line(Artisan::output());
            }

            if ($this->option('seed')) {
                if ($showOutput) {
                    $this->info("Seeding database...");
                }

                Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => 'Database\\Seeders\\TenantSeeder',
                    '--force' => true,
                ]);

                if ($showOutput) {
                    $this->line(Artisan::output());
                }
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            if ($showOutput) {
                $this->error("Migration failed: {$e->getMessage()}");
            }
            throw $e;
        }
    }
}
