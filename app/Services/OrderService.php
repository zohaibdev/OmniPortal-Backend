<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Create a new order (alias for backwards compatibility)
     */
    public function createOrder(Store $store, array $data, ?Customer $customer = null): Order
    {
        return $this->create($store, $data, $customer);
    }

    /**
     * Create a new order
     */
    public function create(Store $store, array $data, ?Customer $customer = null): Order
    {
        return DB::connection('tenant')->transaction(function () use ($store, $data, $customer) {
            // Calculate totals
            $subtotal = 0;
            $taxAmount = 0;
            $items = [];

            foreach ($data['items'] as $itemData) {
                $product = Product::find($itemData['product_id']);
                if (!$product) {
                    throw new \Exception("Product not found: {$itemData['product_id']}");
                }

                // Check for variant
                $variant = null;
                if (!empty($itemData['variant_id'])) {
                    $variant = ProductVariant::find($itemData['variant_id']);
                    if (!$variant || $variant->product_id !== $product->id) {
                        throw new \Exception("Variant not found: {$itemData['variant_id']}");
                    }
                }

                // Use variant price if available, otherwise product price
                $unitPrice = $itemData['unit_price'] ?? ($variant ? $variant->price : $product->price);
                $quantity = $itemData['quantity'];
                $itemTotal = $unitPrice * $quantity;

                // Add options price
                $optionsTotal = 0;
                if (!empty($itemData['options'])) {
                    foreach ($itemData['options'] as $option) {
                        $optionsTotal += ($option['price_modifier'] ?? 0) * $quantity;
                    }
                }

                // Add addons price
                $addonsTotal = 0;
                if (!empty($itemData['addons'])) {
                    foreach ($itemData['addons'] as $addon) {
                        $addonsTotal += ($addon['price'] ?? 0) * $quantity;
                    }
                }

                $itemTotal += $optionsTotal + $addonsTotal;

                // Calculate tax
                $itemTax = 0;
                if ($product->is_taxable) {
                    $taxRate = $product->tax_rate ?? $store->tax_rate;
                    $itemTax = $itemTotal * ($taxRate / 100);
                }

                $subtotal += $itemTotal;
                $taxAmount += $itemTax;

                $items[] = [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_name' => $variant?->name,
                    'sku' => $variant?->sku ?? $product->sku,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => 0,
                    'tax_amount' => $itemTax,
                    'total' => $itemTotal + $itemTax,
                    'options' => $itemData['options'] ?? null,
                    'addons' => $itemData['addons'] ?? null,
                    'special_instructions' => $itemData['special_instructions'] ?? null,
                ];
            }

            // Apply coupon discount or manual discount
            $discountAmount = 0;
            $couponCode = null;
            $couponId = null;
            if (!empty($data['coupon_code'])) {
                $coupon = Coupon::where('code', $data['coupon_code'])->first();

                if ($coupon && $coupon->isValid()) {
                    $discountAmount = $coupon->calculateDiscount($subtotal);
                    $couponCode = $coupon->code;
                    $couponId = $coupon->id;
                    $coupon->increment('times_used');
                }
            } elseif (!empty($data['discount_amount'])) {
                // Manual discount (from dashboard order creation)
                $discountAmount = floatval($data['discount_amount']);
            }

            // Calculate delivery fee
            $deliveryFee = $data['delivery_fee'] ?? 0;
            $serviceFee = $data['service_fee'] ?? 0;

            // Calculate total
            $total = $subtotal + $taxAmount - $discountAmount + $deliveryFee + $serviceFee + ($data['tip_amount'] ?? 0);

            // Map order type (takeaway -> pickup for database compatibility)
            $orderType = $data['type'] ?? 'delivery';
            if ($orderType === 'takeaway' || $orderType === 'takeout') {
                $orderType = 'pickup';
            }

            // Create order
            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $customer?->id ?? $data['customer_id'] ?? null,
                'address_id' => $data['address_id'] ?? null,
                'type' => $orderType,
                'status' => Order::STATUS_PENDING,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'delivery_fee' => $deliveryFee,
                'service_fee' => $serviceFee,
                'tip_amount' => $data['tip_amount'] ?? 0,
                'total' => $total,
                'currency' => $store->currency ?? 'USD',
                'payment_status' => $data['payment_status'] ?? 'pending',
                'payment_method' => $data['payment_method'] ?? null,
                'coupon_id' => $couponId,
                'coupon_code' => $couponCode,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'delivery_address' => $data['delivery_address'] ?? null,
                'delivery_instructions' => $data['delivery_instructions'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'source' => $data['source'] ?? 'web',
                'assigned_employee_id' => $data['assigned_employee_id'] ?? null,
                'created_by_type' => $data['created_by_type'] ?? null,
                'created_by_name' => $data['created_by_name'] ?? null,
                'created_by_id' => $data['created_by_id'] ?? null,
                'meta_data' => $data['meta_data'] ?? null,
            ]);

            // Create order items
            foreach ($items as $item) {
                $order->items()->create($item);
            }

            // Update inventory
            $this->updateInventory($order);

            // Update customer stats
            if ($customer || $order->customer_id) {
                $orderCustomer = $customer ?? Customer::find($order->customer_id);
                if ($orderCustomer) {
                    $orderCustomer->updateOrderStats();
                }
            }

            return $order->load('items');
        });
    }

    /**
     * Update order status
     */
    public function updateStatus(Order $order, string $status, ?int $userId = null, ?string $notes = null): Order
    {
        $order->updateStatus($status, $userId, $notes);
        return $order->fresh();
    }

    /**
     * Cancel order
     */
    public function cancel(Order $order, string $reason, ?int $userId = null): Order
    {
        if (!$order->canBeCancelled()) {
            throw new \Exception('This order cannot be cancelled');
        }

        $order->cancellation_reason = $reason;
        $order->save();

        $this->updateStatus($order, Order::STATUS_CANCELLED, $userId, $reason);

        // Restore inventory
        $this->restoreInventory($order);

        return $order->fresh();
    }

    /**
     * Update inventory after order
     */
    protected function updateInventory(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product && $item->product->track_inventory) {
                $item->product->decrement('stock_quantity', $item->quantity);
            }
        }
    }

    /**
     * Restore inventory after cancellation
     */
    protected function restoreInventory(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product && $item->product->track_inventory) {
                $item->product->increment('stock_quantity', $item->quantity);
            }
        }
    }

    /**
     * Get order statistics for store
     */
    public function getStatistics(Store $store, ?string $period = 'today'): array
    {
        $query = $store->orders();

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->where('created_at', '>=', now()->startOfWeek());
                break;
            case 'month':
                $query->where('created_at', '>=', now()->startOfMonth());
                break;
            case 'year':
                $query->where('created_at', '>=', now()->startOfYear());
                break;
        }

        return [
            'total_orders' => $query->count(),
            'pending_orders' => (clone $query)->where('status', Order::STATUS_PENDING)->count(),
            'completed_orders' => (clone $query)->where('status', Order::STATUS_COMPLETED)->count(),
            'cancelled_orders' => (clone $query)->where('status', Order::STATUS_CANCELLED)->count(),
            'total_revenue' => (clone $query)->where('payment_status', 'paid')->sum('total'),
            'average_order_value' => (clone $query)->where('payment_status', 'paid')->avg('total') ?? 0,
        ];
    }

    /**
     * Generate unique order number
     */
    protected function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $date = now()->format('ymd');
        $random = strtoupper(\Illuminate\Support\Str::random(6));
        
        return "{$prefix}-{$date}-{$random}";
    }
}
