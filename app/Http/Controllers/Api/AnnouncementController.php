<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AnnouncementController extends Controller
{
    /**
     * List announcements with optional text, type and status filters.
     * Query params: text, type, status, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = Announcement::query()->withTrashed();

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $searchTerm = '%' . $text . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'like', $searchTerm)
                        ->orWhere('description', 'like', $searchTerm)
                        ->orWhere('type', 'like', $searchTerm)
                        ->orWhere('status', 'like', $searchTerm)
                        ->orWhere('id', 'like', $searchTerm);
                });
            }

            if ($type = $request->query('type')) {
                $query->where('type', $type);
            }

            if ($status = $request->query('status')) {
                if ($status === 'Deleted') {
                    $query->onlyTrashed();
                } else {
                    $query->where('status', $status);
                }
            }
        }

        $announcements = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);

        $items = collect($announcements->items())->map(function (Announcement $announcement) {
            return [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'type' => $announcement->type,
                'description' => $announcement->description,
                'status' => $announcement->deleted_at ? 'Deleted' : $announcement->status,
                'is_deleted' => (bool) $announcement->deleted_at,
                'created_at' => $announcement->created_at?->toIso8601String(),
                'updated_at' => $announcement->updated_at?->toIso8601String(),
                'deleted_at' => $announcement->deleted_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Announcements retrieved successfully.',
            'data' => [
                'announcements' => $items,
                'pagination' => [
                    'current_page' => $announcements->currentPage(),
                    'last_page' => $announcements->lastPage(),
                    'per_page' => $announcements->perPage(),
                    'total' => $announcements->total(),
                    'from' => $announcements->firstItem(),
                    'to' => $announcements->lastItem(),
                    'prev_page_url' => $announcements->previousPageUrl(),
                    'next_page_url' => $announcements->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Store a new announcement.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:191', 'unique:announcements,title'],
            'type' => ['required', 'string', 'in:scenario,mock'],
            'description' => ['required', 'string'],
            'status' => ['nullable', 'string', 'in:Active,Inactive'],
        ], [
            'title.required' => 'Title is required.',
            'title.max' => 'Title must not exceed 191 characters.',
            'title.unique' => 'This title is already in use.',
            'type.required' => 'Type is required.',
            'type.in' => 'Type must be either scenario or mock.',
            'description.required' => 'Description is required.',
            'status.in' => 'Status must be either Active or Inactive.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $announcement = Announcement::create([
            'title' => $request->input('title'),
            'type' => $request->input('type'),
            'description' => $request->input('description'),
            'status' => $request->input('status', 'Active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Announcement created successfully.',
            'data' => [
                'announcement' => [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'type' => $announcement->type,
                    'description' => $announcement->description,
                    'status' => $announcement->status,
                    'is_deleted' => false,
                    'created_at' => $announcement->created_at?->toIso8601String(),
                    'updated_at' => $announcement->updated_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * Update an existing announcement.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $announcement = Announcement::withTrashed()->find($id);

        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' => 'Announcement not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:191', 'unique:announcements,title,' . $announcement->id],
            'type' => ['required', 'string', 'in:scenario,mock'],
            'description' => ['required', 'string'],
            'status' => ['nullable', 'string', 'in:Active,Inactive'],
        ], [
            'title.required' => 'Title is required.',
            'title.max' => 'Title must not exceed 191 characters.',
            'title.unique' => 'This title is already in use.',
            'type.required' => 'Type is required.',
            'type.in' => 'Type must be either scenario or mock.',
            'description.required' => 'Description is required.',
            'status.in' => 'Status must be either Active or Inactive.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $announcement->title = $request->input('title');
        $announcement->type = $request->input('type');
        $announcement->description = $request->input('description');
        if ($request->filled('status')) {
            $announcement->status = $request->input('status');
        }
        $announcement->save();

        return response()->json([
            'success' => true,
            'message' => 'Announcement updated successfully.',
            'data' => [
                'announcement' => [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'type' => $announcement->type,
                    'description' => $announcement->description,
                    'status' => $announcement->deleted_at ? 'Deleted' : $announcement->status,
                    'is_deleted' => (bool) $announcement->deleted_at,
                    'created_at' => $announcement->created_at?->toIso8601String(),
                    'updated_at' => $announcement->updated_at?->toIso8601String(),
                    'deleted_at' => $announcement->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Soft delete an announcement.
     */
    public function destroy(int $id): JsonResponse
    {
        $announcement = Announcement::find($id);

        if (!$announcement) {
            $announcement = Announcement::onlyTrashed()->find($id);
        }

        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' => 'Announcement not found.',
            ], 404);
        }

        if ($announcement->deleted_at) {
            return response()->json([
                'success' => true,
                'message' => 'Announcement is already deleted.',
                'data' => [
                    'announcement' => [
                        'id' => $announcement->id,
                        'title' => $announcement->title,
                        'type' => $announcement->type,
                        'description' => $announcement->description,
                        'status' => 'Deleted',
                        'is_deleted' => true,
                        'created_at' => $announcement->created_at?->toIso8601String(),
                        'updated_at' => $announcement->updated_at?->toIso8601String(),
                        'deleted_at' => $announcement->deleted_at?->toIso8601String(),
                    ],
                ],
            ], 200);
        }

        $announcement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Announcement deleted successfully.',
            'data' => [
                'announcement' => [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'type' => $announcement->type,
                    'description' => $announcement->description,
                    'status' => 'Deleted',
                    'is_deleted' => true,
                    'created_at' => $announcement->created_at?->toIso8601String(),
                    'updated_at' => $announcement->updated_at?->toIso8601String(),
                    'deleted_at' => $announcement->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Restore a soft-deleted announcement.
     */
    public function restore(int $id): JsonResponse
    {
        $announcement = Announcement::onlyTrashed()->find($id);

        if (!$announcement) {
            return response()->json([
                'success' => false,
                'message' => 'Announcement not found or not deleted.',
            ], 404);
        }

        $announcement->restore();

        return response()->json([
            'success' => true,
            'message' => 'Announcement restored successfully.',
            'data' => [
                'announcement' => [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'type' => $announcement->type,
                    'description' => $announcement->description,
                    'status' => $announcement->status,
                    'is_deleted' => false,
                    'created_at' => $announcement->created_at?->toIso8601String(),
                    'updated_at' => $announcement->updated_at?->toIso8601String(),
                    'deleted_at' => $announcement->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }
}

