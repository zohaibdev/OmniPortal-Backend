<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function overview(): JsonResponse
    {
        // Calculate revenue this month from subscriptions
        $revenueThisMonth = Subscription::where('status', 'active')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->sum('subscription_plans.price');

        // Stores by status
        $storesByStatus = Store::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Revenue chart for last 30 days
        $revenueChart = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $amount = Subscription::where('status', 'active')
                ->whereDate('created_at', $date)
                ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
                ->sum('subscription_plans.price');
            $revenueChart[] = [
                'date' => $date,
                'amount' => (float) $amount,
            ];
        }

        $stats = [
            'total_stores' => Store::count(),
            'active_stores' => Store::where('is_active', true)->where('status', Store::STATUS_ACTIVE)->count(),
            'total_users' => User::count(),
            'total_revenue' => (float) Subscription::where('status', 'active')
                ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
                ->sum('subscription_plans.price'),
            'revenue_this_month' => (float) $revenueThisMonth,
            'new_stores_this_month' => Store::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'new_users_this_month' => User::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'stores_by_status' => $storesByStatus,
            'revenue_chart' => $revenueChart,
        ];

        return response()->json($stats);
    }

    public function revenue(Request $request): JsonResponse
    {
        $period = $request->input('period', 'month');

        $query = Subscription::query()
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('subscriptions.status', 'active');

        $revenue = match ($period) {
            'week' => $query->selectRaw('DATE(subscriptions.created_at) as date, SUM(subscription_plans.price) as total')
                ->where('subscriptions.created_at', '>=', now()->subWeek())
                ->groupBy('date')
                ->get(),
            'month' => $query->selectRaw('DATE(subscriptions.created_at) as date, SUM(subscription_plans.price) as total')
                ->where('subscriptions.created_at', '>=', now()->subMonth())
                ->groupBy('date')
                ->get(),
            'year' => $query->selectRaw('MONTH(subscriptions.created_at) as month, SUM(subscription_plans.price) as total')
                ->where('subscriptions.created_at', '>=', now()->subYear())
                ->groupBy('month')
                ->get(),
            default => $query->selectRaw('DATE(subscriptions.created_at) as date, SUM(subscription_plans.price) as total')
                ->groupBy('date')
                ->get(),
        };

        return response()->json(['revenue' => $revenue]);
    }

    public function stores(Request $request): JsonResponse
    {
        // Get stores with subscription info (orders are in tenant DBs, can't cross-query)
        $stores = Store::with(['activeSubscription.plan'])
            ->where('is_active', true)
            ->latest()
            ->limit(10)
            ->get();

        // For each store, try to get order stats from tenant database
        $topStores = $stores->map(function ($store) {
            $orderCount = 0;
            $revenue = 0;

            if ($store->database_name) {
                try {
                    $tenantService = app(\App\Services\TenantDatabaseService::class);
                    $tenantService->configureTenantConnection($store);

                    $orderCount = \DB::connection('tenant')->table('orders')->count();
                    $revenue = (float) \DB::connection('tenant')
                        ->table('orders')
                        ->where('payment_status', 'paid')
                        ->sum('total');
                } catch (\Exception $e) {
                    // Silently fail for stores without proper tenant DB
                }
            }

            return [
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status,
                'is_active' => $store->is_active,
                'order_count' => $orderCount,
                'revenue' => $revenue,
                'subscription' => $store->activeSubscription,
            ];
        })->sortByDesc('revenue')->values();

        $storesByPlan = DB::table('subscriptions')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->where('subscriptions.status', 'active')
            ->selectRaw('subscription_plans.name, COUNT(*) as count')
            ->groupBy('subscription_plans.id', 'subscription_plans.name')
            ->get();

        return response()->json([
            'top_stores' => $topStores,
            'stores_by_plan' => $storesByPlan,
        ]);
    }
}
