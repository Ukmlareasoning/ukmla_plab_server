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
use App\Http\Controllers\Api\WebinarBookingController;
use App\Http\Controllers\Api\DifficultyLevelController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\PackageSubscriptionController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\TopicFocusController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\ScenarioController;
use App\Http\Controllers\Api\ScenarioExamController;
use App\Http\Controllers\Api\ScenarioQuestionController;
use App\Http\Controllers\Api\ScenarioExamRatingController;
use App\Http\Controllers\Api\ScenarioUserAnswerController;
use App\Http\Controllers\Api\MockController;
use App\Http\Controllers\Api\MockExamController;
use App\Http\Controllers\Api\MockQuestionController;
use App\Http\Controllers\Api\MockExamRatingController;
use App\Http\Controllers\Api\MockUserAnswerController;
use App\Http\Controllers\Api\MockPurchaseController;
use App\Http\Controllers\Api\SubAdminController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Middleware\JwtAuthMiddleware;
use Illuminate\Support\Facades\Route;

// Public: list static pages (for How It Works, Privacy Policy, etc.)
Route::get('/static-pages', [StaticPageController::class, 'index']);

// Public: submit contact form (insert into contacts table)
Route::post('/contacts', [ContactController::class, 'store']);

// Public: newsletter/subscribe form (insert into subscriptions table)
Route::post('/subscriptions', [SubscriptionController::class, 'store']);

// Public: notes (for User Notes page and NoteDetails — no auth)
Route::get('/notes-types', [NotesTypeController::class, 'index']);
Route::get('/notes', [NoteController::class, 'index']);
Route::get('/notes/{id}', [NoteController::class, 'show']);

// Public: difficulty levels (for user scenarios filters — no auth)
Route::get('/difficulty-levels', [DifficultyLevelController::class, 'index']);

// Public: scenario topic/focus (for user scenarios filters — no auth)
Route::get('/scenarios-topic-focuses', [ScenarioTopicFocusController::class, 'index']);

// Public: scenarios list (for user Scenarios page — no auth)
Route::get('/scenarios', [ScenarioController::class, 'index']);

// Public: list services (for User Other Services page — no auth)
Route::get('/services', [ServiceController::class, 'index']);

// Public: list webinars (for User Webinars page — no auth)
Route::get('/webinars', [WebinarController::class, 'index']);

// Public: subscription catalog (pricing page)
Route::get('/subscription-plans', [PackageSubscriptionController::class, 'plans']);

// Stripe webhooks (raw body; no JWT)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

// Public: list scenario exams and questions (for user Scenario Practice pages — no auth)
Route::get('/scenario-exams', [ScenarioExamController::class, 'index']);
Route::get('/scenario-questions', [ScenarioQuestionController::class, 'index']);

