<?php
// git pipeline testing.
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExamTypeController;
use App\Http\Controllers\Api\ScenarioTopicFocusController;
use App\Http\Controllers\Api\NotesTypeController;
use App\Http\Controllers\Api\DifficultyLevelController;
use App\Http\Controllers\Api\TopicFocusController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\JwtAuthMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/create-password', [AuthController::class, 'createPassword']);
    Route::post('/forgot-password/send-otp', [AuthController::class, 'forgotPasswordSendOtp']);
    Route::post('/forgot-password/verify-otp', [AuthController::class, 'forgotPasswordVerifyOtp']);
    Route::post('/forgot-password/reset', [AuthController::class, 'forgotPasswordReset']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware(JwtAuthMiddleware::class);
    Route::get('/me', [AuthController::class, 'me'])->middleware(JwtAuthMiddleware::class);
    Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware(JwtAuthMiddleware::class);
    Route::post('/change-email/send-otp', [AuthController::class, 'changeEmailSendOtp'])->middleware(JwtAuthMiddleware::class);
    Route::post('/change-email/verify-otp', [AuthController::class, 'changeEmailVerifyOtp'])->middleware(JwtAuthMiddleware::class);
});

Route::middleware(JwtAuthMiddleware::class)->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::match(['put', 'post'], '/users/{id}', [UserController::class, 'update'])
        ->middleware('image.upload:profile_image,2048');

    // Exam Types module (protected by JWT)
    Route::get('/exam-types', [ExamTypeController::class, 'index']);
    Route::post('/exam-types', [ExamTypeController::class, 'store']);
    Route::match(['put', 'post'], '/exam-types/{id}', [ExamTypeController::class, 'update']);
    Route::delete('/exam-types/{id}', [ExamTypeController::class, 'destroy']);
    Route::post('/exam-types/{id}/restore', [ExamTypeController::class, 'restore']);

    // Topic / Focus module (protected by JWT)
    Route::get('/topic-focuses', [TopicFocusController::class, 'index']);
    Route::post('/topic-focuses', [TopicFocusController::class, 'store']);
    Route::match(['put', 'post'], '/topic-focuses/{id}', [TopicFocusController::class, 'update']);
    Route::delete('/topic-focuses/{id}', [TopicFocusController::class, 'destroy']);
    Route::post('/topic-focuses/{id}/restore', [TopicFocusController::class, 'restore']);

    // Scenario Topic / Focus module (protected by JWT)
    Route::get('/scenarios-topic-focuses', [ScenarioTopicFocusController::class, 'index']);
    Route::post('/scenarios-topic-focuses', [ScenarioTopicFocusController::class, 'store']);
    Route::match(['put', 'post'], '/scenarios-topic-focuses/{id}', [ScenarioTopicFocusController::class, 'update']);
    Route::delete('/scenarios-topic-focuses/{id}', [ScenarioTopicFocusController::class, 'destroy']);
    Route::post('/scenarios-topic-focuses/{id}/restore', [ScenarioTopicFocusController::class, 'restore']);

    // Notes Types module (protected by JWT)
    Route::get('/notes-types', [NotesTypeController::class, 'index']);
    Route::post('/notes-types', [NotesTypeController::class, 'store']);
    Route::match(['put', 'post'], '/notes-types/{id}', [NotesTypeController::class, 'update']);
    Route::delete('/notes-types/{id}', [NotesTypeController::class, 'destroy']);
    Route::post('/notes-types/{id}/restore', [NotesTypeController::class, 'restore']);

    // Difficulty Levels module (protected by JWT)
    Route::get('/difficulty-levels', [DifficultyLevelController::class, 'index']);
    Route::post('/difficulty-levels', [DifficultyLevelController::class, 'store']);
    Route::match(['put', 'post'], '/difficulty-levels/{id}', [DifficultyLevelController::class, 'update']);
    Route::delete('/difficulty-levels/{id}', [DifficultyLevelController::class, 'destroy']);
    Route::post('/difficulty-levels/{id}/restore', [DifficultyLevelController::class, 'restore']);
});
