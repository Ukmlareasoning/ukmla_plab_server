<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QbCaseSimulation;
use App\Models\QbCaseSimulationRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QbCaseSimulationRatingController extends Controller
{
    /**
     * Admin: list all ratings for a given case simulation.
     * Query params: qb_case_simulation_id (required), page, per_page
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

        $averageRating = (float) (QbCaseSimulationRating::query()
            ->where('qb_case_simulation_id', $caseId)
            ->avg('stars') ?? 0);

        $ratings = QbCaseSimulationRating::query()
            ->with('user:id,first_name,last_name,profile_image')
            ->where('qb_case_simulation_id', $caseId)
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        $items = collect($ratings->items())->map(fn($r) => $this->formatRating($r))->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Ratings retrieved successfully.',
            'data' => [
                'ratings' => $items,
                'average_rating' => round($averageRating, 2),
                'pagination' => [
                    'current_page' => $ratings->currentPage(),
                    'last_page'    => $ratings->lastPage(),
                    'per_page'     => $ratings->perPage(),
                    'total'        => $ratings->total(),
                    'from'         => $ratings->firstItem(),
                    'to'           => $ratings->lastItem(),
                ],
            ],
        ], 200);
    }

    /**
     * User: submit or update a rating for a case simulation.
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
            'qb_case_simulation_id' => ['required', 'integer', 'exists:qb_case_simulations,id'],
            'stars'                  => ['required', 'integer', 'min:1', 'max:5'],
            'comment'                => ['nullable', 'string', 'max:2000'],
        ], [
            'qb_case_simulation_id.required' => 'Case simulation is required.',
            'qb_case_simulation_id.exists'   => 'Case simulation not found.',
            'stars.required'                  => 'Star rating is required.',
            'stars.min'                       => 'Rating must be at least 1 star.',
            'stars.max'                       => 'Rating must not exceed 5 stars.',
            'comment.max'                     => 'Comment must not exceed 2000 characters.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $caseId = $request->input('qb_case_simulation_id');

        $rating = QbCaseSimulationRating::withTrashed()
            ->where('qb_case_simulation_id', $caseId)
            ->where('user_id', $userId)
            ->first();

        if ($rating) {
            if ($rating->deleted_at) {
                $rating->restore();
            }
            $rating->stars   = $request->input('stars');
            $rating->comment = $request->input('comment');
            $rating->save();
        } else {
            $rating = QbCaseSimulationRating::create([
                'qb_case_simulation_id' => $caseId,
                'user_id'               => $userId,
                'stars'                 => $request->input('stars'),
                'comment'               => $request->input('comment'),
            ]);
        }

        $rating->load('user:id,first_name,last_name,profile_image');

        return response()->json([
            'success' => true,
            'message' => 'Rating submitted successfully.',
            'data'    => ['rating' => $this->formatRating($rating)],
        ], 200);
    }

    /**
     * User: retrieve my own rating for a case simulation.
     * Query param: qb_case_simulation_id (required)
     */
    public function myRating(Request $request): JsonResponse
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

        $rating = QbCaseSimulationRating::with('user:id,first_name,last_name,profile_image')
            ->where('qb_case_simulation_id', $caseId)
            ->where('user_id', $userId)
            ->first();

        return response()->json([
            'success' => true,
            'message' => $rating ? 'Rating retrieved.' : 'No rating found.',
            'data'    => ['rating' => $rating ? $this->formatRating($rating) : null],
        ], 200);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatRating(QbCaseSimulationRating $r): array
    {
        $fullName = $r->user
            ? trim(($r->user->first_name ?? '') . ' ' . ($r->user->last_name ?? '')) ?: 'Unknown'
            : 'Unknown';
        $profileImage = $r->user?->profile_image ? url($r->user->profile_image) : null;

        return [
            'id'                     => $r->id,
            'qb_case_simulation_id'  => $r->qb_case_simulation_id,
            'user_id'                => $r->user_id,
            'full_name'              => $fullName,
            'profile_image'          => $profileImage,
            'stars'                  => $r->stars,
            'comment'                => $r->comment,
            'created_at'             => $r->created_at?->toIso8601String(),
            'updated_at'             => $r->updated_at?->toIso8601String(),
        ];
    }
}
