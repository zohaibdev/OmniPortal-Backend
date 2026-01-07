<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->store ?? app('current.store');

        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        $subscription = $store->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
                'error' => 'subscription_required',
            ], 402);
        }

        if (!$subscription->isActive()) {
            return response()->json([
                'message' => 'Subscription is not active',
                'error' => 'subscription_inactive',
                'status' => $subscription->status,
            ], 402);
        }

        return $next($request);
    }
}
