<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class OwnerController extends Controller
{
    /**
     * Display a listing of owners.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Owner::withCount('stores');

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $owners = $query->latest()->paginate(20);

        return response()->json($owners);
    }

    /**
     * Store a newly created owner.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:owners'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in(['pending', 'active', 'suspended'])],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $owner = Owner::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Owner created successfully',
            'data' => $owner,
        ], 201);
    }

    /**
     * Display the specified owner.
     */
    public function show(Owner $owner): JsonResponse
    {
        $owner->load(['stores.activeSubscription.plan']);
        $owner->loadCount('stores');

        return response()->json([
            'success' => true,
            'data' => $owner,
        ]);
    }

    /**
     * Update the specified owner.
     */
    public function update(Request $request, Owner $owner): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:191', Rule::unique('owners')->ignore($owner->id)],
            'password' => ['sometimes', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['sometimes', Rule::in(['pending', 'active', 'suspended'])],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        // Check if status is being changed to suspended
        $beingSuspended = isset($validated['status']) 
            && $validated['status'] === Owner::STATUS_SUSPENDED 
            && $owner->status !== Owner::STATUS_SUSPENDED;

        $owner->update($validated);

        // When owner is suspended, revoke tokens and suspend all their stores
        if ($beingSuspended) {
            $owner->tokens()->delete();
            
            // Suspend all stores belonging to this owner
            $owner->stores()->update(['status' => 'suspended']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Owner updated successfully',
            'data' => $owner->fresh(),
        ]);
    }

    /**
     * Remove the specified owner.
     */
    public function destroy(Owner $owner): JsonResponse
    {
        // Check if owner has active stores
        if ($owner->stores()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete owner with existing stores. Please delete or transfer stores first.',
            ], 422);
        }

        $owner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Owner deleted successfully',
        ]);
    }

    /**
     * Get owner statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total' => Owner::count(),
            'active' => Owner::where('status', 'active')->count(),
            'pending' => Owner::where('status', 'pending')->count(),
            'suspended' => Owner::where('status', 'suspended')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Reset owner password.
     */
    public function resetPassword(Request $request, Owner $owner): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        $owner->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Revoke all existing tokens
        $owner->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. Owner has been logged out from all devices.',
        ]);
    }
}
