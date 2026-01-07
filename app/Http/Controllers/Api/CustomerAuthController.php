<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CustomerAuthController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    public function register(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
        ]);

        $existing = Customer::where('email', $request->email)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Email already registered',
            ], 422);
        }

        $customer = Customer::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        $token = $customer->createToken('customer-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'customer' => $customer,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $customer = Customer::where('email', $request->email)
            ->first();

        if (!$customer || !Hash::check($request->password, $customer->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $token = $customer->createToken('customer-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'customer' => $customer,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'customer' => $request->user(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $customer = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $customer->update($request->only(['name', 'phone']));

        return response()->json([
            'message' => 'Profile updated',
            'customer' => $customer->fresh(),
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $customer = $request->user();

        $orders = Order::where('customer_id', $customer->id)
            ->with('items')
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    public function order(Request $request, string $orderNumber): JsonResponse
    {
        $customer = $request->user();

        $order = Order::where('customer_id', $customer->id)
            ->where('order_number', $orderNumber)
            ->with(['items', 'payments'])
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json($order);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');
        $customer = $request->user();

        $request->validate([
            'type' => 'required|in:delivery,pickup,dine_in',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:tenant.products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.variant_id' => 'nullable|exists:tenant.product_variants,id',
            'items.*.options' => 'nullable|array',
            'delivery_address' => 'required_if:type,delivery|nullable|array',
            'coupon_code' => 'nullable|string',
            'notes' => 'nullable|string',
            'payment_method' => 'required|in:cash,card,online',
        ]);

        $order = $this->orderService->createOrder($store, [
            'customer_id' => $customer->id,
            ...$request->all(),
        ]);

        return response()->json([
            'message' => 'Order placed successfully',
            'order' => $order->load('items'),
        ], 201);
    }

    public function addresses(Request $request): JsonResponse
    {
        $customer = $request->user();

        return response()->json([
            'addresses' => $customer->addresses ?? [],
        ]);
    }

    public function addAddress(Request $request): JsonResponse
    {
        $customer = $request->user();

        $request->validate([
            'label' => 'required|string|max:50',
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'is_default' => 'boolean',
        ]);

        $addresses = $customer->addresses ?? [];

        if ($request->boolean('is_default')) {
            $addresses = array_map(function ($addr) {
                $addr['is_default'] = false;
                return $addr;
            }, $addresses);
        }

        $addresses[] = [
            'id' => uniqid(),
            'label' => $request->label,
            'address' => $request->address,
            'city' => $request->city,
            'is_default' => $request->boolean('is_default'),
        ];

        $customer->update(['addresses' => $addresses]);

        return response()->json([
            'message' => 'Address added',
            'addresses' => $addresses,
        ]);
    }
}
