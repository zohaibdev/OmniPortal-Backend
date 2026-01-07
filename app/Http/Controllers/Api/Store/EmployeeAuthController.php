<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmployeeAuthController extends Controller
{
    /**
     * Employee login with email and password
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $employee = Employee::where('email', $request->email)->first();

        if (!$employee) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if employee has a password set
        if (empty($employee->password)) {
            throw ValidationException::withMessages([
                'email' => ['No password set for this account. Please contact your manager.'],
            ]);
        }

        if (!Hash::check($request->password, $employee->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($employee->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your account is not active. Please contact your manager.'],
            ]);
        }

        // Get the store from the request (set by resolve.tenant middleware)
        $store = $request->attributes->get('store');
        
        // Create a token for the employee (store in central DB with store_id reference)
        $token = $employee->createToken(
            'employee-token', 
            $employee->permissions ?? ['*'], 
            null, 
            $store->id // Pass store_id for tenant resolution
        )->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'employee' => $employee,
            'store' => $store,
            'token' => $token,
        ]);
    }

    /**
     * Employee login with PIN (for POS)
     */
    public function loginWithPin(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => 'required|string',
        ]);

        $employee = Employee::where('pin', $request->pin)
            ->where('status', 'active')
            ->first();

        if (!$employee) {
            throw ValidationException::withMessages([
                'pin' => ['Invalid PIN.'],
            ]);
        }

        // Get the store from the request
        $store = $request->attributes->get('store');
        
        // Create a token for the employee (POS access, store in central DB with store_id)
        $token = $employee->createToken(
            'employee-pos-token', 
            $employee->permissions ?? ['*'], 
            null, 
            $store->id
        )->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'employee' => $employee,
            'store' => $store,
            'token' => $token,
        ]);
    }

    /**
     * Get current employee profile
     */
    public function profile(Request $request, string $storeId, int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);
        
        return response()->json([
            'employee' => $employee,
        ]);
    }

    /**
     * Update employee password
     */
    public function updatePassword(Request $request, string $storeId, int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        $request->validate([
            'current_password' => 'required_with:new_password|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        // If employee has a password, verify current password
        if (!empty($employee->password) && $request->has('current_password')) {
            if (!Hash::check($request->current_password, $employee->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Current password is incorrect.'],
                ]);
            }
        }

        $employee->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }

    /**
     * Set initial password (for first-time setup)
     */
    public function setPassword(Request $request, string $storeId, int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $employee->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password set successfully',
        ]);
    }

    /**
     * Get current authenticated employee
     */
    public function me(Request $request): JsonResponse
    {
        $employee = $request->user();
        $store = $request->attributes->get('store');
        
        return response()->json([
            'employee' => $employee,
            'store' => $store,
        ]);
    }

    /**
     * Logout employee (revoke current token)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
