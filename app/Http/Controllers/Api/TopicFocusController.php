<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TopicFocus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TopicFocusController extends Controller
{
    /**
     * List topic focuses with optional text and status filters.
     * Query params: text, status, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = TopicFocus::query()->withTrashed();

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $searchTerm = '%' . $text . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', $searchTerm)
                        ->orWhere('status', 'like', $searchTerm)
                        ->orWhere('id', 'like', $searchTerm);
                });
            }

            if ($status = $request->query('status')) {
                if ($status === 'Deleted') {
                    $query->onlyTrashed();
                } else {
                    $query->where('status', $status);
                }
            }
        }

        $topics = $query->orderBy('id', 'asc')->paginate($perPage);

        $items = collect($topics->items())->map(function (TopicFocus $topic) {
            return [
                'id' => $topic->id,
                'name' => $topic->name,
                'status' => $topic->deleted_at ? 'Deleted' : $topic->status,
                'is_deleted' => (bool) $topic->deleted_at,
                'created_at' => $topic->created_at?->toIso8601String(),
                'updated_at' => $topic->updated_at?->toIso8601String(),
                'deleted_at' => $topic->deleted_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Topics retrieved successfully.',
            'data' => [
                'topic_focuses' => $items,
                'pagination' => [
                    'current_page' => $topics->currentPage(),
                    'last_page' => $topics->lastPage(),
                    'per_page' => $topics->perPage(),
                    'total' => $topics->total(),
                    'from' => $topics->firstItem(),
                    'to' => $topics->lastItem(),
                    'prev_page_url' => $topics->previousPageUrl(),
                    'next_page_url' => $topics->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Store a new topic focus.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:191', 'unique:topic_focuses,name'],
            'status' => ['nullable', 'string', 'in:Active,Inactive'],
        ], [
            'name.required' => 'Topic / focus name is required.',
            'name.max' => 'Topic / focus name must not exceed 191 characters.',
            'name.unique' => 'This topic / focus name is already in use.',
            'status.in' => 'Status must be either Active or Inactive.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $topic = TopicFocus::create([
            'name' => $request->input('name'),
            'status' => $request->input('status', 'Active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Topic / focus created successfully.',
            'data' => [
                'topic_focus' => [
                    'id' => $topic->id,
                    'name' => $topic->name,
                    'status' => $topic->status,
                    'is_deleted' => false,
                    'created_at' => $topic->created_at?->toIso8601String(),
                    'updated_at' => $topic->updated_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * Update an existing topic focus.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $topic = TopicFocus::withTrashed()->find($id);

        if (!$topic) {
            return response()->json([
                'success' => false,
                'message' => 'Topic / focus not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:191', 'unique:topic_focuses,name,' . $topic->id],
            'status' => ['nullable', 'string', 'in:Active,Inactive'],
        ], [
            'name.required' => 'Topic / focus name is required.',
            'name.max' => 'Topic / focus name must not exceed 191 characters.',
            'name.unique' => 'This topic / focus name is already in use.',
            'status.in' => 'Status must be either Active or Inactive.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $topic->name = $request->input('name');
        if ($request->filled('status')) {
            $topic->status = $request->input('status');
        }
        $topic->save();

        return response()->json([
            'success' => true,
            'message' => 'Topic / focus updated successfully.',
            'data' => [
                'topic_focus' => [
                    'id' => $topic->id,
                    'name' => $topic->name,
                    'status' => $topic->deleted_at ? 'Deleted' : $topic->status,
                    'is_deleted' => (bool) $topic->deleted_at,
                    'created_at' => $topic->created_at?->toIso8601String(),
                    'updated_at' => $topic->updated_at?->toIso8601String(),
                    'deleted_at' => $topic->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Soft delete a topic focus.
     */
    public function destroy(int $id): JsonResponse
    {
        $topic = TopicFocus::find($id);

        if (!$topic) {
            $topic = TopicFocus::onlyTrashed()->find($id);
        }

        if (!$topic) {
            return response()->json([
                'success' => false,
                'message' => 'Topic / focus not found.',
            ], 404);
        }

        if ($topic->deleted_at) {
            return response()->json([
                'success' => true,
                'message' => 'Topic / focus is already deleted.',
                'data' => [
                    'topic_focus' => [
                        'id' => $topic->id,
                        'name' => $topic->name,
                        'status' => 'Deleted',
                        'is_deleted' => true,
                        'created_at' => $topic->created_at?->toIso8601String(),
                        'updated_at' => $topic->updated_at?->toIso8601String(),
                        'deleted_at' => $topic->deleted_at?->toIso8601String(),
                    ],
                ],
            ], 200);
        }

        $topic->delete();

        return response()->json([
            'success' => true,
            'message' => 'Topic / focus deleted successfully.',
            'data' => [
                'topic_focus' => [
                    'id' => $topic->id,
                    'name' => $topic->name,
                    'status' => 'Deleted',
                    'is_deleted' => true,
                    'created_at' => $topic->created_at?->toIso8601String(),
                    'updated_at' => $topic->updated_at?->toIso8601String(),
                    'deleted_at' => $topic->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Restore a soft-deleted topic focus.
     */
    public function restore(int $id): JsonResponse
    {
        $topic = TopicFocus::onlyTrashed()->find($id);

        if (!$topic) {
            return response()->json([
                'success' => false,
                'message' => 'Topic / focus not found or not deleted.',
            ], 404);
        }

        $topic->restore();

        return response()->json([
            'success' => true,
            'message' => 'Topic / focus restored successfully.',
            'data' => [
                'topic_focus' => [
                    'id' => $topic->id,
                    'name' => $topic->name,
                    'status' => $topic->status,
                    'is_deleted' => false,
                    'created_at' => $topic->created_at?->toIso8601String(),
                    'updated_at' => $topic->updated_at?->toIso8601String(),
                    'deleted_at' => $topic->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }
}

