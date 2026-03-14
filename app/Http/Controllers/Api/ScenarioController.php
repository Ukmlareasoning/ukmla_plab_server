<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scenario;
use App\Models\ScenarioExam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ScenarioController extends Controller
{
    /**
     * List scenarios with optional filters (text, status, exam_type_id, difficulty_level_id).
     * Query params: text, status, exam_type_id, difficulty_level_id, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = Scenario::query()
            ->withTrashed()
            ->with(['examType', 'difficultyLevel', 'topicFocuses'])
            ->withCount(['exams as total_exams_count' => function ($q) {
                $q->withoutTrashed();
            }]);

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $searchTerm = '%' . $text . '%';
                $query->where('title', 'like', $searchTerm);
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

        $scenarios = $query->orderBy('id', 'asc')->paginate($perPage);

        $items = collect($scenarios->items())->map(fn (Scenario $s) => $this->formatScenario($s))->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Scenarios retrieved successfully.',
            'data' => [
                'scenarios' => $items,
                'pagination' => [
                    'current_page' => $scenarios->currentPage(),
                    'last_page'    => $scenarios->lastPage(),
                    'per_page'     => $scenarios->perPage(),
                    'total'        => $scenarios->total(),
                    'from'         => $scenarios->firstItem(),
                    'to'           => $scenarios->lastItem(),
                ],
            ],
        ]);
    }

    /**
     * Store a new scenario (with topic_focus_ids pivot).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'              => ['required', 'string', 'max:191', 'unique:scenarios,title'],
            'exam_type_id'       => ['required', 'integer', 'exists:exam_types,id'],
            'difficulty_level_id'=> ['required', 'integer', 'exists:difficulty_levels,id'],
            'icon_key'           => ['required', 'string', 'max:64'],
            'description'        => ['required', 'string'],
            'duration_type'      => ['required', 'in:Week,Month'],
            'duration'           => ['required', 'integer', 'min:1', 'max:255'],
            'per_day_exams'      => ['required', 'integer', 'min:1', 'max:255'],
            'exams_release_mode' => ['nullable', 'in:all_at_once,one_after_another'],
            'status'             => ['nullable', 'in:Active,Inactive'],
            'topic_focus_ids'    => ['required', 'array', 'min:1'],
            'topic_focus_ids.*'  => ['integer', 'exists:scenarios_topic_focus,id'],
        ], [
            'title.required'              => 'Scenario title is required.',
            'title.unique'                => 'This scenario title is already in use.',
            'exam_type_id.required'       => 'Scenario exam type is required.',
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

        $durationType  = $request->input('duration_type', 'Week');
        $duration      = (int) $request->input('duration', 1);
        $perDayExams   = (int) $request->input('per_day_exams', 1);
        $daysPerUnit   = $durationType === 'Week' ? 7 : 30;
        $totalExams    = $duration * $daysPerUnit * $perDayExams;

        DB::beginTransaction();
        try {
            $scenario = Scenario::create([
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
                'status'             => $request->input('status', 'Active'),
            ]);

            // Sync topic focuses
            $topicFocusIds = $request->input('topic_focus_ids', []);
            if (!empty($topicFocusIds)) {
                $scenario->topicFocuses()->sync($topicFocusIds);
            }

            // Auto-create scenario_exams rows
            for ($i = 1; $i <= $totalExams; $i++) {
                ScenarioExam::create([
                    'scenario_id' => $scenario->id,
                    'exam_no'     => $i,
                    'status'      => 'Active',
                ]);
            }

            DB::commit();

            $scenario->load(['examType', 'difficultyLevel', 'topicFocuses']);
            $scenario->loadCount(['exams as total_exams_count' => function ($q) {
                $q->withoutTrashed();
            }]);

            return response()->json([
                'success' => true,
                'message' => 'Scenario created successfully.',
                'data'    => ['scenario' => $this->formatScenario($scenario)],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Unable to create scenario. Please try again.',
            ], 500);
        }
    }

    /**
     * Show a single scenario.
     */
    public function show(int $id): JsonResponse
    {
        $scenario = Scenario::withTrashed()
            ->with(['examType', 'difficultyLevel', 'topicFocuses'])
            ->withCount(['exams as total_exams_count' => function ($q) {
                $q->withoutTrashed();
            }])
            ->find($id);

        if (!$scenario) {
            return response()->json(['success' => false, 'message' => 'Scenario not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Scenario retrieved successfully.',
            'data'    => ['scenario' => $this->formatScenario($scenario)],
        ]);
    }

    /**
     * Update an existing scenario.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $scenario = Scenario::withTrashed()->find($id);
        if (!$scenario) {
            return response()->json(['success' => false, 'message' => 'Scenario not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title'              => ['required', 'string', 'max:191', 'unique:scenarios,title,' . $scenario->id],
            'exam_type_id'       => ['required', 'integer', 'exists:exam_types,id'],
            'difficulty_level_id'=> ['required', 'integer', 'exists:difficulty_levels,id'],
            'icon_key'           => ['required', 'string', 'max:64'],
            'description'        => ['required', 'string'],
            'duration_type'      => ['required', 'in:Week,Month'],
            'duration'           => ['required', 'integer', 'min:1', 'max:255'],
            'per_day_exams'      => ['required', 'integer', 'min:1', 'max:255'],
            'exams_release_mode' => ['nullable', 'in:all_at_once,one_after_another'],
            'status'             => ['nullable', 'in:Active,Inactive'],
            'topic_focus_ids'    => ['required', 'array', 'min:1'],
            'topic_focus_ids.*'  => ['integer', 'exists:scenarios_topic_focus,id'],
        ], [
            'title.required'              => 'Scenario title is required.',
            'title.unique'                => 'This scenario title is already in use.',
            'exam_type_id.required'       => 'Scenario exam type is required.',
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

        $durationType = $request->input('duration_type', $scenario->duration_type);
        $duration     = (int) $request->input('duration', $scenario->duration);
        $perDayExams  = (int) $request->input('per_day_exams', $scenario->per_day_exams);
        $daysPerUnit  = $durationType === 'Week' ? 7 : 30;
        $totalExams   = $duration * $daysPerUnit * $perDayExams;

        DB::beginTransaction();
        try {
            $scenario->fill([
                'exam_type_id'       => $request->input('exam_type_id'),
                'difficulty_level_id'=> $request->input('difficulty_level_id'),
                'icon_key'           => $request->input('icon_key', $scenario->icon_key),
                'title'              => $request->input('title'),
                'description'        => $request->input('description', $scenario->description),
                'duration_type'      => $durationType,
                'duration'           => $duration,
                'per_day_exams'      => $perDayExams,
                'total_exams'        => $totalExams,
                'exams_release_mode' => $request->input('exams_release_mode', $scenario->exams_release_mode),
            ]);
            if ($request->filled('status')) {
                $scenario->status = $request->input('status');
            }
            $scenario->save();

            // Sync topic focuses
            $topicFocusIds = $request->input('topic_focus_ids', []);
            $scenario->topicFocuses()->sync($topicFocusIds);

            DB::commit();

            $scenario->load(['examType', 'difficultyLevel', 'topicFocuses']);
            $scenario->loadCount(['exams as total_exams_count' => function ($q) {
                $q->withoutTrashed();
            }]);

            return response()->json([
                'success' => true,
                'message' => 'Scenario updated successfully.',
                'data'    => ['scenario' => $this->formatScenario($scenario)],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Unable to update scenario. Please try again.',
            ], 500);
        }
    }

    /**
     * Soft delete a scenario.
     */
    public function destroy(int $id): JsonResponse
    {
        $scenario = Scenario::find($id) ?? Scenario::onlyTrashed()->find($id);
        if (!$scenario) {
            return response()->json(['success' => false, 'message' => 'Scenario not found.'], 404);
        }
        if ($scenario->deleted_at) {
            return response()->json(['success' => true, 'message' => 'Scenario is already deleted.']);
        }
        $scenario->delete();
        return response()->json(['success' => true, 'message' => 'Scenario deleted successfully.']);
    }

    /**
     * Restore a soft-deleted scenario.
     */
    public function restore(int $id): JsonResponse
    {
        $scenario = Scenario::onlyTrashed()->find($id);
        if (!$scenario) {
            return response()->json(['success' => false, 'message' => 'Scenario not found or not deleted.'], 404);
        }
        $scenario->restore();
        $scenario->load(['examType', 'difficultyLevel', 'topicFocuses']);
        $scenario->loadCount(['exams as total_exams_count' => function ($q) {
            $q->withoutTrashed();
        }]);
        return response()->json([
            'success' => true,
            'message' => 'Scenario restored successfully.',
            'data'    => ['scenario' => $this->formatScenario($scenario)],
        ]);
    }

    private function formatScenario(Scenario $s): array
    {
        return [
            'id'                  => $s->id,
            'exam_type_id'        => $s->exam_type_id,
            'exam_type_name'      => $s->examType?->name,
            'difficulty_level_id' => $s->difficulty_level_id,
            'difficulty_level_name' => $s->difficultyLevel?->name,
            'icon_key'            => $s->icon_key,
            'title'               => $s->title,
            'description'         => $s->description,
            'duration_type'       => $s->duration_type,
            'duration'            => $s->duration,
            'per_day_exams'       => $s->per_day_exams,
            'total_exams'         => $s->total_exams,
            'exams_release_mode'  => $s->exams_release_mode,
            'topic_focuses'       => $s->topicFocuses
                ? $s->topicFocuses->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values()->toArray()
                : [],
            'status'              => $s->deleted_at ? 'Deleted' : $s->status,
            'is_deleted'          => (bool) $s->deleted_at,
            'created_at'          => $s->created_at?->toIso8601String(),
            'updated_at'          => $s->updated_at?->toIso8601String(),
            'deleted_at'          => $s->deleted_at?->toIso8601String(),
        ];
    }
}
