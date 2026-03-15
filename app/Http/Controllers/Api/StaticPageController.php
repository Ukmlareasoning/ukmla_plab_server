<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaticPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaticPageController extends Controller
{
    /**
     * List static pages with optional text and page filters.
     * Query params: text, page_type, page, per_page, apply_filters, active
     * When active=1 only non-deleted (active) pages are returned.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $activeOnly = filter_var($request->query('active', false), FILTER_VALIDATE_BOOLEAN);
        $query = $activeOnly ? StaticPage::query() : StaticPage::query()->withTrashed();

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $searchTerm = '%' . $text . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'like', $searchTerm)
                        ->orWhere('description', 'like', $searchTerm)
                        ->orWhere('page', 'like', $searchTerm)
                        ->orWhere('id', 'like', $searchTerm);
                });
            }

            if ($pageType = $request->query('page_type')) {
                $query->where('page', $pageType);
            }
        }

        $pages = $query->orderBy('id', 'asc')->paginate($perPage);

        $items = collect($pages->items())->map(function (StaticPage $page) {
            return [
                'id' => $page->id,
                'icon_key' => $page->icon_key,
                'page' => $page->page,
                'title' => $page->title,
                'description' => $page->description,
                'status' => $page->deleted_at ? 'Deleted' : 'Active',
                'is_deleted' => (bool) $page->deleted_at,
                'created_at' => $page->created_at?->toIso8601String(),
                'updated_at' => $page->updated_at?->toIso8601String(),
                'deleted_at' => $page->deleted_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Static pages retrieved successfully.',
            'data' => [
                'static_pages' => $items,
                'pagination' => [
                    'current_page' => $pages->currentPage(),
                    'last_page' => $pages->lastPage(),
                    'per_page' => $pages->perPage(),
                    'total' => $pages->total(),
                    'from' => $pages->firstItem(),
                    'to' => $pages->lastItem(),
                    'prev_page_url' => $pages->previousPageUrl(),
                    'next_page_url' => $pages->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Store a new static page.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'icon_key' => ['required', 'string', 'max:100'],
            'page' => ['required', 'string', 'max:191'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string'],
        ], [
            'icon_key.required' => 'Icon is required.',
            'icon_key.max' => 'Icon key must not exceed 100 characters.',
            'page.required' => 'Page is required.',
            'title.required' => 'Title is required.',
            'title.max' => 'Title must not exceed 191 characters.',
            'description.required' => 'Description is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $page = StaticPage::create([
            'icon_key' => $request->input('icon_key'),
            'page' => $request->input('page'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Static page created successfully.',
            'data' => [
                'static_page' => [
                    'id' => $page->id,
                    'icon_key' => $page->icon_key,
                    'page' => $page->page,
                    'title' => $page->title,
                    'description' => $page->description,
                    'status' => 'Active',
                    'is_deleted' => false,
                    'created_at' => $page->created_at?->toIso8601String(),
                    'updated_at' => $page->updated_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * Show a single static page.
     */
    public function show(int $id): JsonResponse
    {
        $page = StaticPage::withTrashed()->find($id);

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Static page not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Static page retrieved successfully.',
            'data' => [
                'static_page' => [
                    'id' => $page->id,
                    'icon_key' => $page->icon_key,
                    'page' => $page->page,
                    'title' => $page->title,
                    'description' => $page->description,
                    'status' => $page->deleted_at ? 'Deleted' : 'Active',
                    'is_deleted' => (bool) $page->deleted_at,
                    'created_at' => $page->created_at?->toIso8601String(),
                    'updated_at' => $page->updated_at?->toIso8601String(),
                    'deleted_at' => $page->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Update an existing static page.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $page = StaticPage::withTrashed()->find($id);

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Static page not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'icon_key' => ['required', 'string', 'max:100'],
            'page' => ['required', 'string', 'max:191'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string'],
        ], [
            'icon_key.required' => 'Icon is required.',
            'icon_key.max' => 'Icon key must not exceed 100 characters.',
            'page.required' => 'Page is required.',
            'title.required' => 'Title is required.',
            'title.max' => 'Title must not exceed 191 characters.',
            'description.required' => 'Description is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $page->icon_key = $request->input('icon_key');
        $page->page = $request->input('page');
        $page->title = $request->input('title');
        $page->description = $request->input('description');
        $page->save();

        return response()->json([
            'success' => true,
            'message' => 'Static page updated successfully.',
            'data' => [
                'static_page' => [
                    'id' => $page->id,
                    'icon_key' => $page->icon_key,
                    'page' => $page->page,
                    'title' => $page->title,
                    'description' => $page->description,
                    'status' => $page->deleted_at ? 'Deleted' : 'Active',
                    'is_deleted' => (bool) $page->deleted_at,
                    'created_at' => $page->created_at?->toIso8601String(),
                    'updated_at' => $page->updated_at?->toIso8601String(),
                    'deleted_at' => $page->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Soft delete a static page.
     */
    public function destroy(int $id): JsonResponse
    {
        $page = StaticPage::find($id);

        if (!$page) {
            $page = StaticPage::onlyTrashed()->find($id);
        }

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Static page not found.',
            ], 404);
        }

        if ($page->deleted_at) {
            return response()->json([
                'success' => true,
                'message' => 'Static page is already deleted.',
                'data' => [
                    'static_page' => [
                        'id' => $page->id,
                        'icon_key' => $page->icon_key,
                        'page' => $page->page,
                        'title' => $page->title,
                        'description' => $page->description,
                        'status' => 'Deleted',
                        'is_deleted' => true,
                        'created_at' => $page->created_at?->toIso8601String(),
                        'updated_at' => $page->updated_at?->toIso8601String(),
                        'deleted_at' => $page->deleted_at?->toIso8601String(),
                    ],
                ],
            ], 200);
        }

        $page->delete();

        return response()->json([
            'success' => true,
            'message' => 'Static page deleted successfully.',
            'data' => [
                'static_page' => [
                    'id' => $page->id,
                    'icon_key' => $page->icon_key,
                    'page' => $page->page,
                    'title' => $page->title,
                    'description' => $page->description,
                    'status' => 'Deleted',
                    'is_deleted' => true,
                    'created_at' => $page->created_at?->toIso8601String(),
                    'updated_at' => $page->updated_at?->toIso8601String(),
                    'deleted_at' => $page->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Restore a soft-deleted static page.
     */
    public function restore(int $id): JsonResponse
    {
        $page = StaticPage::onlyTrashed()->find($id);

        if (!$page) {
            return response()->json([
                'success' => false,
                'message' => 'Static page not found or not deleted.',
            ], 404);
        }

        $page->restore();

        return response()->json([
            'success' => true,
            'message' => 'Static page restored successfully.',
            'data' => [
                'static_page' => [
                    'id' => $page->id,
                    'icon_key' => $page->icon_key,
                    'page' => $page->page,
                    'title' => $page->title,
                    'description' => $page->description,
                    'status' => 'Active',
                    'is_deleted' => false,
                    'created_at' => $page->created_at?->toIso8601String(),
                    'updated_at' => $page->updated_at?->toIso8601String(),
                    'deleted_at' => $page->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }
}

