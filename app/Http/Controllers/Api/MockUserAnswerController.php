<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MockExam;
use App\Models\MockQuestion;
use App\Models\MockUserAnswer;
use App\Models\Mock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MockUserAnswerController extends Controller
{
    /**
     * Submit or update a user's answer for a mock question.
     * POST /mock-user-answers
     * Auth: required
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'mock_id'          => ['required', 'integer', 'exists:mocks,id'],
            'mock_exam_id'     => ['required', 'integer', 'exists:mocks_exams,id'],
            'mock_question_id' => ['required', 'integer', 'exists:mocks_questions,id'],
            'user_answer'      => ['nullable', 'string', 'max:5000'],
        ], [
            'mock_id.required'          => 'Mock is required.',
            'mock_exam_id.required'     => 'Mock exam is required.',
            'mock_question_id.required' => 'Question is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $question = MockQuestion::with('options')->find($request->input('mock_question_id'));
        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found.',
            ], 404);
        }

        $isCorrect     = null;
        $correctAnswer = null;
        $userAnswer    = $request->input('user_answer');

        if (in_array($question->question_type, ['mcq', 'trueFalse'])) {
            $correctOption = $question->options->firstWhere('is_correct', true);
            $correctAnswer = $correctOption?->option_letter ?? $question->correct_option;
            if ($correctAnswer !== null && $userAnswer !== null) {
                $isCorrect = strtolower(trim((string) $userAnswer)) === strtolower(trim((string) $correctAnswer));
            }
        }

        MockUserAnswer::updateOrCreate(
            [
                'user_id'          => $user->id,
                'mock_exam_id'     => $request->input('mock_exam_id'),
                'mock_question_id' => $request->input('mock_question_id'),
            ],
            [
                'mock_id'      => $request->input('mock_id'),
                'user_answer'  => $userAnswer,
                'is_correct'   => $isCorrect,
                'attempted_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Answer saved.',
            'data'    => [
                'is_correct'     => $isCorrect,
                'correct_answer' => $correctAnswer,
            ],
        ]);
    }

    /**
     * List answers for the current user.
     * GET /mock-user-answers?mock_exam_id=X
     * GET /mock-user-answers?mock_id=X
     * Auth: required
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = MockUserAnswer::where('user_id', $user->id)
            ->with([
                'question.options',
                'question.aiTutor',
                'mockExam:id,exam_no',
            ]);

        if ($examId = $request->query('mock_exam_id')) {
            $query->where('mock_exam_id', $examId);
        }
        if ($mockId = $request->query('mock_id')) {
            $query->where('mock_id', $mockId);
        }

        $answers = $query
            ->orderBy('mock_exam_id')
            ->orderBy('mock_question_id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Answers retrieved.',
            'data'    => [
                'answers' => $answers->map(fn ($a) => $this->formatAnswer($a))->toArray(),
            ],
        ]);
    }

    /**
     * Get progress summary for the current user.
     * GET /mock-user-progress?mock_id=X  → single mock breakdown
     * GET /mock-user-progress            → all mocks the user has attempted
     * Auth: required
     */
    public function progress(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($mockId = $request->query('mock_id')) {
            return $this->singleMockProgress($user->id, (int) $mockId);
        }

        return $this->allMocksProgress($user->id);
    }

    private function singleMockProgress(int $userId, int $mockId): JsonResponse
    {
        $exams = MockExam::where('mock_id', $mockId)
            ->where('status', 'Active')
            ->withCount(['questions as total_questions' => fn ($q) => $q->where('status', 'Active')])
            ->orderBy('exam_no')
            ->get();

        $answers = MockUserAnswer::where('user_id', $userId)
            ->where('mock_id', $mockId)
            ->get();

        $totalQuestions    = $exams->sum('total_questions');
        $answeredQuestions = $answers->count();

        $totalExams     = $exams->count();
        $attemptedExams = $answers->pluck('mock_exam_id')->unique()->count();
        $progressPct    = $totalExams > 0 ? round(($attemptedExams / $totalExams) * 100) : 0;

        $answersByExam = $answers->groupBy('mock_exam_id');

        $examWise = $exams->map(function ($exam) use ($answersByExam) {
            $examAnswers = $answersByExam->get($exam->id, collect());
            $answered    = $examAnswers->count();
            $total       = $exam->total_questions;
            $pct         = $total > 0 ? round(($answered / $total) * 100) : 0;
            $correct     = $examAnswers->filter(fn ($a) => $a->is_correct === true || $a->is_correct === null)->count();
            $obtainedPct = $total > 0 ? round(($correct / $total) * 100) : 0;

            return [
                'mock_exam_id'        => $exam->id,
                'exam_no'             => $exam->exam_no,
                'total_questions'     => $total,
                'answered_questions'  => $answered,
                'exam_percentage'     => $pct,
                'obtained_percentage' => $obtainedPct,
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'data'    => [
                'mock_id'             => $mockId,
                'total_questions'     => $totalQuestions,
                'answered_questions'  => $answeredQuestions,
                'total_exams'         => $totalExams,
                'attempted_exams'     => $attemptedExams,
                'progress_percentage' => $progressPct,
                'exam_wise'           => $examWise,
            ],
        ]);
    }

    private function allMocksProgress(int $userId): JsonResponse
    {
        $attemptedIds = MockUserAnswer::where('user_id', $userId)
            ->distinct()
            ->pluck('mock_id');

        $mocks = Mock::whereIn('id', $attemptedIds)
            ->where('status', 'Active')
            ->with(['examType:id,name', 'difficultyLevel:id,name'])
            ->get();

        $result = $mocks->map(function ($mock) use ($userId) {
            $userAnswersQuery = MockUserAnswer::where('user_id', $userId)
                ->where('mock_id', $mock->id);

            $answeredQuestions = $userAnswersQuery->count();

            $totalQuestions = MockQuestion::where('mock_id', $mock->id)
                ->where('status', 'Active')
                ->count();

            // How many answers are correct (or open‑ended, where is_correct is null)
            $correctQuestions = (clone $userAnswersQuery)
                ->where(function ($q) {
                    $q->where('is_correct', true)
                        ->orWhereNull('is_correct');
                })
                ->count();

            $obtainedPct = $totalQuestions > 0
                ? round(($correctQuestions / $totalQuestions) * 100)
                : 0;

            $totalExams = MockExam::where('mock_id', $mock->id)
                ->where('status', 'Active')
                ->count();

            $attemptedExams = MockUserAnswer::where('user_id', $userId)
                ->where('mock_id', $mock->id)
                ->distinct()
                ->pluck('mock_exam_id')
                ->count();

            $progressPct = $totalExams > 0 ? round(($attemptedExams / $totalExams) * 100) : 0;

            return [
                'mock_id'               => $mock->id,
                'title'                 => $mock->title,
                'description'           => $mock->description,
                'icon_key'              => $mock->icon_key,
                'exam_type_name'        => $mock->examType?->name,
                'difficulty_level_name' => $mock->difficultyLevel?->name,
                'is_paid'               => (bool) $mock->is_paid,
                'price_eur'             => $mock->price_eur,
                'duration'              => $mock->duration,
                'duration_type'         => $mock->duration_type,
                'total_questions'       => $totalQuestions,
                'answered_questions'    => $answeredQuestions,
                'total_exams'           => $totalExams,
                'attempted_exams'       => $attemptedExams,
                'progress_percentage'   => $progressPct,
                // Overall obtained % based on questions (correct / total)
                'obtained_percentage'   => $obtainedPct,
                'is_completed'          => $progressPct >= 100,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'mocks' => $result->toArray(),
            ],
        ]);
    }

    private function formatAnswer(MockUserAnswer $a): array
    {
        $q      = $a->question;
        $examNo = $a->mockExam?->exam_no ?? null;

        $options = [];
        if ($q && $q->options) {
            $options = $q->options->map(fn ($o) => [
                'letter'     => $o->option_letter,
                'text'       => $o->option_text,
                'is_correct' => (bool) $o->is_correct,
            ])->toArray();
        }

        $correctAnswerText = null;
        if ($q && $q->options) {
            $correctOpt = $q->options->firstWhere('is_correct', true);
            if ($correctOpt) {
                $correctAnswerText = $correctOpt->option_letter . ') ' . $correctOpt->option_text;
            }
        }
        if (!$correctAnswerText && $q) {
            $correctAnswerText = $q->answer_description;
        }

        return [
            'id'               => $a->id,
            'mock_exam_id'     => $a->mock_exam_id,
            'exam_no'          => $examNo,
            'mock_question_id' => $a->mock_question_id,
            'question_type'    => $q?->question_type,
            'question'         => $q?->question,
            'options'          => $options,
            'correct_answer_text' => $correctAnswerText,
            'answer_description'  => $q?->answer_description,
            'user_answer'      => $a->user_answer,
            'is_correct'       => $a->is_correct,
            'attempted_at'     => $a->attempted_at?->toIso8601String(),
        ];
    }
}
