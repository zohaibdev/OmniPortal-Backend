<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $query = Order::with(['customer', 'items']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(20);

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'customer_id' => 'nullable|exists:tenant.customers,id',
            'type' => 'required|in:delivery,pickup,dine_in,pos,takeaway',
            'source' => 'nullable|string|max:30',
            'table_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'discount_amount' => 'nullable|numeric|min:0',
            'delivery_fee' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string',
            'payment_status' => 'nullable|in:pending,paid,partially_paid,refunded,failed',
            'delivery_address' => 'nullable|array',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:tenant.products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.special_instructions' => 'nullable|string',
        ]);

        // Add creator info from authenticated user
        $orderData = $request->all();
        $user = $request->user();
        
        if ($user) {
            $orderData['created_by_type'] = 'owner';
            $orderData['created_by_name'] = $user->name;
            $orderData['created_by_id'] = $user->id;
        }

        $order = $this->orderService->createOrder($store, $orderData);

        return response()->json([
            'message' => 'Order created',
            'order' => $order->load(['customer', 'items']),
        ], 201);
    }

    public function show(Request $request, string $storeId, int $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        return response()->json([
            'order' => $order->load(['customer', 'items.product', 'payments']),
        ]);
    }

    public function update(Request $request, string $storeId, int $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        $order->update($request->all());

        return response()->json([
            'message' => 'Order updated',
            'order' => $order->fresh(['customer', 'items']),
        ]);
    }

    public function destroy(Request $request, string $storeId, int $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        $order->delete();

        return response()->json(['message' => 'Order deleted']);
    }

    public function updateStatus(Request $request, string $storeId, int $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready,out_for_delivery,delivered,completed,cancelled,refunded',
        ]);

        $order = $this->orderService->updateStatus($order, $request->status);

        return response()->json([
            'message' => 'Status updated',
            'order' => $order,
        ]);
    }

    public function cancel(Request $request, string $storeId, int $orderId): JsonResponse
    {
        $order = Order::findOrFail($orderId);
        $order = $this->orderService->cancelOrder($order, $request->reason);

        return response()->json([
            'message' => 'Order cancelled',
            'order' => $order,
        ]);
    }
}
