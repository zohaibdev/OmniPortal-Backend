<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $payments = Payment::with(['order', 'customer'])
            ->latest()
            ->paginate(20);

        return response()->json($payments);
    }

    public function show(Request $request, string $storeId, int $paymentId): JsonResponse
    {
        $payment = Payment::findOrFail($paymentId);
        return response()->json(['payment' => $payment->load(['order', 'customer'])]);
    }

    public function refund(Request $request, string $storeId, int $paymentId): JsonResponse
    {
        $payment = Payment::findOrFail($paymentId);
        $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $payment->amount,
            'reason' => 'nullable|string',
        ]);

        $refund = $this->paymentService->refundPayment(
            $payment,
            $request->amount,
            $request->reason
        );

        return response()->json([
            'message' => 'Refund processed',
            'refund' => $refund,
        ]);
    }
}
