<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::withCount('subscriptions')
            ->orderBy('price')
            ->get();

        return response()->json($plans);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:subscription_plans',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'interval' => 'required|in:monthly,yearly',
            'currency' => 'sometimes|string|max:3',
            'trial_days' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'max_products' => 'nullable|integer|min:0',
            'max_orders_per_month' => 'nullable|integer|min:0',
            'max_employees' => 'nullable|integer|min:0',
            'custom_domain_allowed' => 'boolean',
            'pos_enabled' => 'boolean',
            'multi_currency_enabled' => 'boolean',
            'advanced_analytics' => 'boolean',
            'priority_support' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $plan = SubscriptionPlan::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Plan created successfully',
            'data' => $plan,
        ], 201);
    }

    public function show(SubscriptionPlan $plan): JsonResponse
    {
        $plan->loadCount('subscriptions');

        return response()->json($plan);
    }

    public function update(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:subscription_plans,slug,' . $plan->id,
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'interval' => 'sometimes|in:monthly,yearly',
            'currency' => 'sometimes|string|max:3',
            'trial_days' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'max_products' => 'nullable|integer|min:0',
            'max_orders_per_month' => 'nullable|integer|min:0',
            'max_employees' => 'nullable|integer|min:0',
            'custom_domain_allowed' => 'boolean',
            'pos_enabled' => 'boolean',
            'multi_currency_enabled' => 'boolean',
            'advanced_analytics' => 'boolean',
            'priority_support' => 'boolean',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $plan->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Plan updated successfully',
            'data' => $plan->fresh(),
        ]);
    }

    public function destroy(SubscriptionPlan $plan): JsonResponse
    {
        if ($plan->subscriptions()->where('status', 'active')->exists()) {
            return response()->json([
                'message' => 'Cannot delete plan with active subscriptions',
            ], 400);
        }

        $plan->delete();

        return response()->json([
            'message' => 'Plan deleted',
        ]);
    }
}
