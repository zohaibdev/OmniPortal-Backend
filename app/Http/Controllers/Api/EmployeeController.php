<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with('user');

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('search')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $employees = $query->latest()->paginate(20);

        return response()->json($employees);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:manager,cashier,kitchen,delivery',
            'permissions' => 'nullable|array',
            'hourly_rate' => 'nullable|numeric|min:0',
            'pin' => 'nullable|string|size:4',
        ]);

        // Create user account
        $user = \App\Models\User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make(Str::random(12)),
            'type' => 'employee',
        ]);

        // Create employee record
        $employee = Employee::create([
            'user_id' => $user->id,
            'role' => $request->role,
            'permissions' => $request->permissions ?? [],
            'hourly_rate' => $request->hourly_rate,
            'pin' => $request->pin,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Employee created successfully',
            'employee' => $employee->load('user'),
        ], 201);
    }

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $employee->load('user');

        return response()->json($employee);
    }

    public function update(Request $request, Employee $employee): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|in:manager,cashier,kitchen,delivery',
            'permissions' => 'nullable|array',
            'hourly_rate' => 'nullable|numeric|min:0',
            'pin' => 'nullable|string|size:4',
            'is_active' => 'boolean',
        ]);

        if ($request->has('name') || $request->has('phone')) {
            $employee->user->update($request->only(['name', 'phone']));
        }

        $employee->update($request->only([
            'role', 'permissions', 'hourly_rate', 'pin', 'is_active'
        ]));

        return response()->json([
            'message' => 'Employee updated',
            'employee' => $employee->fresh('user'),
        ]);
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $employee->update(['is_active' => false]);

        return response()->json([
            'message' => 'Employee deactivated',
        ]);
    }

    public function verifyPin(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'pin' => 'required|string|size:4',
        ]);

        $employee = Employee::where('pin', $request->pin)
            ->where('is_active', true)
            ->with('user')
            ->first();

        if (!$employee) {
            return response()->json(['message' => 'Invalid PIN'], 401);
        }

        return response()->json([
            'employee' => $employee,
        ]);
    }
}
