<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QbCaseSimulation;
use App\Models\QbCaseSimulationQuestion;
use App\Models\QbCaseSimulationQuestionOption;
use App\Models\QbCaseSimulationQuestionAiTutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class QbCaseSimulationQuestionController extends Controller
{
    /** Trim string values in ai_tutor before validation (whitespace-only becomes empty). */
    private function normalizeAiTutorInput(Request $request): void
    {
        $ai = $request->input('ai_tutor');
        if (!is_array($ai)) {
            return;
        }
        $trimmed = [];
        foreach ($ai as $key => $value) {
            $trimmed[$key] = is_string($value) ? trim($value) : $value;
        }
        $request->merge(['ai_tutor' => $trimmed]);
    }

    /** @return array<string, mixed> */
    private function aiTutorValidationRules(): array
    {
        $text = ['nullable', 'string', 'max:65535'];

        return [
            'ai_tutor'                              => ['nullable', 'array'],
            'ai_tutor.validation'                 => $text,
            'ai_tutor.key_clues'                    => $text,
            'ai_tutor.missing_clues'                => $text,
            'ai_tutor.examiner_logic'               => $text,
            'ai_tutor.option_elimination'           => $text,
            'ai_tutor.trap_alert'                   => $text,
            'ai_tutor.pattern_label'                => $text,
            'ai_tutor.socratic_follow_up'           => $text,
            'ai_tutor.investigation_interpretation' => $text,
            'ai_tutor.management_ladder'            => $text,
            'ai_tutor.guideline_justification'      => $text,
            'ai_tutor.safety_netting'               => $text,
            'ai_tutor.exam_summary'                 => $text,
            'ai_tutor.one_screen_map'               => $text,
        ];
    }

    /** @return array<string, string> */
    private function aiTutorValidationMessages(): array
    {
        return [
            'ai_tutor.examiner_logic.max'          => 'Examiner explanation may not exceed 65535 characters.',
            'ai_tutor.trap_alert.max'              => 'Exam trap may not exceed 65535 characters.',
            'ai_tutor.option_elimination.max'      => 'Why other options are wrong may not exceed 65535 characters.',
            'ai_tutor.exam_summary.max'            => 'Reference may not exceed 65535 characters.',
        ];
    }

    /**
     * List questions for a case simulation.
     * Query params: qb_case_simulation_id (required), question_type, status,
     *               text, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $caseId = $request->query('qb_case_simulation_id');
        if (!$caseId) {
            return response()->json([
                'success' => false,
                'message' => 'qb_case_simulation_id is required.',
            ], 422);
        }

        $case = QbCaseSimulation::withTrashed()->find($caseId);
        if (!$case) {
            return response()->json([
                'success' => false,
                'message' => 'Case simulation not found.',
            ], 404);
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = QbCaseSimulationQuestion::query()
            ->withTrashed()
            ->where('qb_case_simulation_id', $caseId)
            ->with(['options', 'aiTutor']);

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $query->where('question', 'like', '%' . $text . '%');
            }
            if ($questionType = $request->query('question_type')) {
                $query->where('question_type', $questionType);
            }
            if ($status = $request->query('status')) {
                if ($status === 'Deleted') {
                    $query->onlyTrashed();
                } else {
                    $query->where('status', $status);
                }
            }
        }

        $questions = $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->paginate($perPage);

        $items = collect($questions->items())->map(fn($q) => $this->formatQuestion($q))->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Questions retrieved successfully.',
            'data' => [
                'qb_case_simulation' => [
                    'id'       => $case->id,
                    'title'    => $case->title,
                    'icon_key' => $case->icon_key,
                    'status'   => $case->deleted_at ? 'Deleted' : $case->status,
                ],
                'qb_case_simulation_questions' => $items,
                'pagination' => [
                    'current_page'  => $questions->currentPage(),
                    'last_page'     => $questions->lastPage(),
                    'per_page'      => $questions->perPage(),
                    'total'         => $questions->total(),
                    'from'          => $questions->firstItem(),
                    'to'            => $questions->lastItem(),
                ],
            ],
        ], 200);
    }

    /**
     * Show a single question with full details (admin edit).
     */
    public function show(int $id): JsonResponse
    {
        $question = QbCaseSimulationQuestion::withTrashed()->with(['options', 'aiTutor'])->find($id);

        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Question retrieved successfully.',
            'data'    => ['qb_case_simulation_question' => $this->formatQuestion($question)],
        ], 200);
    }

    /**
     * Store a new question with options and optional AI tutor.
     *
     * Payload:
     *   qb_case_simulation_id, question_type, question, sort_order, status
     *   options: [{ option_letter, option_text, is_correct }]
     *   ai_tutor: { validation, key_clues, ... } (all optional)
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeAiTutorInput($request);

        $validator = Validator::make($request->all(), array_merge([
            'qb_case_simulation_id'  => ['required', 'integer', 'exists:qb_case_simulations,id'],
            'question_type'          => ['required', 'in:mcq'],
            'question'               => ['required', 'string'],
            'sort_order'             => ['nullable', 'integer', 'min:0'],
            'status'                 => ['nullable', 'in:Active,Inactive'],
            'options'                => ['required', 'array', 'min:2', 'max:10'],
            'options.*.option_letter'=> ['required', 'string', 'max:1'],
            'options.*.option_text'  => ['required', 'string'],
            'options.*.is_correct'   => ['required', 'boolean'],
        ], $this->aiTutorValidationRules()), array_merge([
            'qb_case_simulation_id.required' => 'Case simulation is required.',
            'qb_case_simulation_id.exists'   => 'Case simulation not found.',
            'question_type.required'          => 'Question type is required.',
            'question.required'               => 'Question text is required.',
            'options.required'                => 'At least 2 answer options are required.',
            'options.min'                     => 'At least 2 answer options are required.',
            'options.*.option_letter.required'=> 'Option letter is required.',
            'options.*.option_text.required'  => 'Option text is required.',
            'options.*.is_correct.required'   => 'Correct flag is required for every option.',
        ], $this->aiTutorValidationMessages()));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Ensure exactly one correct option for MCQ
        if ($request->input('question_type') === 'mcq') {
            $correctCount = collect($request->input('options', []))->where('is_correct', true)->count();
            if ($correctCount !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => ['options' => ['Exactly one option must be marked as correct for MCQ.']],
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $question = QbCaseSimulationQuestion::create([
                'qb_case_simulation_id' => $request->input('qb_case_simulation_id'),
                'question_type'         => $request->input('question_type'),
                'question'              => $request->input('question'),
                'sort_order'            => $request->input('sort_order', 0),
                'status'                => $request->input('status', 'Active'),
            ]);

            foreach ($request->input('options') as $opt) {
                QbCaseSimulationQuestionOption::create([
                    'qb_case_simulation_question_id' => $question->id,
                    'option_letter'                   => strtoupper($opt['option_letter']),
                    'option_text'                     => $opt['option_text'],
                    'is_correct'                      => (bool) $opt['is_correct'],
                ]);
            }

            $aiTutorData = $request->input('ai_tutor', []);
            if (!empty(array_filter($aiTutorData ?? []))) {
                QbCaseSimulationQuestionAiTutor::create(array_merge(
                    ['qb_case_simulation_question_id' => $question->id],
                    array_intersect_key($aiTutorData, array_flip([
                        'validation', 'key_clues', 'missing_clues', 'examiner_logic',
                        'option_elimination', 'trap_alert', 'pattern_label',
                        'socratic_follow_up', 'investigation_interpretation',
                        'management_ladder', 'guideline_justification',
                        'safety_netting', 'exam_summary', 'one_screen_map',
                    ]))
                ));
            }

            DB::commit();

            $question->load(['options', 'aiTutor']);

            return response()->json([
                'success' => true,
                'message' => 'Question created successfully.',
                'data'    => ['qb_case_simulation_question' => $this->formatQuestion($question)],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create question. Please try again.',
            ], 500);
        }
    }

    /**
     * Update an existing question with options and AI tutor.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $question = QbCaseSimulationQuestion::withTrashed()->with(['options', 'aiTutor'])->find($id);

        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found.',
            ], 404);
        }

        $this->normalizeAiTutorInput($request);

        $validator = Validator::make($request->all(), array_merge([
            'question_type'          => ['required', 'in:mcq'],
            'question'               => ['required', 'string'],
            'sort_order'             => ['nullable', 'integer', 'min:0'],
            'status'                 => ['nullable', 'in:Active,Inactive'],
            'options'                => ['required', 'array', 'min:2', 'max:10'],
            'options.*.option_letter'=> ['required', 'string', 'max:1'],
            'options.*.option_text'  => ['required', 'string'],
            'options.*.is_correct'   => ['required', 'boolean'],
        ], $this->aiTutorValidationRules()), array_merge([
            'question_type.required'          => 'Question type is required.',
            'question.required'               => 'Question text is required.',
            'options.required'                => 'At least 2 answer options are required.',
            'options.min'                     => 'At least 2 answer options are required.',
            'options.*.option_letter.required'=> 'Option letter is required.',
            'options.*.option_text.required'  => 'Option text is required.',
            'options.*.is_correct.required'   => 'Correct flag is required for every option.',
        ], $this->aiTutorValidationMessages()));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->input('question_type') === 'mcq') {
            $correctCount = collect($request->input('options', []))->where('is_correct', true)->count();
            if ($correctCount !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => ['options' => ['Exactly one option must be marked as correct for MCQ.']],
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $question->question_type = $request->input('question_type');
            $question->question      = $request->input('question');
            $question->sort_order    = $request->input('sort_order', $question->sort_order);
            if ($request->filled('status')) {
                $question->status = $request->input('status');
            }
            $question->save();

            // Replace options
            $question->options()->delete();
            foreach ($request->input('options') as $opt) {
                QbCaseSimulationQuestionOption::create([
                    'qb_case_simulation_question_id' => $question->id,
                    'option_letter'                   => strtoupper($opt['option_letter']),
                    'option_text'                     => $opt['option_text'],
                    'is_correct'                      => (bool) $opt['is_correct'],
                ]);
            }

            // Upsert AI tutor
            $aiTutorData = $request->input('ai_tutor', []);
            $allowedKeys = [
                'validation', 'key_clues', 'missing_clues', 'examiner_logic',
                'option_elimination', 'trap_alert', 'pattern_label',
                'socratic_follow_up', 'investigation_interpretation',
                'management_ladder', 'guideline_justification',
                'safety_netting', 'exam_summary', 'one_screen_map',
            ];
            $filteredAi = array_intersect_key($aiTutorData ?? [], array_flip($allowedKeys));
            if (!empty(array_filter($filteredAi))) {
                if ($question->aiTutor) {
                    $question->aiTutor->update($filteredAi);
                } else {
                    QbCaseSimulationQuestionAiTutor::create(array_merge(
                        ['qb_case_simulation_question_id' => $question->id],
                        $filteredAi
                    ));
                }
            } elseif ($question->aiTutor) {
                $question->aiTutor->delete();
            }

            DB::commit();

            $question->load(['options', 'aiTutor']);

            return response()->json([
                'success' => true,
                'message' => 'Question updated successfully.',
                'data'    => ['qb_case_simulation_question' => $this->formatQuestion($question)],
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update question. Please try again.',
            ], 500);
        }
    }

    /**
     * Soft delete a question.
     */
    public function destroy(int $id): JsonResponse
    {
        $question = QbCaseSimulationQuestion::find($id);

        if (!$question) {
            $question = QbCaseSimulationQuestion::onlyTrashed()->find($id);
        }

        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found.',
            ], 404);
        }

        if ($question->deleted_at) {
            return response()->json([
                'success' => true,
                'message' => 'Question is already deleted.',
                'data'    => ['qb_case_simulation_question' => $this->formatQuestion($question)],
            ], 200);
        }

        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'Question deleted successfully.',
            'data'    => ['qb_case_simulation_question' => $this->formatQuestion($question)],
        ], 200);
    }

    /**
     * Restore a soft-deleted question.
     */
    public function restore(int $id): JsonResponse
    {
        $question = QbCaseSimulationQuestion::onlyTrashed()->find($id);

        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found or not deleted.',
            ], 404);
        }

        $question->restore();
        $question->load(['options', 'aiTutor']);

        return response()->json([
            'success' => true,
            'message' => 'Question restored successfully.',
            'data'    => ['qb_case_simulation_question' => $this->formatQuestion($question)],
        ], 200);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatQuestion(QbCaseSimulationQuestion $q): array
    {
        $options = $q->relationLoaded('options')
            ? $q->options->map(fn($o) => [
                'id'            => $o->id,
                'option_letter' => $o->option_letter,
                'option_text'   => $o->option_text,
                'is_correct'    => (bool) $o->is_correct,
            ])->values()->toArray()
            : [];

        $aiTutor = null;
        if ($q->relationLoaded('aiTutor') && $q->aiTutor) {
            $at = $q->aiTutor;
            $aiTutor = [
                'id'                         => $at->id,
                'validation'                 => $at->validation,
                'key_clues'                  => $at->key_clues,
                'missing_clues'              => $at->missing_clues,
                'examiner_logic'             => $at->examiner_logic,
                'option_elimination'         => $at->option_elimination,
                'trap_alert'                 => $at->trap_alert,
                'pattern_label'              => $at->pattern_label,
                'socratic_follow_up'         => $at->socratic_follow_up,
                'investigation_interpretation' => $at->investigation_interpretation,
                'management_ladder'          => $at->management_ladder,
                'guideline_justification'    => $at->guideline_justification,
                'safety_netting'             => $at->safety_netting,
                'exam_summary'               => $at->exam_summary,
                'one_screen_map'             => $at->one_screen_map,
            ];
        }

        return [
            'id'                       => $q->id,
            'qb_case_simulation_id'    => $q->qb_case_simulation_id,
            'question_type'            => $q->question_type,
            'question'                 => $q->question,
            'sort_order'               => $q->sort_order,
            'status'                   => $q->deleted_at ? 'Deleted' : $q->status,
            'is_deleted'               => (bool) $q->deleted_at,
            'options'                  => $options,
            'ai_tutor'                 => $aiTutor,
            'created_at'               => $q->created_at?->toIso8601String(),
            'updated_at'               => $q->updated_at?->toIso8601String(),
            'deleted_at'               => $q->deleted_at?->toIso8601String(),
        ];
    }
}
