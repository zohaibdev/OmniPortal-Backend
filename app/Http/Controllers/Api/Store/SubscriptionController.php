<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $subscription = Subscription::where('store_id', $store->id)
            ->with('plan')
            ->first();

        return response()->json(['subscription' => $subscription]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'required|string',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        $subscription = $this->subscriptionService->subscribe(
            $store,
            $plan,
            $request->payment_method
        );

        return response()->json([
            'message' => 'Subscribed successfully',
            'subscription' => $subscription,
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $subscription = Subscription::where('store_id', $store->id)->first();

        if (!$subscription) {
            return response()->json(['message' => 'No active subscription'], 404);
        }

        $this->subscriptionService->cancel($subscription);

        return response()->json(['message' => 'Subscription cancelled']);
    }

    public function resume(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $subscription = Subscription::where('store_id', $store->id)->first();

        if (!$subscription) {
            return response()->json(['message' => 'No subscription found'], 404);
        }

        $this->subscriptionService->resume($subscription);

        return response()->json(['message' => 'Subscription resumed']);
    }

    public function changePlan(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $subscription = Subscription::where('store_id', $store->id)->first();
        $newPlan = SubscriptionPlan::findOrFail($request->plan_id);

        $subscription = $this->subscriptionService->changePlan($subscription, $newPlan);

        return response()->json([
            'message' => 'Plan changed successfully',
            'subscription' => $subscription,
        ]);
    }
}
