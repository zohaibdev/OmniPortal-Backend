<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Stripe\Stripe;
use Stripe\Customer as StripeCustomer;
use Stripe\Subscription as StripeSubscription;

class SubscriptionService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create subscription for store
     */
    public function create(Store $store, SubscriptionPlan $plan, string $paymentMethodId): Subscription
    {
        // Get or create Stripe customer
        $stripeCustomerId = $this->getOrCreateStripeCustomer($store);

        // Attach payment method
        \Stripe\PaymentMethod::retrieve($paymentMethodId)->attach([
            'customer' => $stripeCustomerId,
        ]);

        // Set as default payment method
        StripeCustomer::update($stripeCustomerId, [
            'invoice_settings' => [
                'default_payment_method' => $paymentMethodId,
            ],
        ]);

        // Create Stripe subscription
        $stripeSubscription = StripeSubscription::create([
            'customer' => $stripeCustomerId,
            'items' => [
                ['price' => $plan->stripe_price_id],
            ],
            'trial_period_days' => $plan->trial_days,
            'expand' => ['latest_invoice.payment_intent'],
        ]);

        // Create local subscription
        return Subscription::create([
            'store_id' => $store->id,
            'plan_id' => $plan->id,
            'stripe_subscription_id' => $stripeSubscription->id,
            'stripe_customer_id' => $stripeCustomerId,
            'status' => $this->mapStripeStatus($stripeSubscription->status),
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'trial_ends_at' => $stripeSubscription->trial_end 
                ? now()->setTimestamp($stripeSubscription->trial_end) 
                : null,
            'current_period_start' => now()->setTimestamp($stripeSubscription->current_period_start),
            'current_period_end' => now()->setTimestamp($stripeSubscription->current_period_end),
        ]);
    }

    /**
     * Cancel subscription
     */
    public function cancel(Subscription $subscription, bool $immediately = false): Subscription
    {
        if ($subscription->stripe_subscription_id) {
            if ($immediately) {
                StripeSubscription::retrieve($subscription->stripe_subscription_id)->cancel();
            } else {
                StripeSubscription::update($subscription->stripe_subscription_id, [
                    'cancel_at_period_end' => true,
                ]);
            }
        }

        $subscription->update([
            'cancelled_at' => now(),
            'status' => $immediately ? 'cancelled' : $subscription->status,
            'ended_at' => $immediately ? now() : null,
        ]);

        return $subscription->fresh();
    }

    /**
     * Resume cancelled subscription
     */
    public function resume(Subscription $subscription): Subscription
    {
        if ($subscription->stripe_subscription_id) {
            StripeSubscription::update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => false,
            ]);
        }

        $subscription->update([
            'cancelled_at' => null,
        ]);

        return $subscription->fresh();
    }

    /**
     * Change subscription plan
     */
    public function changePlan(Subscription $subscription, SubscriptionPlan $newPlan): Subscription
    {
        if ($subscription->stripe_subscription_id) {
            $stripeSubscription = StripeSubscription::retrieve($subscription->stripe_subscription_id);
            
            StripeSubscription::update($subscription->stripe_subscription_id, [
                'items' => [
                    [
                        'id' => $stripeSubscription->items->data[0]->id,
                        'price' => $newPlan->stripe_price_id,
                    ],
                ],
                'proration_behavior' => 'create_prorations',
            ]);
        }

        $subscription->update([
            'plan_id' => $newPlan->id,
            'amount' => $newPlan->price,
        ]);

        return $subscription->fresh();
    }

    /**
     * Handle Stripe webhook
     */
    public function handleWebhook(array $payload): void
    {
        $type = $payload['type'];
        $data = $payload['data']['object'];

        match ($type) {
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($data),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($data),
            'invoice.paid' => $this->handleInvoicePaid($data),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($data),
            default => null,
        };
    }

    protected function handleSubscriptionUpdated(array $data): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $data['id'])->first();
        
        if ($subscription) {
            $subscription->update([
                'status' => $this->mapStripeStatus($data['status']),
                'current_period_start' => now()->setTimestamp($data['current_period_start']),
                'current_period_end' => now()->setTimestamp($data['current_period_end']),
            ]);
        }
    }

    protected function handleSubscriptionDeleted(array $data): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $data['id'])->first();
        
        if ($subscription) {
            $subscription->update([
                'status' => 'cancelled',
                'ended_at' => now(),
            ]);
        }
    }

    protected function handleInvoicePaid(array $data): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $data['subscription'])->first();
        
        if ($subscription) {
            $subscription->invoices()->create([
                'stripe_invoice_id' => $data['id'],
                'number' => $data['number'],
                'amount' => $data['subtotal'] / 100,
                'tax' => $data['tax'] / 100,
                'total' => $data['total'] / 100,
                'currency' => strtoupper($data['currency']),
                'status' => 'paid',
                'hosted_invoice_url' => $data['hosted_invoice_url'],
                'pdf_url' => $data['invoice_pdf'],
                'paid_at' => now(),
            ]);
        }
    }

    protected function handleInvoicePaymentFailed(array $data): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $data['subscription'])->first();
        
        if ($subscription) {
            $subscription->update([
                'status' => 'past_due',
            ]);
        }
    }

    protected function getOrCreateStripeCustomer(Store $store): string
    {
        $existingSubscription = $store->subscriptions()->whereNotNull('stripe_customer_id')->first();
        
        if ($existingSubscription) {
            return $existingSubscription->stripe_customer_id;
        }

        $owner = $store->owner;
        $customer = StripeCustomer::create([
            'email' => $owner->email,
            'name' => $owner->name,
            'metadata' => [
                'store_id' => $store->id,
                'user_id' => $owner->id,
            ],
        ]);

        return $customer->id;
    }

    protected function mapStripeStatus(string $status): string
    {
        return match ($status) {
            'trialing' => 'trialing',
            'active' => 'active',
            'past_due' => 'past_due',
            'canceled' => 'cancelled',
            'unpaid' => 'unpaid',
            'incomplete' => 'incomplete',
            'incomplete_expired' => 'incomplete_expired',
            'paused' => 'paused',
            default => 'active',
        };
    }
}
