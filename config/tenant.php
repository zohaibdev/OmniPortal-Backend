<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for multi-tenant database architecture
    |
    */

    // Prefix for tenant database names
    'database_prefix' => env('TENANT_DATABASE_PREFIX', 'tenant_'),

    // Whether to auto-create database on store creation
    'auto_create_database' => env('TENANT_AUTO_CREATE', true),

    // Whether to auto-migrate on database creation
    'auto_migrate' => env('TENANT_AUTO_MIGRATE', true),

    // Whether to auto-seed on database creation
    'auto_seed' => env('TENANT_AUTO_SEED', true),

    // Migrations path for tenant databases
    'migrations_path' => database_path('migrations/tenant'),

    // Seeder class for tenant databases
    'seeder_class' => 'Database\\Seeders\\TenantSeeder',

    // Maximum number of tenants (0 = unlimited)
    'max_tenants' => env('TENANT_MAX_COUNT', 0),

    // Database backup settings
    'backup' => [
        'enabled' => env('TENANT_BACKUP_ENABLED', false),
        'path' => storage_path('backups/tenants'),
        'retention_days' => env('TENANT_BACKUP_RETENTION', 30),
    ],

    // Database pooling (for connection reuse)
    'connection_pool' => [
        'enabled' => env('TENANT_POOL_ENABLED', false),
        'max_connections' => env('TENANT_POOL_MAX', 10),
    ],
];
