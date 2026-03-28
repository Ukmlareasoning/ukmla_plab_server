<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Webinar;
use App\Models\WebinarBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class WebinarBookingController extends Controller
{
    /**
     * Get webinar IDs the authenticated user has booked (Confirmed).
     * GET /webinars/my-bookings
     * Requires JWT auth.
     */
    public function myBookings(Request $request): JsonResponse
    {
        $user = $request->get('auth_user');
        if (!$user || !$user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required.',
            ], 401);
        }

        $webinarIds = WebinarBooking::where('user_id', $user->id)
            ->where('status', 'Confirmed')
            ->pluck('webinar_id')
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Booked webinars retrieved.',
            'data' => [
                'webinar_ids' => $webinarIds,
            ],
        ], 200);
    }

    /**
     * Create a Stripe PaymentIntent for a paid webinar booking (client completes card in the browser).
     * POST /webinars/{id}/payment-intent
     */
    public function createPaymentIntent(Request $request, int $id): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $precheck = $this->webinarBookingPrecheck($user, $id);
        if ($precheck instanceof JsonResponse) {
            return $precheck;
        }

        /** @var Webinar $webinar */
        $webinar = $precheck['webinar'];
        $price = (float) $webinar->price;
        if ($price <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This webinar is free. Use book without payment.',
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
                'message' => 'Invalid webinar price for payment.',
            ], 422);
        }

        Stripe::setApiKey($secret);

        try {
            $intent = PaymentIntent::create([
                'amount' => $amountCents,
                'currency' => $currency,
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => [
                    'webinar_id' => (string) $webinar->id,
                    'user_id' => (string) $user->id,
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
     * Book a webinar for the authenticated user.
     * POST /webinars/{id}/book
     * Free: no body. Paid: JSON { "payment_intent_id": "pi_..." } after Stripe confirms payment.
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $precheck = $this->webinarBookingPrecheck($user, $id);
        if ($precheck instanceof JsonResponse) {
            return $precheck;
        }

        /** @var Webinar $webinar */
        $webinar = $precheck['webinar'];
        $price = (float) $webinar->price;

        if ($price <= 0) {
            return $this->createConfirmedBooking($webinar, $user, null, null, null);
        }

        $paymentIntentId = $request->input('payment_intent_id');
        if (!is_string($paymentIntentId) || $paymentIntentId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Payment is required for this webinar. Complete card payment first.',
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
                'message' => 'Payment has not completed successfully. Please finish paying or try again.',
            ], 422);
        }

        $metaWebinar = isset($intent->metadata['webinar_id']) ? (int) $intent->metadata['webinar_id'] : 0;
        $metaUser = isset($intent->metadata['user_id']) ? (int) $intent->metadata['user_id'] : 0;
        if ($metaWebinar !== (int) $webinar->id || $metaUser !== (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'This payment does not match this booking.',
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
                'message' => 'Payment amount does not match the webinar price.',
            ], 422);
        }

        $existingPi = WebinarBooking::where('stripe_payment_intent_id', $paymentIntentId)->first();
        if ($existingPi) {
            if ((int) $existingPi->webinar_id === (int) $webinar->id && (int) $existingPi->user_id === (int) $user->id) {
                return response()->json([
                    'success' => true,
                    'message' => 'Webinar booked successfully.',
                    'data' => [
                        'booking' => $this->bookingToArray($existingPi),
                    ],
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'This payment has already been used.',
            ], 422);
        }

        $amountPaid = round(((int) $intent->amount) / 100, 2);
        $payCurrency = strtolower((string) $intent->currency);

        return $this->createConfirmedBooking($webinar, $user, $paymentIntentId, $amountPaid, $payCurrency);
    }

    /**
     * List bookings for a webinar (admin use).
     * GET /webinars/{id}/bookings
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $webinar = Webinar::withTrashed()->find($id);

        if (!$webinar) {
            return response()->json([
                'success' => false,
                'message' => 'Webinar not found.',
            ], 404);
        }

        $bookingModels = WebinarBooking::with('user')
            ->where('webinar_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        $bookings = $bookingModels->map(function (WebinarBooking $b) {
            $row = [
                'id' => $b->id,
                'status' => $b->status,
                'created_at' => $b->created_at?->toIso8601String(),
                'user' => $b->user ? [
                    'id' => $b->user->id,
                    'first_name' => $b->user->first_name,
                    'last_name' => $b->user->last_name,
                    'email' => $b->user->email,
                    'profile_image' => $b->user->profile_image ? url($b->user->profile_image) : null,
                ] : null,
                'stripe_payment_intent_id' => $b->stripe_payment_intent_id,
                'amount_paid' => $b->amount_paid !== null ? (float) $b->amount_paid : null,
                'payment_currency' => $b->payment_currency ? strtoupper($b->payment_currency) : null,
                'payment_type' => $b->stripe_payment_intent_id ? 'stripe' : 'free',
            ];
            return $row;
        });

        $confirmed = $bookingModels->where('status', 'Confirmed');
        $confirmedCount = $confirmed->count();
        $paidConfirmed = $confirmed->filter(fn (WebinarBooking $b) => $b->stripe_payment_intent_id !== null);
        $grossRevenue = round(
            (float) $paidConfirmed->sum(fn (WebinarBooking $b) => (float) ($b->amount_paid ?? 0)),
            2,
        );
        $paidBookingsCount = $paidConfirmed->count();
        $freeBookingsCount = $confirmedCount - $paidBookingsCount;
        $revenueCurrency = strtoupper((string) config('services.stripe.currency', 'eur'));

        $remaining = $webinar->max_attendees !== null ? max(0, $webinar->max_attendees - $confirmedCount) : null;

        return response()->json([
            'success' => true,
            'message' => 'Bookings retrieved successfully.',
            'data' => [
                'bookings' => $bookings->values(),
                'total_bookings' => $bookings->count(),
                'confirmed_bookings' => $confirmedCount,
                'paid_bookings' => $paidBookingsCount,
                'free_bookings' => $freeBookingsCount,
                'gross_revenue' => $grossRevenue,
                'revenue_currency' => $revenueCurrency,
                'remaining_seats' => $remaining,
                'max_attendees' => $webinar->max_attendees,
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
     * @return array{webinar: Webinar}|JsonResponse
     */
    private function webinarBookingPrecheck(User $user, int $id): array|JsonResponse
    {
        $webinar = Webinar::find($id);

        if (!$webinar) {
            return response()->json([
                'success' => false,
                'message' => 'Webinar not found.',
            ], 404);
        }

        if ($webinar->status !== 'Active') {
            return response()->json([
                'success' => false,
                'message' => 'This webinar is not available for booking.',
            ], 422);
        }

        $endDateStr = $webinar->end_date ? $webinar->end_date->format('Y-m-d') : null;
        $endTimeStr = $webinar->end_time instanceof \Carbon\CarbonInterface
            ? $webinar->end_time->format('H:i:s')
            : (is_string($webinar->end_time) ? $webinar->end_time : '23:59:59');
        if ($endDateStr) {
            $endDatetime = \Carbon\Carbon::parse($endDateStr . ' ' . $endTimeStr);
            if ($endDatetime->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This webinar has already ended and cannot be booked.',
                ], 422);
            }
        }

        $existing = WebinarBooking::where('webinar_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You have already booked this webinar.',
            ], 422);
        }

        if ($webinar->max_attendees !== null) {
            $confirmed = WebinarBooking::where('webinar_id', $id)
                ->where('status', 'Confirmed')
                ->count();

            if ($confirmed >= $webinar->max_attendees) {
                return response()->json([
                    'success' => false,
                    'message' => 'This webinar is fully booked. No seats remaining.',
                ], 422);
            }
        }

        return ['webinar' => $webinar];
    }

    private function createConfirmedBooking(
        Webinar $webinar,
        User $user,
        ?string $stripePaymentIntentId,
        ?float $amountPaid,
        ?string $paymentCurrency,
    ): JsonResponse {
        $booking = WebinarBooking::create([
            'webinar_id' => $webinar->id,
            'user_id' => $user->id,
            'status' => 'Confirmed',
            'stripe_payment_intent_id' => $stripePaymentIntentId,
            'amount_paid' => $stripePaymentIntentId ? $amountPaid : null,
            'payment_currency' => $stripePaymentIntentId ? strtolower((string) $paymentCurrency) : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webinar booked successfully.',
            'data' => [
                'booking' => $this->bookingToArray($booking),
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingToArray(WebinarBooking $booking): array
    {
        return [
            'id' => $booking->id,
            'webinar_id' => $booking->webinar_id,
            'user_id' => $booking->user_id,
            'status' => $booking->status,
            'created_at' => $booking->created_at?->toIso8601String(),
            'stripe_payment_intent_id' => $booking->stripe_payment_intent_id,
            'amount_paid' => $booking->amount_paid !== null ? (float) $booking->amount_paid : null,
            'payment_currency' => $booking->payment_currency ? strtoupper($booking->payment_currency) : null,
            'payment_type' => $booking->stripe_payment_intent_id ? 'stripe' : 'free',
        ];
    }
}
