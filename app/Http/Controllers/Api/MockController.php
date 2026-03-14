<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mock;
use App\Models\MockExam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = Mock::query()
            ->withTrashed()
            ->with(['examType', 'difficultyLevel', 'topicFocuses'])
            ->withCount(['exams as total_exams_count' => function ($q) {
                $q->withoutTrashed();
            }]);

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $query->where('title', 'like', '%' . $text . '%');
            }
            if ($status = $request->query('status')) {
                if ($status === 'Deleted') {
                    $query->onlyTrashed();
                } else {
                    $query->where('status', $status);
                }
            }
            if ($examTypeId = $request->query('exam_type_id')) {
                $query->where('exam_type_id', $examTypeId);
            }
            if ($difficultyLevelId = $request->query('difficulty_level_id')) {
                $query->where('difficulty_level_id', $difficultyLevelId);
            }
        }

        $mocks = $query->orderBy('id', 'asc')->paginate($perPage);

        $items = collect($mocks->items())->map(fn (Mock $m) => $this->formatMock($m))->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Mocks retrieved successfully.',
            'data' => [
                'mocks' => $items,
                'pagination' => [
                    'current_page' => $mocks->currentPage(),
                    'last_page'    => $mocks->lastPage(),
                    'per_page'     => $mocks->perPage(),
                    'total'        => $mocks->total(),
                    'from'         => $mocks->firstItem(),
                    'to'           => $mocks->lastItem(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'              => ['required', 'string', 'max:191', 'unique:mocks,title'],
            'exam_type_id'       => ['required', 'integer', 'exists:exam_types,id'],
            'difficulty_level_id'=> ['required', 'integer', 'exists:difficulty_levels,id'],
            'icon_key'           => ['required', 'string', 'max:64'],
            'description'        => ['required', 'string'],
            'duration_type'      => ['required', 'in:Week,Month'],
            'duration'           => ['required', 'integer', 'min:1', 'max:255'],
            'per_day_exams'      => ['required', 'integer', 'min:1', 'max:255'],
            'exams_release_mode' => ['nullable', 'in:all_at_once,one_after_another'],
            'is_paid'            => ['nullable', 'boolean'],
            'price_eur'          => ['nullable', 'numeric', 'min:0'],
            'status'             => ['nullable', 'in:Active,Inactive'],
            'topic_focus_ids'    => ['required', 'array', 'min:1'],
            'topic_focus_ids.*'  => ['integer', 'exists:topic_focuses,id'],
        ], [
            'title.required'              => 'Mock exam title is required.',
            'title.unique'                => 'This mock exam title is already in use.',
            'exam_type_id.required'       => 'Exam type is required.',
            'difficulty_level_id.required'=> 'Difficulty level is required.',
            'icon_key.required'           => 'Icon is required.',
            'description.required'        => 'Description is required.',
            'duration_type.required'      => 'Duration type is required.',
            'duration.required'           => 'Duration is required.',
            'per_day_exams.required'      => 'Per day exams is required.',
            'topic_focus_ids.required'    => 'At least one Topic / focus is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $durationType = $request->input('duration_type', 'Week');
        $duration     = (int) $request->input('duration', 1);
        $perDayExams  = (int) $request->input('per_day_exams', 1);
        $daysPerUnit  = $durationType === 'Week' ? 7 : 30;
        $totalExams   = $duration * $daysPerUnit * $perDayExams;
        $isPaid       = filter_var($request->input('is_paid', false), FILTER_VALIDATE_BOOLEAN);

        DB::beginTransaction();
        try {
            $mock = Mock::create([
                'exam_type_id'       => $request->input('exam_type_id'),
                'difficulty_level_id'=> $request->input('difficulty_level_id'),
                'icon_key'           => $request->input('icon_key'),
                'title'              => $request->input('title'),
                'description'        => $request->input('description'),
                'duration_type'      => $durationType,
                'duration'           => $duration,
                'per_day_exams'      => $perDayExams,
                'total_exams'        => $totalExams,
                'exams_release_mode' => $request->input('exams_release_mode', 'all_at_once'),
                'is_paid'            => $isPaid,
                'price_eur'          => $isPaid ? $request->input('price_eur') : null,
                'status'             => $request->input('status', 'Active'),
            ]);

            $topicFocusIds = $request->input('topic_focus_ids', []);
            if (!empty($topicFocusIds)) {
                $mock->topicFocuses()->sync($topicFocusIds);
            }

            for ($i = 1; $i <= $totalExams; $i++) {
                MockExam::create([
                    'mock_id' => $mock->id,
                    'exam_no' => $i,
                    'status'  => 'Active',
                ]);
            }

            DB::commit();

            $mock->load(['examType', 'difficultyLevel', 'topicFocuses']);
            $mock->loadCount(['exams as total_exams_count' => function ($q) {
                $q->withoutTrashed();
            }]);

            return response()->json([
                'success' => true,
                'message' => 'Mock exam created successfully.',
                'data'    => ['mock' => $this->formatMock($mock)],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Unable to create mock exam. Please try again.',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $mock = Mock::withTrashed()
            ->with(['examType', 'difficultyLevel', 'topicFocuses'])
            ->withCount(['exams as total_exams_count' => function ($q) {
                $q->withoutTrashed();
            }])
            ->find($id);

        if (!$mock) {
            return response()->json(['success' => false, 'message' => 'Mock exam not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Mock exam retrieved successfully.',
            'data'    => ['mock' => $this->formatMock($mock)],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $mock = Mock::withTrashed()->find($id);
        if (!$mock) {
            return response()->json(['success' => false, 'message' => 'Mock exam not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'              => ['required', 'string', 'max:191', 'unique:mocks,title,' . $mock->id],
            'exam_type_id'       => ['required', 'integer', 'exists:exam_types,id'],
            'difficulty_level_id'=> ['required', 'integer', 'exists:difficulty_levels,id'],
            'icon_key'           => ['required', 'string', 'max:64'],
            'description'        => ['required', 'string'],
            'duration_type'      => ['required', 'in:Week,Month'],
            'duration'           => ['required', 'integer', 'min:1', 'max:255'],
            'per_day_exams'      => ['required', 'integer', 'min:1', 'max:255'],
            'exams_release_mode' => ['nullable', 'in:all_at_once,one_after_another'],
            'is_paid'            => ['nullable', 'boolean'],
            'price_eur'          => ['nullable', 'numeric', 'min:0'],
            'status'             => ['nullable', 'in:Active,Inactive'],
            'topic_focus_ids'    => ['required', 'array', 'min:1'],
            'topic_focus_ids.*'  => ['integer', 'exists:topic_focuses,id'],
        ], [
            'title.required'              => 'Mock exam title is required.',
            'title.unique'                => 'This mock exam title is already in use.',
            'exam_type_id.required'       => 'Exam type is required.',
            'difficulty_level_id.required'=> 'Difficulty level is required.',
            'icon_key.required'           => 'Icon is required.',
            'description.required'        => 'Description is required.',
            'duration_type.required'      => 'Duration type is required.',
            'duration.required'           => 'Duration is required.',
            'per_day_exams.required'      => 'Per day exams is required.',
            'topic_focus_ids.required'    => 'At least one Topic / focus is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $durationType = $request->input('duration_type', $mock->duration_type);
        $duration     = (int) $request->input('duration', $mock->duration);
        $perDayExams  = (int) $request->input('per_day_exams', $mock->per_day_exams);
        $daysPerUnit  = $durationType === 'Week' ? 7 : 30;
        $totalExams   = $duration * $daysPerUnit * $perDayExams;
        $isPaid       = filter_var($request->input('is_paid', $mock->is_paid), FILTER_VALIDATE_BOOLEAN);

        DB::beginTransaction();
        try {
            $mock->fill([
                'exam_type_id'       => $request->input('exam_type_id'),
                'difficulty_level_id'=> $request->input('difficulty_level_id'),
                'icon_key'           => $request->input('icon_key', $mock->icon_key),
                'title'              => $request->input('title'),
                'description'        => $request->input('description', $mock->description),
                'duration_type'      => $durationType,
                'duration'           => $duration,
                'per_day_exams'      => $perDayExams,
                'total_exams'        => $totalExams,
                'exams_release_mode' => $request->input('exams_release_mode', $mock->exams_release_mode),
                'is_paid'            => $isPaid,
                'price_eur'          => $isPaid ? $request->input('price_eur', $mock->price_eur) : null,
            ]);
            if ($request->filled('status')) {
                $mock->status = $request->input('status');
            }
            $mock->save();

            $topicFocusIds = $request->input('topic_focus_ids', []);
            $mock->topicFocuses()->sync($topicFocusIds);

            DB::commit();

            $mock->load(['examType', 'difficultyLevel', 'topicFocuses']);
            $mock->loadCount(['exams as total_exams_count' => function ($q) {
                $q->withoutTrashed();
            }]);

            return response()->json([
                'success' => true,
                'message' => 'Mock exam updated successfully.',
                'data'    => ['mock' => $this->formatMock($mock)],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Unable to update mock exam. Please try again.',
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $mock = Mock::find($id) ?? Mock::onlyTrashed()->find($id);
        if (!$mock) {
            return response()->json(['success' => false, 'message' => 'Mock exam not found.'], 404);
        }
        if ($mock->deleted_at) {
            return response()->json(['success' => true, 'message' => 'Mock exam is already deleted.']);
        }
        $mock->delete();
        return response()->json(['success' => true, 'message' => 'Mock exam deleted successfully.']);
    }

    /**
     * Update only pricing (is_paid, price_eur). No full-table validation.
     */
    public function updatePricing(Request $request, int $id): JsonResponse
    {
        $mock = Mock::withTrashed()->find($id);
        if (!$mock) {
            return response()->json(['success' => false, 'message' => 'Mock exam not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'is_paid'  => ['required', 'boolean'],
            'price_eur'=> ['nullable', 'numeric', 'min:0'],
        ], [
            'is_paid.required' => 'Pricing type (paid/free) is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $isPaid = filter_var($request->input('is_paid'), FILTER_VALIDATE_BOOLEAN);
        $mock->is_paid  = $isPaid;
        $mock->price_eur = $isPaid ? $request->input('price_eur') : null;
        $mock->save();

        $mock->load(['examType', 'difficultyLevel', 'topicFocuses']);
        $mock->loadCount(['exams as total_exams_count' => function ($q) {
            $q->withoutTrashed();
        }]);

        return response()->json([
            'success' => true,
            'message' => 'Pricing updated successfully.',
            'data'    => ['mock' => $this->formatMock($mock)],
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $mock = Mock::onlyTrashed()->find($id);
        if (!$mock) {
            return response()->json(['success' => false, 'message' => 'Mock exam not found or not deleted.'], 404);
        }
        $mock->restore();
        $mock->load(['examType', 'difficultyLevel', 'topicFocuses']);
        $mock->loadCount(['exams as total_exams_count' => function ($q) {
            $q->withoutTrashed();
        }]);
        return response()->json([
            'success' => true,
            'message' => 'Mock exam restored successfully.',
            'data'    => ['mock' => $this->formatMock($mock)],
        ]);
    }

    private function formatMock(Mock $m): array
    {
        return [
            'id'                    => $m->id,
            'exam_type_id'          => $m->exam_type_id,
            'exam_type_name'        => $m->examType?->name,
            'difficulty_level_id'   => $m->difficulty_level_id,
            'difficulty_level_name' => $m->difficultyLevel?->name,
            'icon_key'              => $m->icon_key,
            'title'                 => $m->title,
            'description'           => $m->description,
            'duration_type'         => $m->duration_type,
            'duration'              => $m->duration,
            'per_day_exams'         => $m->per_day_exams,
            'total_exams'           => $m->total_exams,
            'exams_release_mode'    => $m->exams_release_mode,
            'is_paid'               => (bool) $m->is_paid,
            'price_eur'             => $m->price_eur,
            'topic_focuses'         => $m->topicFocuses
                ? $m->topicFocuses->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()->toArray()
                : [],
            'status'                => $m->deleted_at ? 'Deleted' : $m->status,
            'is_deleted'            => (bool) $m->deleted_at,
            'created_at'            => $m->created_at?->toIso8601String(),
            'updated_at'            => $m->updated_at?->toIso8601String(),
            'deleted_at'            => $m->deleted_at?->toIso8601String(),
        ];
    }
}
