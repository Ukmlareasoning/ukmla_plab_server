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
use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\ScenarioController;
use App\Http\Controllers\Api\ScenarioExamController;
use App\Http\Controllers\Api\ScenarioQuestionController;
use App\Http\Controllers\Api\ScenarioExamRatingController;
use App\Http\Controllers\Api\MockController;
use App\Http\Controllers\Api\MockExamController;
use App\Http\Controllers\Api\MockQuestionController;
use App\Http\Controllers\Api\MockExamRatingController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Middleware\JwtAuthMiddleware;
use Illuminate\Support\Facades\Route;

// Public: list static pages (for How It Works, Privacy Policy, etc.)
Route::get('/static-pages', [StaticPageController::class, 'index']);

// Public: submit contact form (insert into contacts table)
Route::post('/contacts', [ContactController::class, 'store']);

// Public: newsletter/subscribe form (insert into subscriptions table)
Route::post('/subscriptions', [SubscriptionController::class, 'store']);

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
    Route::get('/accounting', [AccountingController::class, 'index']);

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

    // Static Pages module (protected by JWT; GET /static-pages is public above)
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

    // Activity Logs module (protected by JWT)
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::post('/activity-logs/bulk-delete', [ActivityLogController::class, 'bulkDestroy']);

    // Contacts module (protected by JWT)
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts/{id}/reply', [ContactController::class, 'reply']);
    Route::delete('/contacts/{id}', [ContactController::class, 'destroy']);
    Route::post('/contacts/{id}/restore', [ContactController::class, 'restore']);

    // Scenarios module (protected by JWT)
    Route::get('/scenarios', [ScenarioController::class, 'index']);
    Route::post('/scenarios', [ScenarioController::class, 'store']);
    Route::get('/scenarios/{id}', [ScenarioController::class, 'show']);
    Route::match(['put', 'post'], '/scenarios/{id}', [ScenarioController::class, 'update']);
    Route::delete('/scenarios/{id}', [ScenarioController::class, 'destroy']);
    Route::post('/scenarios/{id}/restore', [ScenarioController::class, 'restore']);

    // Scenario Exams module (protected by JWT)
    Route::get('/scenario-exams', [ScenarioExamController::class, 'index']);
    Route::post('/scenario-exams', [ScenarioExamController::class, 'store']);
    Route::post('/scenario-exams/release-mode', [ScenarioExamController::class, 'updateReleaseMode']);
    Route::match(['put', 'post'], '/scenario-exams/{id}', [ScenarioExamController::class, 'update']);
    Route::delete('/scenario-exams/{id}', [ScenarioExamController::class, 'destroy']);
    Route::post('/scenario-exams/{id}/restore', [ScenarioExamController::class, 'restore']);

    // Scenario Questions module (protected by JWT)
    Route::get('/scenario-questions', [ScenarioQuestionController::class, 'index']);
    Route::post('/scenario-questions', [ScenarioQuestionController::class, 'store']);
    Route::get('/scenario-questions/{id}', [ScenarioQuestionController::class, 'show']);
    Route::match(['put', 'post'], '/scenario-questions/{id}', [ScenarioQuestionController::class, 'update']);
    Route::delete('/scenario-questions/{id}', [ScenarioQuestionController::class, 'destroy']);
    Route::post('/scenario-questions/{id}/restore', [ScenarioQuestionController::class, 'restore']);

    // Scenario Exam Ratings (protected by JWT)
    Route::get('/scenario-exam-ratings', [ScenarioExamRatingController::class, 'index']);

    // Mocks module (protected by JWT)
    Route::get('/mocks', [MockController::class, 'index']);
    Route::post('/mocks', [MockController::class, 'store']);
    Route::get('/mocks/{id}', [MockController::class, 'show']);
    Route::put('/mocks/{id}/pricing', [MockController::class, 'updatePricing']);
    Route::match(['put', 'post'], '/mocks/{id}', [MockController::class, 'update']);
    Route::delete('/mocks/{id}', [MockController::class, 'destroy']);
    Route::post('/mocks/{id}/restore', [MockController::class, 'restore']);

    // Mock Exams module (protected by JWT)
    Route::get('/mock-exams', [MockExamController::class, 'index']);
    Route::post('/mock-exams', [MockExamController::class, 'store']);
    Route::post('/mock-exams/release-mode', [MockExamController::class, 'updateReleaseMode']);
    Route::match(['put', 'post'], '/mock-exams/{id}', [MockExamController::class, 'update']);
    Route::delete('/mock-exams/{id}', [MockExamController::class, 'destroy']);
    Route::post('/mock-exams/{id}/restore', [MockExamController::class, 'restore']);

    // Mock Questions module (protected by JWT)
    Route::get('/mock-questions', [MockQuestionController::class, 'index']);
    Route::post('/mock-questions', [MockQuestionController::class, 'store']);
    Route::get('/mock-questions/{id}', [MockQuestionController::class, 'show']);
    Route::match(['put', 'post'], '/mock-questions/{id}', [MockQuestionController::class, 'update']);
    Route::delete('/mock-questions/{id}', [MockQuestionController::class, 'destroy']);
    Route::post('/mock-questions/{id}/restore', [MockQuestionController::class, 'restore']);

    // Mock Exam Ratings (protected by JWT)
    Route::get('/mock-exam-ratings', [MockExamRatingController::class, 'index']);
});
