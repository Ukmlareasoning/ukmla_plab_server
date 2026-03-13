<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotesType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotesTypeController extends Controller
{
    /**
     * List notes types with optional text and status filters.
     * Query params: text, status, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = NotesType::query()->withTrashed();

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

        $notesTypes = $query->orderBy('id', 'asc')->paginate($perPage);

        $items = collect($notesTypes->items())->map(function (NotesType $notesType) {
            return [
                'id' => $notesType->id,
                'name' => $notesType->name,
                'status' => $notesType->deleted_at ? 'Deleted' : $notesType->status,
                'is_deleted' => (bool) $notesType->deleted_at,
                'created_at' => $notesType->created_at?->toIso8601String(),
                'updated_at' => $notesType->updated_at?->toIso8601String(),
                'deleted_at' => $notesType->deleted_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Notes types retrieved successfully.',
            'data' => [
                'notes_types' => $items,
                'pagination' => [
                    'current_page' => $notesTypes->currentPage(),
                    'last_page' => $notesTypes->lastPage(),
                    'per_page' => $notesTypes->perPage(),
                    'total' => $notesTypes->total(),
                    'from' => $notesTypes->firstItem(),
                    'to' => $notesTypes->lastItem(),
                    'prev_page_url' => $notesTypes->previousPageUrl(),
                    'next_page_url' => $notesTypes->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Store a new notes type.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:191', 'unique:notes_types,name'],
            'status' => ['nullable', 'string', 'in:Active,Inactive'],
        ], [
            'name.required' => 'Notes type name is required.',
            'name.max' => 'Notes type name must not exceed 191 characters.',
            'name.unique' => 'This notes type name is already in use.',
            'status.in' => 'Status must be either Active or Inactive.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $notesType = NotesType::create([
            'name' => $request->input('name'),
            'status' => $request->input('status', 'Active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notes type created successfully.',
            'data' => [
                'notes_type' => [
                    'id' => $notesType->id,
                    'name' => $notesType->name,
                    'status' => $notesType->status,
                    'is_deleted' => false,
                    'created_at' => $notesType->created_at?->toIso8601String(),
                    'updated_at' => $notesType->updated_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * Update an existing notes type.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $notesType = NotesType::withTrashed()->find($id);

        if (!$notesType) {
            return response()->json([
                'success' => false,
                'message' => 'Notes type not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:191', 'unique:notes_types,name,' . $notesType->id],
            'status' => ['nullable', 'string', 'in:Active,Inactive'],
        ], [
            'name.required' => 'Notes type name is required.',
            'name.max' => 'Notes type name must not exceed 191 characters.',
            'name.unique' => 'This notes type name is already in use.',
            'status.in' => 'Status must be either Active or Inactive.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $notesType->name = $request->input('name');
        if ($request->filled('status')) {
            $notesType->status = $request->input('status');
        }
        $notesType->save();

        return response()->json([
            'success' => true,
            'message' => 'Notes type updated successfully.',
            'data' => [
                'notes_type' => [
                    'id' => $notesType->id,
                    'name' => $notesType->name,
                    'status' => $notesType->deleted_at ? 'Deleted' : $notesType->status,
                    'is_deleted' => (bool) $notesType->deleted_at,
                    'created_at' => $notesType->created_at?->toIso8601String(),
                    'updated_at' => $notesType->updated_at?->toIso8601String(),
                    'deleted_at' => $notesType->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Soft delete a notes type.
     */
    public function destroy(int $id): JsonResponse
    {
        $notesType = NotesType::find($id);

        if (!$notesType) {
            $notesType = NotesType::onlyTrashed()->find($id);
        }

        if (!$notesType) {
            return response()->json([
                'success' => false,
                'message' => 'Notes type not found.',
            ], 404);
        }

        if ($notesType->deleted_at) {
            return response()->json([
                'success' => true,
                'message' => 'Notes type is already deleted.',
                'data' => [
                    'notes_type' => [
                        'id' => $notesType->id,
                        'name' => $notesType->name,
                        'status' => 'Deleted',
                        'is_deleted' => true,
                        'created_at' => $notesType->created_at?->toIso8601String(),
                        'updated_at' => $notesType->updated_at?->toIso8601String(),
                        'deleted_at' => $notesType->deleted_at?->toIso8601String(),
                    ],
                ],
            ], 200);
        }

        $notesType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notes type deleted successfully.',
            'data' => [
                'notes_type' => [
                    'id' => $notesType->id,
                    'name' => $notesType->name,
                    'status' => 'Deleted',
                    'is_deleted' => true,
                    'created_at' => $notesType->created_at?->toIso8601String(),
                    'updated_at' => $notesType->updated_at?->toIso8601String(),
                    'deleted_at' => $notesType->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Restore a soft-deleted notes type.
     */
    public function restore(int $id): JsonResponse
    {
        $notesType = NotesType::onlyTrashed()->find($id);

        if (!$notesType) {
            return response()->json([
                'success' => false,
                'message' => 'Notes type not found or not deleted.',
            ], 404);
        }

        $notesType->restore();

        return response()->json([
            'success' => true,
            'message' => 'Notes type restored successfully.',
            'data' => [
                'notes_type' => [
                    'id' => $notesType->id,
                    'name' => $notesType->name,
                    'status' => $notesType->status,
                    'is_deleted' => false,
                    'created_at' => $notesType->created_at?->toIso8601String(),
                    'updated_at' => $notesType->updated_at?->toIso8601String(),
                    'deleted_at' => $notesType->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }
}

