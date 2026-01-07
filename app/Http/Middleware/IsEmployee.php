<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsEmployee
{
    /**
     * Handle an incoming request - check if the authenticated user is an Employee.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Check if the authenticated user is an Employee
        if (!($user instanceof \App\Models\Employee)) {
            return response()->json([
                'message' => 'Access denied. Employee access only.',
            ], 403);
        }

        // Check if the employee is active
        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active.',
            ], 403);
        }

        // Check permissions if specified
        if (!empty($permissions)) {
            foreach ($permissions as $permission) {
                if (!$user->hasPermission($permission)) {
                    return response()->json([
                        'message' => 'You do not have permission to perform this action.',
                        'required_permission' => $permission,
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
