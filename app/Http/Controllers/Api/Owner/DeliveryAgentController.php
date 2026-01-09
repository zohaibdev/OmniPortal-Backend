<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DeliveryAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeliveryAgentController extends Controller
{
    /**
     * Get all delivery agents
     */
    public function index(): JsonResponse
    {
        $agents = DeliveryAgent::withCount('orders')->get();

        return response()->json($agents);
    }

    /**
     * Get active delivery agents
     */
    public function active(): JsonResponse
    {
        $agents = DeliveryAgent::active()->withCount('orders')->get();

        return response()->json($agents);
    }

    /**
     * Get available delivery agents
     */
    public function available(): JsonResponse
    {
        $agents = DeliveryAgent::available()->withCount('orders')->get();

        return response()->json($agents);
    }

    /**
     * Create delivery agent
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email',
            'is_active' => 'boolean',
            'max_orders' => 'integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $agent = DeliveryAgent::create($validator->validated());

        return response()->json($agent, 201);
    }

    /**
     * Update delivery agent
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $agent = DeliveryAgent::find($id);

        if (!$agent) {
            return response()->json(['message' => 'Delivery agent not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|string|max:20',
            'email' => 'nullable|email',
            'is_active' => 'boolean',
            'max_orders' => 'integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $agent->update($validator->validated());

        return response()->json($agent);
    }

    /**
     * Delete delivery agent
     */
    public function destroy(int $id): JsonResponse
    {
        $agent = DeliveryAgent::find($id);

        if (!$agent) {
            return response()->json(['message' => 'Delivery agent not found'], 404);
        }

        if ($agent->current_orders > 0) {
            return response()->json([
                'message' => 'Cannot delete agent with active orders',
            ], 422);
        }

        $agent->delete();

        return response()->json(['message' => 'Delivery agent deleted']);
    }

    /**
     * Get agent statistics
     */
    public function stats(int $id): JsonResponse
    {
        $agent = DeliveryAgent::withCount(['orders'])->find($id);

        if (!$agent) {
            return response()->json(['message' => 'Delivery agent not found'], 404);
        }

        return response()->json([
            'agent' => $agent,
            'current_orders' => $agent->current_orders,
            'total_orders' => $agent->orders_count,
            'is_available' => $agent->isAvailable(),
        ]);
    }
}
