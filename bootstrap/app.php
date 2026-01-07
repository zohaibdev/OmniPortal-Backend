<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Disable CSRF for API routes
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'sanctum/csrf-cookie',
        ]);

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'resolve.tenant' => \App\Http\Middleware\ResolveTenant::class,
            'store.active' => \App\Http\Middleware\EnsureStoreIsActive::class,
            'subscription.active' => \App\Http\Middleware\EnsureHasActiveSubscription::class,
            'user.type' => \App\Http\Middleware\EnsureUserType::class,
            'employee.permission' => \App\Http\Middleware\CheckEmployeePermission::class,
            'is.owner' => \App\Http\Middleware\EnsureIsOwner::class,
            'is.employee' => \App\Http\Middleware\IsEmployee::class,
            'is.owner.or.employee' => \App\Http\Middleware\IsOwnerOrEmployee::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
