<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Subscription as StripeSubscription;
use Stripe\SetupIntent;
use Stripe\Exception\ApiErrorException;

class SubscriptionController extends Controller
{
    protected bool $stripeEnabled = false;

    public function __construct()
    {
        $stripeSecret = config('services.stripe.secret');
        if ($stripeSecret && !str_starts_with($stripeSecret, 'sk_test_placeholder')) {
            Stripe::setApiKey($stripeSecret);
            $this->stripeEnabled = true;
        }
    }

    /**
     * Get current store subscription
     */
    public function show(Store $store): JsonResponse
    {
        $subscription = $store->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'subscription' => null,
                'trial' => [
                    'on_trial' => $store->onTrial(),
                    'trial_ends_at' => $store->trial_ends_at,
                    'days_remaining' => $store->trialDaysRemaining(),
                    'trial_expired' => $store->trialExpired(),
                ],
            ]);
        }

        $subscription->load('plan');

        return response()->json([
            'subscription' => $subscription,
            'trial' => [
                'on_trial' => $store->onTrial(),
                'trial_ends_at' => $store->trial_ends_at,
                'days_remaining' => $store->trialDaysRemaining(),
                'trial_expired' => $store->trialExpired(),
            ],
        ]);
    }

    /**
     * Get available subscription plans
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()->ordered()->get();

        return response()->json([
            'plans' => $plans,
        ]);
    }

    /**
     * Create Stripe setup intent for adding payment method
     */
    public function createSetupIntent(Store $store): JsonResponse
    {
        // If Stripe not configured, return a mock client secret
        if (!$this->stripeEnabled) {
            return response()->json([
                'client_secret' => 'manual_mode_no_stripe',
                'manual_mode' => true,
            ]);
        }

        try {
            $owner = $store->owner;

            // Get or create Stripe customer
            $stripeCustomerId = $this->getOrCreateStripeCustomer($store, $owner);

            $setupIntent = SetupIntent::create([
                'customer' => $stripeCustomerId,
                'payment_method_types' => ['card'],
                'metadata' => [
                    'store_id' => $store->id,
                    'owner_id' => $owner->id,
                ],
            ]);

            return response()->json([
                'client_secret' => $setupIntent->client_secret,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe setup intent error', [
                'error' => $e->getMessage(),
                'store_id' => $store->id,
            ]);

            return response()->json([
                'message' => 'Failed to initialize payment setup',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Subscribe to a plan
     */
    public function subscribe(Request $request, Store $store): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
            'payment_method_id' => 'nullable|string',
        ]);

        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        // If Stripe is not enabled, create a manual subscription
        if (!$this->stripeEnabled) {
            return $this->createManualSubscription($store, $plan);
        }

        if (!$plan->stripe_price_id) {
            return response()->json([
                'message' => 'This plan is not available for subscription',
            ], 400);
        }

        try {
            $owner = $store->owner;
            $stripeCustomerId = $this->getOrCreateStripeCustomer($store, $owner);

            // Attach payment method to customer
            $paymentMethod = \Stripe\PaymentMethod::retrieve($request->payment_method_id);
            $paymentMethod->attach(['customer' => $stripeCustomerId]);

            // Set as default payment method
            Customer::update($stripeCustomerId, [
                'invoice_settings' => [
                    'default_payment_method' => $request->payment_method_id,
                ],
            ]);

            // Cancel any existing subscription
            if ($store->activeSubscription && $store->activeSubscription->stripe_subscription_id) {
                try {
                    $existingSub = StripeSubscription::retrieve($store->activeSubscription->stripe_subscription_id);
                    $existingSub->cancel();
                } catch (\Exception $e) {
                    Log::warning('Could not cancel existing subscription', ['error' => $e->getMessage()]);
                }
                $store->activeSubscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            }

            // Create Stripe subscription
            $stripeSubscription = StripeSubscription::create([
                'customer' => $stripeCustomerId,
                'items' => [
                    ['price' => $plan->stripe_price_id],
                ],
                'metadata' => [
                    'store_id' => $store->id,
                    'owner_id' => $owner->id,
                    'plan_id' => $plan->id,
                ],
                'expand' => ['latest_invoice.payment_intent'],
            ]);

            // Create local subscription record
            $subscription = Subscription::create([
                'store_id' => $store->id,
                'plan_id' => $plan->id,
                'stripe_subscription_id' => $stripeSubscription->id,
                'stripe_customer_id' => $stripeCustomerId,
                'status' => $this->mapStripeStatus($stripeSubscription->status),
                'amount' => $plan->price,
                'currency' => $plan->currency ?? 'USD',
                'current_period_start' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                'current_period_end' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end),
            ]);

            // Activate the store
            $store->update([
                'status' => Store::STATUS_ACTIVE,
                'is_active' => true,
            ]);

            return response()->json([
                'message' => 'Subscription created successfully',
                'subscription' => $subscription->load('plan'),
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe subscription error', [
                'error' => $e->getMessage(),
                'store_id' => $store->id,
            ]);

            return response()->json([
                'message' => 'Failed to create subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a manual subscription (when Stripe is not configured)
     */
    protected function createManualSubscription(Store $store, SubscriptionPlan $plan): JsonResponse
    {
        // Cancel any existing subscription
        if ($store->activeSubscription) {
            $store->activeSubscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
        }

        // Create local subscription record
        $subscription = Subscription::create([
            'store_id' => $store->id,
            'plan_id' => $plan->id,
            'stripe_subscription_id' => null,
            'stripe_customer_id' => null,
            'status' => 'active',
            'amount' => $plan->price,
            'currency' => $plan->currency ?? 'USD',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        // Activate the store
        $store->update([
            'status' => Store::STATUS_ACTIVE,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Subscription created successfully',
            'subscription' => $subscription->load('plan'),
        ]);
    }

    /**
     * Cancel subscription
     */
    public function cancel(Store $store): JsonResponse
    {
        $subscription = $store->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'message' => 'No active subscription found',
            ], 404);
        }

        try {
            if ($subscription->stripe_subscription_id) {
                $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);
                $stripeSubscription->cancel_at_period_end = true;
                $stripeSubscription->save();
            }

            $subscription->update([
                'cancelled_at' => now(),
            ]);

            return response()->json([
                'message' => 'Subscription will be cancelled at the end of the billing period',
                'subscription' => $subscription->fresh()->load('plan'),
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe cancellation error', [
                'error' => $e->getMessage(),
                'store_id' => $store->id,
            ]);

            return response()->json([
                'message' => 'Failed to cancel subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resume cancelled subscription
     */
    public function resume(Store $store): JsonResponse
    {
        $subscription = $store->activeSubscription;

        if (!$subscription || !$subscription->cancelled_at) {
            return response()->json([
                'message' => 'No cancelled subscription found',
            ], 404);
        }

        try {
            if ($subscription->stripe_subscription_id) {
                $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);
                $stripeSubscription->cancel_at_period_end = false;
                $stripeSubscription->save();
            }

            $subscription->update([
                'cancelled_at' => null,
            ]);

            return response()->json([
                'message' => 'Subscription resumed',
                'subscription' => $subscription->fresh()->load('plan'),
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe resume error', [
                'error' => $e->getMessage(),
                'store_id' => $store->id,
            ]);

            return response()->json([
                'message' => 'Failed to resume subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get billing history
     */
    public function invoices(Store $store): JsonResponse
    {
        $subscription = $store->activeSubscription;

        if (!$subscription || !$subscription->stripe_customer_id) {
            return response()->json([
                'invoices' => [],
            ]);
        }

        try {
            $invoices = \Stripe\Invoice::all([
                'customer' => $subscription->stripe_customer_id,
                'limit' => 20,
            ]);

            $formattedInvoices = collect($invoices->data)->map(function ($invoice) {
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

            return response()->json([
                'invoices' => $formattedInvoices,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe invoices error', [
                'error' => $e->getMessage(),
                'store_id' => $store->id,
            ]);

            return response()->json([
                'invoices' => [],
            ]);
        }
    }

    /**
     * Get or create Stripe customer
     */
    protected function getOrCreateStripeCustomer(Store $store, $owner): string
    {
        // Check if store has a subscription with customer ID
        $existingSubscription = $store->subscriptions()
            ->whereNotNull('stripe_customer_id')
            ->first();

        if ($existingSubscription) {
            return $existingSubscription->stripe_customer_id;
        }

        // Create new customer
        $customer = Customer::create([
            'email' => $owner->email,
            'name' => $owner->name,
            'metadata' => [
                'owner_id' => $owner->id,
                'store_id' => $store->id,
            ],
        ]);

        return $customer->id;
    }

    /**
     * Map Stripe subscription status to local status
     */
    protected function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'trialing' => Subscription::STATUS_TRIALING,
            'active' => Subscription::STATUS_ACTIVE,
            'past_due' => Subscription::STATUS_PAST_DUE,
            'canceled', 'cancelled' => Subscription::STATUS_CANCELLED,
            'unpaid' => Subscription::STATUS_UNPAID,
            default => $stripeStatus,
        };
    }
}
