<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivePackageSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Invoice;
use Stripe\InvoicePayment;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Subscription;

class PackageSubscriptionController extends Controller
{
    /**
     * Public catalog for the pricing page.
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (SubscriptionPlan $plan) {
                $requiresPayment = (float) $plan->amount > 0;
                $priceId = $plan->resolvedStripePriceId();

                return [
                    'slug' => $plan->slug,
                    'title' => $plan->title,
                    'plan_name' => $plan->plan_name,
                    'price_display' => $plan->price_display,
                    'period_label' => $plan->period_label,
                    'amount' => $plan->amount,
                    'features' => $plan->features ?? [],
                    'who_for' => $plan->who_for,
                    'is_popular' => (bool) $plan->is_popular,
                    'saving_percent' => $plan->saving_percent,
                    'requires_payment' => $requiresPayment,
                    'stripe_ready' => !$requiresPayment || !empty($priceId),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'Subscription plans retrieved successfully.',
            'data' => [
                'plans' => $plans,
            ],
        ], 200);
    }

    /**
     * Current user's package subscription and access (JWT required).
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $sub = $this->currentEntitledSubscription($user->id);
        if ($sub && !empty($sub->stripe_subscription_id)) {
            $this->syncEntitledSubscriptionFromStripe($user, $sub);
            $sub = $sub->fresh();
        }

        if (!$sub) {
            return response()->json([
                'success' => true,
                'message' => 'No active package subscription.',
                'data' => [
                    'has_access' => false,
                    'subscription' => null,
                ],
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription retrieved successfully.',
            'data' => [
                'has_access' => true,
                'subscription' => $this->formatSubscription($sub),
            ],
        ], 200);
    }

    /**
     * Start Stripe subscription checkout: Payment Element client_secret (same pattern as webinars).
     * POST /my-package-subscription/subscribe-intent  { "plan_slug": "standard_monthly" }
     */
    public function createSubscribeIntent(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($this->currentEntitledSubscription($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active subscription. Cancel or end it before starting a new one.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'plan_slug' => ['required', 'string', 'max:64'],
        ], [
            'plan_slug.required' => 'Please choose a plan.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $plan = SubscriptionPlan::query()
            ->where('slug', $request->input('plan_slug'))
            ->where('is_active', true)
            ->first();

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found or inactive.',
            ], 404);
        }

