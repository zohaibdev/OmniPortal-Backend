<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Owner;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Invoice;
use Stripe\Exception\ApiErrorException;

class BillingController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Get billing overview for all stores
     */
    public function overview(): JsonResponse
    {
        $activeSubscriptions = Subscription::where('status', 'active')->count();
        $trialingStores = Store::where('trial_used', true)
            ->where('trial_ends_at', '>', now())
            ->whereDoesntHave('activeSubscription')
            ->count();
        $expiredTrials = Store::where('trial_used', true)
            ->where('trial_ends_at', '<', now())
            ->whereDoesntHave('activeSubscription')
            ->count();

        $monthlyRevenue = Subscription::where('status', 'active')
            ->whereHas('plan', fn($q) => $q->where('interval', 'month'))
            ->sum('amount');

        $yearlyRevenue = Subscription::where('status', 'active')
            ->whereHas('plan', fn($q) => $q->where('interval', 'year'))
            ->sum('amount');

        // Get subscription by plan breakdown
        $planBreakdown = SubscriptionPlan::withCount(['subscriptions' => function ($query) {
            $query->where('status', 'active');
        }])->get()->map(function ($plan) {
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'price' => $plan->price,
                'interval' => $plan->interval,
                'active_subscriptions' => $plan->subscriptions_count,
            ];
        });

        return response()->json([
            'overview' => [
                'active_subscriptions' => $activeSubscriptions,
                'trialing_stores' => $trialingStores,
                'expired_trials' => $expiredTrials,
                'monthly_recurring_revenue' => $monthlyRevenue,
                'yearly_recurring_revenue' => $yearlyRevenue / 12, // Monthly equivalent
                'total_mrr' => $monthlyRevenue + ($yearlyRevenue / 12),
            ],
            'plan_breakdown' => $planBreakdown,
        ]);
    }

    /**
     * Get all subscriptions with filtering
     */
    public function subscriptions(Request $request): JsonResponse
    {
        $query = Subscription::with(['store.owner', 'plan']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        $subscriptions = $query->latest()->paginate(20);

        return response()->json($subscriptions);
    }

    /**
     * Get billing details for a specific owner
     */
    public function ownerBilling(Owner $owner): JsonResponse
    {
        $stores = $owner->stores()->with(['activeSubscription.plan'])->get();

        $billing = [
            'owner' => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'company_name' => $owner->company_name,
            ],
            'stores' => $stores->map(function ($store) {
                return [
                    'id' => $store->id,
                    'name' => $store->name,
                    'status' => $store->status,
                    'trial' => [
                        'on_trial' => $store->onTrial(),
                        'trial_ends_at' => $store->trial_ends_at,
                        'days_remaining' => $store->trialDaysRemaining(),
                        'trial_expired' => $store->trialExpired(),
                    ],
                    'subscription' => $store->activeSubscription ? [
                        'id' => $store->activeSubscription->id,
                        'plan' => $store->activeSubscription->plan,
                        'status' => $store->activeSubscription->status,
                        'amount' => $store->activeSubscription->amount,
                        'currency' => $store->activeSubscription->currency,
                        'current_period_start' => $store->activeSubscription->current_period_start,
                        'current_period_end' => $store->activeSubscription->current_period_end,
                        'cancelled_at' => $store->activeSubscription->cancelled_at,
                    ] : null,
                ];
            }),
            'total_monthly_spend' => $stores->sum(function ($store) {
                if (!$store->activeSubscription) return 0;
                $amount = $store->activeSubscription->amount;
                if ($store->activeSubscription->plan && $store->activeSubscription->plan->interval === 'year') {
                    return $amount / 12;
                }
                return $amount;
            }),
        ];

        // Get invoices if any store has stripe customer
        $invoices = [];
        foreach ($stores as $store) {
            if ($store->activeSubscription && $store->activeSubscription->stripe_customer_id) {
                try {
                    $stripeInvoices = Invoice::all([
                        'customer' => $store->activeSubscription->stripe_customer_id,
                        'limit' => 10,
                    ]);

                    foreach ($stripeInvoices->data as $invoice) {
                        $invoices[] = [
                            'id' => $invoice->id,
                            'store_name' => $store->name,
                            'number' => $invoice->number,
                            'amount' => $invoice->amount_paid / 100,
                            'currency' => strtoupper($invoice->currency),
                            'status' => $invoice->status,
                            'created_at' => \Carbon\Carbon::createFromTimestamp($invoice->created)->toISOString(),
                            'pdf_url' => $invoice->invoice_pdf,
                        ];
                    }
                } catch (ApiErrorException $e) {
                    Log::warning('Could not fetch invoices for store', [
                        'store_id' => $store->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Sort invoices by date
        usort($invoices, fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));
        $billing['invoices'] = array_slice($invoices, 0, 20);

        return response()->json($billing);
    }

    /**
     * Get billing details for a specific store
     */
    public function storeBilling(Store $store): JsonResponse
    {
        $store->load(['owner', 'activeSubscription.plan', 'subscriptions.plan']);

        $billing = [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'status' => $store->status,
            ],
            'owner' => [
                'id' => $store->owner->id,
                'name' => $store->owner->name,
                'email' => $store->owner->email,
            ],
            'trial' => [
                'on_trial' => $store->onTrial(),
                'trial_ends_at' => $store->trial_ends_at,
                'days_remaining' => $store->trialDaysRemaining(),
                'trial_expired' => $store->trialExpired(),
            ],
            'current_subscription' => $store->activeSubscription,
            'subscription_history' => $store->subscriptions,
            'invoices' => [],
        ];

        // Get Stripe invoices
        if ($store->activeSubscription && $store->activeSubscription->stripe_customer_id) {
            try {
                $stripeInvoices = Invoice::all([
                    'customer' => $store->activeSubscription->stripe_customer_id,
                    'limit' => 20,
                ]);

                $billing['invoices'] = collect($stripeInvoices->data)->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'amount' => $invoice->amount_paid / 100,
                        'currency' => strtoupper($invoice->currency),
                        'status' => $invoice->status,
                        'created_at' => \Carbon\Carbon::createFromTimestamp($invoice->created)->toISOString(),
                        'pdf_url' => $invoice->invoice_pdf,
                        'hosted_invoice_url' => $invoice->hosted_invoice_url,
                    ];
                });
            } catch (ApiErrorException $e) {
                Log::warning('Could not fetch invoices', [
                    'store_id' => $store->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json($billing);
    }

    /**
     * Manually extend trial for a store
     */
    public function extendTrial(Request $request, Store $store): JsonResponse
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:90',
        ]);

        $newTrialEnd = $store->trial_ends_at 
            ? $store->trial_ends_at->addDays($request->days)
            : now()->addDays($request->days);

        $store->update([
            'trial_ends_at' => $newTrialEnd,
            'trial_used' => true,
            'status' => Store::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => "Trial extended by {$request->days} days",
            'trial_ends_at' => $store->trial_ends_at,
        ]);
    }

    /**
     * Cancel a store's subscription (admin action)
     */
    public function cancelSubscription(Store $store): JsonResponse
    {
        $subscription = $store->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        try {
            if ($subscription->stripe_subscription_id) {
                Stripe::setApiKey(config('services.stripe.secret'));
                $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_subscription_id);
                $stripeSubscription->cancel();
            }

            $subscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'ended_at' => now(),
            ]);

            return response()->json([
                'message' => 'Subscription cancelled',
            ]);
        } catch (ApiErrorException $e) {
            return response()->json([
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
