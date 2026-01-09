<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes - Platform Admin auth
Route::prefix('auth')->group(function () {
    Route::post('login', [\App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('forgot-password', [\App\Http\Controllers\Api\AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [\App\Http\Controllers\Api\AuthController::class, 'resetPassword']);
});

// Public routes - Owner auth
Route::prefix('owner/auth')->group(function () {
    Route::post('login', [\App\Http\Controllers\Api\Owner\AuthController::class, 'login']);
});

// Public routes - Employee auth (needs store context)
Route::prefix('employee/{store}')->middleware('resolve.tenant')->group(function () {
    Route::post('auth/login', [\App\Http\Controllers\Api\Store\EmployeeAuthController::class, 'login']);
    Route::post('auth/login-pin', [\App\Http\Controllers\Api\Store\EmployeeAuthController::class, 'loginWithPin']);
});



// Subscription plans (public)
Route::get('plans', [\App\Http\Controllers\Api\SubscriptionController::class, 'plans']);

// Webhook routes
Route::post('webhooks/stripe', [\App\Http\Controllers\Api\WebhookController::class, 'stripe']);

// WhatsApp webhook (global - routes by phone_number_id)
Route::prefix('webhooks/whatsapp')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\Owner\WhatsAppWebhookController::class, 'verify']);
    Route::post('/', [\App\Http\Controllers\Api\Owner\WhatsAppWebhookController::class, 'webhook']);
});

// WhatsApp OAuth callback (public)
Route::get('whatsapp/callback', [\App\Http\Controllers\Api\Owner\WhatsAppOAuthController::class, 'handleCallback']);




// Test route for custom middleware (no sanctum)
Route::middleware(['user.type:super_admin'])->get('test-middleware', function() {
    return response()->json(['message' => 'You passed user.type:super_admin middleware']);
});


