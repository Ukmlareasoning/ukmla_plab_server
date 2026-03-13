<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExamTypeController extends Controller
{
    /**
     * List exam types with optional text and status filters.
     * Query params: text, status, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = ExamType::query()->withTrashed();

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

        $examTypes = $query->orderBy('id', 'asc')->paginate($perPage);

        $items = collect($examTypes->items())->map(function (ExamType $examType) {
            return [
                'id' => $examType->id,
                'name' => $examType->name,
                'status' => $examType->deleted_at ? 'Deleted' : $examType->status,
                'is_deleted' => (bool) $examType->deleted_at,
                'created_at' => $examType->created_at?->toIso8601String(),
                'updated_at' => $examType->updated_at?->toIso8601String(),
                'deleted_at' => $examType->deleted_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Exam types retrieved successfully.',
            'data' => [
                'exam_types' => $items,
                'pagination' => [
                    'current_page' => $examTypes->currentPage(),
                    'last_page' => $examTypes->lastPage(),
                    'per_page' => $examTypes->perPage(),
                    'total' => $examTypes->total(),
                    'from' => $examTypes->firstItem(),
                    'to' => $examTypes->lastItem(),
                    'prev_page_url' => $examTypes->previousPageUrl(),
                    'next_page_url' => $examTypes->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Store a new exam type.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:191', 'unique:exam_types,name'],
            'status' => ['nullable', 'string', 'in:Active,Inactive'],
        ], [
            'name.required' => 'Exam type name is required.',
            'name.max' => 'Exam type name must not exceed 191 characters.',
            'name.unique' => 'This exam type name is already in use.',
            'status.in' => 'Status must be either Active or Inactive.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $examType = ExamType::create([
            'name' => $request->input('name'),
            'status' => $request->input('status', 'Active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Exam type created successfully.',
            'data' => [
                'exam_type' => [
                    'id' => $examType->id,
                    'name' => $examType->name,
                    'status' => $examType->status,
                    'is_deleted' => false,
                    'created_at' => $examType->created_at?->toIso8601String(),
                    'updated_at' => $examType->updated_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * Update an existing exam type.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $examType = ExamType::withTrashed()->find($id);

        if (!$examType) {
            return response()->json([
                'success' => false,
                'message' => 'Exam type not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:191', 'unique:exam_types,name,' . $examType->id],
            'status' => ['nullable', 'string', 'in:Active,Inactive'],
        ], [
            'name.required' => 'Exam type name is required.',
            'name.max' => 'Exam type name must not exceed 191 characters.',
            'name.unique' => 'This exam type name is already in use.',
            'status.in' => 'Status must be either Active or Inactive.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $examType->name = $request->input('name');
        if ($request->filled('status')) {
            $examType->status = $request->input('status');
        }
        $examType->save();

        return response()->json([
            'success' => true,
            'message' => 'Exam type updated successfully.',
            'data' => [
                'exam_type' => [
                    'id' => $examType->id,
                    'name' => $examType->name,
                    'status' => $examType->deleted_at ? 'Deleted' : $examType->status,
                    'is_deleted' => (bool) $examType->deleted_at,
                    'created_at' => $examType->created_at?->toIso8601String(),
                    'updated_at' => $examType->updated_at?->toIso8601String(),
                    'deleted_at' => $examType->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Soft delete an exam type.
     */
    public function destroy(int $id): JsonResponse
    {
        $examType = ExamType::find($id);

        if (!$examType) {
            $examType = ExamType::onlyTrashed()->find($id);
        }

        if (!$examType) {
            return response()->json([
                'success' => false,
                'message' => 'Exam type not found.',
            ], 404);
        }

        if ($examType->deleted_at) {
            return response()->json([
                'success' => true,
                'message' => 'Exam type is already deleted.',
                'data' => [
                    'exam_type' => [
                        'id' => $examType->id,
                        'name' => $examType->name,
                        'status' => 'Deleted',
                        'is_deleted' => true,
                        'created_at' => $examType->created_at?->toIso8601String(),
                        'updated_at' => $examType->updated_at?->toIso8601String(),
                        'deleted_at' => $examType->deleted_at?->toIso8601String(),
                    ],
                ],
            ], 200);
        }

        $examType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Exam type deleted successfully.',
            'data' => [
                'exam_type' => [
                    'id' => $examType->id,
                    'name' => $examType->name,
                    'status' => 'Deleted',
                    'is_deleted' => true,
                    'created_at' => $examType->created_at?->toIso8601String(),
                    'updated_at' => $examType->updated_at?->toIso8601String(),
                    'deleted_at' => $examType->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Restore a soft-deleted exam type.
     */
    public function restore(int $id): JsonResponse
    {
        $examType = ExamType::onlyTrashed()->find($id);

        if (!$examType) {
            return response()->json([
                'success' => false,
                'message' => 'Exam type not found or not deleted.',
            ], 404);
        }

        $examType->restore();

        return response()->json([
            'success' => true,
            'message' => 'Exam type restored successfully.',
            'data' => [
                'exam_type' => [
                    'id' => $examType->id,
                    'name' => $examType->name,
                    'status' => $examType->status,
                    'is_deleted' => false,
                    'created_at' => $examType->created_at?->toIso8601String(),
                    'updated_at' => $examType->updated_at?->toIso8601String(),
                    'deleted_at' => $examType->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }
}

