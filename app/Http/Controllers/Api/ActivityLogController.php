<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ActivityLogController extends Controller
{
    /**
     * List activity logs with user relation.
     * Query params: text (search action), page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = ActivityLog::query()
            ->withTrashed()
            ->with(['user:id,first_name,last_name,email,profile_image,is_online']);

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $searchTerm = '%' . $text . '%';
                $query->where('action', 'like', $searchTerm);
            }
        }

        $logs = $query->orderBy('id', 'desc')->paginate($perPage);

        $items = collect($logs->items())->map(function (ActivityLog $log) {
            $user = $log->user;
            $userPayload = null;
            if ($user) {
                $userPayload = [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'full_name' => trim($user->first_name . ' ' . $user->last_name) ?: $user->email,
                    'profile_image_url' => $user->profile_image ? url($user->profile_image) : null,
                    'is_online' => (bool) $user->is_online,
                ];
            }
            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user' => $userPayload,
                'action' => $log->action,
                'status' => $log->deleted_at ? 'Deleted' : 'Active',
                'is_deleted' => (bool) $log->deleted_at,
                'created_at' => $log->created_at?->toIso8601String(),
                'updated_at' => $log->updated_at?->toIso8601String(),
                'deleted_at' => $log->deleted_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Activity logs retrieved successfully.',
            'data' => [
                'activity_logs' => $items,
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                    'prev_page_url' => $logs->previousPageUrl(),
                    'next_page_url' => $logs->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Soft delete selected activity logs by ids.
     * Body: { ids: [1, 2, 3] }
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:activity_logs,id'],
        ], [
            'ids.required' => 'No activity logs selected.',
            'ids.*.exists' => 'One or more selected activity logs do not exist.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ids = $request->input('ids');
        $deleted = ActivityLog::whereIn('id', $ids)->whereNull('deleted_at')->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted === 1
                ? '1 activity log deleted successfully.'
                : $deleted . ' activity logs deleted successfully.',
            'data' => [
                'deleted_count' => $deleted,
            ],
        ], 200);
    }

    /**
     * Restore selected soft-deleted activity logs by ids.
     * Body: { ids: [1, 2, 3] }
     */
    public function bulkRestore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer'],
        ], [
            'ids.required' => 'No activity logs selected.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ids = $request->input('ids');
        $restored = ActivityLog::onlyTrashed()->whereIn('id', $ids)->restore();

        return response()->json([
            'success' => true,
            'message' => $restored === 1
                ? '1 activity log restored successfully.'
                : $restored . ' activity logs restored successfully.',
            'data' => [
                'restored_count' => $restored,
            ],
        ], 200);
    }
}
