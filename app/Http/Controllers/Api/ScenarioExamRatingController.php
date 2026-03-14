<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScenarioExamRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScenarioExamRatingController extends Controller
{
    /**
     * List ratings for a scenario exam.
     * Query params: scenario_exam_id (required), page, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $examId = $request->query('scenario_exam_id');
        if (!$examId) {
            return response()->json([
                'success' => false,
                'message' => 'scenario_exam_id is required.',
            ], 422);
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $ratings = ScenarioExamRating::query()
            ->with('user:id,first_name,last_name,email,profile_image')
            ->where('scenario_exam_id', $examId)
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        $items = collect($ratings->items())->map(function (ScenarioExamRating $r) {
            $fullName = $r->user
                ? trim(($r->user->first_name ?? '') . ' ' . ($r->user->last_name ?? '')) ?: 'Unknown'
                : 'Unknown';
            $profileImageUrl = $r->user?->profile_image ? url($r->user->profile_image) : null;
            return [
                'id'               => $r->id,
                'scenario_exam_id' => $r->scenario_exam_id,
                'user_id'          => $r->user_id,
                'full_name'        => $fullName,
                'profile_image'    => $profileImageUrl,
                'stars'            => $r->stars,
                'comment'          => $r->comment,
                'created_at'       => $r->created_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Ratings retrieved successfully.',
            'data' => [
                'ratings' => $items,
                'pagination' => [
                    'current_page' => $ratings->currentPage(),
                    'last_page'    => $ratings->lastPage(),
                    'per_page'     => $ratings->perPage(),
                    'total'        => $ratings->total(),
                    'from'         => $ratings->firstItem(),
                    'to'           => $ratings->lastItem(),
                ],
            ],
        ]);
    }
}
