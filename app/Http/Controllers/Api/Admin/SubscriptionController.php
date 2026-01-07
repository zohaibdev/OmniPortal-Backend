<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::with(['store', 'plan']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        $subscriptions = $query->latest()->paginate(20);

        return response()->json($subscriptions);
    }

    public function show(Subscription $subscription): JsonResponse
    {
        $subscription->load(['store.owner', 'plan']);

        return response()->json($subscription);
    }

    public function cancel(Subscription $subscription): JsonResponse
    {
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'message' => 'Subscription cancelled',
            'subscription' => $subscription->fresh(),
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = [
            'total' => Subscription::count(),
            'active' => Subscription::where('status', 'active')->count(),
            'cancelled' => Subscription::where('status', 'cancelled')->count(),
            'trial' => Subscription::where('status', 'trial')->count(),
            'mrr' => Subscription::where('status', 'active')
                ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
                ->sum('subscription_plans.price'),
            'by_plan' => Subscription::where('status', 'active')
                ->selectRaw('plan_id, COUNT(*) as count')
                ->groupBy('plan_id')
                ->with('plan:id,name')
                ->get(),
        ];

        return response()->json($stats);
    }
}
