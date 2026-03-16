<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScenarioExam;
use App\Models\ScenarioQuestion;
use App\Models\ScenarioUserAnswer;
use App\Models\Scenario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScenarioUserAnswerController extends Controller
{
    /**
     * Submit or update a user's answer for a scenario question.
     * POST /scenario-user-answers
     * Auth: required
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'scenario_id'          => ['required', 'integer', 'exists:scenarios,id'],
            'scenario_exam_id'     => ['required', 'integer', 'exists:scenario_exams,id'],
            'scenario_question_id' => ['required', 'integer', 'exists:scenario_questions,id'],
            'user_answer'          => ['nullable', 'string', 'max:5000'],
        ], [
            'scenario_id.required'          => 'Scenario is required.',
            'scenario_exam_id.required'     => 'Scenario exam is required.',
            'scenario_question_id.required' => 'Question is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $question = ScenarioQuestion::with('options')->find($request->input('scenario_question_id'));
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

        ScenarioUserAnswer::updateOrCreate(
            [
                'user_id'              => $user->id,
                'scenario_exam_id'     => $request->input('scenario_exam_id'),
                'scenario_question_id' => $request->input('scenario_question_id'),
            ],
            [
                'scenario_id'  => $request->input('scenario_id'),
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
     * GET /scenario-user-answers?scenario_exam_id=X
     * GET /scenario-user-answers?scenario_id=X
     * Auth: required
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ScenarioUserAnswer::where('user_id', $user->id)
            ->with([
                'question.options',
                'question.aiTutor',
                'scenarioExam:id,exam_no',
            ]);

        if ($examId = $request->query('scenario_exam_id')) {
            $query->where('scenario_exam_id', $examId);
        }
        if ($scenarioId = $request->query('scenario_id')) {
            $query->where('scenario_id', $scenarioId);
        }

        $answers = $query
            ->orderBy('scenario_exam_id')
            ->orderBy('scenario_question_id')
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
     * GET /scenario-user-progress?scenario_id=X  → single scenario breakdown
     * GET /scenario-user-progress               → all scenarios the user has attempted
     * Auth: required
     */
    public function progress(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($scenarioId = $request->query('scenario_id')) {
            return $this->singleScenarioProgress($user->id, (int) $scenarioId);
        }

        return $this->allScenariosProgress($user->id);
    }

    private function singleScenarioProgress(int $userId, int $scenarioId): JsonResponse
    {
        $exams = ScenarioExam::where('scenario_id', $scenarioId)
            ->where('status', 'Active')
            ->withCount(['questions as total_questions' => fn ($q) => $q->where('status', 'Active')])
            ->orderBy('exam_no')
            ->get();

        $answers = ScenarioUserAnswer::where('user_id', $userId)
            ->where('scenario_id', $scenarioId)
            ->get();

        // Question-based totals (kept for compatibility / detailed stats)
        $totalQuestions    = $exams->sum('total_questions');
        $answeredQuestions = $answers->count();

        // Exam-based progress: how many exams have been practised at least once
        $totalExams     = $exams->count();
        $attemptedExams = $answers->pluck('scenario_exam_id')->unique()->count();
        $progressPct    = $totalExams > 0 ? round(($attemptedExams / $totalExams) * 100) : 0;

        $answersByExam = $answers->groupBy('scenario_exam_id');

        $examWise = $exams->map(function ($exam) use ($answersByExam) {
            $examAnswers = $answersByExam->get($exam->id, collect());
            $answered    = $examAnswers->count();
            $total       = $exam->total_questions;
            // Completion % (questions attempted / total) — for internal use if needed
            $pct = $total > 0 ? round(($answered / $total) * 100) : 0;
            // Obtained marks: correct (and open-ended) out of total questions
            $correct = $examAnswers->filter(fn ($a) => $a->is_correct === true || $a->is_correct === null)->count();
            $obtainedPct = $total > 0 ? round(($correct / $total) * 100) : 0;

            return [
                'scenario_exam_id'    => $exam->id,
                'exam_no'             => $exam->exam_no,
                'total_questions'     => $total,
                'answered_questions'   => $answered,
                'exam_percentage'     => $pct,
                'obtained_percentage' => $obtainedPct,
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'data'    => [
                'scenario_id'           => $scenarioId,
                'total_questions'       => $totalQuestions,
                'answered_questions'    => $answeredQuestions,
                'total_exams'           => $totalExams,
                'attempted_exams'       => $attemptedExams,
                // This percentage is exam-based: how many exams practised out of total exams
                'progress_percentage'   => $progressPct,
                'exam_wise'             => $examWise,
            ],
        ]);
    }

    private function allScenariosProgress(int $userId): JsonResponse
    {
        $attemptedIds = ScenarioUserAnswer::where('user_id', $userId)
            ->distinct()
            ->pluck('scenario_id');

        $scenarios = Scenario::whereIn('id', $attemptedIds)
            ->where('status', 'Active')
            ->with(['examType:id,name', 'difficultyLevel:id,name'])
            ->withCount([
                'questions as total_questions' => fn ($q) => $q->where('status', 'Active'),
            ])
            ->get();

        $result = $scenarios->map(function ($scenario) use ($userId) {
            // Question-based stats
            $userAnswersQuery = ScenarioUserAnswer::where('user_id', $userId)
                ->where('scenario_id', $scenario->id);

            $answeredQuestions = $userAnswersQuery->count();
            $totalQuestions    = $scenario->total_questions ?? 0;

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

            // Exam-based stats: how many scenario exams practised at least once
            $totalExams = ScenarioExam::where('scenario_id', $scenario->id)
                ->where('status', 'Active')
                ->count();

            $attemptedExams = ScenarioUserAnswer::where('user_id', $userId)
                ->where('scenario_id', $scenario->id)
                ->distinct()
                ->pluck('scenario_exam_id')
                ->count();

            $progressPct = $totalExams > 0 ? round(($attemptedExams / $totalExams) * 100) : 0;

            return [
                'scenario_id'           => $scenario->id,
                'title'                 => $scenario->title,
                'description'           => $scenario->description,
                'icon_key'              => $scenario->icon_key,
                'exam_type_name'        => $scenario->examType?->name,
                'difficulty_level_name' => $scenario->difficultyLevel?->name,
                'duration'              => $scenario->duration,
                'duration_type'         => $scenario->duration_type,
                'total_questions'       => $totalQuestions,
                'answered_questions'    => $answeredQuestions,
                'total_exams'           => $totalExams,
                'attempted_exams'       => $attemptedExams,
                // This percentage is exam-based: how many exams practised out of total exams
                'progress_percentage'   => $progressPct,
                // Overall obtained % based on questions (correct / total)
                'obtained_percentage'   => $obtainedPct,
                'is_completed'          => $progressPct >= 100,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'scenarios' => $result->toArray(),
            ],
        ]);
    }

    private function formatAnswer(ScenarioUserAnswer $a): array
    {
        $q      = $a->question;
        $examNo = $a->scenarioExam?->exam_no ?? null;

        $options = [];
        if ($q && $q->options) {
            $options = $q->options->map(fn ($o) => [
                'letter'     => $o->option_letter,
                'text'       => $o->option_text,
                'is_correct' => (bool) $o->is_correct,
            ])->toArray();
        }

        // Derive the correct answer text for display
        $correctAnswerText = null;
        if ($q && $q->options) {
            $correctOpt = $q->options->firstWhere('is_correct', true);
            if ($correctOpt) {
                $correctAnswerText = $correctOpt->option_letter . ') ' . $correctOpt->option_text;
            }
        }
        // For open-ended, use answer_description as the correct answer display
        if (!$correctAnswerText && $q) {
            $correctAnswerText = $q->answer_description;
        }

        return [
            'id'                   => $a->id,
            'scenario_exam_id'     => $a->scenario_exam_id,
            'exam_no'              => $examNo,
            'scenario_question_id' => $a->scenario_question_id,
            'question_type'        => $q?->question_type,
            'question'             => $q?->question,
            'options'              => $options,
            'correct_answer_text'  => $correctAnswerText,
            'answer_description'   => $q?->answer_description,
            'user_answer'          => $a->user_answer,
            'is_correct'           => $a->is_correct,
            'attempted_at'         => $a->attempted_at?->toIso8601String(),
        ];
    }
}