// Platform Admin authenticated routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
        Route::get('user', [\App\Http\Controllers\Api\AuthController::class, 'user']);
        Route::put('profile', [\App\Http\Controllers\Api\AuthController::class, 'updateProfile']);
        Route::put('password', [\App\Http\Controllers\Api\AuthController::class, 'updatePassword']);
    });

    
    // Super Admin routes
    Route::prefix('admin')->middleware('user.type:super_admin,support')->group(function () {
        // Stores management
        Route::post('stores', [\App\Http\Controllers\Api\Admin\StoreController::class, 'store']);
        Route::get('stores', [\App\Http\Controllers\Api\Admin\StoreController::class, 'index']);
        Route::get('stores/{store}', [\App\Http\Controllers\Api\Admin\StoreController::class, 'show']);
        Route::put('stores/{store}', [\App\Http\Controllers\Api\Admin\StoreController::class, 'update']);
        Route::post('stores/{store}/activate', [\App\Http\Controllers\Api\Admin\StoreController::class, 'activate']);
        Route::post('stores/{store}/suspend', [\App\Http\Controllers\Api\Admin\StoreController::class, 'suspend']);
        Route::delete('stores/{store}', [\App\Http\Controllers\Api\Admin\StoreController::class, 'destroy']);
        Route::post('stores/{store}/redeploy', [\App\Http\Controllers\Api\Admin\StoreController::class, 'redeploy']);
        Route::get('stores/{store}/deployment-status', [\App\Http\Controllers\Api\Admin\StoreController::class, 'deploymentStatus']);
        Route::post('stores/{store}/provision-forge', [\App\Http\Controllers\Api\Admin\StoreController::class, 'provisionForge']);
        Route::put('stores/{store}/domain', [\App\Http\Controllers\Api\Admin\StoreController::class, 'updateDomain']);

        // Store Configuration (Payment Methods, Delivery Agents)
        Route::get('stores/{store}/payment-methods', [\App\Http\Controllers\Api\Admin\StoreConfigurationController::class, 'paymentMethods']);
        Route::put('stores/{store}/payment-methods', [\App\Http\Controllers\Api\Admin\StoreConfigurationController::class, 'updatePaymentMethods']);
        Route::get('payment-methods', [\App\Http\Controllers\Api\Admin\StoreConfigurationController::class, 'allPaymentMethods']);

        // Delivery Agents (restaurants only)
        Route::get('stores/{store}/delivery-agents', [\App\Http\Controllers\Api\Admin\StoreConfigurationController::class, 'deliveryAgents']);
        Route::post('stores/{store}/delivery-agents', [\App\Http\Controllers\Api\Admin\StoreConfigurationController::class, 'createDeliveryAgent']);
        Route::put('stores/{store}/delivery-agents/{deliveryAgent}', [\App\Http\Controllers\Api\Admin\StoreConfigurationController::class, 'updateDeliveryAgent']);
        Route::delete('stores/{store}/delivery-agents/{deliveryAgent}', [\App\Http\Controllers\Api\Admin\StoreConfigurationController::class, 'deleteDeliveryAgent']);

        // Billing & Subscriptions
        Route::apiResource('owners', \App\Http\Controllers\Api\Admin\OwnerController::class);
        Route::post('owners/{owner}/reset-password', [\App\Http\Controllers\Api\Admin\OwnerController::class, 'resetPassword']);
        Route::get('owners/{owner}/billing', [\App\Http\Controllers\Api\Admin\BillingController::class, 'ownerBilling']);

        // Users management (platform admins)
        Route::apiResource('users', \App\Http\Controllers\Api\Admin\UserController::class);
        Route::post('users/{user}/reset-password', [\App\Http\Controllers\Api\Admin\UserController::class, 'resetPassword']);

        // Subscription plans management
        Route::apiResource('plans', \App\Http\Controllers\Api\Admin\PlanController::class);

        // Billing & Subscriptions
        Route::get('billing/overview', [\App\Http\Controllers\Api\Admin\BillingController::class, 'overview']);
        Route::get('billing/subscriptions', [\App\Http\Controllers\Api\Admin\BillingController::class, 'subscriptions']);
        Route::get('billing/stores/{store}', [\App\Http\Controllers\Api\Admin\BillingController::class, 'storeBilling']);
        Route::post('billing/stores/{store}/extend-trial', [\App\Http\Controllers\Api\Admin\BillingController::class, 'extendTrial']);
        Route::post('billing/stores/{store}/cancel', [\App\Http\Controllers\Api\Admin\BillingController::class, 'cancelSubscription']);

        // Analytics
        Route::get('analytics/overview', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'overview']);
        Route::get('analytics/revenue', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'revenue']);
        Route::get('analytics/stores', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'stores']);

        // Platform Settings
        Route::get('settings', [\App\Http\Controllers\Api\Admin\SettingsController::class, 'index']);
        Route::get('settings/system-info', [\App\Http\Controllers\Api\Admin\SettingsController::class, 'systemInfo']);
        Route::get('settings/{group}', [\App\Http\Controllers\Api\Admin\SettingsController::class, 'show']);
        Route::put('settings/{group}', [\App\Http\Controllers\Api\Admin\SettingsController::class, 'update']);
        Route::post('settings', [\App\Http\Controllers\Api\Admin\SettingsController::class, 'store']);
        Route::delete('settings/{id}', [\App\Http\Controllers\Api\Admin\SettingsController::class, 'destroy']);
        Route::post('settings/initialize', [\App\Http\Controllers\Api\Admin\SettingsController::class, 'initialize']);
        Route::post('settings/test-email', [\App\Http\Controllers\Api\Admin\SettingsController::class, 'testEmail']);
        Route::post('settings/clear-cache', [\App\Http\Controllers\Api\Admin\SettingsController::class, 'clearCache']);
    });
});

