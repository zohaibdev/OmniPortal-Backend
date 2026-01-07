<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login owner
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $owner = Owner::where('email', $request->email)->first();

        if (!$owner || !Hash::check($request->password, $owner->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($owner->status === Owner::STATUS_SUSPENDED) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been suspended. Please contact support.'],
            ]);
        }

        if ($owner->status === Owner::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'email' => ['Your account is pending approval. Please wait for activation.'],
            ]);
        }

        $token = $owner->createToken('owner-token', ['owner'])->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'owner' => $owner,
            'token' => $token,
        ]);
    }

    /**
     * Get authenticated owner
     */
    public function user(Request $request): JsonResponse
    {
        $owner = $request->user();
        
        return response()->json([
            'owner' => $owner->load('stores'),
        ]);
    }

    /**
     * Logout owner
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Update owner profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $owner = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
        ]);

        $owner->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'owner' => $owner,
        ]);
    }

    /**
     * Update owner password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $owner = $request->user();

        if (!Hash::check($request->current_password, $owner->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $owner->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }
}
