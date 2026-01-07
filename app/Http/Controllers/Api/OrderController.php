<?php

namespace App\Http\Controllers\Api;

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
        $query = Order::with(['customer', 'items']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', '%' . $search . '%')
                    ->orWhere('customer_name', 'like', '%' . $search . '%')
                    ->orWhere('customer_email', 'like', '%' . $search . '%')
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('first_name', 'like', '%' . $search . '%')
                            ->orWhere('last_name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        $orders = $query->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'customer_id' => 'nullable|exists:tenant.customers,id',
            'type' => 'required|in:delivery,pickup,dine_in,pos',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:tenant.products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.variant_id' => 'nullable|exists:tenant.product_variants,id',
            'items.*.options' => 'nullable|array',
            'items.*.notes' => 'nullable|string',
            'delivery_address' => 'required_if:type,delivery|nullable|array',
            'coupon_code' => 'nullable|string',
            'notes' => 'nullable|string',
            'scheduled_at' => 'nullable|date',
        ]);

        $order = $this->orderService->createOrder(
            $store,
            $request->all()
        );

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order->load(['customer', 'items']),
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $order->load(['customer', 'items.product', 'payments', 'employee']);

        return response()->json($order);
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => 'sometimes|in:pending,confirmed,preparing,ready,completed,cancelled',
            'payment_status' => 'sometimes|in:pending,paid,refunded,failed',
            'notes' => 'nullable|string',
            'delivery_address' => 'nullable|array',
            'table_number' => 'nullable|string',
        ]);

        $order->update($request->all());

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $order->fresh(['customer', 'items']),
        ]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready,completed,cancelled',
        ]);

        $order = $this->orderService->updateStatus($order, $request->status);

        return response()->json([
            'message' => 'Order status updated',
            'order' => $order,
        ]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        if (in_array($order->status, ['completed', 'cancelled'])) {
            return response()->json([
                'message' => 'Cannot cancel this order',
            ], 400);
        }

        $request->validate([
            'reason' => 'nullable|string',
        ]);

        $order = $this->orderService->cancelOrder($order, $request->reason);

        return response()->json([
            'message' => 'Order cancelled',
            'order' => $order,
        ]);
    }

    public function addItem(Request $request, Order $order): JsonResponse
    {
        if (in_array($order->status, ['completed', 'cancelled'])) {
            return response()->json([
                'message' => 'Cannot modify this order',
            ], 400);
        }

        $request->validate([
            'product_id' => 'required|exists:tenant.products,id',
            'quantity' => 'required|integer|min:1',
            'variant_id' => 'nullable|exists:tenant.product_variants,id',
            'options' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $product = Product::find($request->product_id);
        $price = $product->price;

        if ($request->variant_id) {
            $variant = $product->variants()->find($request->variant_id);
            if ($variant) {
                $price = $variant->price;
            }
        }

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $request->product_id,
            'variant_id' => $request->variant_id,
            'name' => $product->name,
            'quantity' => $request->quantity,
            'price' => $price,
            'total' => $price * $request->quantity,
            'options' => $request->options ?? [],
            'notes' => $request->notes,
        ]);

        $this->orderService->recalculateOrder($order);

        return response()->json([
            'message' => 'Item added',
            'order' => $order->fresh(['items']),
        ]);
    }

    public function removeItem(Request $request, Order $order, OrderItem $item): JsonResponse
    {
        if ($item->order_id !== $order->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if (in_array($order->status, ['completed', 'cancelled'])) {
            return response()->json([
                'message' => 'Cannot modify this order',
            ], 400);
        }

        $item->delete();
        $this->orderService->recalculateOrder($order);

        return response()->json([
            'message' => 'Item removed',
            'order' => $order->fresh(['items']),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $today = now()->startOfDay();

        $stats = [
            'today' => [
                'orders' => Order::whereDate('created_at', $today)
                    ->count(),
                'revenue' => Order::whereDate('created_at', $today)
                    ->where('payment_status', 'paid')
                    ->sum('total'),
            ],
            'pending' => Order::where('status', 'pending')
                ->count(),
            'preparing' => Order::where('status', 'preparing')
                ->count(),
            'ready' => Order::where('status', 'ready')
                ->count(),
        ];

        return response()->json($stats);
    }
}
