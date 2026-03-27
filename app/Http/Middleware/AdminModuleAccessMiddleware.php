<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminModuleAccessMiddleware
{
    /**
     * Allow full access to admin users and limited access to sub-admin users.
     */
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->user_status === 'admin') {
            return $next($request);
        }

        if ($user->user_status !== 'sub-admin') {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to access admin modules.',
            ], 403);
        }

        if (($user->status ?? 'Active') !== 'Active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact administrator.',
            ], 403);
        }

        $allowedModules = is_array($user->admin_module_access) ? $user->admin_module_access : [];
        if (!in_array($moduleKey, $allowedModules, true)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this module.',
            ], 403);
        }

        return $next($request);
    }
}

