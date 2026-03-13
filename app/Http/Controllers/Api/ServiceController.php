<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    /**
     * List services with optional text and badge filters.
     * Query params: text, badge_type, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = Service::query()->withTrashed();

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $searchTerm = '%' . $text . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('title', 'like', $searchTerm)
                        ->orWhere('description', 'like', $searchTerm)
                        ->orWhere('badge', 'like', $searchTerm)
                        ->orWhere('id', 'like', $searchTerm);
                });
            }

            if ($badgeType = $request->query('badge_type')) {
                $query->where('badge', $badgeType);
            }
        }

        $services = $query->orderBy('id', 'asc')->paginate($perPage);

        $items = collect($services->items())->map(function (Service $service) {
            return [
                'id' => $service->id,
                'icon_key' => $service->icon_key,
                'badge' => $service->badge,
                'title' => $service->title,
                'description' => $service->description,
                'status' => $service->deleted_at ? 'Deleted' : $service->status,
                'is_deleted' => (bool) $service->deleted_at,
                'created_at' => $service->created_at?->toIso8601String(),
                'updated_at' => $service->updated_at?->toIso8601String(),
                'deleted_at' => $service->deleted_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Services retrieved successfully.',
            'data' => [
                'services' => $items,
                'pagination' => [
                    'current_page' => $services->currentPage(),
                    'last_page' => $services->lastPage(),
                    'per_page' => $services->perPage(),
                    'total' => $services->total(),
                    'from' => $services->firstItem(),
                    'to' => $services->lastItem(),
                    'prev_page_url' => $services->previousPageUrl(),
                    'next_page_url' => $services->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Store a new service.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'icon_key' => ['required', 'string', 'max:100'],
            'badge' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string'],
        ], [
            'icon_key.required' => 'Icon is required.',
            'icon_key.max' => 'Icon key must not exceed 100 characters.',
            'badge.required' => 'Badge is required.',
            'badge.max' => 'Badge must not exceed 50 characters.',
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

        $service = Service::create([
            'icon_key' => $request->input('icon_key'),
            'badge' => $request->input('badge'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'status' => 'Active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Service created successfully.',
            'data' => [
                'service' => [
                    'id' => $service->id,
                    'icon_key' => $service->icon_key,
                    'badge' => $service->badge,
                    'title' => $service->title,
                    'description' => $service->description,
                    'status' => $service->status,
                    'is_deleted' => false,
                    'created_at' => $service->created_at?->toIso8601String(),
                    'updated_at' => $service->updated_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * Update an existing service.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $service = Service::withTrashed()->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'icon_key' => ['required', 'string', 'max:100'],
            'badge' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string'],
            'status' => ['nullable', 'in:Active,Inactive'],
        ], [
            'icon_key.required' => 'Icon is required.',
            'icon_key.max' => 'Icon key must not exceed 100 characters.',
            'badge.required' => 'Badge is required.',
            'badge.max' => 'Badge must not exceed 50 characters.',
            'title.required' => 'Title is required.',
            'title.max' => 'Title must not exceed 191 characters.',
            'description.required' => 'Description is required.',
            'status.in' => 'Status must be either Active or Inactive.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $service->icon_key = $request->input('icon_key');
        $service->badge = $request->input('badge');
        $service->title = $request->input('title');
        $service->description = $request->input('description');

        if ($request->filled('status')) {
            $service->status = $request->input('status');
        }

        $service->save();

        return response()->json([
            'success' => true,
            'message' => 'Service updated successfully.',
            'data' => [
                'service' => [
                    'id' => $service->id,
                    'icon_key' => $service->icon_key,
                    'badge' => $service->badge,
                    'title' => $service->title,
                    'description' => $service->description,
                    'status' => $service->deleted_at ? 'Deleted' : $service->status,
                    'is_deleted' => (bool) $service->deleted_at,
                    'created_at' => $service->created_at?->toIso8601String(),
                    'updated_at' => $service->updated_at?->toIso8601String(),
                    'deleted_at' => $service->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Soft delete a service.
     */
    public function destroy(int $id): JsonResponse
    {
        $service = Service::find($id);

        if (!$service) {
            $service = Service::onlyTrashed()->find($id);
        }

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found.',
            ], 404);
        }

        if ($service->deleted_at) {
            return response()->json([
                'success' => true,
                'message' => 'Service is already deleted.',
                'data' => [
                    'service' => [
                        'id' => $service->id,
                        'icon_key' => $service->icon_key,
                        'badge' => $service->badge,
                        'title' => $service->title,
                        'description' => $service->description,
                        'status' => 'Deleted',
                        'is_deleted' => true,
                        'created_at' => $service->created_at?->toIso8601String(),
                        'updated_at' => $service->updated_at?->toIso8601String(),
                        'deleted_at' => $service->deleted_at?->toIso8601String(),
                    ],
                ],
            ], 200);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service deleted successfully.',
            'data' => [
                'service' => [
                    'id' => $service->id,
                    'icon_key' => $service->icon_key,
                    'badge' => $service->badge,
                    'title' => $service->title,
                    'description' => $service->description,
                    'status' => 'Deleted',
                    'is_deleted' => true,
                    'created_at' => $service->created_at?->toIso8601String(),
                    'updated_at' => $service->updated_at?->toIso8601String(),
                    'deleted_at' => $service->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Restore a soft-deleted service.
     */
    public function restore(int $id): JsonResponse
    {
        $service = Service::onlyTrashed()->find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Service not found or not deleted.',
            ], 404);
        }

        $service->restore();

        return response()->json([
            'success' => true,
            'message' => 'Service restored successfully.',
            'data' => [
                'service' => [
                    'id' => $service->id,
                    'icon_key' => $service->icon_key,
                    'badge' => $service->badge,
                    'title' => $service->title,
                    'description' => $service->description,
                    'status' => $service->status,
                    'is_deleted' => false,
                    'created_at' => $service->created_at?->toIso8601String(),
                    'updated_at' => $service->updated_at?->toIso8601String(),
                    'deleted_at' => $service->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }
}