// Public: mocks list and related data (for user Mocks/Courses pages — no auth required)
Route::get('/mocks', [MockController::class, 'index']);
Route::get('/mock-exams', [MockExamController::class, 'index'])->middleware('jwt.optional');
Route::get('/mock-questions', [MockQuestionController::class, 'index'])->middleware('jwt.optional');
Route::get('/exam-types', [ExamTypeController::class, 'index']);
Route::get('/topic-focuses', [TopicFocusController::class, 'index']);

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
    Route::get('/my-package-subscription', [PackageSubscriptionController::class, 'me']);
    Route::post('/my-package-subscription/subscribe', [PackageSubscriptionController::class, 'subscribe']);
    Route::post('/my-package-subscription/subscribe-intent', [PackageSubscriptionController::class, 'createSubscribeIntent']);
    Route::post('/my-package-subscription/complete-subscribe', [PackageSubscriptionController::class, 'completeSubscribe']);
    Route::post('/my-package-subscription/cancel', [PackageSubscriptionController::class, 'cancel']);
    Route::post('/my-package-subscription/end-now', [PackageSubscriptionController::class, 'endNow']);

    Route::get('/accounting', [AccountingController::class, 'index'])->middleware('module.access:accounting');

    Route::get('/users', [UserController::class, 'index'])->middleware('module.access:users');
    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('module.access:users');
    Route::match(['put', 'post'], '/users/{id}', [UserController::class, 'update'])
        ->middleware('module.access:users')
        ->middleware('image.upload:profile_image,2048');

    Route::get('/sub-admins', [SubAdminController::class, 'index'])->middleware('module.access:settings');
    Route::post('/sub-admins', [SubAdminController::class, 'store'])->middleware('module.access:settings');
    Route::get('/sub-admins/{id}', [SubAdminController::class, 'show'])->middleware('module.access:settings');
    Route::match(['put', 'post'], '/sub-admins/{id}', [SubAdminController::class, 'update'])->middleware('module.access:settings');
    Route::post('/sub-admins/{id}/delete', [SubAdminController::class, 'destroy'])->middleware('module.access:settings');
    Route::post('/sub-admins/{id}/restore', [SubAdminController::class, 'restore'])->middleware('module.access:settings');

    // Exam Types module (protected by JWT) — GET /exam-types is now public above
    Route::post('/exam-types', [ExamTypeController::class, 'store'])->middleware('module.access:mocks');
    Route::match(['put', 'post'], '/exam-types/{id}', [ExamTypeController::class, 'update'])->middleware('module.access:mocks');
    Route::delete('/exam-types/{id}', [ExamTypeController::class, 'destroy'])->middleware('module.access:mocks');
    Route::post('/exam-types/{id}/restore', [ExamTypeController::class, 'restore'])->middleware('module.access:mocks');

    // Topic / Focus module (protected by JWT) — GET /topic-focuses is now public above
    Route::post('/topic-focuses', [TopicFocusController::class, 'store'])->middleware('module.access:mocks');
    Route::match(['put', 'post'], '/topic-focuses/{id}', [TopicFocusController::class, 'update'])->middleware('module.access:mocks');
    Route::delete('/topic-focuses/{id}', [TopicFocusController::class, 'destroy'])->middleware('module.access:mocks');
    Route::post('/topic-focuses/{id}/restore', [TopicFocusController::class, 'restore'])->middleware('module.access:mocks');

    // Scenario Topic / Focus module (protected by JWT) — GET is public above
    Route::post('/scenarios-topic-focuses', [ScenarioTopicFocusController::class, 'store'])->middleware('module.access:scenarios');
    Route::match(['put', 'post'], '/scenarios-topic-focuses/{id}', [ScenarioTopicFocusController::class, 'update'])->middleware('module.access:scenarios');
    Route::delete('/scenarios-topic-focuses/{id}', [ScenarioTopicFocusController::class, 'destroy'])->middleware('module.access:scenarios');
    Route::post('/scenarios-topic-focuses/{id}/restore', [ScenarioTopicFocusController::class, 'restore'])->middleware('module.access:scenarios');

    // Notes Types module (protected by JWT) — GET /notes-types is public above
    Route::post('/notes-types', [NotesTypeController::class, 'store'])->middleware('module.access:notes');
    Route::match(['put', 'post'], '/notes-types/{id}', [NotesTypeController::class, 'update'])->middleware('module.access:notes');
    Route::delete('/notes-types/{id}', [NotesTypeController::class, 'destroy'])->middleware('module.access:notes');
    Route::post('/notes-types/{id}/restore', [NotesTypeController::class, 'restore'])->middleware('module.access:notes');

    // Static Pages module (protected by JWT; GET /static-pages is public above)
    Route::post('/static-pages', [StaticPageController::class, 'store'])->middleware('module.access:static_pages');
    Route::get('/static-pages/{id}', [StaticPageController::class, 'show'])->middleware('module.access:static_pages');
    Route::match(['put', 'post'], '/static-pages/{id}', [StaticPageController::class, 'update'])->middleware('module.access:static_pages');
    Route::delete('/static-pages/{id}', [StaticPageController::class, 'destroy'])->middleware('module.access:static_pages');
    Route::post('/static-pages/{id}/restore', [StaticPageController::class, 'restore'])->middleware('module.access:static_pages');

    // Difficulty Levels module (protected by JWT) — GET is public above
    Route::post('/difficulty-levels', [DifficultyLevelController::class, 'store'])->middleware('module.access:mocks');
    Route::match(['put', 'post'], '/difficulty-levels/{id}', [DifficultyLevelController::class, 'update'])->middleware('module.access:mocks');
    Route::delete('/difficulty-levels/{id}', [DifficultyLevelController::class, 'destroy'])->middleware('module.access:mocks');
    Route::post('/difficulty-levels/{id}/restore', [DifficultyLevelController::class, 'restore'])->middleware('module.access:mocks');

    // Services module (protected by JWT) — GET /services is public above
    Route::post('/services', [ServiceController::class, 'store'])->middleware('module.access:services');
    Route::match(['put', 'post'], '/services/{id}', [ServiceController::class, 'update'])->middleware('module.access:services');
    Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->middleware('module.access:services');
    Route::post('/services/{id}/restore', [ServiceController::class, 'restore'])->middleware('module.access:services');

    // Webinars module (protected by JWT) — GET /webinars is public above
    Route::post('/webinars', [WebinarController::class, 'store'])->middleware('module.access:webinars')->middleware('image.upload:banner_image,5120');
    Route::match(['put', 'post'], '/webinars/{id}', [WebinarController::class, 'update'])->middleware('module.access:webinars')->middleware('image.upload:banner_image,5120');
    Route::delete('/webinars/{id}', [WebinarController::class, 'destroy'])->middleware('module.access:webinars');
    Route::post('/webinars/{id}/restore', [WebinarController::class, 'restore'])->middleware('module.access:webinars');

    // Webinar bookings (protected by JWT)
    Route::get('/webinars/my-bookings', [WebinarBookingController::class, 'myBookings']);
    Route::post('/webinars/{id}/payment-intent', [WebinarBookingController::class, 'createPaymentIntent']);
    Route::post('/webinars/{id}/book', [WebinarBookingController::class, 'store']);
    Route::get('/webinars/{id}/bookings', [WebinarBookingController::class, 'index']);

    // Notes module (protected by JWT) — GET /notes and GET /notes/{id} are public above
    Route::post('/notes', [NoteController::class, 'store'])->middleware('module.access:notes');
    Route::match(['put', 'post'], '/notes/{id}', [NoteController::class, 'update'])->middleware('module.access:notes');
    Route::delete('/notes/{id}', [NoteController::class, 'destroy'])->middleware('module.access:notes');
    Route::post('/notes/{id}/restore', [NoteController::class, 'restore'])->middleware('module.access:notes');

    // Announcements module (protected by JWT)
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::post('/announcements', [AnnouncementController::class, 'store'])->middleware('module.access:announcements');
    Route::match(['put', 'post'], '/announcements/{id}', [AnnouncementController::class, 'update'])->middleware('module.access:announcements');
    Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy'])->middleware('module.access:announcements');
    Route::post('/announcements/{id}/restore', [AnnouncementController::class, 'restore'])->middleware('module.access:announcements');

    // Subscriptions module (protected by JWT)
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->middleware('module.access:subscriptions');
    Route::post('/subscriptions/bulk-delete', [SubscriptionController::class, 'bulkDestroy'])->middleware('module.access:subscriptions');
    Route::post('/subscriptions/bulk-restore', [SubscriptionController::class, 'bulkRestore'])->middleware('module.access:subscriptions');

    // Activity Logs module (protected by JWT)
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->middleware('module.access:activity_log');
    Route::post('/activity-logs/bulk-delete', [ActivityLogController::class, 'bulkDestroy'])->middleware('module.access:activity_log');
    Route::post('/activity-logs/bulk-restore', [ActivityLogController::class, 'bulkRestore'])->middleware('module.access:activity_log');

    // Contacts module (protected by JWT)
    Route::get('/contacts', [ContactController::class, 'index'])->middleware('module.access:contacts');
    Route::post('/contacts/{id}/reply', [ContactController::class, 'reply'])->middleware('module.access:contacts');
    Route::delete('/contacts/{id}', [ContactController::class, 'destroy'])->middleware('module.access:contacts');
    Route::post('/contacts/{id}/restore', [ContactController::class, 'restore'])->middleware('module.access:contacts');

    // Scenarios module (protected by JWT) — GET is public above
    Route::post('/scenarios', [ScenarioController::class, 'store'])->middleware('module.access:scenarios');
    Route::get('/scenarios/{id}', [ScenarioController::class, 'show'])->middleware('module.access:scenarios');
    Route::match(['put', 'post'], '/scenarios/{id}', [ScenarioController::class, 'update'])->middleware('module.access:scenarios');
    Route::delete('/scenarios/{id}', [ScenarioController::class, 'destroy'])->middleware('module.access:scenarios');
    Route::post('/scenarios/{id}/restore', [ScenarioController::class, 'restore'])->middleware('module.access:scenarios');

    // Scenario Exams module
    Route::post('/scenario-exams', [ScenarioExamController::class, 'store'])->middleware('module.access:scenarios');
    Route::post('/scenario-exams/release-mode', [ScenarioExamController::class, 'updateReleaseMode'])->middleware('module.access:scenarios');
    Route::match(['put', 'post'], '/scenario-exams/{id}', [ScenarioExamController::class, 'update'])->middleware('module.access:scenarios');
    Route::delete('/scenario-exams/{id}', [ScenarioExamController::class, 'destroy'])->middleware('module.access:scenarios');
    Route::post('/scenario-exams/{id}/restore', [ScenarioExamController::class, 'restore'])->middleware('module.access:scenarios');

    // Scenario Questions module (protected by JWT) — GET list is public above
    Route::post('/scenario-questions', [ScenarioQuestionController::class, 'store'])->middleware('module.access:scenarios');
    Route::get('/scenario-questions/{id}', [ScenarioQuestionController::class, 'show'])->middleware('module.access:scenarios');
    Route::match(['put', 'post'], '/scenario-questions/{id}', [ScenarioQuestionController::class, 'update'])->middleware('module.access:scenarios');
    Route::delete('/scenario-questions/{id}', [ScenarioQuestionController::class, 'destroy'])->middleware('module.access:scenarios');
    Route::post('/scenario-questions/{id}/restore', [ScenarioQuestionController::class, 'restore'])->middleware('module.access:scenarios');

    // Scenario Exam Ratings (protected by JWT)
    Route::get('/scenario-exam-ratings', [ScenarioExamRatingController::class, 'index']);
    Route::post('/scenario-exam-ratings', [ScenarioExamRatingController::class, 'store']);
    Route::get('/scenario-exam-ratings/my-rating', [ScenarioExamRatingController::class, 'myRating']);

    // Scenario User Answers & Progress (protected by JWT)
    Route::get('/scenario-user-answers', [ScenarioUserAnswerController::class, 'index']);
    Route::post('/scenario-user-answers', [ScenarioUserAnswerController::class, 'store']);
    Route::get('/scenario-user-progress', [ScenarioUserAnswerController::class, 'progress']);

    // Mock User Answers & Progress (protected by JWT)
    Route::get('/mock-user-answers', [MockUserAnswerController::class, 'index']);
    Route::post('/mock-user-answers', [MockUserAnswerController::class, 'store']);
    Route::get('/mock-user-progress', [MockUserAnswerController::class, 'progress']);

    // Mock Exam Ratings — store and my-rating (protected by JWT); GET list is now public above
    Route::post('/mock-exam-ratings', [MockExamRatingController::class, 'store']);
    Route::get('/mock-exam-ratings/my-rating', [MockExamRatingController::class, 'myRating']);

    // Mocks module (protected by JWT) — GET /mocks is now public above
    Route::get('/mocks/my-purchases', [MockPurchaseController::class, 'myPurchases']);
    Route::post('/mocks', [MockController::class, 'store'])->middleware('module.access:mocks');
    Route::get('/mocks/{id}', [MockController::class, 'show'])->middleware('module.access:mocks');
    Route::put('/mocks/{id}/pricing', [MockController::class, 'updatePricing'])->middleware('module.access:mocks');
    Route::match(['put', 'post'], '/mocks/{id}', [MockController::class, 'update'])->middleware('module.access:mocks');
    Route::delete('/mocks/{id}', [MockController::class, 'destroy'])->middleware('module.access:mocks');
    Route::post('/mocks/{id}/restore', [MockController::class, 'restore'])->middleware('module.access:mocks');
    Route::post('/mocks/{id}/payment-intent', [MockPurchaseController::class, 'createPaymentIntent']);
    Route::post('/mocks/{id}/purchase', [MockPurchaseController::class, 'purchase']);
    Route::get('/mocks/{id}/purchases', [MockPurchaseController::class, 'adminIndex'])->middleware('module.access:mocks');

    // Mock Exams module (protected by JWT) — GET /mock-exams is now public above
    Route::post('/mock-exams', [MockExamController::class, 'store'])->middleware('module.access:mocks');
    Route::post('/mock-exams/release-mode', [MockExamController::class, 'updateReleaseMode'])->middleware('module.access:mocks');
    Route::match(['put', 'post'], '/mock-exams/{id}', [MockExamController::class, 'update'])->middleware('module.access:mocks');
    Route::delete('/mock-exams/{id}', [MockExamController::class, 'destroy'])->middleware('module.access:mocks');
    Route::post('/mock-exams/{id}/restore', [MockExamController::class, 'restore'])->middleware('module.access:mocks');

    // Mock Questions module (protected by JWT) — GET /mock-questions is now public above
    Route::post('/mock-questions', [MockQuestionController::class, 'store'])->middleware('module.access:mocks');
    Route::get('/mock-questions/{id}', [MockQuestionController::class, 'show'])->middleware('module.access:mocks');
    Route::match(['put', 'post'], '/mock-questions/{id}', [MockQuestionController::class, 'update'])->middleware('module.access:mocks');
    Route::delete('/mock-questions/{id}', [MockQuestionController::class, 'destroy'])->middleware('module.access:mocks');
    Route::post('/mock-questions/{id}/restore', [MockQuestionController::class, 'restore'])->middleware('module.access:mocks');

    // Mock Exam Ratings — GET list (protected by JWT)
    Route::get('/mock-exam-ratings', [MockExamRatingController::class, 'index']);
});
