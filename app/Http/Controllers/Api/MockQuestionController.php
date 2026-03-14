<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MockQuestion;
use App\Models\MockQuestionOption;
use App\Models\MockQuestionAiTutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MockQuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = MockQuestion::query()
            ->withTrashed()
            ->with(['mock:id,title', 'mockExam:id,exam_no', 'options'])
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

        if ($mockId = $request->query('mock_id')) {
            $query->where('mock_id', $mockId);
        }
        if ($examId = $request->query('mock_exam_id')) {
            $query->where('mock_exam_id', $examId);
        }

        $questions = $query->paginate($perPage);

        $items = collect($questions->items())->map(fn (MockQuestion $q) => $this->formatQuestion($q))->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Mock questions retrieved successfully.',
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

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mock_id'            => ['required', 'integer', 'exists:mocks,id'],
            'mock_exam_id'       => ['required', 'integer', 'exists:mocks_exams,id'],
            'question_type'      => ['required', 'in:mcq,shortAnswer,descriptive,trueFalse,fillInBlanks'],
            'question'           => ['required', 'string'],
            'answer_description' => ['nullable', 'string'],
            'status'             => ['nullable', 'in:Active,Inactive'],
            'options'            => ['nullable', 'array'],
            'options.*.letter'   => ['required_with:options', 'string', 'max:1'],
            'options.*.text'     => ['required_with:options', 'string'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'correct_option'     => ['nullable', 'string', 'max:1'],
            'ai_tutor'           => ['nullable', 'array'],
            'ai_tutor.validation'                    => ['nullable', 'string'],
            'ai_tutor.key_clues_identified'          => ['nullable', 'string'],
            'ai_tutor.missing_or_misweighted_clues'  => ['nullable', 'string'],
            'ai_tutor.examiner_logic'                => ['nullable', 'string'],
            'ai_tutor.option_by_option_elimination'  => ['nullable', 'string'],
            'ai_tutor.examiner_trap_alert'           => ['nullable', 'string'],
            'ai_tutor.pattern_recognition_label'     => ['nullable', 'string'],
            'ai_tutor.socratic_follow_up_question'   => ['nullable', 'string'],
            'ai_tutor.investigation_interpretation'  => ['nullable', 'string'],
            'ai_tutor.management_ladder'             => ['nullable', 'string'],
            'ai_tutor.guideline_justification'       => ['nullable', 'string'],
            'ai_tutor.safety_netting_red_flags'      => ['nullable', 'string'],
            'ai_tutor.exam_summary_box'              => ['nullable', 'string'],
            'ai_tutor.one_screen_memory_map'         => ['nullable', 'string'],
        ], [
            'mock_id.required'       => 'Mock exam is required.',
            'mock_exam_id.required'  => 'Exam is required.',
            'question_type.required' => 'Question type is required.',
            'question.required'      => 'Question text is required.',
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
            $mockQuestion = MockQuestion::create([
                'mock_id'            => $request->input('mock_id'),
                'mock_exam_id'       => $request->input('mock_exam_id'),
                'question_type'      => $request->input('question_type'),
                'question'           => $request->input('question'),
                'correct_option'     => $request->input('correct_option'),
                'answer_description' => $request->input('answer_description'),
                'status'             => $request->input('status', 'Active'),
            ]);

            if ($request->has('options')) {
                foreach ($request->input('options') as $opt) {
                    MockQuestionOption::create([
                        'mocks_question_id' => $mockQuestion->id,
                        'option_letter'     => strtoupper($opt['letter']),
                        'option_text'       => $opt['text'],
                        'is_correct'        => (bool) ($opt['is_correct'] ?? false),
                    ]);
                }
            }

            $aiTutorData = $request->input('ai_tutor', []);
            MockQuestionAiTutor::create(array_merge(
                ['mocks_question_id' => $mockQuestion->id],
                $this->extractAiTutorFields($aiTutorData)
            ));

            DB::commit();

            $mockQuestion->load(['mock:id,title', 'mockExam:id,exam_no', 'options', 'aiTutor']);

            return response()->json([
                'success' => true,
                'message' => 'Mock question created successfully.',
                'data'    => ['question' => $this->formatQuestion($mockQuestion, true)],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Unable to create question. Please try again.',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $q = MockQuestion::withTrashed()
            ->with(['mock:id,title', 'mockExam:id,exam_no', 'options', 'aiTutor'])
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

    public function update(Request $request, int $id): JsonResponse
    {
        $q = MockQuestion::withTrashed()->find($id);
        if (!$q) {
            return response()->json(['success' => false, 'message' => 'Question not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'question_type'      => ['required', 'in:mcq,shortAnswer,descriptive,trueFalse,fillInBlanks'],
            'question'           => ['required', 'string'],
            'answer_description' => ['nullable', 'string'],
            'status'             => ['nullable', 'in:Active,Inactive'],
            'options'            => ['nullable', 'array'],
            'options.*.letter'   => ['required_with:options', 'string', 'max:1'],
            'options.*.text'     => ['required_with:options', 'string'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'correct_option'     => ['nullable', 'string', 'max:1'],
            'ai_tutor'           => ['nullable', 'array'],
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
            $q->question_type      = $request->input('question_type');
            $q->question           = $request->input('question');
            $q->correct_option     = $request->input('correct_option', $q->correct_option);
            $q->answer_description = $request->input('answer_description', $q->answer_description);
            if ($request->filled('status')) {
                $q->status = $request->input('status');
            }
            $q->save();

            if ($request->has('options')) {
                $q->options()->delete();
                foreach ($request->input('options') as $opt) {
                    MockQuestionOption::create([
                        'mocks_question_id' => $q->id,
                        'option_letter'     => strtoupper($opt['letter']),
                        'option_text'       => $opt['text'],
                        'is_correct'        => (bool) ($opt['is_correct'] ?? false),
                    ]);
                }
            }

            $aiTutorData = $request->input('ai_tutor', []);
            $aiTutor = $q->aiTutor;
            if ($aiTutor) {
                $aiTutor->update($this->extractAiTutorFields($aiTutorData));
            } else {
                MockQuestionAiTutor::create(array_merge(
                    ['mocks_question_id' => $q->id],
                    $this->extractAiTutorFields($aiTutorData)
                ));
            }

            DB::commit();

            $q->load(['mock:id,title', 'mockExam:id,exam_no', 'options', 'aiTutor']);

            return response()->json([
                'success' => true,
                'message' => 'Mock question updated successfully.',
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

    public function destroy(int $id): JsonResponse
    {
        $q = MockQuestion::find($id) ?? MockQuestion::onlyTrashed()->find($id);
        if (!$q) {
            return response()->json(['success' => false, 'message' => 'Question not found.'], 404);
        }
        if ($q->deleted_at) {
            return response()->json(['success' => true, 'message' => 'Question is already deleted.']);
        }
        $q->delete();
        return response()->json(['success' => true, 'message' => 'Question deleted successfully.']);
    }

    public function restore(int $id): JsonResponse
    {
        $q = MockQuestion::onlyTrashed()->find($id);
        if (!$q) {
            return response()->json(['success' => false, 'message' => 'Question not found or not deleted.'], 404);
        }
        $q->restore();
        $q->load(['mock:id,title', 'mockExam:id,exam_no', 'options']);
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

    private function formatQuestion(MockQuestion $q, bool $includeAiTutor = false): array
    {
        $correctOption = null;
        if ($q->question_type === 'mcq' && $q->relationLoaded('options')) {
            $correctOpt = $q->options->firstWhere('is_correct', true);
            $correctOption = $correctOpt
                ? $correctOpt->option_letter . ') ' . $correctOpt->option_text
                : ($q->correct_option ?? null);
        }

        $result = [
            'id'                 => $q->id,
            'mock_id'            => $q->mock_id,
            'mock_title'         => $q->mock?->title,
            'mock_exam_id'       => $q->mock_exam_id,
            'exam_no'            => $q->mockExam?->exam_no,
            'question_type'      => $q->question_type,
            'question'           => $q->question,
            'correct_option'     => $q->correct_option,
            'answer'             => $correctOption,
            'answer_description' => $q->answer_description,
            'status'             => $q->deleted_at ? 'Deleted' : $q->status,
            'is_deleted'         => (bool) $q->deleted_at,
            'options'            => $q->relationLoaded('options')
                ? $q->options->map(fn ($o) => [
                    'id'            => $o->id,
                    'option_letter' => $o->option_letter,
                    'option_text'   => $o->option_text,
                    'is_correct'    => $o->is_correct,
                ])->values()->toArray()
                : [],
            'created_at'         => $q->created_at?->toIso8601String(),
            'updated_at'         => $q->updated_at?->toIso8601String(),
        ];

        if ($includeAiTutor && $q->relationLoaded('aiTutor') && $q->aiTutor) {
            $ai = $q->aiTutor;
            $result['ai_tutor'] = [
                'validation'                   => $ai->validation,
                'key_clues_identified'         => $ai->key_clues_identified,
                'missing_or_misweighted_clues' => $ai->missing_or_misweighted_clues,
                'examiner_logic'               => $ai->examiner_logic,
                'option_by_option_elimination' => $ai->option_by_option_elimination,
                'examiner_trap_alert'          => $ai->examiner_trap_alert,
                'pattern_recognition_label'    => $ai->pattern_recognition_label,
                'socratic_follow_up_question'  => $ai->socratic_follow_up_question,
                'investigation_interpretation' => $ai->investigation_interpretation,
                'management_ladder'            => $ai->management_ladder,
                'guideline_justification'      => $ai->guideline_justification,
                'safety_netting_red_flags'     => $ai->safety_netting_red_flags,
                'exam_summary_box'             => $ai->exam_summary_box,
                'one_screen_memory_map'        => $ai->one_screen_memory_map,
            ];
        }

        return $result;
    }
}
