<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Step 1: Register user with basic details and generate 6-digit OTP
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ], [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'otp' => $otp,
            'is_email_verified' => false,
            'login_method' => 'web_form',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Please verify your email with the OTP.',
            'data' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'is_email_verified' => $user->is_email_verified,
            ],
        ], 201);
    }

    /**
     * Step 2: Verify OTP and mark email as verified
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.exists' => 'No account found with this email.',
            'otp.required' => 'OTP is required.',
            'otp.regex' => 'OTP must be exactly 6 digits.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user->otp !== $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP. Please try again.',
            ], 400);
        }

        $user->update([
            'is_email_verified' => true,
            'otp' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully. You can now set your password.',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'is_email_verified' => $user->is_email_verified,
            ],
        ], 200);
    }

    /**
     * Step 3: Set password after OTP verification
     */
    public function createPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'confirm_password' => ['required', 'string', 'min:8', 'same:password'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.exists' => 'No account found with this email.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'confirm_password.required' => 'Password confirmation is required.',
            'confirm_password.same' => 'Password and confirmation do not match.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user->is_email_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email with OTP before setting a password.',
            ], 403);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password set successfully. Registration is complete.',
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ], 200);
    }

    /**
     * Login with email and password, returns JWT token
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'password.required' => 'Password is required.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        if (!$user->is_email_verified) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email before logging in.',
            ], 403);
        }

        $token = $this->generateJwtToken($user);

        $user->update(['last_activity_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }

    /**
     * Logout - invalidate the current JWT token
     */
    public function logout(Request $request): JsonResponse
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully.',
            ], 200);
        }

        $token = substr($authHeader, 7);
        $tokenHash = hash('sha256', $token);

        $user = $request->user();
        if ($user) {
            $user->update(['last_activity_at' => Carbon::now()->subMinute()]);
        }

        DB::table('blacklisted_tokens')->insertOrIgnore([
            'jti' => $tokenHash,
            'token_hash' => $tokenHash,
            'expires_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ], 200);
    }

    /**
     * Generate JWT token for user
     */
    private function generateJwtToken(User $user): string
    {
        $secret = config('jwt.secret') ?: config('app.key');
        $ttl = config('jwt.ttl', 604800); // 7 days default

        $payload = [
            'jti' => Str::random(32),
            'sub' => $user->id,
            'email' => $user->email,
            'iat' => time(),
            'exp' => time() + $ttl,
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }
}
