<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use App\Services\PaymentVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class OrderPaymentController extends Controller
{
    public function __construct(
        protected PaymentVerificationService $paymentVerification
    ) {}

    /**
     * Get orders pending payment verification
     */
    public function pendingVerification(): JsonResponse
    {
        $orders = Order::with(['customer', 'paymentMethod', 'items'])
            ->where('payment_status', Order::PAYMENT_STATUS_PENDING_VERIFICATION)
            ->whereNotNull('payment_proof_path')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders);
    }

    /**
     * Get payment screenshot
     */
    public function screenshot(int $id): mixed
    {
        $order = Order::find($id);

        if (!$order || !$order->payment_proof_path) {
            return response()->json(['message' => 'Screenshot not found'], 404);
        }

        if (!Storage::disk('private')->exists($order->payment_proof_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('private')->response($order->payment_proof_path);
    }

    /**
     * Approve payment
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->payment_status !== Order::PAYMENT_STATUS_PENDING_VERIFICATION) {
            return response()->json([
                'message' => 'Order payment is not pending verification',
            ], 422);
        }

        $approved = $this->paymentVerification->approvePayment(
            $order,
            $request->user()?->id
        );

        if (!$approved) {
            return response()->json([
                'message' => 'Failed to approve payment',
            ], 500);
        }

        return response()->json([
            'message' => 'Payment approved',
            'order' => $order->fresh(['customer', 'paymentMethod', 'items']),
        ]);
    }

    /**
     * Reject payment
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->payment_status !== Order::PAYMENT_STATUS_PENDING_VERIFICATION) {
            return response()->json([
                'message' => 'Order payment is not pending verification',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $rejected = $this->paymentVerification->rejectPayment(
            $order,
            $request->input('reason'),
            $request->user()?->id
        );

        if (!$rejected) {
            return response()->json([
                'message' => 'Failed to reject payment',
            ], 500);
        }

        return response()->json([
            'message' => 'Payment rejected',
            'order' => $order->fresh(['customer', 'paymentMethod', 'items']),
        ]);
    }
}
