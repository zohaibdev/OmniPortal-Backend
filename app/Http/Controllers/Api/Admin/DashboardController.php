<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Models\Order;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $stats = [
            'total_stores' => Store::count(),
            'active_stores' => Store::where('is_active', true)->count(),
            'total_users' => User::count(),
            'total_orders' => Order::count(),
            'total_revenue' => Order::where('payment_status', 'paid')->sum('total'),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'mrr' => Subscription::where('status', 'active')
                ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
                ->sum('subscription_plans.price'),
        ];

        return response()->json($stats);
    }

    public function recentStores(): JsonResponse
    {
        $stores = Store::with('owner')
            ->withCount(['orders', 'products'])
            ->latest()
            ->limit(10)
            ->get();

        return response()->json($stores);
    }

    public function revenueChart(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);

        $data = Order::where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }
}
