<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Store;
use App\Models\DeliveryAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreConfigurationController extends Controller
{
    /**
     * Get payment methods for a store
     */
    public function paymentMethods(Store $store): JsonResponse
    {
        $methods = $store->paymentMethods()
            ->with('pivot')
            ->get()
            ->map(fn ($method) => [
                'id' => $method->id,
                'name' => $method->name,
                'type' => $method->type,
                'is_enabled' => $method->pivot->is_enabled,
                'display_order' => $method->pivot->display_order,
            ]);

        return response()->json([
            'data' => $methods,
        ]);
    }

    /**
     * Update store payment methods
     */
    public function updatePaymentMethods(Request $request, Store $store): JsonResponse
    {
        $request->validate([
            'methods' => 'required|array',
            'methods.*.id' => 'required|exists:payment_methods,id',
            'methods.*.is_enabled' => 'required|boolean',
            'methods.*.display_order' => 'required|integer',
        ]);

        $store->paymentMethods()->detach();

        foreach ($request->input('methods') as $method) {
            $store->paymentMethods()->attach($method['id'], [
                'is_enabled' => $method['is_enabled'],
                'display_order' => $method['display_order'],
            ]);
        }

        return response()->json([
            'message' => 'Payment methods updated successfully',
            'data' => $store->paymentMethods()->with('pivot')->get(),
        ]);
    }

    /**
     * Get delivery agents for a restaurant
     */
    public function deliveryAgents(Store $store): JsonResponse
    {
        if ($store->business_type !== 'restaurant') {
            return response()->json([
                'message' => 'Delivery agents are only available for restaurants',
            ], 422);
        }

        $agents = $store->deliveryAgents()->get();

        return response()->json([
            'data' => $agents,
        ]);
    }

    /**
     * Create delivery agent
     */
    public function createDeliveryAgent(Request $request, Store $store): JsonResponse
    {
        if ($store->business_type !== 'restaurant') {
            return response()->json([
                'message' => 'Delivery agents are only available for restaurants',
            ], 422);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
        ]);

        $agent = $store->deliveryAgents()->create($request->validated());

        return response()->json([
            'message' => 'Delivery agent created',
            'data' => $agent,
        ], 201);
    }

    /**
     * Update delivery agent
     */
    public function updateDeliveryAgent(Request $request, Store $store, DeliveryAgent $agent): JsonResponse
    {
        if ($agent->store_id !== $store->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'name' => 'string|max:255',
            'phone' => 'string|max:20',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $agent->update($request->validated());

        return response()->json([
            'message' => 'Delivery agent updated',
            'data' => $agent,
        ]);
    }

    /**
     * Delete delivery agent
     */
    public function deleteDeliveryAgent(Store $store, DeliveryAgent $agent): JsonResponse
    {
        if ($agent->store_id !== $store->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $agent->delete();

        return response()->json([
            'message' => 'Delivery agent deleted',
        ]);
    }

    /**
     * Get all available payment methods
     */
    public function allPaymentMethods(): JsonResponse
    {
        $methods = PaymentMethod::active()->get();

        return response()->json([
            'data' => $methods,
        ]);
    }
}
