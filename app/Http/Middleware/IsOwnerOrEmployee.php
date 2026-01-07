<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\Owner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsOwnerOrEmployee
{
    /**
     * Handle an incoming request - check if the authenticated user is an Owner or Employee.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $isOwner = $user instanceof Owner;
        $isEmployee = $user instanceof Employee;

        if (!$isOwner && !$isEmployee) {
            return response()->json([
                'message' => 'Access denied.',
            ], 403);
        }

        // Owners have full access - no permission check needed
        if ($isOwner) {
            // Attach user type to request for downstream use
            $request->attributes->set('user_type', 'owner');
            return $next($request);
        }

        // For employees, check if active
        if ($isEmployee && $user->status !== 'active') {
            return response()->json([
                'message' => 'Your account is not active.',
            ], 403);
        }

        // Check permissions for employees if specified
        if ($isEmployee && !empty($permissions)) {
            foreach ($permissions as $permission) {
                if (!$user->hasPermission($permission)) {
                    return response()->json([
                        'message' => 'You do not have permission to perform this action.',
                        'required_permission' => $permission,
                    ], 403);
                }
            }
        }

        // Attach user type to request
        $request->attributes->set('user_type', 'employee');
        
        return $next($request);
    }
}
