<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');
        $store->load(['subscription.plan']);

        return response()->json([
            'store' => $store,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
            'cover_image' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'currency' => 'nullable|string|max:3',
            'timezone' => 'nullable|string|max:50',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'delivery_fee' => 'nullable|numeric|min:0',
            'minimum_order' => 'nullable|numeric|min:0',
            'settings' => 'nullable|array',
        ]);

        $store->update($request->all());

        return response()->json([
            'message' => 'Store updated successfully',
            'store' => $store->fresh(),
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        // Query tenant database using models with BelongsToTenant trait
        $todayOrders = Order::whereDate('created_at', $today)->count();
        $todayRevenue = Order::whereDate('created_at', $today)
            ->where('payment_status', 'paid')
            ->sum('total');
        
        $monthOrders = Order::where('created_at', '>=', $thisMonth)->count();
        $monthRevenue = Order::where('created_at', '>=', $thisMonth)
            ->where('payment_status', 'paid')
            ->sum('total');
        
        $totalCustomers = Customer::count();
        $totalProducts = Product::where('status', 'active')->count();
        $pendingOrders = Order::where('status', 'pending')->count();
        
        $recentOrders = Order::with('customer')
            ->latest()
            ->limit(5)
            ->get();

        // Get top products by sales
        $topProducts = DB::connection('tenant')
            ->table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.created_at', '>=', $thisMonth)
            ->selectRaw('products.name, SUM(order_items.quantity) as quantity, SUM(order_items.total) as revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->toArray();

        return response()->json([
            'orders_today' => $todayOrders,
            'revenue_today' => (float) $todayRevenue,
            'month_orders' => $monthOrders,
            'month_revenue' => (float) $monthRevenue,
            'total_customers' => $totalCustomers,
            'total_products' => $totalProducts,
            'pending_orders' => $pendingOrders,
            'recent_orders' => $recentOrders,
            'top_products' => $topProducts,
        ]);
    }
}
