<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a valid Bearer JWT is present, attaches auth_user (same as jwt.auth).
 * Missing or invalid token does not block the request — used for optional auth on public routes.
 */
class JwtOptionalMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $next($request);
        }

        $token = substr($authHeader, 7);
        if ($token === '') {
            return $next($request);
        }

        $secret = config('jwt.secret');
        if (empty($secret)) {
            return $next($request);
        }

        $tokenHash = hash('sha256', $token);
        if (DB::table('blacklisted_tokens')->where('token_hash', $tokenHash)->exists()) {
            return $next($request);
        }

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        } catch (ExpiredException|SignatureInvalidException|\Exception $e) {
            return $next($request);
        }

        $userId = $decoded->sub ?? null;
        if (!$userId) {
            return $next($request);
        }

        $user = User::find($userId);
        if (!$user) {
            return $next($request);
        }

        $request->merge(['auth_user' => $user]);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
