<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    public function process(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:tenant.products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'customer.name' => 'required|string',
            'customer.email' => 'required|email',
            'customer.phone' => 'required|string',
            'delivery_type' => 'required|in:pickup,delivery',
            'delivery_address' => 'required_if:delivery_type,delivery|nullable|string',
            'payment_method' => 'required|in:card,cash',
            'payment_intent_id' => 'required_if:payment_method,card|nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Find or create customer
        $customer = Customer::firstOrCreate(
            [
                'email' => $request->input('customer.email'),
            ],
            [
                'name' => $request->input('customer.name'),
                'phone' => $request->input('customer.phone'),
            ]
        );

        // Calculate totals
        $subtotal = 0;
        $orderItems = [];

        foreach ($request->items as $item) {
            $product = Product::find($item['product_id']);
            if (!$product) {
                continue;
            }

            $price = $product->price;
            $itemTotal = $price * $item['quantity'];
            $subtotal += $itemTotal;

            $orderItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $item['quantity'],
                'price' => $price,
                'total' => $itemTotal,
            ];
        }

        $tax = $subtotal * 0.1; // 10% tax
        $deliveryFee = $request->delivery_type === 'delivery' ? 5.00 : 0;
        $total = $subtotal + $tax + $deliveryFee;

        // Create order
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'status' => 'pending',
            'delivery_type' => $request->delivery_type,
            'delivery_address' => $request->delivery_address,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'delivery_fee' => $deliveryFee,
            'total' => $total,
            'payment_method' => $request->payment_method,
            'payment_status' => $request->payment_method === 'cash' ? 'pending' : 'paid',
            'notes' => $request->notes,
        ]);

        // Create order items
        foreach ($orderItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                ...$item,
            ]);
        }

        return response()->json([
            'message' => 'Order placed successfully',
            'order' => $order->load('items'),
        ], 201);
    }

    public function createPaymentIntent(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'amount' => 'required|numeric|min:0.50',
        ]);

        $paymentIntent = $this->paymentService->createPaymentIntent(
            $request->amount * 100, // Stripe uses cents
            $store->currency ?? 'usd'
        );

        return response()->json([
            'client_secret' => $paymentIntent->client_secret,
        ]);
    }
}