// Owner authenticated routes
Route::prefix('owner')->middleware(['auth:sanctum', 'is.owner'])->group(function () {
    // Owner auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', [\App\Http\Controllers\Api\Owner\AuthController::class, 'logout']);
        Route::get('user', [\App\Http\Controllers\Api\Owner\AuthController::class, 'user']);
        Route::put('profile', [\App\Http\Controllers\Api\Owner\AuthController::class, 'updateProfile']);
        Route::put('password', [\App\Http\Controllers\Api\Owner\AuthController::class, 'updatePassword']);
    });

    // My stores
    Route::get('stores', [\App\Http\Controllers\Api\Owner\StoreController::class, 'index']);
    Route::post('stores', [\App\Http\Controllers\Api\Owner\StoreController::class, 'store']);
    Route::get('stores/{store}', [\App\Http\Controllers\Api\Owner\StoreController::class, 'show']);
    Route::put('stores/{store}', [\App\Http\Controllers\Api\Owner\StoreController::class, 'update']);

    // WhatsApp Account Management
    Route::prefix('whatsapp')->group(function () {
        Route::get('accounts', [\App\Http\Controllers\Api\Owner\WhatsAppOAuthController::class, 'index']);
        Route::get('oauth-url', [\App\Http\Controllers\Api\Owner\WhatsAppOAuthController::class, 'getOAuthUrl']);
        Route::post('accounts/{account}/disconnect', [\App\Http\Controllers\Api\Owner\WhatsAppOAuthController::class, 'disconnect']);
        Route::post('accounts/{account}/set-default', [\App\Http\Controllers\Api\Owner\WhatsAppOAuthController::class, 'setDefault']);
        Route::post('accounts/{account}/toggle-status', [\App\Http\Controllers\Api\Owner\WhatsAppOAuthController::class, 'toggleStatus']);
        Route::post('accounts/{account}/refresh', [\App\Http\Controllers\Api\Owner\WhatsAppOAuthController::class, 'refresh']);
    });

    // Store-specific resources (with store context)
    Route::prefix('{store}')->middleware('resolve.tenant')->group(function () {
        // Dashboard
        Route::get('dashboard', [\App\Http\Controllers\Api\Store\StoreController::class, 'dashboard']);

        // Categories
        Route::apiResource('categories', \App\Http\Controllers\Api\Store\CategoryController::class);

        // Products
        Route::apiResource('products', \App\Http\Controllers\Api\Store\ProductController::class);
        Route::post('products/{product}/variants', [\App\Http\Controllers\Api\Store\ProductController::class, 'storeVariant']);
        Route::put('products/{product}/variants/{variant}', [\App\Http\Controllers\Api\Store\ProductController::class, 'updateVariant']);
        Route::delete('products/{product}/variants/{variant}', [\App\Http\Controllers\Api\Store\ProductController::class, 'destroyVariant']);

        // Product Addons
        Route::apiResource('addons', \App\Http\Controllers\Api\Store\ProductAddonController::class);

        // Orders
        Route::apiResource('orders', \App\Http\Controllers\Api\Store\OrderController::class);
        Route::post('orders/{order}/status', [\App\Http\Controllers\Api\Store\OrderController::class, 'updateStatus']);
        Route::post('orders/{order}/cancel', [\App\Http\Controllers\Api\Store\OrderController::class, 'cancel']);

        // Customers
        Route::apiResource('customers', \App\Http\Controllers\Api\Store\CustomerController::class);

        // Employees
        Route::apiResource('employees', \App\Http\Controllers\Api\Store\EmployeeController::class);
        Route::post('employees/{employee}/clock-in', [\App\Http\Controllers\Api\Store\EmployeeController::class, 'clockIn']);
        Route::post('employees/{employee}/clock-out', [\App\Http\Controllers\Api\Store\EmployeeController::class, 'clockOut']);
        
        // Employee Auth
        Route::post('employees/auth/login', [\App\Http\Controllers\Api\Store\EmployeeAuthController::class, 'login']);
        Route::post('employees/auth/login-pin', [\App\Http\Controllers\Api\Store\EmployeeAuthController::class, 'loginWithPin']);
        Route::get('employees/{employee}/profile', [\App\Http\Controllers\Api\Store\EmployeeAuthController::class, 'profile']);
        Route::put('employees/{employee}/password', [\App\Http\Controllers\Api\Store\EmployeeAuthController::class, 'updatePassword']);
        Route::post('employees/{employee}/set-password', [\App\Http\Controllers\Api\Store\EmployeeAuthController::class, 'setPassword']);

        // Payments
        Route::get('payments', [\App\Http\Controllers\Api\Store\PaymentController::class, 'index']);
        Route::get('payments/{payment}', [\App\Http\Controllers\Api\Store\PaymentController::class, 'show']);
        Route::post('payments/{payment}/refund', [\App\Http\Controllers\Api\Store\PaymentController::class, 'refund']);

        // Payment Methods (Owner-defined)
        Route::apiResource('payment-methods', \App\Http\Controllers\Api\Owner\PaymentMethodController::class);
        Route::get('payment-methods-active', [\App\Http\Controllers\Api\Owner\PaymentMethodController::class, 'active']);

        // Delivery Agents (Restaurant only)
        Route::apiResource('delivery-agents', \App\Http\Controllers\Api\Owner\DeliveryAgentController::class);
        Route::get('delivery-agents-active', [\App\Http\Controllers\Api\Owner\DeliveryAgentController::class, 'active']);
        Route::get('delivery-agents-available', [\App\Http\Controllers\Api\Owner\DeliveryAgentController::class, 'available']);
        Route::get('delivery-agents/{id}/stats', [\App\Http\Controllers\Api\Owner\DeliveryAgentController::class, 'stats']);

        // Payment Verification (Order Payment Confirmation)
        Route::get('orders/pending-payment-verification', [\App\Http\Controllers\Api\Owner\OrderPaymentController::class, 'pendingVerification']);
        Route::get('orders/{id}/payment-screenshot', [\App\Http\Controllers\Api\Owner\OrderPaymentController::class, 'screenshot']);
        Route::post('orders/{id}/approve-payment', [\App\Http\Controllers\Api\Owner\OrderPaymentController::class, 'approve']);
        Route::post('orders/{id}/reject-payment', [\App\Http\Controllers\Api\Owner\OrderPaymentController::class, 'reject']);

        // AI Test Cases
        Route::apiResource('ai-tests', \App\Http\Controllers\Api\Owner\AITestController::class);
        Route::get('ai-tests-summary', [\App\Http\Controllers\Api\Owner\AITestController::class, 'summary']);
        Route::post('ai-tests/{id}/run', [\App\Http\Controllers\Api\Owner\AITestController::class, 'runTest']);
        Route::post('ai-tests-run-all', [\App\Http\Controllers\Api\Owner\AITestController::class, 'runAllTests']);

        // Subscription & Billing (Owner)
        Route::get('subscription', [\App\Http\Controllers\Api\Owner\SubscriptionController::class, 'show']);
        Route::get('subscription/plans', [\App\Http\Controllers\Api\Owner\SubscriptionController::class, 'plans']);
        Route::post('subscription/setup-intent', [\App\Http\Controllers\Api\Owner\SubscriptionController::class, 'createSetupIntent']);
        Route::post('subscription/subscribe', [\App\Http\Controllers\Api\Owner\SubscriptionController::class, 'subscribe']);
        Route::post('subscription/cancel', [\App\Http\Controllers\Api\Owner\SubscriptionController::class, 'cancel']);
        Route::post('subscription/resume', [\App\Http\Controllers\Api\Owner\SubscriptionController::class, 'resume']);
        Route::get('subscription/invoices', [\App\Http\Controllers\Api\Owner\SubscriptionController::class, 'invoices']);

        // CMS
        Route::apiResource('pages', \App\Http\Controllers\Api\Store\PageController::class);
        Route::apiResource('banners', \App\Http\Controllers\Api\Store\BannerController::class);
        Route::apiResource('coupons', \App\Http\Controllers\Api\Store\CouponController::class);

        // Settings
        Route::get('settings', [\App\Http\Controllers\Api\Store\SettingController::class, 'index']);
        Route::put('settings', [\App\Http\Controllers\Api\Store\SettingController::class, 'update']);

        // Analytics
        Route::get('analytics/overview', [\App\Http\Controllers\Api\Store\AnalyticsController::class, 'overview']);
        Route::get('analytics/sales', [\App\Http\Controllers\Api\Store\AnalyticsController::class, 'sales']);
        Route::get('analytics/products', [\App\Http\Controllers\Api\Store\AnalyticsController::class, 'products']);
        Route::get('analytics/customers', [\App\Http\Controllers\Api\Store\AnalyticsController::class, 'customers']);

        // Domains (Custom Domain Management)
        Route::get('domains', [\App\Http\Controllers\Owner\DomainController::class, 'index']);
        Route::post('domains', [\App\Http\Controllers\Owner\DomainController::class, 'store']);
        Route::post('domains/{domain}/verify', [\App\Http\Controllers\Owner\DomainController::class, 'verify']);
        Route::post('domains/{domain}/set-primary', [\App\Http\Controllers\Owner\DomainController::class, 'setPrimary']);
        Route::get('domains/{domain}/dns-instructions', [\App\Http\Controllers\Owner\DomainController::class, 'dnsInstructions']);
        Route::delete('domains/{domain}', [\App\Http\Controllers\Owner\DomainController::class, 'destroy']);

        // Meta Fields - Definitions (store-level field schemas)
        Route::get('meta-fields/types', [\App\Http\Controllers\Api\Store\MetaFieldController::class, 'types']);
        Route::get('meta-fields/{resourceType}/definitions', [\App\Http\Controllers\Api\Store\MetaFieldController::class, 'definitions']);
        Route::post('meta-fields/{resourceType}/definitions', [\App\Http\Controllers\Api\Store\MetaFieldController::class, 'createDefinition']);
        Route::put('meta-fields/{resourceType}/definitions/{definition}', [\App\Http\Controllers\Api\Store\MetaFieldController::class, 'updateDefinition']);
        Route::delete('meta-fields/{resourceType}/definitions/{definition}', [\App\Http\Controllers\Api\Store\MetaFieldController::class, 'deleteDefinition']);

        // Meta Fields - Values (resource-level field values)
        Route::get('meta-fields/{resourceType}/{resourceId}', [\App\Http\Controllers\Api\Store\MetaFieldController::class, 'index']);
        Route::post('meta-fields/{resourceType}/{resourceId}', [\App\Http\Controllers\Api\Store\MetaFieldController::class, 'store']);
        Route::post('meta-fields/{resourceType}/{resourceId}/bulk', [\App\Http\Controllers\Api\Store\MetaFieldController::class, 'storeBulk']);
        Route::get('meta-fields/{resourceType}/{resourceId}/{key}', [\App\Http\Controllers\Api\Store\MetaFieldController::class, 'show']);
        Route::put('meta-fields/{resourceType}/{resourceId}/{key}', [\App\Http\Controllers\Api\Store\MetaFieldController::class, 'update']);
        Route::delete('meta-fields/{resourceType}/{resourceId}/{key}', [\App\Http\Controllers\Api\Store\MetaFieldController::class, 'destroy']);
    });
});