        if ((float) $plan->amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This plan does not require card payment. Use subscribe instead.',
            ], 422);
        }

        $priceId = $plan->resolvedStripePriceId();
        if (empty($priceId)) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is not linked to Stripe yet. Add STRIPE_PRICE_* in .env or stripe_price_id on the plan.',
            ], 422);
        }

        $secret = config('services.stripe.secret');
        if (empty($secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Payments are not configured. Please contact support.',
            ], 503);
        }

        Stripe::setApiKey($secret);

        try {
            $customerId = $this->getOrCreateStripeCustomer($user);
            $this->cancelStaleIncompletePackageSubscriptions($customerId);

            $subscription = Subscription::create([
                'customer' => $customerId,
                'items' => [['price' => $priceId]],
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'plan_slug' => $plan->slug,
                    'plan_name' => $plan->plan_name,
                ],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
                'expand' => ['latest_invoice.payment_intent'],
            ]);
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $clientSecret = $this->resolveSubscriptionCheckoutClientSecret($subscription);
        if ($clientSecret === null || $clientSecret === '') {
            try {
                $subscription = Subscription::retrieve($subscription->id, [
                    'expand' => ['latest_invoice.payment_intent', 'latest_invoice.confirmation_secret'],
                ]);
                $clientSecret = $this->resolveSubscriptionCheckoutClientSecret($subscription);
            } catch (ApiErrorException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
        }

        if ($clientSecret === null || $clientSecret === '') {
            $invoiceId = $this->subscriptionLatestInvoiceId($subscription);
            $invoiceStatus = null;
            $amountDue = null;
            if ($invoiceId) {
                try {
                    $inv = Invoice::retrieve($invoiceId);
                    $invoiceStatus = $inv->status ?? null;
                    $amountDue = $inv->amount_due ?? null;
                } catch (ApiErrorException) {
                    // keep defaults
                }
            }
            $detail = sprintf(
                ' (subscription status: %s; invoice: %s; invoice status: %s; amount_due: %s).',
                $subscription->status ?? 'unknown',
                $invoiceId ?? 'none',
                $invoiceStatus ?? 'n/a',
                $amountDue !== null ? (string) $amountDue : 'n/a'
            );

            return response()->json([
                'success' => false,
                'message' => 'Could not start payment for this subscription. Use a recurring Price ID (price_…), ensure test keys match that price, and avoid a free trial that zeros the first invoice. If the invoice is draft, the server will finalize it — if this persists, check Stripe Dashboard → Invoices for that invoice.'.$detail,
            ], 422);
        }

        $publishable = config('services.stripe.key');
        if (empty($publishable)) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe publishable key is not configured.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment session created.',
            'data' => [
                'client_secret' => $clientSecret,
                'publishable_key' => $publishable,
                'stripe_subscription_id' => $subscription->id,
            ],
        ], 200);
    }

    /**
     * After Stripe.js confirms payment (or redirect return), persist subscription in our DB.
     * POST /my-package-subscription/complete-subscribe  { "payment_intent_id": "pi_..." }
     */
    public function completeSubscribe(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($this->currentEntitledSubscription($user->id)) {
            return response()->json([
                'success' => true,
                'message' => 'You already have an active subscription.',
                'data' => [
                    'subscription' => $this->formatSubscription($this->currentEntitledSubscription($user->id)),
                ],
            ], 200);
        }

        $validator = Validator::make($request->all(), [
            'payment_intent_id' => ['required', 'string', 'max:255'],
            'stripe_subscription_id' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $secret = config('services.stripe.secret');
        if (empty($secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Payments are not configured.',
            ], 503);
        }

        Stripe::setApiKey($secret);

        $user->refresh();

        $paymentIntentId = $request->input('payment_intent_id');
        $clientSubscriptionId = $request->input('stripe_subscription_id');

        try {
            /** @var PaymentIntent $intent */
            $intent = PaymentIntent::retrieve($paymentIntentId, ['expand' => ['invoice']]);
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not verify payment.',
            ], 422);
        }

        if ($intent->status !== 'succeeded') {
            return response()->json([
                'success' => false,
                'message' => 'Payment has not completed yet.',
            ], 422);
        }

        $customerId = is_string($intent->customer ?? null) ? $intent->customer : ($intent->customer->id ?? null);
        if (!$customerId || $customerId !== $user->stripe_id) {
            return response()->json([
                'success' => false,
                'message' => 'This payment does not belong to your account.',
            ], 403);
        }

        $invoiceObj = $intent->invoice ?? null;
        if (is_string($invoiceObj)) {
            try {
                $invoiceObj = Invoice::retrieve($invoiceObj, ['expand' => ['subscription']]);
            } catch (ApiErrorException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not load invoice for this payment.',
                ], 422);
            }
        }

        if (!is_object($invoiceObj)) {
            $invoiceObj = $this->resolveInvoiceFromPaymentIntentId($paymentIntentId);
        }

        if (!is_object($invoiceObj)) {
            return response()->json([
                'success' => false,
                'message' => 'Could not link this payment to an invoice. If this continues, contact support with your payment confirmation.',
            ], 422);
        }

        $stripeSubId = is_string($invoiceObj->subscription ?? null)
            ? $invoiceObj->subscription
            : ($invoiceObj->subscription->id ?? null);

        if (!$stripeSubId && is_string($clientSubscriptionId) && str_starts_with($clientSubscriptionId, 'sub_')) {
            $stripeSubId = $clientSubscriptionId;
        }

        if (!$stripeSubId) {
            return response()->json([
                'success' => false,
                'message' => 'No subscription linked to this payment.',
            ], 422);
        }

        try {
            /** @var Subscription $stripeSub */
            $stripeSub = $this->retrieveStripeSubscriptionAfterPayment($stripeSubId);
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not load subscription.',
            ], 422);
        }

        if (!empty($clientSubscriptionId) && $stripeSub->id !== $clientSubscriptionId) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription session mismatch. Close the dialog and try again.',
            ], 422);
        }

        $subCustomer = is_string($stripeSub->customer ?? null)
            ? $stripeSub->customer
            : ($stripeSub->customer->id ?? null);
        if (!$subCustomer || $subCustomer !== $user->stripe_id) {
            return response()->json([
                'success' => false,
                'message' => 'This subscription does not belong to your account.',
            ], 403);
        }

        $metaUserId = $this->stripeMetadataString($stripeSub->metadata, 'user_id');
        if ((string) $metaUserId !== (string) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription metadata does not match your account.',
            ], 403);
        }

        if (!in_array($stripeSub->status, ['active', 'trialing'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription is not active yet. Status: ' . ($stripeSub->status ?? 'unknown') . '. Wait a few seconds and refresh, or open Billing in Stripe to confirm the invoice is paid.',
            ], 422);
        }

        $existing = ActivePackageSubscription::query()
            ->where('stripe_subscription_id', $stripeSub->id)
            ->first();
        if ($existing) {
            if ((int) $existing->user_id !== (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription conflict.',
                ], 409);
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscription already active.',
                'data' => [
                    'subscription' => $this->formatSubscription($existing->fresh()),
                ],
            ], 200);
        }

        $plan = $this->resolvePlanForStripeSubscription($stripeSub);
        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found for this subscription. Ensure subscription metadata includes plan_slug or the Stripe price is linked in subscription_plans / .env.',
            ], 422);
        }

        $priceId = $this->stripeSubscriptionPriceId($stripeSub) ?? $plan->resolvedStripePriceId();
        $periodEndTs = (int) ($stripeSub->current_period_end ?? 0);
        $periodEnd = $periodEndTs > 0
            ? Carbon::createFromTimestamp($periodEndTs)
            : $this->computeEndsAt($plan);
        $cancelAtPeriodEnd = (bool) ($stripeSub->cancel_at_period_end ?? false);

        try {
            $subscription = DB::transaction(function () use ($user, $plan, $stripeSub, $priceId, $periodEnd, $cancelAtPeriodEnd) {
                $row = ActivePackageSubscription::create([
                    'user_id' => $user->id,
                    'plan_name' => $plan->plan_name,
                    'amount' => $plan->amount,
                    'starts_at' => now(),
                    'ends_at' => $periodEnd,
                    'status' => 'Active',
                    'reference' => 'stripe:' . $stripeSub->id,
                    'auto_renew' => !$cancelAtPeriodEnd,
                    'cancelled_at' => $cancelAtPeriodEnd ? now() : null,
                    'stripe_subscription_id' => $stripeSub->id,
                    'stripe_price_id' => $priceId,
                    'stripe_status' => $stripeSub->status,
                ]);

                $user->update([
                    'is_subscribed' => true,
                    'subscription_type' => $plan->plan_name,
                    'subscription_start_date' => now()->toDateString(),
                    'subscription_end_date' => $periodEnd->toDateString(),
                    'active_subscription_id' => $row->id,
                ]);

                return $row;
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? 'Could not save subscription: ' . $e->getMessage()
                    : 'Could not save subscription. Please contact support.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription started successfully.',
            'data' => [
                'subscription' => $this->formatSubscription($subscription->fresh()),
            ],
        ], 201);
    }

    /**
     * Free / zero-amount plans only (e.g. free trial). Paid plans use subscribe-intent + complete-subscribe.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($this->currentEntitledSubscription($user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active subscription. Cancel or end it before starting a new one.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'plan_slug' => ['required', 'string', 'max:64'],
        ], [
            'plan_slug.required' => 'Please choose a plan.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $plan = SubscriptionPlan::query()
            ->where('slug', $request->input('plan_slug'))
            ->where('is_active', true)
            ->first();

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found or inactive.',
            ], 404);
        }

        if ((float) $plan->amount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'This plan requires card payment. Use the checkout on the pricing page.',
                'errors' => ['plan_slug' => ['Paid plans require Stripe checkout.']],
            ], 422);
        }

        $endsAt = $this->computeEndsAt($plan);

        $subscription = DB::transaction(function () use ($user, $plan, $endsAt) {
            $row = ActivePackageSubscription::create([
                'user_id' => $user->id,
                'plan_name' => $plan->plan_name,
                'amount' => $plan->amount,
                'starts_at' => now(),
                'ends_at' => $endsAt,
                'status' => 'Active',
                'reference' => 'manual:' . uniqid('', true),
                'auto_renew' => true,
                'cancelled_at' => null,
            ]);

            $user->update([
                'is_subscribed' => true,
                'subscription_type' => $plan->plan_name,
                'subscription_start_date' => now()->toDateString(),
                'subscription_end_date' => $endsAt->toDateString(),
                'active_subscription_id' => $row->id,
            ]);

            return $row;
        });

        return response()->json([
            'success' => true,
            'message' => 'Subscription started successfully.',
            'data' => [
                'subscription' => $this->formatSubscription($subscription->fresh()),
            ],
        ], 201);
    }

    /**
     * Cancel auto-renew; access continues until the current period ends (Stripe cancel_at_period_end).
     */
    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $sub = $this->currentEntitledSubscription($user->id);
        if (!$sub) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription to cancel.',
            ], 404);
        }

        if (!$sub->auto_renew) {
            return response()->json([
                'success' => false,
                'message' => 'This subscription is already set to not renew.',
            ], 422);
        }

        $secret = config('services.stripe.secret');
        if (!empty($sub->stripe_subscription_id) && !empty($secret)) {
            Stripe::setApiKey($secret);
            try {
                Subscription::update($sub->stripe_subscription_id, [
                    'cancel_at_period_end' => true,
                ]);
            } catch (ApiErrorException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not update subscription with Stripe: ' . $e->getMessage(),
                ], 422);
            }
        }

        $sub->update([
            'auto_renew' => false,
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Auto-renew is off. You keep access until ' . $sub->ends_at?->toIso8601String() . '.',
            'data' => [
                'subscription' => $this->formatSubscription($sub->fresh()),
            ],
        ], 200);
    }

    /**
     * End subscription immediately (no access); Stripe subscription is canceled right away when linked.
     */
    public function endNow(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $sub = $this->currentEntitledSubscription($user->id);
        if (!$sub) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription to end.',
            ], 404);
        }

        $secret = config('services.stripe.secret');
        if (!empty($sub->stripe_subscription_id) && !empty($secret)) {
            Stripe::setApiKey($secret);
            try {
                $stripeSub = Subscription::retrieve($sub->stripe_subscription_id);
                $stripeSub->cancel();
            } catch (ApiErrorException $e) {
                // If already canceled in Stripe, continue clearing local access
                if (!str_contains(strtolower($e->getMessage()), 'no such subscription')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Could not cancel subscription with Stripe: ' . $e->getMessage(),
                    ], 422);
                }
            }
        }

        $sub->update([
            'status' => 'Ended',
            'ends_at' => now(),
            'auto_renew' => false,
            'cancelled_at' => now(),
            'stripe_status' => 'canceled',
        ]);

        $user->update([
            'is_subscribed' => false,
            'subscription_type' => null,
            'subscription_start_date' => null,
            'subscription_end_date' => null,
            'active_subscription_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your subscription has ended immediately.',
            'data' => [
                'has_access' => false,
            ],
        ], 200);
    }

    private function getOrCreateStripeCustomer(User $user): string
    {
        if (!empty($user->stripe_id)) {
            return $user->stripe_id;
        }

        $customer = Customer::create([
            'email' => $user->email,
            'metadata' => ['user_id' => (string) $user->id],
        ]);

        $user->update(['stripe_id' => $customer->id]);

        return $customer->id;
    }

    /**
     * Avoid cancelling a brand-new incomplete subscription: React Strict Mode (or double subscribe-intent)
     * would DELETE the sub that the Payment Element is still using → "Invalid payment session" on pay.
     * Only cancel incomplete subs older than ~2 minutes, plus incomplete_expired.
     */
    private function cancelStaleIncompletePackageSubscriptions(string $customerId): void
    {
        $now = time();
        try {
            $list = Subscription::all([
                'customer' => $customerId,
                'status' => 'incomplete',
                'limit' => 20,
            ]);
            foreach ($list->data as $s) {
                $created = (int) ($s->created ?? 0);
                if ($now - $created < 120) {
                    continue;
                }
                try {
                    $s->cancel();
                } catch (ApiErrorException) {
                    //
                }
            }
            $expired = Subscription::all([
                'customer' => $customerId,
                'status' => 'incomplete_expired',
                'limit' => 20,
            ]);
            foreach ($expired->data as $s) {
                try {
                    $s->cancel();
                } catch (ApiErrorException) {
                    //
                }
            }
        } catch (ApiErrorException) {
            //
        }
    }

    /**
     * Newer Stripe Billing links invoice ↔ PaymentIntent via InvoicePayment; PI.invoice may be null.
     */
    private function resolveInvoiceFromPaymentIntentId(string $paymentIntentId): ?Invoice
    {
        try {
            $list = InvoicePayment::all([
                'payment' => [
                    'type' => 'payment_intent',
                    'payment_intent' => $paymentIntentId,
                ],
                'limit' => 5,
                'expand' => ['data.invoice', 'data.invoice.subscription'],
            ]);
            foreach ($list->data as $row) {
                $inv = $row->invoice ?? null;
                if (is_string($inv) && $inv !== '') {
                    try {
                        return Invoice::retrieve($inv, ['expand' => ['subscription']]);
                    } catch (ApiErrorException) {
                        continue;
                    }
                }
                if (is_object($inv)) {
                    return $inv;
                }
            }
        } catch (ApiErrorException) {
            return null;
        }

        return null;
    }

    private function syncEntitledSubscriptionFromStripe(User $user, ActivePackageSubscription $sub): void
    {
        $secret = config('services.stripe.secret');
        if (empty($secret) || empty($sub->stripe_subscription_id)) {
            return;
        }

        Stripe::setApiKey($secret);

        try {
            $stripeSub = Subscription::retrieve($sub->stripe_subscription_id);
        } catch (ApiErrorException $e) {
            return;
        }

        $status = $stripeSub->status ?? '';
        $cancelAtPeriodEnd = (bool) ($stripeSub->cancel_at_period_end ?? false);
        $periodEnd = isset($stripeSub->current_period_end)
            ? Carbon::createFromTimestamp((int) $stripeSub->current_period_end)
            : $sub->ends_at;

        $activeLike = in_array($status, ['active', 'trialing'], true);

        $updates = [
            'ends_at' => $periodEnd,
            'stripe_status' => $status,
            'auto_renew' => $activeLike && !$cancelAtPeriodEnd,
        ];

        if ($cancelAtPeriodEnd && !$sub->cancelled_at) {
            $updates['cancelled_at'] = now();
        }

        if (in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            $updates['status'] = 'Ended';
            $updates['auto_renew'] = false;
        } elseif ($activeLike) {
            $updates['status'] = 'Active';
        }

        $sub->update($updates);

        if ((int) $user->active_subscription_id === (int) $sub->id && $sub->fresh()->status === 'Active') {
            $user->update([
                'subscription_end_date' => $periodEnd->toDateString(),
            ]);
        }
    }

    private function subscriptionLatestInvoiceId(Subscription $subscription): ?string
    {
        $inv = $subscription->latest_invoice ?? null;
        if (is_string($inv) && $inv !== '') {
            return $inv;
        }
        if (is_object($inv) && !empty($inv->id)) {
            return $inv->id;
        }

        return null;
    }

    /**
     * Client secret for Payment Element: newer Stripe APIs use invoice confirmation_secret;
     * draft invoices must be finalized before a PaymentIntent / secret exists.
     */
    private function resolveSubscriptionCheckoutClientSecret(Subscription $subscription): ?string
    {
        $invoiceId = $this->subscriptionLatestInvoiceId($subscription);
        if ($invoiceId === null || $invoiceId === '') {
            return null;
        }

        $expand = ['payment_intent', 'confirmation_secret', 'payments'];

        try {
            $invoice = Invoice::retrieve($invoiceId, ['expand' => $expand]);
        } catch (ApiErrorException) {
            return null;
        }

        $secret = $this->extractClientSecretFromInvoice($invoice);
        if ($secret !== null && $secret !== '') {
            return $secret;
        }

        if (($invoice->status ?? '') === 'draft') {
            try {
                $invoice->finalizeInvoice(['expand' => $expand]);
            } catch (ApiErrorException) {
                return null;
            }

            return $this->extractClientSecretFromInvoice($invoice);
        }

        return null;
    }

    private function extractClientSecretFromInvoice(object $invoice): ?string
    {
        $confirmation = $invoice->confirmation_secret ?? null;
        if (is_object($confirmation) && !empty($confirmation->client_secret)) {
            return $confirmation->client_secret;
        }

        $pi = $invoice->payment_intent ?? null;
        if (is_object($pi) && !empty($pi->client_secret)) {
            return $pi->client_secret;
        }
        if (is_string($pi) && $pi !== '') {
            try {
                $retrieved = PaymentIntent::retrieve($pi);

                return $retrieved->client_secret ?? null;
            } catch (ApiErrorException) {
                return null;
            }
        }

        // Stripe Billing (newer APIs): PaymentIntent is linked via InvoicePayment, not always on the invoice root.
        $payments = $invoice->payments ?? null;
        if ($payments && !empty($payments->data)) {
            foreach ($payments->data as $row) {
                $secret = $this->clientSecretFromInvoicePaymentRow($row);
                if ($secret !== null && $secret !== '') {
                    return $secret;
                }
            }
        }

        if (!empty($invoice->id)) {
            try {
                $list = InvoicePayment::all([
                    'invoice' => $invoice->id,
                    'limit' => 10,
                    'expand' => ['data.payment.payment_intent'],
                ]);
                foreach ($list->data as $row) {
                    $secret = $this->clientSecretFromInvoicePaymentRow($row);
                    if ($secret !== null && $secret !== '') {
                        return $secret;
                    }
                }
            } catch (ApiErrorException) {
                // ignore
            }
        }

        return null;
    }

    private function clientSecretFromInvoicePaymentRow(object $invoicePayment): ?string
    {
        $payment = $invoicePayment->payment ?? null;
        if (!is_object($payment)) {
            return null;
        }

        $pi = $payment->payment_intent ?? null;
        if (is_object($pi) && !empty($pi->client_secret)) {
            return $pi->client_secret;
        }
        if (is_string($pi) && $pi !== '') {
            try {
                $retrieved = PaymentIntent::retrieve($pi);

                return $retrieved->client_secret ?? null;
            } catch (ApiErrorException) {
                return null;
            }
        }

        return null;
    }

    /**
     * After confirmPayment succeeds, Stripe can take a short time to move the subscription from incomplete → active.
     */
    private function retrieveStripeSubscriptionAfterPayment(string $stripeSubId): Subscription
    {
        $expand = ['items.data.price'];
        $deadline = microtime(true) + 15.0;
        $sleepMs = 120;
        $last = Subscription::retrieve($stripeSubId, ['expand' => $expand]);

        while (microtime(true) < $deadline) {
            $status = $last->status ?? '';
            if (in_array($status, ['active', 'trialing'], true)) {
                return $last;
            }
            if (in_array($status, ['canceled', 'incomplete_expired', 'unpaid'], true)) {
                return $last;
            }
            usleep($sleepMs * 1000);
            $sleepMs = min(700, (int) ($sleepMs * 1.35));
            $last = Subscription::retrieve($stripeSubId, ['expand' => $expand]);
        }

        return $last;
    }

    private function stripeMetadataString($metadata, string $key): ?string
    {
        if ($metadata === null) {
            return null;
        }
        try {
            if (isset($metadata[$key])) {
                return (string) $metadata[$key];
            }
        } catch (\Throwable) {
            // StripeObject edge cases
        }

        return null;
    }

    private function resolvePlanForStripeSubscription(Subscription $stripeSub): ?SubscriptionPlan
    {
        $slug = $this->stripeMetadataString($stripeSub->metadata, 'plan_slug');
        if ($slug) {
            $bySlug = SubscriptionPlan::query()->where('slug', $slug)->where('is_active', true)->first();
            if ($bySlug) {
                return $bySlug;
            }
        }

        $priceId = $this->stripeSubscriptionPriceId($stripeSub);
        if (!$priceId) {
            return null;
        }

        $byColumn = SubscriptionPlan::query()
            ->where('is_active', true)
            ->where('stripe_price_id', $priceId)
            ->first();
        if ($byColumn) {
            return $byColumn;
        }

        foreach (SubscriptionPlan::query()->where('is_active', true)->cursor() as $plan) {
            if ($plan->resolvedStripePriceId() === $priceId) {
                return $plan;
            }
        }

        return null;
    }

    private function stripeSubscriptionPriceId(Subscription $stripeSub): ?string
    {
        $items = $stripeSub->items ?? null;
        if (!is_object($items)) {
            return null;
        }
        $data = $items->data ?? null;
        if ($data === null) {
            return null;
        }
        foreach ($data as $line) {
            if (!is_object($line)) {
                continue;
            }
            $price = $line->price ?? null;
            if (is_object($price) && !empty($price->id)) {
                return $price->id;
            }
            if (is_string($price) && str_starts_with($price, 'price_')) {
                return $price;
            }
        }

        return null;
    }

    private function currentEntitledSubscription(int $userId): ?ActivePackageSubscription
    {
        return ActivePackageSubscription::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['Active', 'Cancelled'])
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->orderByDesc('ends_at')
            ->first();
    }

    private function computeEndsAt(SubscriptionPlan $plan): Carbon
    {
        if ($plan->duration_days !== null && (int) $plan->duration_days > 0) {
            return now()->addDays((int) $plan->duration_days);
        }

        $months = (int) ($plan->duration_months ?? 1);

        return now()->addMonths(max(1, $months));
    }

    private function formatSubscription(ActivePackageSubscription $sub): array
    {
        $endsAt = $sub->ends_at;
        $willRenew = (bool) $sub->auto_renew && $sub->status === 'Active';

        return [
            'id' => $sub->id,
            'plan_name' => $sub->plan_name,
            'amount' => $sub->amount,
            'starts_at' => $sub->starts_at?->toIso8601String(),
            'ends_at' => $endsAt?->toIso8601String(),
            'status' => $sub->status,
            'reference' => $sub->reference,
            'auto_renew' => (bool) $sub->auto_renew,
            'cancelled_at' => $sub->cancelled_at?->toIso8601String(),
            'will_renew' => $willRenew,
            'stripe_subscription_id' => $sub->stripe_subscription_id,
            'stripe_price_id' => $sub->stripe_price_id,
            'stripe_status' => $sub->stripe_status,
        ];
    }
}
