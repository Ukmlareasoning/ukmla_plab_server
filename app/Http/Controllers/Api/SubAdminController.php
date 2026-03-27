<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SubAdminController extends Controller
{
    public const MODULE_KEYS = [
        'dashboard',
        'users',
        'scenarios',
        'mocks',
        'webinars',
        'notes',
        'announcements',
        'accounting',
        'settings',
        'contacts',
        'subscriptions',
        'static_pages',
        'activity_log',
        'services',
    ];

    private function ensureAdmin(Request $request): ?JsonResponse
    {
        $authUser = $request->user();
        if (!$authUser || $authUser->user_status !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admin can manage sub-admin accounts.',
            ], 403);
        }

        return null;
    }

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->ensureAdmin($request)) return $response;

        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;
        $query = User::query()->where('user_status', 'sub-admin');

        if ($text = trim((string) $request->query('text', ''))) {
            $searchTerm = '%' . $text . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', $searchTerm)
                    ->orWhere('last_name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$searchTerm]);
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $items = $query->orderBy('id', 'desc')->paginate($perPage);
        $subAdmins = collect($items->items())->map(function (User $user) {
            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'status' => $user->status ?: 'Active',
                'user_status' => $user->user_status,
                'admin_module_access' => is_array($user->admin_module_access) ? $user->admin_module_access : [],
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Sub-admins retrieved successfully.',
            'data' => [
                'sub_admins' => $subAdmins,
                'modules' => self::MODULE_KEYS,
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'per_page' => $items->perPage(),
                    'total' => $items->total(),
                ],
            ],
        ], 200);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if ($response = $this->ensureAdmin($request)) return $response;

        $user = User::where('user_status', 'sub-admin')->find($id);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Sub-admin not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sub-admin retrieved successfully.',
            'data' => [
                'sub_admin' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'status' => $user->status ?: 'Active',
                    'user_status' => $user->user_status,
                    'admin_module_access' => is_array($user->admin_module_access) ? $user->admin_module_access : [],
                ],
                'modules' => self::MODULE_KEYS,
            ],
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->ensureAdmin($request)) return $response;

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['nullable', 'string', 'in:Active,InActive'],
            'admin_module_access' => ['required', 'array', 'min:1'],
            'admin_module_access.*' => ['required', 'string', 'in:' . implode(',', self::MODULE_KEYS)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $subAdmin = User::create([
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'is_email_verified' => true,
            'login_method' => 'web_form',
            'status' => $request->input('status', 'Active'),
            'user_status' => 'sub-admin',
            'admin_module_access' => array_values(array_unique($request->input('admin_module_access', []))),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sub-admin created successfully.',
            'data' => [
                'sub_admin' => [
                    'id' => $subAdmin->id,
                    'first_name' => $subAdmin->first_name,
                    'last_name' => $subAdmin->last_name,
                    'email' => $subAdmin->email,
                    'status' => $subAdmin->status,
                    'user_status' => $subAdmin->user_status,
                    'admin_module_access' => $subAdmin->admin_module_access ?? [],
                ],
            ],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($response = $this->ensureAdmin($request)) return $response;

        $subAdmin = User::where('user_status', 'sub-admin')->find($id);
        if (!$subAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Sub-admin not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $subAdmin->id],
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['nullable', 'string', 'in:Active,InActive'],
            'admin_module_access' => ['required', 'array', 'min:1'],
            'admin_module_access.*' => ['required', 'string', 'in:' . implode(',', self::MODULE_KEYS)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = [
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'status' => $request->input('status', $subAdmin->status ?: 'Active'),
            'admin_module_access' => array_values(array_unique($request->input('admin_module_access', []))),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $subAdmin->update($data);
        $subAdmin->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Sub-admin updated successfully.',
            'data' => [
                'sub_admin' => [
                    'id' => $subAdmin->id,
                    'first_name' => $subAdmin->first_name,
                    'last_name' => $subAdmin->last_name,
                    'email' => $subAdmin->email,
                    'status' => $subAdmin->status ?: 'Active',
                    'user_status' => $subAdmin->user_status,
                    'admin_module_access' => $subAdmin->admin_module_access ?? [],
                ],
            ],
        ], 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($response = $this->ensureAdmin($request)) return $response;

        $subAdmin = User::where('user_status', 'sub-admin')->find($id);
        if (!$subAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Sub-admin not found.',
            ], 404);
        }

        $subAdmin->update(['status' => 'InActive']);

        return response()->json([
            'success' => true,
            'message' => 'Sub-admin deleted successfully.',
        ], 200);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        if ($response = $this->ensureAdmin($request)) return $response;

        $subAdmin = User::where('user_status', 'sub-admin')->find($id);
        if (!$subAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Sub-admin not found.',
            ], 404);
        }

        $subAdmin->update(['status' => 'Active']);

        return response()->json([
            'success' => true,
            'message' => 'Sub-admin restored successfully.',
        ], 200);
    }
}

