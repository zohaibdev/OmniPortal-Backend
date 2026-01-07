<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Store;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Refund as StripeRefund;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create payment intent for order
     */
    public function createPaymentIntent(Order $order): array
    {
        $paymentIntent = PaymentIntent::create([
            'amount' => (int) ($order->total * 100), // Convert to cents
            'currency' => strtolower($order->currency),
            'metadata' => [
                'order_id' => $order->id,
                'store_id' => $order->store_id,
                'order_number' => $order->order_number,
            ],
        ]);

        // Create pending payment record
        $payment = Payment::create([
            'store_id' => $order->store_id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'type' => 'payment',
            'method' => 'stripe',
            'status' => 'pending',
            'amount' => $order->total,
            'fee' => 0,
            'net_amount' => $order->total,
            'currency' => $order->currency,
            'gateway' => 'stripe',
            'gateway_payment_intent_id' => $paymentIntent->id,
        ]);

        return [
            'client_secret' => $paymentIntent->client_secret,
            'payment_intent_id' => $paymentIntent->id,
            'payment_id' => $payment->id,
        ];
    }

    /**
     * Process cash payment (for POS)
     */
    public function processCashPayment(Order $order, float $amountReceived): Payment
    {
        return DB::transaction(function () use ($order, $amountReceived) {
            $payment = Payment::create([
                'store_id' => $order->store_id,
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'type' => 'payment',
                'method' => 'cash',
                'status' => 'completed',
                'amount' => $order->total,
                'fee' => 0,
                'net_amount' => $order->total,
                'currency' => $order->currency,
                'gateway' => null,
                'meta' => [
                    'amount_received' => $amountReceived,
                    'change' => $amountReceived - $order->total,
                ],
                'paid_at' => now(),
            ]);

            $order->update(['payment_status' => 'paid']);

            return $payment;
        });
    }

    /**
     * Confirm payment (webhook handler)
     */
    public function confirmPayment(string $paymentIntentId): Payment
    {
        $payment = Payment::where('gateway_payment_intent_id', $paymentIntentId)->firstOrFail();
        
        // Get payment intent details from Stripe
        $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
        
        // Calculate fee (Stripe fee is approximately 2.9% + $0.30)
        $fee = ($payment->amount * 0.029) + 0.30;

        $payment->update([
            'status' => 'completed',
            'gateway_transaction_id' => $paymentIntent->latest_charge,
            'fee' => $fee,
            'net_amount' => $payment->amount - $fee,
            'gateway_response' => $paymentIntent->toArray(),
            'paid_at' => now(),
        ]);

        // Update order payment status
        if ($payment->order) {
            $payment->order->update(['payment_status' => 'paid']);
        }

        return $payment;
    }

    /**
     * Process refund
     */
    public function refund(Payment $payment, float $amount, string $reason = null): Refund
    {
        return DB::transaction(function () use ($payment, $amount, $reason) {
            $stripeRefund = null;

            // Process Stripe refund if applicable
            if ($payment->gateway === 'stripe' && $payment->gateway_transaction_id) {
                $stripeRefund = StripeRefund::create([
                    'charge' => $payment->gateway_transaction_id,
                    'amount' => (int) ($amount * 100),
                    'reason' => 'requested_by_customer',
                ]);
            }

            // Create refund record
            $refund = Refund::create([
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'refund_number' => 'REF-' . strtoupper(\Str::random(12)),
                'amount' => $amount,
                'reason' => $reason,
                'status' => $stripeRefund ? 'completed' : 'pending',
                'gateway_refund_id' => $stripeRefund?->id,
                'gateway_response' => $stripeRefund?->toArray(),
                'refunded_at' => $stripeRefund ? now() : null,
            ]);

            // Update payment status
            $totalRefunded = $payment->refunds()->sum('amount');
            if ($totalRefunded >= $payment->amount) {
                $payment->update(['status' => 'refunded']);
                if ($payment->order) {
                    $payment->order->update(['payment_status' => 'refunded']);
                }
            }

            return $refund;
        });
    }

    /**
     * Get payment statistics for store
     */
    public function getStatistics(Store $store, ?string $period = 'month'): array
    {
        $query = $store->payments()->where('status', 'completed');

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

        $payments = $query->get();

        return [
            'total_transactions' => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'total_fees' => $payments->sum('fee'),
            'net_amount' => $payments->sum('net_amount'),
            'by_method' => $payments->groupBy('method')->map->sum('amount'),
            'average_transaction' => $payments->avg('amount') ?? 0,
        ];
    }
}
