<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['items', 'customer'])
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json(['order' => $order]);
    }

    public function track(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $statusSteps = [
            'pending' => 1,
            'confirmed' => 2,
            'preparing' => 3,
            'ready' => 4,
            'out_for_delivery' => 5,
            'delivered' => 6,
            'completed' => 6,
        ];

        return response()->json([
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_step' => $statusSteps[$order->status] ?? 1,
            'estimated_time' => $order->estimated_ready_at,
            'delivery_type' => $order->delivery_type,
            'updated_at' => $order->updated_at,
        ]);
    }
}
