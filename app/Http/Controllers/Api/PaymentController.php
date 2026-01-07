<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Order;
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
        $query = Payment::with(['order', 'customer']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('method')) {
            $query->where('method', $request->method);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $payments = $query->latest()->paginate(20);

        return response()->json($payments);
    }

    public function store(Request $request): JsonResponse
    {
        $store = $request->attributes->get('store');

        $request->validate([
            'order_id' => 'required|exists:tenant.orders,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,card,online,wallet',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $order = Order::findOrFail($request->order_id);

        $payment = $this->paymentService->processPayment(
            $order,
            $request->amount,
            $request->method,
            $request->only(['reference', 'notes'])
        );

        return response()->json([
            'message' => 'Payment recorded',
            'payment' => $payment,
        ], 201);
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        $payment->load(['order', 'customer']);

        return response()->json($payment);
    }

    public function refund(Request $request, Payment $payment): JsonResponse
    {
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

    public function stats(Request $request): JsonResponse
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $stats = [
            'today' => Payment::whereDate('created_at', $today)
                ->where('status', 'completed')
                ->sum('amount'),
            'this_month' => Payment::where('created_at', '>=', $thisMonth)
                ->where('status', 'completed')
                ->sum('amount'),
            'by_method' => Payment::whereDate('created_at', $today)
                ->where('status', 'completed')
                ->selectRaw('method, SUM(amount) as total, COUNT(*) as count')
                ->groupBy('method')
                ->get(),
        ];

        return response()->json($stats);
    }
}
