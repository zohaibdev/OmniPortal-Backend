<?php

namespace App\Services;

use App\Models\Tenant\Order;
use App\Models\Tenant\PaymentMethod;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaymentVerificationService
{
    public function __construct(
        protected WhatsAppService $whatsapp
    ) {}

    /**
     * Process payment screenshot for order
     */
    public function processPaymentScreenshot(Order $order, string $screenshotPath): bool
    {
        try {
            // Validate image
            if (!$this->validateScreenshot($screenshotPath)) {
                return false;
            }

            // Update order with screenshot
            $order->update([
                'payment_proof_path' => $screenshotPath,
                'payment_status' => Order::PAYMENT_STATUS_PENDING_VERIFICATION,
                'status' => Order::STATUS_CONFIRMED,
            ]);

            // Notify customer
            $this->whatsapp->sendTextMessage(
                $order->customer_phone,
                "Shukriya! Aap ka payment screenshot receive ho gaya hai.\n\nOrder #{$order->order_number}\n\nHum verify kar ke confirm karenge."
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Payment screenshot processing failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Validate screenshot file
     */
    protected function validateScreenshot(string $path): bool
    {
        if (!Storage::disk('private')->exists($path)) {
            return false;
        }

        // Check file size (max 5MB)
        $size = Storage::disk('private')->size($path);
        if ($size > 5 * 1024 * 1024) {
            return false;
        }

        return true;
    }

    /**
     * Approve payment (from Owner Dashboard)
     */
    public function approvePayment(Order $order, ?int $approvedBy = null): bool
    {
        try {
            $order->update([
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'paid_at' => now(),
            ]);

            // Log status change
            $order->statusHistory()->create([
                'status' => $order->status,
                'previous_status' => $order->status,
                'notes' => 'Payment approved by owner',
                'changed_by' => $approvedBy,
                'changed_by_type' => 'owner',
            ]);

            // Notify customer
            $this->whatsapp->sendTextMessage(
                $order->customer_phone,
                "Khush khabri! Aap ka payment confirm ho gaya hai.\n\nOrder #{$order->order_number}\n\nHum jald deliver karenge!"
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Payment approval failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Reject payment (from Owner Dashboard)
     */
    public function rejectPayment(Order $order, string $reason, ?int $rejectedBy = null): bool
    {
        try {
            $order->update([
                'payment_status' => Order::PAYMENT_STATUS_REJECTED,
                'status' => Order::STATUS_CANCELLED,
            ]);

            // Log status change
            $order->statusHistory()->create([
                'status' => Order::STATUS_CANCELLED,
                'previous_status' => $order->status,
                'notes' => "Payment rejected: {$reason}",
                'changed_by' => $rejectedBy,
                'changed_by_type' => 'owner',
            ]);

            // Notify customer
            $this->whatsapp->sendTextMessage(
                $order->customer_phone,
                "Maaf, aap ka payment verify nahi ho saka.\n\nReason: {$reason}\n\nOrder #{$order->order_number} cancel ho gaya hai.\n\nPhir se order karna chahein to batayein."
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Payment rejection failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
