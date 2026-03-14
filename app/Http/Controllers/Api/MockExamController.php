<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mock;
use App\Models\MockExam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MockExamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $mockId = $request->query('mock_id');
        if (!$mockId) {
            return response()->json([
                'success' => false,
                'message' => 'mock_id is required.',
            ], 422);
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 200) : 10;

        $query = MockExam::query()
            ->withTrashed()
            ->withCount(['questions as total_questions' => function ($q) {
                $q->withoutTrashed();
            }])
            ->where('mock_id', $mockId);

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($examNo = $request->query('exam_no')) {
                $query->where('exam_no', $examNo);
            }
            if ($status = $request->query('status')) {
                if ($status === 'Deleted') {
                    $query->onlyTrashed();
                } else {
                    $query->where('status', $status);
                }
            }
        }

        $exams = $query->orderBy('exam_no', 'asc')->paginate($perPage);

        $items = collect($exams->items())->map(fn (MockExam $e) => $this->formatExam($e))->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Mock exams retrieved successfully.',
            'data' => [
                'mock_exams' => $items,
                'pagination' => [
                    'current_page' => $exams->currentPage(),
                    'last_page'    => $exams->lastPage(),
                    'per_page'     => $exams->perPage(),
                    'total'        => $exams->total(),
                    'from'         => $exams->firstItem(),
                    'to'           => $exams->lastItem(),
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mock_id' => ['required', 'integer', 'exists:mocks,id'],
            'exam_no' => ['required', 'integer', 'min:1'],
            'status'  => ['nullable', 'in:Active,Inactive'],
        ], [
            'mock_id.required' => 'Mock exam is required.',
            'exam_no.required' => 'Exam number is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $exists = MockExam::withTrashed()
            ->where('mock_id', $request->input('mock_id'))
            ->where('exam_no', $request->input('exam_no'))
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => ['exam_no' => ['Exam number already exists for this mock.']],
            ], 422);
        }

        $exam = MockExam::create([
            'mock_id' => $request->input('mock_id'),
            'exam_no' => $request->input('exam_no'),
            'status'  => $request->input('status', 'Active'),
        ]);

        $exam->loadCount(['questions as total_questions' => fn ($q) => $q->withoutTrashed()]);

        return response()->json([
            'success' => true,
            'message' => 'Mock exam entry created successfully.',
            'data'    => ['mock_exam' => $this->formatExam($exam)],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $exam = MockExam::withTrashed()->find($id);
        if (!$exam) {
            return response()->json(['success' => false, 'message' => 'Mock exam not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['nullable', 'in:Active,Inactive'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if ($request->filled('status')) {
            $exam->status = $request->input('status');
        }
        $exam->save();

        $exam->loadCount(['questions as total_questions' => fn ($q) => $q->withoutTrashed()]);

        return response()->json([
            'success' => true,
            'message' => 'Mock exam updated successfully.',
            'data'    => ['mock_exam' => $this->formatExam($exam)],
        ]);
    }

    public function updateReleaseMode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mock_id'            => ['required', 'integer', 'exists:mocks,id'],
            'exams_release_mode' => ['required', 'in:all_at_once,one_after_another'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        Mock::where('id', $request->input('mock_id'))
            ->update(['exams_release_mode' => $request->input('exams_release_mode')]);

        return response()->json([
            'success' => true,
            'message' => 'Release mode updated successfully.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $exam = MockExam::find($id) ?? MockExam::onlyTrashed()->find($id);
        if (!$exam) {
            return response()->json(['success' => false, 'message' => 'Mock exam not found.'], 404);
        }
        if ($exam->deleted_at) {
            return response()->json(['success' => true, 'message' => 'Mock exam is already deleted.']);
        }
        $exam->delete();
        return response()->json(['success' => true, 'message' => 'Mock exam deleted successfully.']);
    }

    public function restore(int $id): JsonResponse
    {
        $exam = MockExam::onlyTrashed()->find($id);
        if (!$exam) {
            return response()->json(['success' => false, 'message' => 'Mock exam not found or not deleted.'], 404);
        }
        $exam->restore();
        $exam->loadCount(['questions as total_questions' => fn ($q) => $q->withoutTrashed()]);
        return response()->json([
            'success' => true,
            'message' => 'Mock exam restored successfully.',
            'data'    => ['mock_exam' => $this->formatExam($exam)],
        ]);
    }

    private function formatExam(MockExam $e): array
    {
        return [
            'id'              => $e->id,
            'mock_id'         => $e->mock_id,
            'exam_no'         => $e->exam_no,
            'total_questions' => $e->total_questions ?? 0,
            'status'          => $e->deleted_at ? 'Deleted' : $e->status,
            'is_deleted'      => (bool) $e->deleted_at,
            'created_at'      => $e->created_at?->toIso8601String(),
            'updated_at'      => $e->updated_at?->toIso8601String(),
        ];
    }
}
