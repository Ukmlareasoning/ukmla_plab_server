<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    /**
     * Handle an incoming request.
     * Extracts Bearer token from Authorization header, verifies JWT, and attaches authenticated user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization token is required. Please provide a valid Bearer token.',
            ], 401);
        }

        $token = substr($authHeader, 7);

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization token is required. Please provide a valid Bearer token.',
            ], 401);
        }

        $secret = config('jwt.secret');
        if (empty($secret)) {
            return response()->json([
                'success' => false,
                'message' => 'Server configuration error.',
            ], 500);
        }

        $tokenHash = hash('sha256', $token);
        $blacklisted = DB::table('blacklisted_tokens')
            ->where('token_hash', $tokenHash)
            ->exists();

        if ($blacklisted) {
            return response()->json([
                'success' => false,
                'message' => 'Token has been revoked. Please login again.',
            ], 401);
        }

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (ExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token has expired. Please login again.',
            ], 401);
        } catch (SignatureInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token. Please login again.',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token. Please login again.',
            ], 401);
        }

        $userId = $decoded->sub ?? null;
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token. Please login again.',
            ], 401);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found. Please login again.',
            ], 401);
        }

        if (!$request->is('api/auth/logout')) {
            $user->update(['last_activity_at' => now()]);
        }

        $request->merge(['auth_user' => $user]);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
