<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentMethodController extends Controller
{
    /**
     * Get all payment methods
     */
    public function index(): JsonResponse
    {
        $paymentMethods = PaymentMethod::orderBy('sort_order')->get();

        return response()->json($paymentMethods);
    }

    /**
     * Get active payment methods
     */
    public function active(): JsonResponse
    {
        $paymentMethods = PaymentMethod::active()->get();

        return response()->json($paymentMethods);
    }

    /**
     * Create payment method
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'type' => 'required|in:offline,online',
            'instructions' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $paymentMethod = PaymentMethod::create($validator->validated());

        return response()->json($paymentMethod, 201);
    }

    /**
     * Update payment method
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $paymentMethod = PaymentMethod::find($id);

        if (!$paymentMethod) {
            return response()->json(['message' => 'Payment method not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:50',
            'type' => 'sometimes|in:offline,online',
            'instructions' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $paymentMethod->update($validator->validated());

        return response()->json($paymentMethod);
    }

    /**
     * Delete payment method
     */
    public function destroy(int $id): JsonResponse
    {
        $paymentMethod = PaymentMethod::find($id);

        if (!$paymentMethod) {
            return response()->json(['message' => 'Payment method not found'], 404);
        }

        $paymentMethod->delete();

        return response()->json(['message' => 'Payment method deleted']);
    }
}