// Employee authenticated routes - access store dashboard with limited permissions
Route::prefix('employee/{store}')->middleware(['auth:sanctum', 'is.employee', 'resolve.tenant'])->group(function () {
    // Employee auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', [\App\Http\Controllers\Api\Store\EmployeeAuthController::class, 'logout']);
        Route::get('me', [\App\Http\Controllers\Api\Store\EmployeeAuthController::class, 'me']);
    });

    // Dashboard - employees with any permission can view basic dashboard
    Route::get('dashboard', [\App\Http\Controllers\Api\Store\StoreController::class, 'dashboard']);

    // Categories - view only (requires products.view permission)
    Route::middleware('is.employee:products.view')->group(function () {
        Route::get('categories', [\App\Http\Controllers\Api\Store\CategoryController::class, 'index']);
        Route::get('categories/{category}', [\App\Http\Controllers\Api\Store\CategoryController::class, 'show']);
    });

    // Products - view (requires products.view), manage (requires products.manage)
    Route::middleware('is.employee:products.view')->group(function () {
        Route::get('products', [\App\Http\Controllers\Api\Store\ProductController::class, 'index']);
        Route::get('products/{product}', [\App\Http\Controllers\Api\Store\ProductController::class, 'show']);
    });
    Route::middleware('is.employee:products.manage')->group(function () {
        Route::post('products', [\App\Http\Controllers\Api\Store\ProductController::class, 'store']);
        Route::put('products/{product}', [\App\Http\Controllers\Api\Store\ProductController::class, 'update']);
        Route::delete('products/{product}', [\App\Http\Controllers\Api\Store\ProductController::class, 'destroy']);
    });

    // Orders - view (requires orders.view), manage (requires orders.manage)
    Route::middleware('is.employee:orders.view')->group(function () {
        Route::get('orders', [\App\Http\Controllers\Api\Store\OrderController::class, 'index']);
        Route::get('orders/{order}', [\App\Http\Controllers\Api\Store\OrderController::class, 'show']);
    });
    Route::middleware('is.employee:orders.manage')->group(function () {
        Route::post('orders', [\App\Http\Controllers\Api\Store\OrderController::class, 'store']);
        Route::put('orders/{order}', [\App\Http\Controllers\Api\Store\OrderController::class, 'update']);
        Route::post('orders/{order}/status', [\App\Http\Controllers\Api\Store\OrderController::class, 'updateStatus']);
        Route::post('orders/{order}/cancel', [\App\Http\Controllers\Api\Store\OrderController::class, 'cancel']);
    });

    // Customers - view only (requires customers.view)
    Route::middleware('is.employee:customers.view')->group(function () {
        Route::get('customers', [\App\Http\Controllers\Api\Store\CustomerController::class, 'index']);
        Route::get('customers/{customer}', [\App\Http\Controllers\Api\Store\CustomerController::class, 'show']);
    });

    // Reports/Analytics - requires reports.view
    Route::middleware('is.employee:reports.view')->group(function () {
        Route::get('analytics/overview', [\App\Http\Controllers\Api\Store\AnalyticsController::class, 'overview']);
        Route::get('analytics/sales', [\App\Http\Controllers\Api\Store\AnalyticsController::class, 'sales']);
        Route::get('analytics/products', [\App\Http\Controllers\Api\Store\AnalyticsController::class, 'products']);
        Route::get('analytics/customers', [\App\Http\Controllers\Api\Store\AnalyticsController::class, 'customers']);
    });

    // POS - requires pos.access
    Route::middleware('is.employee:pos.access')->group(function () {
        // POS specific endpoints would go here
    });
});
