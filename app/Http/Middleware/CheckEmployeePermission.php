<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckEmployeePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $store = $request->store ?? app('current.store');

        if (!$user || !$store) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Store owners have all permissions
        if ($user->type === 'owner' && $store->owner_id === $user->id) {
            return $next($request);
        }

        // Check employee permissions
        if ($user->type === 'employee') {
            $employee = Employee::where('user_id', $user->id)
                ->where('store_id', $store->id)
                ->where('is_active', true)
                ->first();

            if (!$employee) {
                return response()->json([
                    'message' => 'You are not an employee of this store',
                ], 403);
            }

            foreach ($permissions as $permission) {
                if (!$employee->hasPermission($permission)) {
                    return response()->json([
                        'message' => 'You do not have permission to perform this action',
                        'required_permission' => $permission,
                    ], 403);
                }
            }

            // Attach employee to request
            $request->merge(['employee' => $employee]);
        }

        return $next($request);
    }
}
