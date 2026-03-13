<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NoteController extends Controller
{
    /**
     * List notes with optional filters.
     * Query params: text, status, notes_type_id, difficulty_level_id, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = Note::query()
            ->with(['type', 'difficultyLevel'])
            ->withTrashed();

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $searchTerm = '%' . $text . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'like', $searchTerm)
                        ->orWhere('description', 'like', $searchTerm)
                        ->orWhere('summary', 'like', $searchTerm);
                });
            }

            if ($status = $request->query('status')) {
                if ($status === 'Deleted') {
                    $query->onlyTrashed();
                } else {
                    $query->where('status', $status);
                }
            }

            if ($typeId = $request->query('notes_type_id')) {
                $query->where('notes_type_id', (int) $typeId);
            }

            if ($difficultyId = $request->query('difficulty_level_id')) {
                $query->where('difficulty_level_id', (int) $difficultyId);
            }
        }

        $notes = $query->orderBy('id', 'asc')->paginate($perPage);

        $items = collect($notes->items())->map(function (Note $note) {
            $type = $note->type;
            $difficulty = $note->difficultyLevel;

            return [
                'id' => $note->id,
                'notes_type_id' => $note->notes_type_id,
                'notes_type_name' => $type?->name,
                'difficulty_level_id' => $note->difficulty_level_id,
                'difficulty_level_name' => $difficulty?->name,
                'title' => $note->title,
                'description' => $note->description,
                'summary' => $note->summary,
                'key_points' => $note->key_points ?? [],
                'exam_importance_level' => $note->exam_importance_level,
                'tags' => $note->tags ?? [],
                'status' => $note->deleted_at ? 'Deleted' : $note->status,
                'is_deleted' => (bool) $note->deleted_at,
                'created_at' => $note->created_at?->toIso8601String(),
                'updated_at' => $note->updated_at?->toIso8601String(),
                'deleted_at' => $note->deleted_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Notes retrieved successfully.',
            'data' => [
                'notes' => $items,
                'pagination' => [
                    'current_page' => $notes->currentPage(),
                    'last_page' => $notes->lastPage(),
                    'per_page' => $notes->perPage(),
                    'total' => $notes->total(),
                    'from' => $notes->firstItem(),
                    'to' => $notes->lastItem(),
                    'prev_page_url' => $notes->previousPageUrl(),
                    'next_page_url' => $notes->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Store a new note.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notes_type_id' => ['required', 'integer', 'exists:notes_types,id'],
            'difficulty_level_id' => ['required', 'integer', 'exists:difficulty_levels,id'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string'],
            'summary' => ['required', 'string'],
            'key_points' => ['required', 'array', 'min:1'],
            'key_points.*' => ['required', 'string'],
            'exam_importance_level' => ['required', 'in:Low,Medium,High'],
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['required', 'string'],
        ], [
            'notes_type_id.required' => 'Type is required.',
            'notes_type_id.exists' => 'Selected type is invalid.',
            'difficulty_level_id.required' => 'Difficulty level is required.',
            'difficulty_level_id.exists' => 'Selected difficulty level is invalid.',
            'title.required' => 'Title is required.',
            'title.max' => 'Title must not exceed 191 characters.',
            'description.required' => 'Description is required.',
            'summary.required' => 'Summary is required.',
            'key_points.required' => 'Key points are required.',
            'key_points.min' => 'Please add at least one key point.',
            'exam_importance_level.required' => 'Exam importance level is required.',
            'exam_importance_level.in' => 'Exam importance level must be Low, Medium, or High.',
            'tags.required' => 'Tags are required.',
            'tags.min' => 'Please add at least one tag.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $note = Note::create([
            'notes_type_id' => $request->input('notes_type_id'),
            'difficulty_level_id' => $request->input('difficulty_level_id'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'summary' => $request->input('summary'),
            'key_points' => $request->input('key_points', []),
            'exam_importance_level' => $request->input('exam_importance_level'),
            'tags' => $request->input('tags', []),
            'status' => 'Active',
        ]);

        $note->load(['type', 'difficultyLevel']);

        return response()->json([
            'success' => true,
            'message' => 'Note created successfully.',
            'data' => [
                'note' => [
                    'id' => $note->id,
                    'notes_type_id' => $note->notes_type_id,
                    'notes_type_name' => $note->type?->name,
                    'difficulty_level_id' => $note->difficulty_level_id,
                    'difficulty_level_name' => $note->difficultyLevel?->name,
                    'title' => $note->title,
                    'description' => $note->description,
                    'summary' => $note->summary,
                    'key_points' => $note->key_points ?? [],
                    'exam_importance_level' => $note->exam_importance_level,
                    'tags' => $note->tags ?? [],
                    'status' => $note->status,
                    'is_deleted' => false,
                    'created_at' => $note->created_at?->toIso8601String(),
                    'updated_at' => $note->updated_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * Show a single note.
     */
    public function show(int $id): JsonResponse
    {
        $note = Note::withTrashed()->with(['type', 'difficultyLevel'])->find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Note retrieved successfully.',
            'data' => [
                'note' => [
                    'id' => $note->id,
                    'notes_type_id' => $note->notes_type_id,
                    'notes_type_name' => $note->type?->name,
                    'difficulty_level_id' => $note->difficulty_level_id,
                    'difficulty_level_name' => $note->difficultyLevel?->name,
                    'title' => $note->title,
                    'description' => $note->description,
                    'summary' => $note->summary,
                    'key_points' => $note->key_points ?? [],
                    'exam_importance_level' => $note->exam_importance_level,
                    'tags' => $note->tags ?? [],
                    'status' => $note->deleted_at ? 'Deleted' : $note->status,
                    'is_deleted' => (bool) $note->deleted_at,
                    'created_at' => $note->created_at?->toIso8601String(),
                    'updated_at' => $note->updated_at?->toIso8601String(),
                    'deleted_at' => $note->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Update an existing note.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $note = Note::withTrashed()->find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'notes_type_id' => ['required', 'integer', 'exists:notes_types,id'],
            'difficulty_level_id' => ['required', 'integer', 'exists:difficulty_levels,id'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string'],
            'summary' => ['required', 'string'],
            'key_points' => ['required', 'array', 'min:1'],
            'key_points.*' => ['required', 'string'],
            'exam_importance_level' => ['required', 'in:Low,Medium,High'],
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['required', 'string'],
            'status' => ['nullable', 'in:Active,Inactive'],
        ], [
            'notes_type_id.required' => 'Type is required.',
            'notes_type_id.exists' => 'Selected type is invalid.',
            'difficulty_level_id.required' => 'Difficulty level is required.',
            'difficulty_level_id.exists' => 'Selected difficulty level is invalid.',
            'title.required' => 'Title is required.',
            'title.max' => 'Title must not exceed 191 characters.',
            'description.required' => 'Description is required.',
            'summary.required' => 'Summary is required.',
            'key_points.required' => 'Key points are required.',
            'key_points.min' => 'Please add at least one key point.',
            'exam_importance_level.required' => 'Exam importance level is required.',
            'exam_importance_level.in' => 'Exam importance level must be Low, Medium, or High.',
            'tags.required' => 'Tags are required.',
            'tags.min' => 'Please add at least one tag.',
            'status.in' => 'Status must be either Active or Inactive.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $note->notes_type_id = $request->input('notes_type_id');
        $note->difficulty_level_id = $request->input('difficulty_level_id');
        $note->title = $request->input('title');
        $note->description = $request->input('description');
        $note->summary = $request->input('summary');
        $note->key_points = $request->input('key_points', []);
        $note->exam_importance_level = $request->input('exam_importance_level');
        $note->tags = $request->input('tags', []);

        if ($request->filled('status')) {
            $note->status = $request->input('status');
        }

        $note->save();
        $note->load(['type', 'difficultyLevel']);

        return response()->json([
            'success' => true,
            'message' => 'Note updated successfully.',
            'data' => [
                'note' => [
                    'id' => $note->id,
                    'notes_type_id' => $note->notes_type_id,
                    'notes_type_name' => $note->type?->name,
                    'difficulty_level_id' => $note->difficulty_level_id,
                    'difficulty_level_name' => $note->difficultyLevel?->name,
                    'title' => $note->title,
                    'description' => $note->description,
                    'summary' => $note->summary,
                    'key_points' => $note->key_points ?? [],
                    'exam_importance_level' => $note->exam_importance_level,
                    'tags' => $note->tags ?? [],
                    'status' => $note->deleted_at ? 'Deleted' : $note->status,
                    'is_deleted' => (bool) $note->deleted_at,
                    'created_at' => $note->created_at?->toIso8601String(),
                    'updated_at' => $note->updated_at?->toIso8601String(),
                    'deleted_at' => $note->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Soft delete a note.
     */
    public function destroy(int $id): JsonResponse
    {
        $note = Note::find($id);

        if (!$note) {
            $note = Note::onlyTrashed()->find($id);
        }

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found.',
            ], 404);
        }

        if ($note->deleted_at) {
            return response()->json([
                'success' => true,
                'message' => 'Note is already deleted.',
                'data' => [
                    'note' => [
                        'id' => $note->id,
                        'title' => $note->title,
                        'status' => 'Deleted',
                        'is_deleted' => true,
                        'created_at' => $note->created_at?->toIso8601String(),
                        'updated_at' => $note->updated_at?->toIso8601String(),
                        'deleted_at' => $note->deleted_at?->toIso8601String(),
                    ],
                ],
            ], 200);
        }

        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Note deleted successfully.',
            'data' => [
                'note' => [
                    'id' => $note->id,
                    'title' => $note->title,
                    'status' => 'Deleted',
                    'is_deleted' => true,
                    'created_at' => $note->created_at?->toIso8601String(),
                    'updated_at' => $note->updated_at?->toIso8601String(),
                    'deleted_at' => $note->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Restore a soft-deleted note.
     */
    public function restore(int $id): JsonResponse
    {
        $note = Note::onlyTrashed()->find($id);

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found or not deleted.',
            ], 404);
        }

        $note->restore();

        return response()->json([
            'success' => true,
            'message' => 'Note restored successfully.',
            'data' => [
                'note' => [
                    'id' => $note->id,
                    'title' => $note->title,
                    'status' => $note->status,
                    'is_deleted' => false,
                    'created_at' => $note->created_at?->toIso8601String(),
                    'updated_at' => $note->updated_at?->toIso8601String(),
                    'deleted_at' => $note->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }
}

