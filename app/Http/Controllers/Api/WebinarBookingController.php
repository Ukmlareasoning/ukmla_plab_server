<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webinar;
use App\Models\WebinarBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * Book a webinar for the authenticated user.
     * POST /webinars/{id}/book
     * Requires JWT auth.
     */
    public function store(Request $request, int $id): JsonResponse
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

        // Check if the webinar end datetime has passed (build safely: date + time string)
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

        $user = $request->get('auth_user');
        if (!$user || !$user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please login again.',
            ], 401);
        }

        // Check if already booked
        $existing = WebinarBooking::where('webinar_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You have already booked this webinar.',
            ], 422);
        }

        // Check max attendees
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

        $booking = WebinarBooking::create([
            'webinar_id' => $id,
            'user_id'    => $user->id,
            'status'     => 'Confirmed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webinar booked successfully.',
            'data' => [
                'booking' => [
                    'id'         => $booking->id,
                    'webinar_id' => $booking->webinar_id,
                    'user_id'    => $booking->user_id,
                    'status'     => $booking->status,
                    'created_at' => $booking->created_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * List bookings for a webinar (admin use).
     * GET /webinars/{id}/bookings
     * Requires JWT auth.
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

        $bookings = WebinarBooking::with('user')
            ->where('webinar_id', $id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function (WebinarBooking $b) {
                return [
                    'id'         => $b->id,
                    'status'     => $b->status,
                    'created_at' => $b->created_at?->toIso8601String(),
                    'user' => $b->user ? [
                        'id'            => $b->user->id,
                        'first_name'    => $b->user->first_name,
                        'last_name'     => $b->user->last_name,
                        'email'         => $b->user->email,
                        'profile_image' => $b->user->profile_image ? url($b->user->profile_image) : null,
                    ] : null,
                ];
            });

        $confirmed = $bookings->where('status', 'Confirmed')->count();
        $remaining = $webinar->max_attendees !== null ? max(0, $webinar->max_attendees - $confirmed) : null;

        return response()->json([
            'success' => true,
            'message' => 'Bookings retrieved successfully.',
            'data' => [
                'bookings'            => $bookings->values(),
                'total_bookings'      => $bookings->count(),
                'confirmed_bookings'  => $confirmed,
                'remaining_seats'     => $remaining,
                'max_attendees'       => $webinar->max_attendees,
            ],
        ], 200);
    }
}
