<?php

namespace App\Http\Controllers\Api;

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

    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('price')
            ->get();

        return response()->json(['plans' => $plans]);
    }

    public function current(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $subscription = $store->subscription;

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription',
            ], 404);
        }

        $subscription->load('plan');

        return response()->json($subscription);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'required|string',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        $subscription = $this->subscriptionService->createSubscription(
            $store,
            $plan,
            $request->payment_method
        );

        return response()->json([
            'message' => 'Subscription created',
            'subscription' => $subscription->load('plan'),
        ], 201);
    }

    public function changePlan(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        $subscription = $this->subscriptionService->changePlan(
            $store->subscription,
            $plan
        );

        return response()->json([
            'message' => 'Plan changed successfully',
            'subscription' => $subscription->load('plan'),
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        if (!$store->subscription) {
            return response()->json([
                'message' => 'No active subscription',
            ], 404);
        }

        $request->validate([
            'reason' => 'nullable|string',
            'immediately' => 'boolean',
        ]);

        $subscription = $this->subscriptionService->cancelSubscription(
            $store->subscription,
            $request->boolean('immediately'),
            $request->reason
        );

        return response()->json([
            'message' => 'Subscription cancelled',
            'subscription' => $subscription,
        ]);
    }

    public function resume(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        if (!$store->subscription) {
            return response()->json([
                'message' => 'No subscription found',
            ], 404);
        }

        $subscription = $this->subscriptionService->resumeSubscription(
            $store->subscription
        );

        return response()->json([
            'message' => 'Subscription resumed',
            'subscription' => $subscription->load('plan'),
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $invoices = $this->subscriptionService->getInvoices($store);

        return response()->json($invoices);
    }

    public function updatePaymentMethod(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'payment_method' => 'required|string',
        ]);

        $this->subscriptionService->updatePaymentMethod(
            $store,
            $request->payment_method
        );

        return response()->json([
            'message' => 'Payment method updated',
        ]);
    }
}
