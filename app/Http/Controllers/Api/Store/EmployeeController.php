<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Employee::query();
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        
        $employees = $query->latest()->paginate(20);

        return response()->json($employees);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => 'required|email|max:100',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:manager,staff,cashier,delivery',
            'permissions' => 'nullable|array',
            'pin' => 'nullable|string|max:10',
            'password' => 'nullable|string|min:6',
            'hourly_rate' => 'nullable|numeric|min:0',
            'hire_date' => 'nullable|date',
        ]);

        $employeeData = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role' => $request->role,
            'permissions' => $request->permissions ?? [],
            'pin' => $request->pin,
            'hourly_rate' => $request->hourly_rate,
            'hire_date' => $request->hire_date,
            'status' => 'active',
        ];

        // Hash password if provided
        if ($request->filled('password')) {
            $employeeData['password'] = Hash::make($request->password);
        }

        $employee = Employee::create($employeeData);

        return response()->json([
            'message' => 'Employee created',
            'employee' => $employee,
        ], 201);
    }

    public function show(Request $request, string $storeId, int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);
        return response()->json(['employee' => $employee]);
    }

    public function update(Request $request, string $storeId, int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);
        
        $request->validate([
            'first_name' => 'sometimes|string|max:50',
            'last_name' => 'sometimes|string|max:50',
            'email' => 'sometimes|email|max:100',
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|in:manager,staff,cashier,delivery',
            'permissions' => 'nullable|array',
            'pin' => 'nullable|string|max:10',
            'password' => 'nullable|string|min:6',
            'hourly_rate' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:active,inactive',
        ]);
        
        $updateData = $request->only([
            'first_name', 'last_name', 'email', 'phone', 
            'role', 'permissions', 'pin', 'hourly_rate', 'status'
        ]);

        // Hash password if provided
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }
        
        $employee->update($updateData);

        return response()->json([
            'message' => 'Employee updated',
            'employee' => $employee->fresh(),
        ]);
    }

    public function destroy(Request $request, string $storeId, int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);
        $employee->update(['status' => 'inactive']);

        return response()->json(['message' => 'Employee deactivated']);
    }

    public function clockIn(Request $request, string $storeId, int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);
        
        // Create a new shift record
        $employee->shifts()->create([
            'clock_in' => now(),
        ]);

        return response()->json(['message' => 'Clocked in']);
    }

    public function clockOut(Request $request, string $storeId, int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);
        
        // Update the latest open shift
        $shift = $employee->shifts()->whereNull('clock_out')->latest()->first();
        if ($shift) {
            $clockIn = $shift->clock_in;
            $clockOut = now();
            $totalHours = $clockOut->diffInMinutes($clockIn) / 60;
            
            $shift->update([
                'clock_out' => $clockOut,
                'total_hours' => round($totalHours, 2),
            ]);
        }

        return response()->json(['message' => 'Clocked out']);
    }
}
