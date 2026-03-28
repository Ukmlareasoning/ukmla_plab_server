<?php

namespace App\Http\Support;

use App\Models\Mock;
use App\Models\MockExam;
use App\Models\MockPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaidMockAccess
{
    public static function ensurePracticeAccess(Request $request, int $mockId): ?JsonResponse
    {
        $mock = Mock::withoutTrashed()->find($mockId);
        if (!$mock || $mock->status !== 'Active') {
            return response()->json([
                'success' => false,
                'message' => 'Mock exam not found or not available.',
            ], 404);
        }

        if (!$mock->is_paid) {
            return null;
        }

        $user = $request->get('auth_user');
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Please sign in to access this paid mock exam.',
            ], 401);
        }

        $hasPurchase = MockPurchase::where('user_id', $user->id)
            ->where('mock_id', $mockId)
            ->exists();

        if (!$hasPurchase) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase this mock exam to access practice content.',
                'errors' => ['access' => ['Payment required.']],
            ], 403);
        }

        return null;
    }

    public static function resolveMockIdFromQuestionQuery(Request $request): ?int
    {
        if ($request->filled('mock_id')) {
            return (int) $request->query('mock_id');
        }
        if ($request->filled('mock_exam_id')) {
            $exam = MockExam::withoutTrashed()->find((int) $request->query('mock_exam_id'));

            return $exam ? (int) $exam->mock_id : null;
        }

        return null;
    }
}
