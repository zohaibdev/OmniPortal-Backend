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
    'storefront_build_path' => env('STOREFRONT_BUILD_PATH', base_path('../OmniPortal-Storefront/dist')),

    // Path where store deployments are created (local development)
    'stores_path' => env('STORES_DEPLOY_PATH', base_path('../stores')),

    // Auto-create Forge site on store creation
    'auto_create_forge_site' => env('AUTO_CREATE_FORGE_SITE', true),

    // Auto-deploy storefront on store creation
    'auto_deploy_storefront' => env('AUTO_DEPLOY_STOREFRONT', true),

    // Base domain for store subdomains
    'base_domain' => env('STORE_BASE_DOMAIN', 'time-luxe.com'),

    // API URL for storefront configuration
    'api_url' => env('API_URL', 'https://api.time-luxe.com'),

    /*
    |--------------------------------------------------------------------------
    | Production Paths (Forge Server)
    |--------------------------------------------------------------------------
    */

    // Production path prefix for store folders
    'production_path_prefix' => env('PRODUCTION_PATH_PREFIX', '/home/forge'),

    // Central themes folder on production
    'themes_path' => env('THEMES_PATH', '/home/forge/storefront-themes'),

    /*
    |--------------------------------------------------------------------------
    | Deployment Options
    |--------------------------------------------------------------------------
    */

    // Maximum file upload size for store assets (in KB)
    'max_upload_size' => env('STORE_MAX_UPLOAD_SIZE', 10240), // 10MB

    // Allowed file extensions for uploads
    'allowed_extensions' => [
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'],
        'documents' => ['pdf', 'doc', 'docx', 'txt'],
        'code' => ['css', 'js', 'json'],
    ],

    // Storage quota per store (in MB, 0 = unlimited)
    'storage_quota' => env('STORE_STORAGE_QUOTA', 500),

    /*
    |--------------------------------------------------------------------------
    | Laravel Forge SDK Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Laravel Forge SDK integration
    |
    */

    'forge' => [
        // Forge API Token (from forge.laravel.com/user-profile/api)
        'api_token' => env('FORGE_API_TOKEN'),

        // Server ID where sites will be created
        'server_id' => env('FORGE_SERVER_ID'),

        // PHP version for new sites
        'php_version' => env('FORGE_PHP_VERSION', 'php83'),

        // Git provider (github, gitlab, bitbucket, custom)
        'git_provider' => env('FORGE_GIT_PROVIDER', 'github'),

        // Git repository for storefront
        'repository' => env('FORGE_REPOSITORY', 'your-org/omniportal-storefront'),

        // Git branch to deploy
        'branch' => env('FORGE_BRANCH', 'main'),
    ],
];
