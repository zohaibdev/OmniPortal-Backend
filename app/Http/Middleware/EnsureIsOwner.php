<?php

namespace App\Http\Middleware;

use App\Models\Owner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsOwner
{
    /**
     * Ensure the authenticated user is an Owner.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user instanceof Owner) {
            return response()->json([
                'message' => 'Unauthorized. Owner access required.',
            ], 403);
        }

        // Check owner status
        if ($user->status === Owner::STATUS_SUSPENDED) {
            return response()->json([
                'message' => 'Your account has been suspended.',
            ], 403);
        }

        if ($user->status === Owner::STATUS_PENDING) {
            return response()->json([
                'message' => 'Your account is pending approval.',
            ], 403);
        }

        return $next($request);
    }
}
