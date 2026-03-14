<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivePackageSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountingController extends Controller
{
    /**
     * Accounting overview: stats (subscriptions, earnings, orders count) and paginated order history.
     * Order history = active_package_subscriptions with user, filtered by text, date_from, date_to, package.
     * Query params: page, per_page, text, date_from, date_to, package
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        // Global stats (all-time, not filtered)
        $activeSubscriptionsCount = ActivePackageSubscription::where('status', 'Active')->count();
        $cancelledCount = ActivePackageSubscription::where('status', 'Cancelled')->count();
        $endedCount = ActivePackageSubscription::where('status', 'Ended')->count();
        $totalEarnings = (float) ActivePackageSubscription::sum('amount');
        $ordersCount = ActivePackageSubscription::count();
        $totalForPercent = $ordersCount > 0 ? $ordersCount : 1;
        $percentActive = round($activeSubscriptionsCount / $totalForPercent * 100, 1);
        $percentCancelled = round($cancelledCount / $totalForPercent * 100, 1);
        $percentEnded = round($endedCount / $totalForPercent * 100, 1);

        $query = ActivePackageSubscription::with(['user' => function ($q) {
            $q->select('id', 'first_name', 'last_name', 'email', 'profile_image');
        }])->orderByDesc('starts_at')->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($text = trim($request->query('text', ''))) {
            $searchTerm = '%' . $text . '%';
            $query->whereHas('user', function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', $searchTerm)
                    ->orWhere('last_name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$searchTerm]);
            });
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('starts_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('starts_at', '<=', $dateTo);
        }

        if ($package = trim($request->query('package', ''))) {
            $query->where('plan_name', 'like', '%' . $package . '%');
        }

        $orders = $query->paginate($perPage);

        $items = collect($orders->items())->map(function (ActivePackageSubscription $sub) {
            $user = $sub->user;
            $userData = null;
            if ($user) {
                $userData = [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'full_name' => trim($user->first_name . ' ' . $user->last_name) ?: $user->email,
                    'profile_image_url' => $user->profile_image ? url($user->profile_image) : null,
                ];
            }
            return [
                'id' => $sub->id,
                'user_id' => $sub->user_id,
                'user' => $userData,
                'plan_name' => $sub->plan_name,
                'amount' => $sub->amount,
                'starts_at' => $sub->starts_at?->toIso8601String(),
                'ends_at' => $sub->ends_at?->toIso8601String(),
                'status' => $sub->status,
                'reference' => $sub->reference,
                'created_at' => $sub->created_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Accounting data retrieved successfully.',
            'data' => [
                'stats' => [
                    'active_subscriptions' => $activeSubscriptionsCount,
                    'cancelled_subscriptions' => $cancelledCount,
                    'ended_subscriptions' => $endedCount,
                    'total_earnings' => round($totalEarnings, 2),
                    'orders_count' => $ordersCount,
                    'percent_active' => $percentActive,
                    'percent_cancelled' => $percentCancelled,
                    'percent_ended' => $percentEnded,
                ],
                'orders' => $items,
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                    'prev_page_url' => $orders->previousPageUrl(),
                    'next_page_url' => $orders->nextPageUrl(),
                ],
            ],
        ], 200);
    }
}
