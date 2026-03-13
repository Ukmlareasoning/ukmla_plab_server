<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DifficultyLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DifficultyLevelController extends Controller
{
    /**
     * List difficulty levels with optional text and status filters.
     * Query params: text, status, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = DifficultyLevel::query()->withTrashed();

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

        $levels = $query->orderBy('id', 'asc')->paginate($perPage);

        $items = collect($levels->items())->map(function (DifficultyLevel $level) {
            return [
                'id' => $level->id,
                'name' => $level->name,
                'status' => $level->deleted_at ? 'Deleted' : $level->status,
                'is_deleted' => (bool) $level->deleted_at,
                'created_at' => $level->created_at?->toIso8601String(),
                'updated_at' => $level->updated_at?->toIso8601String(),
                'deleted_at' => $level->deleted_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Difficulty levels retrieved successfully.',
            'data' => [
                'difficulty_levels' => $items,
                'pagination' => [
                    'current_page' => $levels->currentPage(),
                    'last_page' => $levels->lastPage(),
                    'per_page' => $levels->perPage(),
                    'total' => $levels->total(),
                    'from' => $levels->firstItem(),
                    'to' => $levels->lastItem(),
                    'prev_page_url' => $levels->previousPageUrl(),
                    'next_page_url' => $levels->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Store a new difficulty level.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:191', 'unique:difficulty_levels,name'],
            'status' => ['nullable', 'string', 'in:Active,Inactive'],
        ], [
            'name.required' => 'Difficulty level name is required.',
            'name.max' => 'Difficulty level name must not exceed 191 characters.',
            'name.unique' => 'This difficulty level name is already in use.',
            'status.in' => 'Status must be either Active or Inactive.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $level = DifficultyLevel::create([
            'name' => $request->input('name'),
            'status' => $request->input('status', 'Active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Difficulty level created successfully.',
            'data' => [
                'difficulty_level' => [
                    'id' => $level->id,
                    'name' => $level->name,
                    'status' => $level->status,
                    'is_deleted' => false,
                    'created_at' => $level->created_at?->toIso8601String(),
                    'updated_at' => $level->updated_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * Update an existing difficulty level.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $level = DifficultyLevel::withTrashed()->find($id);

        if (!$level) {
            return response()->json([
                'success' => false,
                'message' => 'Difficulty level not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:191', 'unique:difficulty_levels,name,' . $level->id],
            'status' => ['nullable', 'string', 'in:Active,Inactive'],
        ], [
            'name.required' => 'Difficulty level name is required.',
            'name.max' => 'Difficulty level name must not exceed 191 characters.',
            'name.unique' => 'This difficulty level name is already in use.',
            'status.in' => 'Status must be either Active or Inactive.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $level->name = $request->input('name');
        if ($request->filled('status')) {
            $level->status = $request->input('status');
        }
        $level->save();

        return response()->json([
            'success' => true,
            'message' => 'Difficulty level updated successfully.',
            'data' => [
                'difficulty_level' => [
                    'id' => $level->id,
                    'name' => $level->name,
                    'status' => $level->deleted_at ? 'Deleted' : $level->status,
                    'is_deleted' => (bool) $level->deleted_at,
                    'created_at' => $level->created_at?->toIso8601String(),
                    'updated_at' => $level->updated_at?->toIso8601String(),
                    'deleted_at' => $level->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Soft delete a difficulty level.
     */
    public function destroy(int $id): JsonResponse
    {
        $level = DifficultyLevel::find($id);

        if (!$level) {
            $level = DifficultyLevel::onlyTrashed()->find($id);
        }

        if (!$level) {
            return response()->json([
                'success' => false,
                'message' => 'Difficulty level not found.',
            ], 404);
        }

        if ($level->deleted_at) {
            return response()->json([
                'success' => true,
                'message' => 'Difficulty level is already deleted.',
                'data' => [
                    'difficulty_level' => [
                        'id' => $level->id,
                        'name' => $level->name,
                        'status' => 'Deleted',
                        'is_deleted' => true,
                        'created_at' => $level->created_at?->toIso8601String(),
                        'updated_at' => $level->updated_at?->toIso8601String(),
                        'deleted_at' => $level->deleted_at?->toIso8601String(),
                    ],
                ],
            ], 200);
        }

        $level->delete();

        return response()->json([
            'success' => true,
            'message' => 'Difficulty level deleted successfully.',
            'data' => [
                'difficulty_level' => [
                    'id' => $level->id,
                    'name' => $level->name,
                    'status' => 'Deleted',
                    'is_deleted' => true,
                    'created_at' => $level->created_at?->toIso8601String(),
                    'updated_at' => $level->updated_at?->toIso8601String(),
                    'deleted_at' => $level->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Restore a soft-deleted difficulty level.
     */
    public function restore(int $id): JsonResponse
    {
        $level = DifficultyLevel::onlyTrashed()->find($id);

        if (!$level) {
            return response()->json([
                'success' => false,
                'message' => 'Difficulty level not found or not deleted.',
            ], 404);
        }

        $level->restore();

        return response()->json([
            'success' => true,
            'message' => 'Difficulty level restored successfully.',
            'data' => [
                'difficulty_level' => [
                    'id' => $level->id,
                    'name' => $level->name,
                    'status' => $level->status,
                    'is_deleted' => false,
                    'created_at' => $level->created_at?->toIso8601String(),
                    'updated_at' => $level->updated_at?->toIso8601String(),
                    'deleted_at' => $level->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }
}

