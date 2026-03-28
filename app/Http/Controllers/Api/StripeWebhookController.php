<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivePackageSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    /**
     * Stripe sends raw body; signature in Stripe-Signature header.
     */
    public function handle(Request $request): Response
    {
        $secret = config('services.stripe.webhook_secret');
        $apiSecret = config('services.stripe.secret');
        if (empty($secret) || empty($apiSecret)) {
            return response('Webhook not configured.', 503);
        }

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        if (!$sigHeader) {
            return response('Missing signature.', 400);
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            return response('Invalid signature.', 400);
        }

        Stripe::setApiKey($apiSecret);

        try {
            match ($event->type) {
                'invoice.payment_succeeded' => $this->onInvoicePaymentSucceeded($event->data->object),
                'customer.subscription.updated' => $this->onSubscriptionUpdated($event->data->object),
                'customer.subscription.deleted' => $this->onSubscriptionDeleted($event->data->object),
                default => null,
            };
        } catch (\Throwable $e) {
            report($e);

            return response('Handler error.', 500);
        }

        return response('OK', 200);
    }

    private function onInvoicePaymentSucceeded(object $invoice): void
    {
        $subId = is_string($invoice->subscription ?? null) ? $invoice->subscription : ($invoice->subscription->id ?? null);
        if (!$subId) {
            return;
        }

        $row = ActivePackageSubscription::query()
            ->where('stripe_subscription_id', $subId)
            ->first();
        if (!$row) {
            return;
        }

        $stripeSub = Subscription::retrieve($subId);
        $this->applyStripeSubscriptionToRow($row, $stripeSub);
    }

    private function onSubscriptionUpdated(object $stripeSub): void
    {
        $subId = $stripeSub->id ?? null;
        if (!$subId) {
            return;
        }

        $row = ActivePackageSubscription::query()
            ->where('stripe_subscription_id', $subId)
            ->first();
        if (!$row) {
            return;
        }

        $this->applyStripeSubscriptionToRow($row, $stripeSub);
    }

    private function onSubscriptionDeleted(object $stripeSub): void
    {
        $subId = $stripeSub->id ?? null;
        if (!$subId) {
            return;
        }

        $row = ActivePackageSubscription::query()
            ->where('stripe_subscription_id', $subId)
            ->first();
        if (!$row) {
            return;
        }

        $row->update([
            'status' => 'Ended',
            'auto_renew' => false,
            'stripe_status' => 'canceled',
            'ends_at' => now(),
        ]);

        $user = User::query()->find($row->user_id);
        if ($user && (int) $user->active_subscription_id === (int) $row->id) {
            $user->update([
                'is_subscribed' => false,
                'subscription_type' => null,
                'subscription_start_date' => null,
                'subscription_end_date' => null,
                'active_subscription_id' => null,
            ]);
        }
    }

    private function applyStripeSubscriptionToRow(ActivePackageSubscription $row, object $stripeSub): void
    {
        $status = $stripeSub->status ?? '';
        $cancelAtPeriodEnd = (bool) ($stripeSub->cancel_at_period_end ?? false);
        $periodEnd = isset($stripeSub->current_period_end)
            ? Carbon::createFromTimestamp((int) $stripeSub->current_period_end)
            : $row->ends_at;

        $activeLike = in_array($status, ['active', 'trialing'], true);

        $updates = [
            'ends_at' => $periodEnd,
            'stripe_status' => $status,
            'auto_renew' => $activeLike && !$cancelAtPeriodEnd,
        ];

        if ($cancelAtPeriodEnd && !$row->cancelled_at) {
            $updates['cancelled_at'] = now();
        }

        if (!$cancelAtPeriodEnd && $activeLike && $row->cancelled_at && $row->status === 'Active') {
            $updates['cancelled_at'] = null;
        }

        if (in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            $updates['status'] = 'Ended';
            $updates['auto_renew'] = false;
        } elseif ($activeLike && $row->status !== 'Ended') {
            $updates['status'] = 'Active';
        }

        $row->update($updates);
        $row = $row->fresh();

        $user = User::query()->find($row->user_id);
        if (!$user || (int) $user->active_subscription_id !== (int) $row->id) {
            return;
        }

        if ($row->status === 'Active') {
            $user->update([
                'subscription_end_date' => $periodEnd->toDateString(),
            ]);

            return;
        }

        $user->update([
            'is_subscribed' => false,
            'subscription_type' => null,
            'subscription_start_date' => null,
            'subscription_end_date' => null,
            'active_subscription_id' => null,
        ]);
    }
}
