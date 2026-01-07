<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deployment Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for store frontend deployment
    |
    */

    // Path to the storefront build directory
    'storefront_build_path' => env('STOREFRONT_BUILD_PATH', base_path('../storefront/dist')),

    // Path where store deployments are created
    'stores_path' => env('STORES_DEPLOY_PATH', base_path('../stores')),

    // Auto-create Forge site on store creation
    'auto_create_forge_site' => env('AUTO_CREATE_FORGE_SITE', true),

    // Auto-deploy storefront on store creation
    'auto_deploy_storefront' => env('AUTO_DEPLOY_STOREFRONT', true),

    // Base domain for store subdomains
    'base_domain' => env('STORE_BASE_DOMAIN', 'time-luxe.com'),

    // API URL for storefront configuration
    'api_url' => env('API_URL', 'https://api.time-luxe.com'),
];
