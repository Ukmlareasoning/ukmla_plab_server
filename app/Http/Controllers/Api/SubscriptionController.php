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
     * List subscriptions with optional text filter (email search).
     * Query params: text, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = Subscription::query();

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $searchTerm = '%' . $text . '%';
                $query->where('email', 'like', $searchTerm);
            }
        }

        $subscriptions = $query->orderBy('id', 'desc')->paginate($perPage);

        $items = collect($subscriptions->items())->map(function (Subscription $subscription) {
            return [
                'id' => $subscription->id,
                'email' => $subscription->email,
                'created_at' => $subscription->created_at?->toIso8601String(),
                'updated_at' => $subscription->updated_at?->toIso8601String(),
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
     * Permanently delete selected subscriptions by ids.
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
        $deleted = Subscription::whereIn('id', $ids)->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted === 1
                ? '1 subscription deleted permanently.'
                : $deleted . ' subscriptions deleted permanently.',
            'data' => [
                'deleted_count' => $deleted,
            ],
        ], 200);
    }
}
