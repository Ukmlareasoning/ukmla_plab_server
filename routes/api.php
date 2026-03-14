<?php
// git pipeline testing.
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExamTypeController;
use App\Http\Controllers\Api\ScenarioTopicFocusController;
use App\Http\Controllers\Api\NotesTypeController;
use App\Http\Controllers\Api\StaticPageController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\WebinarController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\DifficultyLevelController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\SubscriptionController;
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
    Route::get('/users/{id}', [UserController::class, 'show']);
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

    // Static Pages module (protected by JWT)
    Route::get('/static-pages', [StaticPageController::class, 'index']);
    Route::post('/static-pages', [StaticPageController::class, 'store']);
    Route::get('/static-pages/{id}', [StaticPageController::class, 'show']);
    Route::match(['put', 'post'], '/static-pages/{id}', [StaticPageController::class, 'update']);
    Route::delete('/static-pages/{id}', [StaticPageController::class, 'destroy']);
    Route::post('/static-pages/{id}/restore', [StaticPageController::class, 'restore']);

    // Difficulty Levels module (protected by JWT)
    Route::get('/difficulty-levels', [DifficultyLevelController::class, 'index']);
    Route::post('/difficulty-levels', [DifficultyLevelController::class, 'store']);
    Route::match(['put', 'post'], '/difficulty-levels/{id}', [DifficultyLevelController::class, 'update']);
    Route::delete('/difficulty-levels/{id}', [DifficultyLevelController::class, 'destroy']);
    Route::post('/difficulty-levels/{id}/restore', [DifficultyLevelController::class, 'restore']);

    // Services module (protected by JWT)
    Route::get('/services', [ServiceController::class, 'index']);
    Route::post('/services', [ServiceController::class, 'store']);
    Route::match(['put', 'post'], '/services/{id}', [ServiceController::class, 'update']);
    Route::delete('/services/{id}', [ServiceController::class, 'destroy']);
    Route::post('/services/{id}/restore', [ServiceController::class, 'restore']);

    // Webinars module (protected by JWT)
    Route::get('/webinars', [WebinarController::class, 'index']);
    Route::post('/webinars', [WebinarController::class, 'store'])->middleware('image.upload:banner_image,5120');
    Route::match(['put', 'post'], '/webinars/{id}', [WebinarController::class, 'update'])->middleware('image.upload:banner_image,5120');
    Route::delete('/webinars/{id}', [WebinarController::class, 'destroy']);
    Route::post('/webinars/{id}/restore', [WebinarController::class, 'restore']);

    // Notes module (protected by JWT)
    Route::get('/notes', [NoteController::class, 'index']);
    Route::post('/notes', [NoteController::class, 'store']);
    Route::get('/notes/{id}', [NoteController::class, 'show']);
    Route::match(['put', 'post'], '/notes/{id}', [NoteController::class, 'update']);
    Route::delete('/notes/{id}', [NoteController::class, 'destroy']);
    Route::post('/notes/{id}/restore', [NoteController::class, 'restore']);

    // Announcements module (protected by JWT)
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::match(['put', 'post'], '/announcements/{id}', [AnnouncementController::class, 'update']);
    Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy']);
    Route::post('/announcements/{id}/restore', [AnnouncementController::class, 'restore']);

    // Subscriptions module (protected by JWT)
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::post('/subscriptions/bulk-delete', [SubscriptionController::class, 'bulkDestroy']);

    // Contacts module (protected by JWT)
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts/{id}/reply', [ContactController::class, 'reply']);
    Route::delete('/contacts/{id}', [ContactController::class, 'destroy']);
    Route::post('/contacts/{id}/restore', [ContactController::class, 'restore']);
});
