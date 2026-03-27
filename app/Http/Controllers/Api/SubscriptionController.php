<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    /**
     * Store a new subscription (public; no auth required).
     * Body: { email: string }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:191'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'Email must not exceed 191 characters.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->input('email');

        $existing = Subscription::withTrashed()->where('email', $email)->first();
        if ($existing && !$existing->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'This email is already subscribed.',
                'errors' => [
                    'email' => ['This email is already subscribed.'],
                ],
            ], 422);
        }

        if ($existing && $existing->trashed()) {
            $existing->restore();
            return response()->json([
                'success' => true,
                'message' => 'You are now subscribed. We will send you tips and updates.',
                'data' => [
                    'subscription' => [
                        'id' => $existing->id,
                        'email' => $existing->email,
                        'created_at' => $existing->created_at?->toIso8601String(),
                    ],
                ],
            ], 200);
        }

        $subscription = Subscription::create(['email' => $email]);

        return response()->json([
            'success' => true,
            'message' => 'You are now subscribed. We will send you tips and updates.',
            'data' => [
                'subscription' => [
                    'id' => $subscription->id,
                    'email' => $subscription->email,
                    'created_at' => $subscription->created_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * List subscriptions with optional text filter (email search).
     * Query params: text, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = Subscription::query()->withTrashed();

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $searchTerm = '%' . $text . '%';
                $query->where('email', 'like', $searchTerm);
            }
            if ($status = $request->query('status')) {
                if ($status === 'Deleted') {
                    $query->onlyTrashed();
                } else {
                    $query->whereNull('deleted_at');
                }
            }
        }

        $subscriptions = $query->orderBy('id', 'desc')->paginate($perPage);

        $items = collect($subscriptions->items())->map(function (Subscription $subscription) {
            return [
                'id' => $subscription->id,
                'email' => $subscription->email,
                'status' => $subscription->deleted_at ? 'Deleted' : 'Active',
                'is_deleted' => (bool) $subscription->deleted_at,
                'created_at' => $subscription->created_at?->toIso8601String(),
                'updated_at' => $subscription->updated_at?->toIso8601String(),
                'deleted_at' => $subscription->deleted_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Subscriptions retrieved successfully.',
            'data' => [
                'subscriptions' => $items,
                'pagination' => [
                    'current_page' => $subscriptions->currentPage(),
                    'last_page' => $subscriptions->lastPage(),
                    'per_page' => $subscriptions->perPage(),
                    'total' => $subscriptions->total(),
                    'from' => $subscriptions->firstItem(),
                    'to' => $subscriptions->lastItem(),
                    'prev_page_url' => $subscriptions->previousPageUrl(),
                    'next_page_url' => $subscriptions->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Soft delete selected subscriptions by ids.
     * Body: { ids: [1, 2, 3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:subscriptions,id'],
        ], [
            'ids.required' => 'No subscriptions selected.',
            'ids.*.exists' => 'One or more selected subscriptions do not exist.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ids = $request->input('ids');
        $deleted = Subscription::whereIn('id', $ids)->whereNull('deleted_at')->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted === 1
                ? '1 subscription deleted successfully.'
                : $deleted . ' subscriptions deleted successfully.',
            'data' => [
                'deleted_count' => $deleted,
            ],
        ], 200);
    }

    /**
     * Restore selected soft-deleted subscriptions by ids.
     * Body: { ids: [1, 2, 3] }
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
        ], [
            'ids.required' => 'No subscriptions selected.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ids = $request->input('ids');
        $restored = Subscription::onlyTrashed()->whereIn('id', $ids)->restore();

        return response()->json([
            'success' => true,
            'message' => $restored === 1
                ? '1 subscription restored successfully.'
                : $restored . ' subscriptions restored successfully.',
            'data' => [
                'restored_count' => $restored,
            ],
        ], 200);
    }
}
