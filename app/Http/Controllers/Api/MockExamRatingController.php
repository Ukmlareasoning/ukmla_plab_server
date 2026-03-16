<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MockExamRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MockExamRatingController extends Controller
{
    /**
     * List ratings for a specific mock exam (admin/public).
     * GET /mock-exam-ratings?mock_exam_id=X
     */
    public function index(Request $request): JsonResponse
    {
        $examId = $request->query('mock_exam_id');
        if (!$examId) {
            return response()->json([
                'success' => false,
                'message' => 'mock_exam_id is required.',
            ], 422);
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $ratings = MockExamRating::query()
            ->with('user:id,first_name,last_name,email,profile_image')
            ->where('mocks_exam_id', $examId)
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        $items = collect($ratings->items())->map(function (MockExamRating $r) {
            $fullName = $r->user
                ? trim(($r->user->first_name ?? '') . ' ' . ($r->user->last_name ?? '')) ?: 'Unknown'
                : 'Unknown';
            $profileImageUrl = $r->user?->profile_image ? url($r->user->profile_image) : null;
            return $this->formatRating($r, $fullName, $profileImageUrl);
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

    /**
     * Submit a rating for a mock exam (one per user per exam).
     * POST /mock-exam-ratings
     * Auth: required
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'mock_exam_id' => ['required', 'integer', 'exists:mocks_exams,id'],
            'stars'        => ['required', 'integer', 'min:1', 'max:5'],
            'comment'      => ['nullable', 'string', 'max:2000'],
        ], [
            'mock_exam_id.required' => 'Mock exam is required.',
            'stars.required'        => 'Star rating is required.',
            'stars.min'             => 'Rating must be at least 1 star.',
            'stars.max'             => 'Rating cannot exceed 5 stars.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $examId = $request->input('mock_exam_id');

        $existing = MockExamRating::where('mocks_exam_id', $examId)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You have already rated this mock exam.',
            ], 409);
        }

        $rating = MockExamRating::create([
            'mocks_exam_id' => $examId,
            'user_id'       => $user->id,
            'stars'         => $request->input('stars'),
            'comment'       => $request->input('comment'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rating submitted successfully.',
            'data'    => ['rating' => $this->formatRating($rating)],
        ], 201);
    }

    /**
     * Get the current user's rating for a specific mock exam.
     * GET /mock-exam-ratings/my-rating?mock_exam_id=X
     * Auth: required
     */
    public function myRating(Request $request): JsonResponse
    {
        $user   = $request->user();
        $examId = $request->query('mock_exam_id');

        if (!$examId) {
            return response()->json([
                'success' => false,
                'message' => 'mock_exam_id is required.',
            ], 422);
        }

        $rating = MockExamRating::where('mocks_exam_id', $examId)
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'success' => true,
            'data'    => ['rating' => $rating ? $this->formatRating($rating) : null],
        ]);
    }

    private function formatRating(MockExamRating $r, ?string $fullName = null, ?string $profileImageUrl = null): array
    {
        return [
            'id'            => $r->id,
            'mocks_exam_id' => $r->mocks_exam_id,
            'user_id'       => $r->user_id,
            'full_name'     => $fullName,
            'profile_image' => $profileImageUrl,
            'stars'         => $r->stars,
            'comment'       => $r->comment,
            'created_at'    => $r->created_at?->toIso8601String(),
        ];
    }
}
