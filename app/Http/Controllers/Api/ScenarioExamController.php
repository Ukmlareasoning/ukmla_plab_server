<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScenarioExam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScenarioExamController extends Controller
{
    /**
     * List exams for a scenario.
     * Query params: scenario_id (required), exam_no, status, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $scenarioId = $request->query('scenario_id');
        if (!$scenarioId) {
            return response()->json([
                'success' => false,
                'message' => 'scenario_id is required.',
            ], 422);
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 200) : 10;

        $query = ScenarioExam::query()
            ->withTrashed()
            ->withCount(['questions as total_questions' => function ($q) {
                $q->withoutTrashed();
            }])
            ->where('scenario_id', $scenarioId);

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($examNo = $request->query('exam_no')) {
                $query->where('exam_no', $examNo);
            }
            if ($status = $request->query('status')) {
                if ($status === 'Deleted') {
                    $query->onlyTrashed();
                } else {
                    $query->where('status', $status);
                }
            }
        }

        $exams = $query->orderBy('exam_no', 'asc')->paginate($perPage);

        $items = collect($exams->items())->map(fn (ScenarioExam $e) => $this->formatExam($e))->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Scenario exams retrieved successfully.',
            'data' => [
                'scenario_exams' => $items,
                'pagination' => [
                    'current_page' => $exams->currentPage(),
                    'last_page'    => $exams->lastPage(),
                    'per_page'     => $exams->perPage(),
                    'total'        => $exams->total(),
                    'from'         => $exams->firstItem(),
                    'to'           => $exams->lastItem(),
                ],
            ],
        ]);
    }

    /**
     * Store a new scenario exam.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'scenario_id' => ['required', 'integer', 'exists:scenarios,id'],
            'exam_no'     => ['required', 'integer', 'min:1'],
            'status'      => ['nullable', 'in:Active,Inactive'],
        ], [
            'scenario_id.required' => 'Scenario is required.',
            'exam_no.required'     => 'Exam number is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Check for duplicate exam_no within same scenario
        $exists = ScenarioExam::withTrashed()
            ->where('scenario_id', $request->input('scenario_id'))
            ->where('exam_no', $request->input('exam_no'))
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['exam_no' => ['Exam number already exists for this scenario.']],
            ], 422);
        }

        $exam = ScenarioExam::create([
            'scenario_id' => $request->input('scenario_id'),
            'exam_no'     => $request->input('exam_no'),
            'status'      => $request->input('status', 'Active'),
        ]);

        $exam->loadCount(['questions as total_questions' => fn ($q) => $q->withoutTrashed()]);

        return response()->json([
            'success' => true,
            'message' => 'Scenario exam created successfully.',
            'data'    => ['scenario_exam' => $this->formatExam($exam)],
        ], 201);
    }

    /**
     * Update a scenario exam (status only; exam_no is immutable).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $exam = ScenarioExam::withTrashed()->find($id);
        if (!$exam) {
            return response()->json(['success' => false, 'message' => 'Scenario exam not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status'             => ['nullable', 'in:Active,Inactive'],
            'exams_release_mode' => ['nullable', 'in:all_at_once,one_after_another'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->filled('status')) {
            $exam->status = $request->input('status');
        }
        $exam->save();

        $exam->loadCount(['questions as total_questions' => fn ($q) => $q->withoutTrashed()]);

        return response()->json([
            'success' => true,
            'message' => 'Scenario exam updated successfully.',
            'data'    => ['scenario_exam' => $this->formatExam($exam)],
        ]);
    }

    /**
     * Update the release mode for all exams in a scenario.
     * POST /scenario-exams/release-mode   { scenario_id, exams_release_mode }
     */
    public function updateReleaseMode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'scenario_id'        => ['required', 'integer', 'exists:scenarios,id'],
            'exams_release_mode' => ['required', 'in:all_at_once,one_after_another'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        \App\Models\Scenario::where('id', $request->input('scenario_id'))
            ->update(['exams_release_mode' => $request->input('exams_release_mode')]);

        return response()->json([
            'success' => true,
            'message' => 'Release mode updated successfully.',
        ]);
    }

    /**
     * Soft delete a scenario exam.
     */
    public function destroy(int $id): JsonResponse
    {
        $exam = ScenarioExam::find($id) ?? ScenarioExam::onlyTrashed()->find($id);
        if (!$exam) {
            return response()->json(['success' => false, 'message' => 'Scenario exam not found.'], 404);
        }
        if ($exam->deleted_at) {
            return response()->json(['success' => true, 'message' => 'Scenario exam is already deleted.']);
        }
        $exam->delete();
        return response()->json(['success' => true, 'message' => 'Scenario exam deleted successfully.']);
    }

    /**
     * Restore a soft-deleted scenario exam.
     */
    public function restore(int $id): JsonResponse
    {
        $exam = ScenarioExam::onlyTrashed()->find($id);
        if (!$exam) {
            return response()->json(['success' => false, 'message' => 'Scenario exam not found or not deleted.'], 404);
        }
        $exam->restore();
        $exam->loadCount(['questions as total_questions' => fn ($q) => $q->withoutTrashed()]);
        return response()->json([
            'success' => true,
            'message' => 'Scenario exam restored successfully.',
            'data'    => ['scenario_exam' => $this->formatExam($exam)],
        ]);
    }

    private function formatExam(ScenarioExam $e): array
    {
        return [
            'id'              => $e->id,
            'scenario_id'     => $e->scenario_id,
            'exam_no'         => $e->exam_no,
            'total_questions' => $e->total_questions ?? 0,
            'status'          => $e->deleted_at ? 'Deleted' : $e->status,
            'is_deleted'      => (bool) $e->deleted_at,
            'created_at'      => $e->created_at?->toIso8601String(),
            'updated_at'      => $e->updated_at?->toIso8601String(),
        ];
    }
}
