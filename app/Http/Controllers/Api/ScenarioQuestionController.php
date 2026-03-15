<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScenarioQuestion;
use App\Models\ScenarioQuestionOption;
use App\Models\ScenarioQuestionAiTutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ScenarioQuestionController extends Controller
{
    /**
     * List questions with optional filters.
     * Query params: scenario_id, scenario_exam_id, question_type, status, text, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = ScenarioQuestion::query()
            ->withTrashed()
            ->with(['scenario:id,title', 'scenarioExam:id,exam_no', 'options', 'aiTutor'])
            ->orderBy('id', 'desc');

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $query->where('question', 'like', '%' . $text . '%');
            }
            if ($status = $request->query('status')) {
                if ($status === 'Deleted') {
                    $query->onlyTrashed();
                } else {
                    $query->where('status', $status);
                }
            }
            if ($qt = $request->query('question_type')) {
                $query->where('question_type', $qt);
            }
        }

        // Always filter by scenario / exam when provided (even without apply_filters)
        if ($scenarioId = $request->query('scenario_id')) {
            $query->where('scenario_id', $scenarioId);
        }
        if ($examId = $request->query('scenario_exam_id')) {
            $query->where('scenario_exam_id', $examId);
        }

        $questions = $query->paginate($perPage);

        // For user practice pages we want AI-tutor fields as well, so always include them here.
        $items = collect($questions->items())->map(fn (ScenarioQuestion $q) => $this->formatQuestion($q, true))->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Scenario questions retrieved successfully.',
            'data' => [
                'questions' => $items,
                'pagination' => [
                    'current_page' => $questions->currentPage(),
                    'last_page'    => $questions->lastPage(),
                    'per_page'     => $questions->perPage(),
                    'total'        => $questions->total(),
                    'from'         => $questions->firstItem(),
                    'to'           => $questions->lastItem(),
                ],
            ],
        ]);
    }

    /**
     * Store a new question with options and AI-tutor fields.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'scenario_id'      => ['required', 'integer', 'exists:scenarios,id'],
            'scenario_exam_id' => ['required', 'integer', 'exists:scenario_exams,id'],
            'question_type'    => ['required', 'in:mcq,shortAnswer,descriptive,trueFalse,fillInBlanks'],
            'question'         => ['required', 'string'],
            'answer_description' => ['nullable', 'string'],
            'status'           => ['nullable', 'in:Active,Inactive'],
            // MCQ options
            'options'            => ['nullable', 'array'],
            'options.*.letter'   => ['required_with:options', 'string', 'max:1'],
            'options.*.text'     => ['required_with:options', 'string'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'correct_option'     => ['nullable', 'string', 'max:1'],
            // AI-tutor fields
            'ai_tutor'                           => ['nullable', 'array'],
            'ai_tutor.validation'                => ['nullable', 'string'],
            'ai_tutor.key_clues_identified'      => ['nullable', 'string'],
            'ai_tutor.missing_or_misweighted_clues' => ['nullable', 'string'],
            'ai_tutor.examiner_logic'            => ['nullable', 'string'],
            'ai_tutor.option_by_option_elimination' => ['nullable', 'string'],
            'ai_tutor.examiner_trap_alert'       => ['nullable', 'string'],
            'ai_tutor.pattern_recognition_label' => ['nullable', 'string'],
            'ai_tutor.socratic_follow_up_question' => ['nullable', 'string'],
            'ai_tutor.investigation_interpretation' => ['nullable', 'string'],
            'ai_tutor.management_ladder'         => ['nullable', 'string'],
            'ai_tutor.guideline_justification'   => ['nullable', 'string'],
            'ai_tutor.safety_netting_red_flags'  => ['nullable', 'string'],
            'ai_tutor.exam_summary_box'          => ['nullable', 'string'],
            'ai_tutor.one_screen_memory_map'     => ['nullable', 'string'],
        ], [
            'scenario_id.required'      => 'Scenario is required.',
            'scenario_exam_id.required' => 'Scenario exam is required.',
            'question_type.required'    => 'Question type is required.',
            'question.required'         => 'Question text is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $scenarioQuestion = ScenarioQuestion::create([
                'scenario_id'      => $request->input('scenario_id'),
                'scenario_exam_id' => $request->input('scenario_exam_id'),
                'question_type'    => $request->input('question_type'),
                'question'         => $request->input('question'),
                'correct_option'   => $request->input('correct_option'),
                'answer_description' => $request->input('answer_description'),
                'status'           => $request->input('status', 'Active'),
            ]);

            // Save MCQ options
            if ($request->has('options')) {
                foreach ($request->input('options') as $opt) {
                    ScenarioQuestionOption::create([
                        'scenario_question_id' => $scenarioQuestion->id,
                        'option_letter'        => strtoupper($opt['letter']),
                        'option_text'          => $opt['text'],
                        'is_correct'           => (bool) ($opt['is_correct'] ?? false),
                    ]);
                }
            }

            // Save AI-tutor fields
            $aiTutorData = $request->input('ai_tutor', []);
            ScenarioQuestionAiTutor::create(array_merge(
                ['scenario_question_id' => $scenarioQuestion->id],
                $this->extractAiTutorFields($aiTutorData)
            ));

            DB::commit();

            $scenarioQuestion->load(['scenario:id,title', 'scenarioExam:id,exam_no', 'options', 'aiTutor']);

            return response()->json([
                'success' => true,
                'message' => 'Scenario question created successfully.',
                'data'    => ['question' => $this->formatQuestion($scenarioQuestion, true)],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Unable to create question. Please try again.',
            ], 500);
        }
    }

    /**
     * Show a single question with options and AI-tutor.
     */
    public function show(int $id): JsonResponse
    {
        $q = ScenarioQuestion::withTrashed()
            ->with(['scenario:id,title', 'scenarioExam:id,exam_no', 'options', 'aiTutor'])
            ->find($id);

        if (!$q) {
            return response()->json(['success' => false, 'message' => 'Question not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Question retrieved successfully.',
            'data'    => ['question' => $this->formatQuestion($q, true)],
        ]);
    }

    /**
     * Update a question with its options and AI-tutor fields.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $q = ScenarioQuestion::withTrashed()->find($id);
        if (!$q) {
            return response()->json(['success' => false, 'message' => 'Question not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'question_type'    => ['required', 'in:mcq,shortAnswer,descriptive,trueFalse,fillInBlanks'],
            'question'         => ['required', 'string'],
            'answer_description' => ['nullable', 'string'],
            'status'           => ['nullable', 'in:Active,Inactive'],
            'options'          => ['nullable', 'array'],
            'options.*.letter' => ['required_with:options', 'string', 'max:1'],
            'options.*.text'   => ['required_with:options', 'string'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'correct_option'   => ['nullable', 'string', 'max:1'],
            'ai_tutor'         => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $q->question_type    = $request->input('question_type');
            $q->question         = $request->input('question');
            $q->correct_option   = $request->input('correct_option', $q->correct_option);
            $q->answer_description = $request->input('answer_description', $q->answer_description);
            if ($request->filled('status')) {
                $q->status = $request->input('status');
            }
            $q->save();

            // Re-sync options
            if ($request->has('options')) {
                $q->options()->delete();
                foreach ($request->input('options') as $opt) {
                    ScenarioQuestionOption::create([
                        'scenario_question_id' => $q->id,
                        'option_letter'        => strtoupper($opt['letter']),
                        'option_text'          => $opt['text'],
                        'is_correct'           => (bool) ($opt['is_correct'] ?? false),
                    ]);
                }
            }

            // Update AI-tutor
            $aiTutorData = $request->input('ai_tutor', []);
            $aiTutor = $q->aiTutor;
            if ($aiTutor) {
                $aiTutor->update($this->extractAiTutorFields($aiTutorData));
            } else {
                ScenarioQuestionAiTutor::create(array_merge(
                    ['scenario_question_id' => $q->id],
                    $this->extractAiTutorFields($aiTutorData)
                ));
            }

            DB::commit();

            $q->load(['scenario:id,title', 'scenarioExam:id,exam_no', 'options', 'aiTutor']);

            return response()->json([
                'success' => true,
                'message' => 'Question updated successfully.',
                'data'    => ['question' => $this->formatQuestion($q, true)],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Unable to update question. Please try again.',
            ], 500);
        }
    }

    /**
     * Soft delete a question.
     */
    public function destroy(int $id): JsonResponse
    {
        $q = ScenarioQuestion::find($id) ?? ScenarioQuestion::onlyTrashed()->find($id);
        if (!$q) {
            return response()->json(['success' => false, 'message' => 'Question not found.'], 404);
        }
        if ($q->deleted_at) {
            return response()->json(['success' => true, 'message' => 'Question is already deleted.']);
        }
        $q->delete();
        return response()->json(['success' => true, 'message' => 'Question deleted successfully.']);
    }

    /**
     * Restore a soft-deleted question.
     */
    public function restore(int $id): JsonResponse
    {
        $q = ScenarioQuestion::onlyTrashed()->find($id);
        if (!$q) {
            return response()->json(['success' => false, 'message' => 'Question not found or not deleted.'], 404);
        }
        $q->restore();
        $q->load(['scenario:id,title', 'scenarioExam:id,exam_no', 'options']);
        return response()->json([
            'success' => true,
            'message' => 'Question restored successfully.',
            'data'    => ['question' => $this->formatQuestion($q)],
        ]);
    }

    private function extractAiTutorFields(array $data): array
    {
        $fields = [
            'validation', 'key_clues_identified', 'missing_or_misweighted_clues',
            'examiner_logic', 'option_by_option_elimination', 'examiner_trap_alert',
            'pattern_recognition_label', 'socratic_follow_up_question',
            'investigation_interpretation', 'management_ladder', 'guideline_justification',
            'safety_netting_red_flags', 'exam_summary_box', 'one_screen_memory_map',
        ];
        $result = [];
        foreach ($fields as $field) {
            $result[$field] = $data[$field] ?? null;
        }
        return $result;
    }

    private function formatQuestion(ScenarioQuestion $q, bool $includeAiTutor = false): array
    {
        $correctOption = null;
        if ($q->question_type === 'mcq' && $q->relationLoaded('options')) {
            $correctOpt = $q->options->firstWhere('is_correct', true);
            $correctOption = $correctOpt
                ? $correctOpt->option_letter . ') ' . $correctOpt->option_text
                : ($q->correct_option ?? null);
        }

        $result = [
            'id'               => $q->id,
            'scenario_id'      => $q->scenario_id,
            'scenario_title'   => $q->scenario?->title,
            'scenario_exam_id' => $q->scenario_exam_id,
            'exam_no'          => $q->scenarioExam?->exam_no,
            'question_type'    => $q->question_type,
            'question'         => $q->question,
            'correct_option'   => $q->correct_option,
            'answer'           => $correctOption,
            'answer_description' => $q->answer_description,
            'status'           => $q->deleted_at ? 'Deleted' : $q->status,
            'is_deleted'       => (bool) $q->deleted_at,
            'options'          => $q->relationLoaded('options')
                ? $q->options->map(fn ($o) => [
                    'id'            => $o->id,
                    'option_letter' => $o->option_letter,
                    'option_text'   => $o->option_text,
                    'is_correct'    => $o->is_correct,
                ])->values()->toArray()
                : [],
            'created_at'       => $q->created_at?->toIso8601String(),
            'updated_at'       => $q->updated_at?->toIso8601String(),
        ];

        if ($includeAiTutor && $q->relationLoaded('aiTutor') && $q->aiTutor) {
            $ai = $q->aiTutor;
            $result['ai_tutor'] = [
                'validation'                  => $ai->validation,
                'key_clues_identified'        => $ai->key_clues_identified,
                'missing_or_misweighted_clues'=> $ai->missing_or_misweighted_clues,
                'examiner_logic'              => $ai->examiner_logic,
                'option_by_option_elimination'=> $ai->option_by_option_elimination,
                'examiner_trap_alert'         => $ai->examiner_trap_alert,
                'pattern_recognition_label'   => $ai->pattern_recognition_label,
                'socratic_follow_up_question' => $ai->socratic_follow_up_question,
                'investigation_interpretation'=> $ai->investigation_interpretation,
                'management_ladder'           => $ai->management_ladder,
                'guideline_justification'     => $ai->guideline_justification,
                'safety_netting_red_flags'    => $ai->safety_netting_red_flags,
                'exam_summary_box'            => $ai->exam_summary_box,
                'one_screen_memory_map'       => $ai->one_screen_memory_map,
            ];
        }

        return $result;
    }
}
