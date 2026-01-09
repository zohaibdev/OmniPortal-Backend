<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Owner;
use App\Models\Subscription;
use App\Services\StoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreController extends Controller
{
    protected StoreService $storeService;

    public function __construct(StoreService $storeService)
    {
        $this->storeService = $storeService;
    }

    /**
     * Create a new store for an owner
     * Slug is auto-generated - admin only provides name and other details
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'owner_id' => 'required|exists:owners,id',
            'name' => 'required|string|max:255',
            'business_type' => 'required|in:restaurant,clothing,electronics,grocery,services,other',
            'description' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'whatsapp_business_number' => 'required|string|max:20|regex:/^[0-9+]{1,20}$/',
            'whatsapp_business_id' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:2',
            'postal_code' => 'nullable|string|max:20',
            'timezone' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
        ]);

        $owner = Owner::findOrFail($request->owner_id);
        
        $storeData = $request->only([
            'name',
            'business_type',
            'description',
            'email',
            'phone',
            'whatsapp_business_number',
            'whatsapp_business_id',
            'address',
            'city',
            'state',
            'country',
            'postal_code',
            'timezone',
            'currency',
        ]);

        // Set defaults
        $storeData['currency'] = $storeData['currency'] ?? config('app.currency', 'PKR');
        $storeData['timezone'] = $storeData['timezone'] ?? config('app.timezone', 'UTC');

        $store = $this->storeService->create($owner, $storeData);

        // Add default payment methods
        $this->addDefaultPaymentMethods($store);

        $store->load('owner', 'paymentMethods');

        return response()->json([
            'message' => 'Store created successfully',
            'store' => $store,
        ], 201);
    }

    /**
     * Add default payment methods for a store based on business type
     */
    private function addDefaultPaymentMethods(Store $store): void
    {
        $defaultMethods = [
            ['id' => 1, 'order' => 0], // Cash on Delivery
        ];

        // Add online payment methods for non-service businesses
        if ($store->business_type !== 'services') {
            $defaultMethods[] = ['id' => 2, 'order' => 1]; // EasyPaisa
            $defaultMethods[] = ['id' => 3, 'order' => 2]; // JazzCash
        }

        foreach ($defaultMethods as $method) {
            $store->paymentMethods()->attach($method['id'], [
                'display_order' => $method['order'],
                'is_enabled' => true,
            ]);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $query = Store::with(['owner', 'activeSubscription.plan']);

        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('slug', 'like', '%' . $request->search . '%')
                    ->orWhere('subdomain', 'like', '%' . $request->search . '%')
                    ->orWhere('custom_domain', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $stores = $query->latest()->paginate(20);

        return response()->json($stores);
    }

    public function show(Store $store): JsonResponse
    {
        $store->load(['owner', 'activeSubscription.plan']);

        // Get tenant database stats if database exists
        $stats = [
            'total_revenue' => 0,
            'revenue_today' => 0,
            'revenue_this_week' => 0,
            'revenue_this_month' => 0,
            'orders_count' => 0,
            'orders_today' => 0,
            'orders_this_week' => 0,
            'orders_this_month' => 0,
            'products_count' => 0,
            'customers_count' => 0,
            'categories_count' => 0,
            'employees_count' => 0,
            'avg_order_value' => 0,
            'pending_orders' => 0,
            'completed_orders' => 0,
            'cancelled_orders' => 0,
        ];

        $recentOrders = [];

        if ($store->database_name) {
            try {
                $tenantService = app(\App\Services\TenantDatabaseService::class);
                $tenantService->configureTenantConnection($store);

                // Basic counts
                $stats['products_count'] = DB::connection('tenant')->table('products')->count();
                $stats['orders_count'] = DB::connection('tenant')->table('orders')->count();
                $stats['customers_count'] = DB::connection('tenant')->table('customers')->count();
                $stats['categories_count'] = DB::connection('tenant')->table('categories')->count();
                $stats['employees_count'] = DB::connection('tenant')->table('employees')->count();
                
                // Revenue stats
                $stats['total_revenue'] = (float) DB::connection('tenant')
                    ->table('orders')
                    ->where('payment_status', 'paid')
                    ->sum('total');

                $stats['revenue_today'] = (float) DB::connection('tenant')
                    ->table('orders')
                    ->where('payment_status', 'paid')
                    ->where('created_at', '>=', now()->startOfDay())
                    ->sum('total');

                $stats['revenue_this_week'] = (float) DB::connection('tenant')
                    ->table('orders')
                    ->where('payment_status', 'paid')
                    ->where('created_at', '>=', now()->startOfWeek())
                    ->sum('total');

                $stats['revenue_this_month'] = (float) DB::connection('tenant')
                    ->table('orders')
                    ->where('payment_status', 'paid')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->sum('total');
                    
                // Order counts by period
                $stats['orders_today'] = DB::connection('tenant')
                    ->table('orders')
                    ->where('created_at', '>=', now()->startOfDay())
                    ->count();

                $stats['orders_this_week'] = DB::connection('tenant')
                    ->table('orders')
                    ->where('created_at', '>=', now()->startOfWeek())
                    ->count();

                $stats['orders_this_month'] = DB::connection('tenant')
                    ->table('orders')
                    ->where('created_at', '>=', now()->startOfMonth())
                    ->count();

                // Order status counts
                $stats['pending_orders'] = DB::connection('tenant')
                    ->table('orders')
                    ->where('status', 'pending')
                    ->count();

                $stats['completed_orders'] = DB::connection('tenant')
                    ->table('orders')
                    ->where('status', 'completed')
                    ->count();

                $stats['cancelled_orders'] = DB::connection('tenant')
                    ->table('orders')
                    ->where('status', 'cancelled')
                    ->count();

                // Average order value
                if ($stats['orders_count'] > 0) {
                    $stats['avg_order_value'] = round($stats['total_revenue'] / $stats['orders_count'], 2);
                }

                // Recent orders (last 10)
                $recentOrders = DB::connection('tenant')
                    ->table('orders')
                    ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
                    ->select([
                        'orders.id',
                        'orders.order_number',
                        'orders.total',
                        'orders.status',
                        'orders.payment_status',
                        'orders.created_at',
                        'customers.first_name',
                        'customers.last_name',
                        'customers.email as customer_email',
                    ])
                    ->orderBy('orders.created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(function ($order) {
                        $customerName = null;
                        if ($order->first_name || $order->last_name) {
                            $customerName = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? ''));
                        }
                        return [
                            'id' => $order->id,
                            'order_number' => $order->order_number,
                            'total' => $order->total,
                            'status' => $order->status,
                            'payment_status' => $order->payment_status,
                            'created_at' => $order->created_at,
                            'customer_name' => $customerName,
                            'customer_email' => $order->customer_email,
                        ];
                    })
                    ->toArray();

            } catch (\Exception $e) {
                // Tenant database might not exist or have issues
                Log::warning('Could not fetch tenant stats', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json(array_merge($store->toArray(), [
            'stats' => $stats,
            'recent_orders' => $recentOrders,
        ]));
    }

    public function update(Request $request, Store $store): JsonResponse
    {
        $request->validate([
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $store->update($request->only(['is_active', 'notes']));

        return response()->json([
            'message' => 'Store updated',
            'store' => $store->fresh(),
        ]);
    }

    public function suspend(Store $store): JsonResponse
    {
        $store->update([
            'is_active' => false,
            'status' => Store::STATUS_SUSPENDED,
        ]);

        if ($store->subscription) {
            $store->subscription->update(['status' => 'suspended']);
        }

        return response()->json([
            'message' => 'Store suspended',
            'store' => $store->fresh(),
        ]);
    }

    public function activate(Store $store): JsonResponse
    {
        $store->update([
            'is_active' => true,
            'status' => Store::STATUS_ACTIVE,
        ]);

        if ($store->subscription && $store->subscription->status === 'suspended') {
            $store->subscription->update(['status' => 'active']);
        }

        return response()->json([
            'message' => 'Store activated',
            'store' => $store->fresh(),
        ]);
    }

    public function destroy(Store $store): JsonResponse
    {
        $hardDelete = request()->boolean('hard_delete', false);
        
        if ($hardDelete) {
            // Use service method for deletion since DeleteStoreJob is disabled
            $this->storeService->delete($store);
            
            // Force delete the store record
            $store->forceDelete();

            return response()->json([
                'message' => 'Store deletion initiated. All associated resources will be cleaned up.',
            ]);
        }

        // Soft delete - keep data but disable store
        $this->storeService->softDelete($store);

        return response()->json([
            'message' => 'Store deleted (soft delete - data preserved)',
        ]);
    }

    /**
     * Redeploy store frontend (disabled - requires deployment job)
     */
    public function redeploy(Store $store): JsonResponse
    {
        // DeployStorefrontJob is disabled for local development
        return response()->json([
            'message' => 'Store redeployment is disabled for local development',
        ], 501);
    }

    /**
     * Get store deployment status
     */
    public function deploymentStatus(Store $store): JsonResponse
    {
        return response()->json([
            'deployment_status' => $this->storeService->getDeploymentStatus($store),
        ]);
    }

    /**
     * Provision Forge site for existing store (disabled)
     */
    public function provisionForge(Store $store): JsonResponse
    {
        return response()->json([
            'message' => 'Forge provisioning is disabled for local development',
        ], 501);
    }

    /**
     * Update custom domain for store
     */
    public function updateDomain(Request $request, Store $store): JsonResponse
    {
        $request->validate([
            'custom_domain' => 'nullable|string|max:255',
        ]);

        $oldDomain = $store->custom_domain;
        $newDomain = $request->input('custom_domain');

        $store = $this->storeService->update($store, ['custom_domain' => $newDomain]);

        // Clear domain cache
        \App\Http\Middleware\ResolveTenant::clearStoreCache($store);

        return response()->json([
            'message' => 'Custom domain updated',
            'store' => $store,
            'note' => $newDomain 
                ? 'Please update DNS to point ' . $newDomain . ' to your server IP'
                : 'Custom domain removed',
        ]);
    }
}
