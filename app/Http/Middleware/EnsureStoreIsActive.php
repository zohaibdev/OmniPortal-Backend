<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->store ?? app('current.store');

        if (!$store) {
            return response()->json([
                'message' => 'Store not found',
            ], 404);
        }

        if (!$store->is_active) {
            return response()->json([
                'message' => 'This store is currently disabled',
            ], 403);
        }

        if ($store->status === 'suspended') {
            return response()->json([
                'message' => 'This store has been suspended',
            ], 403);
        }

        return $next($request);
    }
}
