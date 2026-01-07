<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');
        $period = $request->input('period', 'week');

        \Log::info('Analytics overview called', [
            'store_id' => $store ? $store->id : 'none',
            'store_db' => $store ? $store->database_name : 'none',
        ]);

        try {
            // Get date ranges
            $now = Carbon::now();
            $weekStart = $now->copy()->startOfWeek();
            $weekEnd = $now->copy()->endOfWeek();
            $monthStart = $now->copy()->startOfMonth();
            $monthEnd = $now->copy()->endOfMonth();
            $lastWeekStart = $now->copy()->subWeek()->startOfWeek();
            $lastWeekEnd = $now->copy()->subWeek()->endOfWeek();

            // Get daily revenue for last 7 days
            $dailyRevenue = [];
            $dailyOrders = [];
            $dailyLabels = [];
            
            for ($i = 6; $i >= 0; $i--) {
                $date = $now->copy()->subDays($i);
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();
                
                $dayRevenue = DB::connection('tenant')->table('orders')
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->whereNotIn('status', ['cancelled'])
                    ->sum('total');
                
                $dayOrderCount = DB::connection('tenant')->table('orders')
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->whereNotIn('status', ['cancelled'])
                    ->count();
                
                $dailyRevenue[] = (float) $dayRevenue;
                $dailyOrders[] = $dayOrderCount;
                $dailyLabels[] = $date->format('D');
            }

            // Total revenue this week
            $totalRevenue = DB::connection('tenant')->table('orders')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->whereNotIn('status', ['cancelled'])
                ->sum('total');

            // Last week revenue for comparison
            $lastWeekRevenue = DB::connection('tenant')->table('orders')
                ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
                ->whereNotIn('status', ['cancelled'])
                ->sum('total');

            // Total orders this week
            $totalOrders = DB::connection('tenant')->table('orders')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();

            // Last week orders for comparison
            $lastWeekOrders = DB::connection('tenant')->table('orders')
                ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
                ->count();

            // Average order value
            $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
            $lastWeekAvg = $lastWeekOrders > 0 ? $lastWeekRevenue / $lastWeekOrders : 0;

            // New customers this week
            $newCustomers = DB::connection('tenant')->table('customers')
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->count();

            // Last week new customers
            $lastWeekCustomers = DB::connection('tenant')->table('customers')
                ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
                ->count();

            // Order types breakdown
            $orderTypes = DB::connection('tenant')->table('orders')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->whereNotIn('status', ['cancelled'])
                ->select('order_type', DB::raw('COUNT(*) as count'))
                ->groupBy('order_type')
                ->pluck('count', 'order_type')
                ->toArray();

            $dineIn = $orderTypes['dine_in'] ?? $orderTypes['dine-in'] ?? 0;
            $takeaway = $orderTypes['takeaway'] ?? $orderTypes['pickup'] ?? 0;
            $delivery = $orderTypes['delivery'] ?? 0;

            // Top products
            $topProducts = DB::connection('tenant')->table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->whereBetween('orders.created_at', [$weekStart, $weekEnd])
                ->whereNotIn('orders.status', ['cancelled'])
                ->select('products.name', DB::raw('SUM(order_items.quantity) as quantity'), DB::raw('SUM(order_items.total) as revenue'))
                ->groupBy('products.id', 'products.name')
                ->orderByDesc('quantity')
                ->limit(5)
                ->get();

            // Calculate percentage changes
            $revenueChange = $lastWeekRevenue > 0 
                ? (($totalRevenue - $lastWeekRevenue) / $lastWeekRevenue) * 100 
                : ($totalRevenue > 0 ? 100 : 0);

            $ordersChange = $lastWeekOrders > 0 
                ? (($totalOrders - $lastWeekOrders) / $lastWeekOrders) * 100 
                : ($totalOrders > 0 ? 100 : 0);

            $avgChange = $lastWeekAvg > 0 
                ? (($avgOrderValue - $lastWeekAvg) / $lastWeekAvg) * 100 
                : ($avgOrderValue > 0 ? 100 : 0);

            $customersChange = $lastWeekCustomers > 0 
                ? (($newCustomers - $lastWeekCustomers) / $lastWeekCustomers) * 100 
                : ($newCustomers > 0 ? 100 : 0);

            return response()->json([
                'daily_revenue' => $dailyRevenue,
                'daily_orders' => $dailyOrders,
                'daily_labels' => $dailyLabels,
                'order_types' => [$dineIn, $takeaway, $delivery],
                'total_revenue' => (float) $totalRevenue,
                'total_orders' => $totalOrders,
                'avg_order_value' => (float) $avgOrderValue,
                'new_customers' => $newCustomers,
                'revenue_change' => round($revenueChange, 1),
                'orders_change' => round($ordersChange, 1),
                'avg_change' => round($avgChange, 1),
                'customers_change' => round($customersChange, 1),
                'top_products' => $topProducts,
            ]);
        } catch (\Exception $e) {
            // Log the exception for debugging
            \Log::error('Analytics overview error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'store' => $store ? $store->id : 'no store'
            ]);
            
            // Return empty data if no tenant database
            return response()->json([
                'daily_revenue' => [0, 0, 0, 0, 0, 0, 0],
                'daily_orders' => [0, 0, 0, 0, 0, 0, 0],
                'daily_labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'order_types' => [0, 0, 0],
                'total_revenue' => 0,
                'total_orders' => 0,
                'avg_order_value' => 0,
                'new_customers' => 0,
                'revenue_change' => 0,
                'orders_change' => 0,
                'avg_change' => 0,
                'customers_change' => 0,
                'top_products' => [],
                'error' => $e->getMessage(), // Include error in response for debugging
            ]);
        }
    }

    public function sales(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');
        $period = $request->input('period', 'week');

        $dateRange = $this->getDateRange($period);

        $sales = Order::whereBetween('created_at', $dateRange)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DATE(created_at) as date, SUM(total) as total, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json(['sales' => $sales]);
    }

    public function products(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');
        $period = $request->input('period', 'month');

        $dateRange = $this->getDateRange($period);

        $products = DB::connection('tenant')->table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereBetween('orders.created_at', $dateRange)
            ->selectRaw('products.name, SUM(order_items.quantity) as quantity, SUM(order_items.total) as revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(20)
            ->get();

        return response()->json(['products' => $products]);
    }

    public function customers(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');
        $period = $request->input('period', 'month');

        $dateRange = $this->getDateRange($period);

        $customers = DB::connection('tenant')->table('orders')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->whereBetween('orders.created_at', $dateRange)
            ->selectRaw('customers.id, customers.name, customers.email, COUNT(*) as orders, SUM(orders.total) as spent')
            ->groupBy('customers.id', 'customers.name', 'customers.email')
            ->orderByDesc('spent')
            ->limit(20)
            ->get();

        return response()->json(['customers' => $customers]);
    }

    private function getDateRange(string $period): array
    {
        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    private function getRevenue(int $storeId, array $dateRange): array
    {
        $current = Order::whereBetween('created_at', $dateRange)
            ->where('status', '!=', 'cancelled')
            ->sum('total');

        return [
            'total' => $current,
            'currency' => 'USD',
        ];
    }

    private function getOrderStats(int $storeId, array $dateRange): array
    {
        $query = Order::whereBetween('created_at', $dateRange);

        return [
            'total' => $query->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
        ];
    }

    private function getTopProducts(int $storeId, array $dateRange): array
    {
        return DB::connection('tenant')->table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereBetween('orders.created_at', $dateRange)
            ->selectRaw('products.name, SUM(order_items.quantity) as quantity')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get()
            ->toArray();
    }

    private function getHourlySales(int $storeId, array $dateRange): array
    {
        return Order::whereBetween('created_at', $dateRange)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('HOUR(created_at) as hour, SUM(total) as total')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->toArray();
    }
}
