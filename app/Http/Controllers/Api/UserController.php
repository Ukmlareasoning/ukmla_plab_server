<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivePackageSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    private const ONLINE_THRESHOLD_MINUTES = 5;
    private const JUST_NOW_THRESHOLD_MINUTES = 1;

    /**
     * Get users with pagination and filters. Requires valid JWT token.
     * Query params: text, status, gender, availability, page, per_page
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = User::select(
            'id', 'first_name', 'last_name', 'email', 'is_email_verified',
            'profile_image', 'login_method', 'gender', 'status',
            'last_activity_at', 'is_online', 'created_at',
            'is_subscribed', 'subscription_type', 'subscription_start_date', 'subscription_end_date', 'active_subscription_id'
        );

        if ($text = $request->query('text')) {
            $searchTerm = '%' . $text . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', $searchTerm)
                    ->orWhere('last_name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('gender', 'like', $searchTerm)
                    ->orWhere('status', 'like', $searchTerm)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$searchTerm]);
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($gender = $request->query('gender')) {
            $query->where('gender', strtolower($gender));
        }

        if ($availability = $request->query('availability')) {
            if (strtolower($availability) === 'online') {
                $query->where('is_online', true);
            } elseif (strtolower($availability) === 'offline') {
                $query->where(function ($q) {
                    $q->where('is_online', false)->orWhereNull('is_online');
                });
            }
        }

        $users = $query->orderBy('id')->paginate($perPage);
        $userIds = collect($users->items())->pluck('id')->all();
        $activeSubsByUser = [];
        if (!empty($userIds)) {
            $activeSubs = ActivePackageSubscription::whereIn('user_id', $userIds)
                ->whereIn('status', ['Active', 'Cancelled'])
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->orderByDesc('ends_at')
                ->get();
            foreach ($activeSubs as $sub) {
                if (!isset($activeSubsByUser[$sub->user_id])) {
                    $activeSubsByUser[$sub->user_id] = [
                        'plan_name' => $sub->plan_name,
                        'status' => $sub->status,
                    ];
                }
            }
        }

        $usersWithAvailability = collect($users->items())->map(function ($user) use ($activeSubsByUser) {
            $userData = $this->appendAvailability($user);
            if (!empty($userData['profile_image'])) {
                $userData['profile_image_url'] = url($userData['profile_image']);
            }
            $activeSub = $activeSubsByUser[$user->id] ?? null;
            $userData['subscription_plan'] = $activeSub
                ? $activeSub['plan_name']
                : ($userData['subscription_type'] ?? null);
            $userData['subscription_status'] = $activeSub ? $activeSub['status'] : (isset($userData['subscription_type']) && $userData['subscription_type'] ? 'Active' : null);
            return $userData;
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully.',
            'data' => [
                'users' => $usersWithAvailability,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                    'prev_page_url' => $users->previousPageUrl(),
                    'next_page_url' => $users->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Append availability status to user (based on is_online; label uses last_activity_at when offline).
     */
    private function appendAvailability($user): array
    {
        $userArray = is_array($user) ? $user : $user->toArray();
        $isOnline = (bool) ($userArray['is_online'] ?? false);
        $lastActivity = $userArray['last_activity_at'] ?? null;

        if ($isOnline) {
            $userArray['availability'] = 'online';
            $userArray['availability_label'] = 'Just now';
            if ($lastActivity) {
                $lastActivity = $lastActivity instanceof \DateTimeInterface ? $lastActivity : Carbon::parse($lastActivity);
                $userArray['last_activity_at'] = $lastActivity->toIso8601String();
            }
            return $userArray;
        }

        if (!$lastActivity) {
            $userArray['availability'] = 'offline';
            $userArray['availability_label'] = 'Offline';
            return $userArray;
        }

        $lastActivity = $lastActivity instanceof Carbon ? $lastActivity : Carbon::parse($lastActivity);
        $userArray['availability'] = 'offline';
        $userArray['availability_label'] = $lastActivity->diffForHumans();
        $userArray['last_activity_at'] = $lastActivity->toIso8601String();

        return $userArray;
    }

    /**
     * Update user. Requires valid JWT token.
     * Updates: first_name, last_name, profile_image, gender, status (Active/InActive)
     * Use POST with form-data for file uploads (PHP does not parse PUT form-data).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:male,female,other,Male,Female,Other'],
            'status' => ['nullable', 'string', 'in:Active,InActive'],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:2048'],
        ], [
            'first_name.required' => 'First name is required.',
            'first_name.max' => 'First name must not exceed 255 characters.',
            'last_name.required' => 'Last name is required.',
            'last_name.max' => 'Last name must not exceed 255 characters.',
            'gender.required' => 'Gender is required.',
            'gender.in' => 'Gender must be one of: male, female, other.',
            'profile_image.required' => 'Profile image is required.',
            'profile_image.image' => 'Profile image must be an image file.',
            'profile_image.mimes' => 'Profile image must be jpeg, jpg, png, gif, or webp.',
            'profile_image.max' => 'Profile image must not exceed 2 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $data = [
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'gender' => strtolower($request->input('gender')),
        ];

        if ($request->filled('status')) {
            $data['status'] = $request->input('status');
        }

        if ($request->hasFile('profile_image')) {
            $uploadPath = public_path('assets/user_profiles');
            File::ensureDirectoryExists($uploadPath, 0755);

            $file = $request->file('profile_image');
            $filename = uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move($uploadPath, $filename);
            $data['profile_image'] = 'assets/user_profiles/' . $filename;

            if ($user->profile_image && file_exists(public_path($user->profile_image))) {
                unlink(public_path($user->profile_image));
            }
        }

        $user->update($data);

        $user->refresh();

        $userData = $user->only(['id', 'first_name', 'last_name', 'email', 'profile_image', 'gender', 'status']);
        if ($userData['profile_image']) {
            $userData['profile_image_url'] = url($userData['profile_image']);
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => [
                'user' => $userData,
            ],
        ], 200);
    }

    /**
     * Get a single user with subscription history.
     */
    public function show(int $id): JsonResponse
    {
        $user = User::select(
            'id',
            'first_name',
            'last_name',
            'email',
            'is_email_verified',
            'profile_image',
            'login_method',
            'gender',
            'status',
            'last_activity_at',
            'is_online',
            'is_subscribed',
            'subscription_type',
            'subscription_start_date',
            'subscription_end_date'
        )->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $userData = $this->appendAvailability($user);
        $userData['full_name'] = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
        if (!empty($userData['profile_image'])) {
            $userData['profile_image_url'] = url($userData['profile_image']);
        }

        $subscriptions = ActivePackageSubscription::where('user_id', $user->id)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();

        $history = $subscriptions->map(function (ActivePackageSubscription $sub) {
            return [
                'id' => $sub->id,
                'plan_name' => $sub->plan_name,
                'amount' => $sub->amount,
                'starts_at' => $sub->starts_at?->toIso8601String(),
                'ends_at' => $sub->ends_at?->toIso8601String(),
                'status' => $sub->status,
                'reference' => $sub->reference,
                'auto_renew' => (bool) ($sub->auto_renew ?? true),
                'cancelled_at' => $sub->cancelled_at?->toIso8601String(),
                'created_at' => $sub->created_at?->toIso8601String(),
                'updated_at' => $sub->updated_at?->toIso8601String(),
            ];
        })->toArray();

        $active = $subscriptions->filter(function (ActivePackageSubscription $sub) {
            if (!in_array($sub->status, ['Active', 'Cancelled'], true)) {
                return false;
            }
            if ($sub->ends_at === null) {
                return true;
            }

            return $sub->ends_at->isFuture();
        })->values()->map(function (ActivePackageSubscription $sub) {
            return [
                'id' => $sub->id,
                'plan_name' => $sub->plan_name,
                'amount' => $sub->amount,
                'starts_at' => $sub->starts_at?->toIso8601String(),
                'ends_at' => $sub->ends_at?->toIso8601String(),
                'status' => $sub->status,
                'reference' => $sub->reference,
                'auto_renew' => (bool) ($sub->auto_renew ?? true),
                'cancelled_at' => $sub->cancelled_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'User details retrieved successfully.',
            'data' => [
                'user' => $userData,
                'active_subscriptions' => $active,
                'subscription_history' => $history,
            ],
        ], 200);
    }
}
