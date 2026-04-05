<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QbCaseSimulation;
use App\Models\QbCaseSimulationQuestion;
use App\Models\QbCaseSimulationUserAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class QbCaseSimulationUserAnswerController extends Controller
{
    /**
     * Store or update the user's answer for one question (MCQ).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated.',
            ], 401);
        }
        $userId = $user->id;

        $validator = Validator::make($request->all(), [
            'qb_case_simulation_question_id' => ['required', 'integer', 'exists:qb_case_simulation_questions,id'],
            'selected_option_letter'           => ['required', 'string', 'max:2'],
        ], [
            'qb_case_simulation_question_id.required' => 'Question is required.',
            'qb_case_simulation_question_id.exists'   => 'Question not found.',
            'selected_option_letter.required'         => 'Please select an option.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $question = QbCaseSimulationQuestion::query()
            ->where('id', $request->input('qb_case_simulation_question_id'))
            ->where('status', 'Active')
            ->with(['options'])
            ->first();

        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'Question not found or inactive.',
            ], 404);
        }

        $case = QbCaseSimulation::query()
            ->whereKey($question->qb_case_simulation_id)
            ->where('status', 'Active')
            ->first();

        if (!$case) {
            return response()->json([
                'success' => false,
                'message' => 'Case simulation not found.',
            ], 404);
        }

        $letter = strtoupper(trim($request->input('selected_option_letter')));
        $correctOption = $question->options->firstWhere('is_correct', true);
        $correctLetter = $correctOption ? strtoupper($correctOption->option_letter) : '';
        $isCorrect = $correctLetter !== '' && $letter === $correctLetter;

        $answer = QbCaseSimulationUserAnswer::query()->updateOrCreate(
            [
                'user_id'                       => $userId,
                'qb_case_simulation_question_id' => $question->id,
            ],
            [
                'qb_case_simulation_id' => $question->qb_case_simulation_id,
                'selected_option_letter' => $letter,
                'is_correct'            => $isCorrect,
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Answer saved.',
            'data'    => [
                'qb_case_simulation_user_answer' => [
                    'id'                              => $answer->id,
                    'qb_case_simulation_id'           => $answer->qb_case_simulation_id,
                    'qb_case_simulation_question_id'  => $answer->qb_case_simulation_question_id,
                    'selected_option_letter'          => $answer->selected_option_letter,
                    'is_correct'                      => $answer->is_correct,
                    'correct_answer_letter'           => $correctLetter,
                ],
            ],
        ], 200);
    }

    /**
     * List current user's answers for a case (for practice resume / history details).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated.',
            ], 401);
        }
        $userId = $user->id;
        $caseId = $request->query('qb_case_simulation_id');

        if (!$caseId) {
            return response()->json([
                'success' => false,
                'message' => 'qb_case_simulation_id is required.',
            ], 422);
        }

        $case = QbCaseSimulation::query()->whereKey($caseId)->where('status', 'Active')->first();
        if (!$case) {
            return response()->json([
                'success' => false,
                'message' => 'Case simulation not found.',
            ], 404);
        }

        $rows = QbCaseSimulationUserAnswer::query()
            ->where('user_id', $userId)
            ->where('qb_case_simulation_id', $caseId)
            ->orderBy('id')
            ->get();

        $items = $rows->map(fn ($a) => [
            'id'                             => $a->id,
            'qb_case_simulation_question_id' => $a->qb_case_simulation_question_id,
            'selected_option_letter'         => $a->selected_option_letter,
            'is_correct'                     => $a->is_correct,
        ])->values()->toArray();

        $summary = $this->buildCaseProgressSummary($userId, (int) $caseId);

        return response()->json([
            'success' => true,
            'message' => 'Answers retrieved.',
            'data'    => [
                'qb_case_simulation_user_answers' => $items,
                'summary'                         => $summary,
            ],
        ], 200);
    }

    /**
     * Completed case simulations (100% questions attempted) for the current user.
     */
    public function completedHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated.',
            ], 401);
        }
        $userId = $user->id;

        $perPage = (int) $request->query('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $countRow = DB::selectOne(
            'SELECT COUNT(*) AS c FROM (
                SELECT c.id
                FROM qb_case_simulations c
                INNER JOIN (
                    SELECT qb_case_simulation_id, COUNT(*) AS total
                    FROM qb_case_simulation_questions
                    WHERE deleted_at IS NULL AND status = ?
                    GROUP BY qb_case_simulation_id
                ) tq ON tq.qb_case_simulation_id = c.id
                INNER JOIN (
                    SELECT ua.qb_case_simulation_id,
                        COUNT(DISTINCT ua.qb_case_simulation_question_id) AS answered,
                        SUM(CASE WHEN ua.is_correct THEN 1 ELSE 0 END) AS correct
                    FROM qb_case_simulation_user_answers ua
                    INNER JOIN qb_case_simulation_questions q ON q.id = ua.qb_case_simulation_question_id
                        AND q.deleted_at IS NULL AND q.status = ?
                    WHERE ua.user_id = ?
                    GROUP BY ua.qb_case_simulation_id
                ) ta ON ta.qb_case_simulation_id = c.id AND ta.answered = tq.total
                WHERE c.status = ? AND c.deleted_at IS NULL AND tq.total > 0
            ) x',
            ['Active', 'Active', $userId, 'Active'],
        );
        $total = (int) ($countRow->c ?? 0);

        $rows = DB::select(
            'SELECT c.id, c.title, c.icon_key, tq.total AS total_questions, ta.answered, ta.correct
                FROM qb_case_simulations c
                INNER JOIN (
                    SELECT qb_case_simulation_id, COUNT(*) AS total
                    FROM qb_case_simulation_questions
                    WHERE deleted_at IS NULL AND status = ?
                    GROUP BY qb_case_simulation_id
                ) tq ON tq.qb_case_simulation_id = c.id
                INNER JOIN (
                    SELECT ua.qb_case_simulation_id,
                        COUNT(DISTINCT ua.qb_case_simulation_question_id) AS answered,
                        SUM(CASE WHEN ua.is_correct THEN 1 ELSE 0 END) AS correct
                    FROM qb_case_simulation_user_answers ua
                    INNER JOIN qb_case_simulation_questions q ON q.id = ua.qb_case_simulation_question_id
                        AND q.deleted_at IS NULL AND q.status = ?
                    WHERE ua.user_id = ?
                    GROUP BY ua.qb_case_simulation_id
                ) ta ON ta.qb_case_simulation_id = c.id AND ta.answered = tq.total
                WHERE c.status = ? AND c.deleted_at IS NULL AND tq.total > 0
                ORDER BY c.id DESC
                LIMIT ? OFFSET ?',
            ['Active', 'Active', $userId, 'Active', $perPage, $offset],
        );

        $items = collect($rows)->map(function ($row) {
            $totalQ = (int) $row->total_questions;
            $correct = (int) $row->correct;
            $accuracy = $totalQ > 0 ? (int) round(($correct / $totalQ) * 100) : 0;

            return [
                'case_id'          => (int) $row->id,
                'title'            => $row->title,
                'icon_key'         => $row->icon_key,
                'total_questions'  => $totalQ,
                'correct_count'    => $correct,
                'score_percentage' => $accuracy,
            ];
        })->values()->toArray();

        $lastPage = max(1, (int) ceil($total / $perPage));

        return response()->json([
            'success' => true,
            'message' => 'History retrieved.',
            'data'    => [
                'cases' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'last_page'    => $lastPage,
                    'per_page'     => $perPage,
                    'total'        => $total,
                ],
            ],
        ], 200);
    }

    /**
     * Per-case progress for dashboard (cases the user has attempted at least once).
     * GET /qb-case-user-progress
     * Mirrors shape of scenario-user-progress / mock-user-progress lists for the user app.
     */
    public function progress(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated.',
            ], 401);
        }

        $attemptedCaseIds = QbCaseSimulationUserAnswer::query()
            ->where('user_id', $user->id)
            ->distinct()
            ->pluck('qb_case_simulation_id');

        if ($attemptedCaseIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Progress retrieved.',
                'data'    => ['cases' => []],
            ], 200);
        }

        $cases = QbCaseSimulation::query()
            ->whereIn('id', $attemptedCaseIds)
            ->where('status', 'Active')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        $items = $cases->map(function (QbCaseSimulation $case) use ($user) {
            $summary = self::buildCaseProgressSummary($user->id, $case->id);
            $total = $summary['total_questions'];
            $correct = $summary['correct_count'];
            $obtainedPct = $total > 0 ? (int) round(($correct / $total) * 100) : 0;

            return [
                'qb_case_simulation_id' => $case->id,
                'case_simulation_id'    => $case->id,
                'title'                 => $case->title,
                'icon_key'              => $case->icon_key,
                'total_questions'       => $total,
                'answered_questions'    => $summary['answered_count'],
                'progress_percentage'   => $summary['attempt_percent'],
                'obtained_percentage'   => $obtainedPct,
                'is_completed'          => $summary['is_complete'],
            ];
        })->values()->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Progress retrieved.',
            'data'    => ['cases' => $items],
        ], 200);
    }

    /** @return array{total_questions:int,answered_count:int,correct_count:int,attempt_percent:int,accuracy_percent:int,is_complete:bool,status:string} */
    public static function buildCaseProgressSummary(int $userId, int $caseId): array
    {
        $totalQuestions = QbCaseSimulationQuestion::query()
            ->where('qb_case_simulation_id', $caseId)
            ->where('status', 'Active')
            ->count();

        $stats = QbCaseSimulationUserAnswer::query()
            ->where('user_id', $userId)
            ->where('qb_case_simulation_id', $caseId)
            ->selectRaw('COUNT(*) as answered, SUM(CASE WHEN is_correct THEN 1 ELSE 0 END) as correct')
            ->first();

        $answered = (int) ($stats->answered ?? 0);
        $correct = (int) ($stats->correct ?? 0);

        $attemptPercent = $totalQuestions > 0
            ? (int) round(($answered / $totalQuestions) * 100)
            : 0;

        $accuracyPercent = $answered > 0
            ? (int) round(($correct / $answered) * 100)
            : 0;

        $isComplete = $totalQuestions > 0 && $answered >= $totalQuestions;

        $status = 'not_started';
        if ($answered > 0 && !$isComplete) {
            $status = 'ongoing';
        } elseif ($isComplete) {
            $status = 'completed';
        }

        return [
            'total_questions'   => $totalQuestions,
            'answered_count'    => $answered,
            'correct_count'     => $correct,
            'attempt_percent'   => $attemptPercent,
            'accuracy_percent'  => $accuracyPercent,
            'is_complete'       => $isComplete,
            'status'            => $status,
        ];
    }
}
