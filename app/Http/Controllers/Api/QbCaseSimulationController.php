<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QbCaseSimulation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QbCaseSimulationController extends Controller
{
    /**
     * List question bank case simulations with optional text and status filters.
     * Query params: text, status, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = QbCaseSimulation::query()->withTrashed();

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $searchTerm = '%' . $text . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'like', $searchTerm)
                        ->orWhere('description', 'like', $searchTerm)
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

        $cases = $query->withCount([
            'questions as total_questions_count' => function ($q) {
                $q->withoutTrashed();
            },
        ])->orderBy('id', 'asc')->paginate($perPage);

        $items = collect($cases->items())->map(function (QbCaseSimulation $case) {
            return $this->formatCase($case);
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Question bank case simulations retrieved successfully.',
            'data' => [
                'qb_case_simulations' => $items,
                'pagination' => [
                    'current_page' => $cases->currentPage(),
                    'last_page'    => $cases->lastPage(),
                    'per_page'     => $cases->perPage(),
                    'total'        => $cases->total(),
                    'from'         => $cases->firstItem(),
                    'to'           => $cases->lastItem(),
                    'prev_page_url' => $cases->previousPageUrl(),
                    'next_page_url' => $cases->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Public catalogue: active, non-deleted case simulations only.
     * No authentication. Query params: text (with apply_filters=1), page, per_page.
     * Search matches title and description only.
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = QbCaseSimulation::query()->where('status', 'Active');

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters && ($text = $request->query('text'))) {
            $searchTerm = '%' . $text . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm);
            });
        }

        $cases = $query->withCount([
            'questions as total_questions_count' => function ($q) {
                $q->withoutTrashed();
            },
        ])->orderBy('id', 'asc')->paginate($perPage);

        $items = collect($cases->items())->map(function (QbCaseSimulation $case) {
            return $this->formatPublicCase($case);
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Question bank case simulations retrieved successfully.',
            'data' => [
                'qb_case_simulations' => $items,
                'pagination' => [
                    'current_page' => $cases->currentPage(),
                    'last_page'    => $cases->lastPage(),
                    'per_page'     => $cases->perPage(),
                    'total'        => $cases->total(),
                    'from'         => $cases->firstItem(),
                    'to'           => $cases->lastItem(),
                    'prev_page_url' => $cases->previousPageUrl(),
                    'next_page_url' => $cases->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Store a new question bank case simulation.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'icon_key'    => ['required', 'string', 'max:64'],
            'title'       => ['required', 'string', 'max:191', 'unique:qb_case_simulations,title'],
            'description' => ['required', 'string', 'max:5000'],
            'status'      => ['nullable', 'string', 'in:Active,Inactive'],
        ], [
            'icon_key.required'    => 'Icon is required.',
            'title.required'       => 'Case simulation title is required.',
            'title.max'            => 'Title must not exceed 191 characters.',
            'title.unique'         => 'This case simulation title is already in use.',
            'description.required' => 'Description is required.',
            'description.max'      => 'Description must not exceed 5000 characters.',
            'status.in'            => 'Status must be Active or Inactive.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $case = QbCaseSimulation::create([
            'icon_key'    => $request->input('icon_key'),
            'title'       => $request->input('title'),
            'description' => $request->input('description'),
            'status'      => $request->input('status', 'Active'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Case simulation created successfully.',
            'data'    => ['qb_case_simulation' => $this->formatCase($case)],
        ], 201);
    }

    /**
     * Update an existing case simulation.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $case = QbCaseSimulation::withTrashed()->find($id);

        if (!$case) {
            return response()->json([
                'success' => false,
                'message' => 'Case simulation not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'icon_key'    => ['required', 'string', 'max:64'],
            'title'       => ['required', 'string', 'max:191', 'unique:qb_case_simulations,title,' . $case->id],
            'description' => ['required', 'string', 'max:5000'],
            'status'      => ['nullable', 'string', 'in:Active,Inactive'],
        ], [
            'icon_key.required'    => 'Icon is required.',
            'title.required'       => 'Case simulation title is required.',
            'title.max'            => 'Title must not exceed 191 characters.',
            'title.unique'         => 'This case simulation title is already in use.',
            'description.required' => 'Description is required.',
            'description.max'      => 'Description must not exceed 5000 characters.',
            'status.in'            => 'Status must be Active or Inactive.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $case->icon_key    = $request->input('icon_key');
        $case->title       = $request->input('title');
        $case->description = $request->input('description');
        if ($request->filled('status')) {
            $case->status = $request->input('status');
        }
        $case->save();

        return response()->json([
            'success' => true,
            'message' => 'Case simulation updated successfully.',
            'data'    => ['qb_case_simulation' => $this->formatCase($case)],
        ], 200);
    }

    /**
     * Soft delete a case simulation.
     */
    public function destroy(int $id): JsonResponse
    {
        $case = QbCaseSimulation::find($id);

        if (!$case) {
            $case = QbCaseSimulation::onlyTrashed()->find($id);
        }

        if (!$case) {
            return response()->json([
                'success' => false,
                'message' => 'Case simulation not found.',
            ], 404);
        }

        if ($case->deleted_at) {
            return response()->json([
                'success' => true,
                'message' => 'Case simulation is already deleted.',
                'data'    => ['qb_case_simulation' => $this->formatCase($case)],
            ], 200);
        }

        $case->delete();

        return response()->json([
            'success' => true,
            'message' => 'Case simulation deleted successfully.',
            'data'    => ['qb_case_simulation' => $this->formatCase($case)],
        ], 200);
    }

    /**
     * Restore a soft-deleted case simulation.
     */
    public function restore(int $id): JsonResponse
    {
        $case = QbCaseSimulation::onlyTrashed()->find($id);

        if (!$case) {
            return response()->json([
                'success' => false,
                'message' => 'Case simulation not found or not deleted.',
            ], 404);
        }

        $case->restore();

        return response()->json([
            'success' => true,
            'message' => 'Case simulation restored successfully.',
            'data'    => ['qb_case_simulation' => $this->formatCase($case)],
        ], 200);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatCase(QbCaseSimulation $case): array
    {
        return [
            'id'                    => $case->id,
            'icon_key'              => $case->icon_key,
            'title'                 => $case->title,
            'description'           => $case->description,
            'status'                => $case->deleted_at ? 'Deleted' : $case->status,
            'is_deleted'            => (bool) $case->deleted_at,
            'total_questions_count' => $case->total_questions_count ?? 0,
            'created_at'            => $case->created_at?->toIso8601String(),
            'updated_at'            => $case->updated_at?->toIso8601String(),
            'deleted_at'            => $case->deleted_at?->toIso8601String(),
        ];
    }

    /** Minimal shape for unauthenticated catalogue responses. */
    private function formatPublicCase(QbCaseSimulation $case): array
    {
        return [
            'id'                    => $case->id,
            'icon_key'              => $case->icon_key,
            'title'                 => $case->title,
            'description'           => $case->description,
            'status'                => $case->status,
            'total_questions_count' => $case->total_questions_count ?? 0,
        ];
    }
}
