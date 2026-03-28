<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mock;
use App\Models\MockPurchase;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class MockPurchaseController extends Controller
{
    /**
     * Mock IDs the current user has purchased (paid mocks).
     * GET /mocks/my-purchases
     */
    public function myPurchases(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $ids = MockPurchase::where('user_id', $user->id)
            ->pluck('mock_id')
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Purchased mocks retrieved.',
            'data' => [
                'mock_ids' => $ids,
            ],
        ], 200);
    }

    /**
     * Stripe PaymentIntent for purchasing access to a paid mock.
     * POST /mocks/{id}/payment-intent
     */
    public function createPaymentIntent(Request $request, int $id): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $precheck = $this->purchasePrecheck($user, $id);
        if ($precheck instanceof JsonResponse) {
            return $precheck;
        }

        /** @var Mock $mock */
        $mock = $precheck['mock'];
        $price = (float) ($mock->price_eur ?? 0);
        if ($price <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This mock is free. No payment is required.',
            ], 422);
        }

        $secret = config('services.stripe.secret');
        if (empty($secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Payments are not configured. Please contact support.',
            ], 503);
        }

        $currency = strtolower((string) config('services.stripe.currency', 'eur'));
        $amountCents = (int) round($price * 100);
        if ($amountCents < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid price for payment.',
            ], 422);
        }

        Stripe::setApiKey($secret);

        try {
            $intent = PaymentIntent::create([
                'amount' => $amountCents,
                'currency' => $currency,
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => [
                    'mock_id' => (string) $mock->id,
                    'user_id' => (string) $user->id,
                    'type' => 'mock_purchase',
                ],
            ]);
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to start payment. Please try again.',
            ], 502);
        }

        $publishable = config('services.stripe.key');
        if (empty($publishable)) {
            return response()->json([
                'success' => false,
                'message' => 'Payments are not fully configured. Please contact support.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment initialized.',
            'data' => [
                'client_secret' => $intent->client_secret,
                'publishable_key' => $publishable,
                'payment_intent_id' => $intent->id,
                'amount_cents' => $amountCents,
                'currency' => $currency,
            ],
        ], 200);
    }

    /**
     * Confirm purchase after Stripe succeeds. Body: { "payment_intent_id": "pi_..." }
     * POST /mocks/{id}/purchase
     */
    public function purchase(Request $request, int $id): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $precheck = $this->purchasePrecheck($user, $id);
        if ($precheck instanceof JsonResponse) {
            return $precheck;
        }

        /** @var Mock $mock */
        $mock = $precheck['mock'];
        $price = (float) ($mock->price_eur ?? 0);

        if (!$mock->is_paid || $price <= 0) {
            return response()->json([
                'success' => true,
                'message' => 'This mock is free — you can start practising without payment.',
                'data' => ['free_access' => true],
            ], 200);
        }

        $paymentIntentId = $request->input('payment_intent_id');
        if (!is_string($paymentIntentId) || $paymentIntentId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Complete card payment first.',
                'errors' => ['payment_intent_id' => ['Payment confirmation is required.']],
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
            $intent = PaymentIntent::retrieve($paymentIntentId);
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to verify payment. Please try again.',
            ], 422);
        }

        if ($intent->status !== 'succeeded') {
            return response()->json([
                'success' => false,
                'message' => 'Payment has not completed successfully.',
            ], 422);
        }

        $metaMock = isset($intent->metadata['mock_id']) ? (int) $intent->metadata['mock_id'] : 0;
        $metaUser = isset($intent->metadata['user_id']) ? (int) $intent->metadata['user_id'] : 0;
        if ($metaMock !== (int) $mock->id || $metaUser !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'This payment does not match this mock exam.',
            ], 422);
        }

        $currency = strtolower((string) config('services.stripe.currency', 'eur'));
        if (strtolower((string) $intent->currency) !== $currency) {
            return response()->json([
                'success' => false,
                'message' => 'Payment currency mismatch.',
            ], 422);
        }

        $expectedCents = (int) round($price * 100);
        if ((int) $intent->amount !== $expectedCents) {
            return response()->json([
                'success' => false,
                'message' => 'Payment amount does not match the mock price.',
            ], 422);
        }

        $existingPi = MockPurchase::where('stripe_payment_intent_id', $paymentIntentId)->first();
        if ($existingPi) {
            if ((int) $existingPi->mock_id === (int) $mock->id && (int) $existingPi->user_id === (int) $user->id) {
                return response()->json([
                    'success' => true,
                    'message' => 'You already have access to this mock exam.',
                    'data' => ['purchase' => $this->purchaseToArray($existingPi)],
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'This payment has already been used.',
            ], 422);
        }

        $existingUserMock = MockPurchase::where('user_id', $user->id)
            ->where('mock_id', $mock->id)
            ->first();
        if ($existingUserMock) {
            return response()->json([
                'success' => true,
                'message' => 'You already have access to this mock exam.',
                'data' => ['purchase' => $this->purchaseToArray($existingUserMock)],
            ], 200);
        }

        $amountPaid = round(((int) $intent->amount) / 100, 2);
        $payCurrency = strtolower((string) $intent->currency);

        $purchase = MockPurchase::create([
            'user_id' => $user->id,
            'mock_id' => $mock->id,
            'stripe_payment_intent_id' => $paymentIntentId,
            'amount_paid' => $amountPaid,
            'payment_currency' => $payCurrency,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Purchase successful. You can start practising.',
            'data' => ['purchase' => $this->purchaseToArray($purchase)],
        ], 201);
    }

    /**
     * Admin: list purchases / earnings for a mock.
     * GET /mocks/{id}/purchases
     */
    public function adminIndex(Request $request, int $id): JsonResponse
    {
        $mock = Mock::withTrashed()->find($id);
        if (!$mock) {
            return response()->json([
                'success' => false,
                'message' => 'Mock exam not found.',
            ], 404);
        }

        $purchases = MockPurchase::with('user')
            ->where('mock_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        $rows = $purchases->map(function (MockPurchase $p) {
            return [
                'id' => $p->id,
                'created_at' => $p->created_at?->toIso8601String(),
                'stripe_payment_intent_id' => $p->stripe_payment_intent_id,
                'amount_paid' => $p->amount_paid !== null ? (float) $p->amount_paid : null,
                'payment_currency' => $p->payment_currency ? strtoupper($p->payment_currency) : null,
                'user' => $p->user ? [
                    'id' => $p->user->id,
                    'first_name' => $p->user->first_name,
                    'last_name' => $p->user->last_name,
                    'email' => $p->user->email,
                    'profile_image' => $p->user->profile_image ? url($p->user->profile_image) : null,
                ] : null,
            ];
        });

        $grossRevenue = round(
            (float) $purchases->sum(fn (MockPurchase $p) => (float) ($p->amount_paid ?? 0)),
            2,
        );
        $revenueCurrency = strtoupper((string) config('services.stripe.currency', 'eur'));

        return response()->json([
            'success' => true,
            'message' => 'Purchases retrieved successfully.',
            'data' => [
                'mock_id' => $mock->id,
                'mock_title' => $mock->title,
                'is_paid' => (bool) $mock->is_paid,
                'list_price_eur' => $mock->price_eur,
                'purchases' => $rows->values(),
                'total_purchases' => $purchases->count(),
                'gross_revenue' => $grossRevenue,
                'revenue_currency' => $revenueCurrency,
            ],
        ], 200);
    }

    private function requireUser(Request $request): User|JsonResponse
    {
        $user = $request->get('auth_user');
        if (!$user || !$user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please login again.',
            ], 401);
        }

        return $user;
    }

    /**
     * @return array{mock: Mock}|JsonResponse
     */
    private function purchasePrecheck(User $user, int $id): array|JsonResponse
    {
        $mock = Mock::withoutTrashed()->find($id);
        if (!$mock) {
            return response()->json([
                'success' => false,
                'message' => 'Mock exam not found.',
            ], 404);
        }

        if ($mock->status !== 'Active') {
            return response()->json([
                'success' => false,
                'message' => 'This mock exam is not available for purchase.',
            ], 422);
        }

        if (MockPurchase::where('user_id', $user->id)->where('mock_id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have access to this mock exam.',
            ], 422);
        }

        return ['mock' => $mock];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseToArray(MockPurchase $p): array
    {
        return [
            'id' => $p->id,
            'mock_id' => $p->mock_id,
            'user_id' => $p->user_id,
            'stripe_payment_intent_id' => $p->stripe_payment_intent_id,
            'amount_paid' => $p->amount_paid !== null ? (float) $p->amount_paid : null,
            'payment_currency' => $p->payment_currency ? strtoupper($p->payment_currency) : null,
            'created_at' => $p->created_at?->toIso8601String(),
        ];
    }
}
