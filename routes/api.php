<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\Learner\RevisionController;
use App\Http\Controllers\Admin\ConceptCategoryController;
use App\Http\Controllers\Admin\ConceptController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\LevelController;
use App\Http\Controllers\Admin\SlideController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TermController;
use App\Http\Controllers\Admin\TrophyController;
use App\Http\Controllers\Admin\LeaderboardController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\BillingPlanController;
use App\Http\Controllers\Admin\UserEntitlementController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Learner\LearnerUserController;
use App\Http\Controllers\Learner\LearnerCourseController;
use App\Http\Controllers\Learner\GamificationController;
use App\Http\Controllers\Learner\ProgressController;
use App\Http\Controllers\Learner\LearnerReceiptController;
use App\Http\Controllers\Learner\CoursesContentController;
use App\Http\Controllers\Learner\LearnerEntitlementController;
use App\Http\Controllers\Learner\ActiveCourseController;
use App\Http\Controllers\Learner\LearnerDashboardController;
use App\Http\Controllers\PaymentGatewayController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

// SPA Authentication (Session-based)
Route::prefix('auth')->group(function () {
    // Public routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    // Protected routes
    Route::middleware(['auth:sanctum', \App\Http\Middleware\SetLocale::class])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });
});

// API Token Authentication
Route::prefix('token')->group(function () {
    // Public routes
    Route::post('/create', [TokenController::class, 'createToken']);
    Route::post('/register', [TokenController::class, 'register']);

    // Protected routes
    Route::middleware(['auth:sanctum', \App\Http\Middleware\SetLocale::class])->group(function () {
        Route::get('/user', [TokenController::class, 'user']);
        Route::delete('/revoke', [TokenController::class, 'revokeToken']);
        Route::delete('/revoke-all', [TokenController::class, 'revokeAllTokens']);
        Route::delete('/revoke/{tokenId}', [TokenController::class, 'revokeSpecificToken']);
        Route::get('/list', [TokenController::class, 'getTokens']);
    });
});

/*
|--------------------------------------------------------------------------
| General User Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', \App\Http\Middleware\SetLocale::class])->group(function () {
    // Profile and general user routes
    Route::get('/profile', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'auth_type' => $request->user()->currentAccessToken() ? 'token' : 'session'
        ]);
    });

    Route::post('/user/locale', [LearnerUserController::class, 'updateLocale']);
    Route::post('/user/active-course', [ActiveCourseController::class, 'update']);
    Route::get('/user/active-course', [ActiveCourseController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::get('public/landing-page-settings', [SettingController::class, 'getPublicLandingPageSettings']);

use App\Http\Controllers\Admin\DashboardController;

Route::middleware(['auth:sanctum', \App\Http\Middleware\SetLocale::class])->prefix('admin')->group(function () {
    // Dashboard Stats
    Route::get('/dashboard/stats', [DashboardController::class, 'index']);

    // App Settings
    Route::get('settings/{group}', [SettingController::class, 'show']);
    Route::post('settings/{group}', [SettingController::class, 'update']);
    Route::post('settings/{group}/upload', [SettingController::class, 'upload']);

    // User & Role Management
    Route::get('users/select-fields', [UserController::class, 'getUsersForSelectFields']);
    Route::apiResource('users', UserController::class);
    Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::post('users/{user}/assign-role', [UserController::class, 'assignRole']);
    Route::get('roles', [UserController::class, 'getRoles']);
    Route::apiResource('roles', RoleController::class);
    Route::get('permissions', [RoleController::class, 'getPermissions']);

    // Feature Management
    Route::get('features', [\App\Http\Controllers\Admin\FeatureController::class, 'index']);

    // System Management
    Route::get('trash', [\App\Http\Controllers\Admin\TrashController::class, 'index']);
    Route::post('trash/bulk-restore', [\App\Http\Controllers\Admin\TrashController::class, 'bulkRestore']);
    Route::post('trash/bulk-delete', [\App\Http\Controllers\Admin\TrashController::class, 'bulkDelete']);
    Route::post('trash/{id}/restore', [\App\Http\Controllers\Admin\TrashController::class, 'restore']);
    Route::delete('trash/{id}', [\App\Http\Controllers\Admin\TrashController::class, 'destroy']);
    Route::post('trash/empty', [\App\Http\Controllers\Admin\TrashController::class, 'emptyTrash']);
    Route::get('trash/model-types', [\App\Http\Controllers\Admin\TrashController::class, 'getModelTypes']);

    // Exam Grading
    Route::get('/exam-responses/pending', [\App\Http\Controllers\Admin\ExamResponseController::class, 'pendingResponses']);
    Route::get('/exam-responses/{response}', [\App\Http\Controllers\Admin\ExamResponseController::class, 'show']);
    Route::post('/exam-responses/{response}/grade', [\App\Http\Controllers\Admin\ExamResponseController::class, 'gradeResponse']);

    // Course Structure Management
    Route::get('courses/select-fields', [CourseController::class, 'getCoursesForSelectFields']);
    Route::apiResource('courses', CourseController::class);
    Route::apiResource('course-categories', \App\Http\Controllers\Admin\CourseCategoryController::class);

    // Courses Content
    Route::prefix('/courses/{course}')->scopeBindings()->group(function () {
        // Assessment System (Course-bound)
        Route::apiResource('/exams', \App\Http\Controllers\Admin\ExamController::class);
        Route::apiResource('/exam-sections', \App\Http\Controllers\Admin\ExamSectionController::class);
        Route::post('/exam-sections/{section}/questions', [\App\Http\Controllers\Admin\ExamSectionController::class, 'addQuestion']);
        Route::delete('/exam-sections/{section}/questions/{question}', [\App\Http\Controllers\Admin\ExamSectionController::class, 'removeQuestion']);
        Route::post('/exam-sections/{section}/reorder-questions', [\App\Http\Controllers\Admin\ExamSectionController::class, 'reorderQuestions']);

        // Exam Questions (Removed - Refactored to use canonical questions via pivot)


        // Legacy / Course-bound questions
        Route::get('/questions/types', [\App\Http\Controllers\Admin\QuestionController::class, 'getTypes']);
        Route::get('/questions/select-fields', [\App\Http\Controllers\Admin\QuestionController::class, 'getQuestionsForSelectFields']);
        Route::apiResource('/questions', \App\Http\Controllers\Admin\QuestionController::class);

        // Question Contexts
        Route::post('/question-contexts/{questionContext}/questions', [\App\Http\Controllers\Admin\QuestionContextController::class, 'attachQuestions']);
        Route::delete('/question-contexts/{questionContext}/questions/{question}', [\App\Http\Controllers\Admin\QuestionContextController::class, 'detachQuestion']);
        Route::apiResource('/question-contexts', \App\Http\Controllers\Admin\QuestionContextController::class);

        // Billing Plans
        Route::get('billing-plans', [BillingPlanController::class, 'index']);
        Route::post('billing-plans', [BillingPlanController::class, 'store']);
        Route::get('billing-plans/{billingPlan}', [BillingPlanController::class, 'show']);
        Route::put('billing-plans/{billingPlan}', [BillingPlanController::class, 'update']);
        Route::delete('billing-plans/{billingPlan}', [BillingPlanController::class, 'destroy']);
        Route::post('billing-plans/{billingPlan}/toggle-status', [BillingPlanController::class, 'toggleStatus']);

        Route::prefix('/levels')->group(function () {
            Route::get('/', [LevelController::class, 'index']);
            Route::post('/order', [LevelController::class, 'updateOrder']);
            Route::post('/', [LevelController::class, 'store']);
            Route::get('{level}', [LevelController::class, 'show']);
            Route::put('{level}', [LevelController::class, 'update']);
            Route::delete('{level}', [LevelController::class, 'destroy']);
            Route::patch('{level}/unlock', [LevelController::class, 'toggleUnlock']);

            // Lesson Management (nested under levels)
            Route::prefix('/{level}/lessons')->group(function () {
                Route::get('/', [LessonController::class, 'index']);
                Route::post('/order', [LessonController::class, 'updateOrder']);
                Route::post('/', [LessonController::class, 'store']);
                Route::get('/{lesson}', [LessonController::class, 'show']);
                Route::put('/{lesson}', [LessonController::class, 'update']);
                Route::delete('/{lesson}', [LessonController::class, 'destroy']);
                Route::patch('/{lesson}/configure', [LessonController::class, 'configure']);

                // Slide Management (nested under lessons)
                Route::prefix('/{lesson}/slides')->group(function () {
                    Route::get('/', [SlideController::class, 'index']);
                    Route::put('/order', [SlideController::class, 'updateOrder']);
                    Route::post('/', [SlideController::class, 'store']);
                    Route::get('{slide}', [SlideController::class, 'show']);
                    Route::put('{slide}', [SlideController::class, 'update']);
                    Route::delete('{slide}', [SlideController::class, 'destroy']);
                });
            });
        });

        // Assessment System (Moved out)

        // Content Management (nested under courses)
        // Terms
        Route::prefix('/terms')->group(function () {
            Route::get('/', [TermController::class, 'index']);
            Route::get('/select-fields', [TermController::class, 'getTermsForSelectFields']);
            Route::post('/', [TermController::class, 'store']);
            Route::get('/{term}', [TermController::class, 'show']);
            Route::put('/{term}', [TermController::class, 'update']);
            Route::delete('/{term}', [TermController::class, 'destroy']);
        });

        // Concepts
        Route::prefix('/concepts')->group(function () {
            Route::get('/', [ConceptController::class, 'index']);
            Route::get('/select-fields', [ConceptController::class, 'getConceptsForSelectFields']);
            Route::post('/', [ConceptController::class, 'store']);
            Route::get('/{concept}', [ConceptController::class, 'show']);
            Route::put('/{concept}', [ConceptController::class, 'update']);
            Route::delete('/{concept}', [ConceptController::class, 'destroy']);
            Route::post('/{concept}/translate', [ConceptController::class, 'translate']);
        });

        // Concept Categories
        Route::prefix('/concept-categories')->group(function () {
            Route::get('/', [ConceptCategoryController::class, 'index']);
            Route::post('/', [ConceptCategoryController::class, 'store']);
            Route::get('/{category}', [ConceptCategoryController::class, 'show']);
            Route::put('/{category}', [ConceptCategoryController::class, 'update']);
            Route::delete('/{category}', [ConceptCategoryController::class, 'destroy']);
        });
    });

    Route::apiResource('course-categories', \App\Http\Controllers\Admin\CourseCategoryController::class);
    Route::get('slides/types', [SlideController::class, 'getTypes']);
    Route::get('concepts/types', [ConceptController::class, 'getTypes']);


    // Gamification Management
    Route::get('trophies/trigger-types', [TrophyController::class, 'getTriggerTypes']);
    Route::get('trophies/rarity-levels', [TrophyController::class, 'getRarityLevels']);
    Route::apiResource('trophies', TrophyController::class);
    Route::apiResource('leaderboards', LeaderboardController::class);
    Route::get('leaderboard-reset-frequencies', [LeaderboardController::class, 'getResetFrequencies']);
    Route::get('leaderboards/{leaderboard}/entries', [LeaderboardController::class, 'viewEntries']);
    Route::post('leaderboards/{leaderboard}/recalculate', [LeaderboardController::class, 'recalculateRanks']);
    Route::post('leaderboards/{leaderboard}/reset', [LeaderboardController::class, 'resetLeaderboard']);

    // Payment & Entitlement Management
    Route::apiResource('billing-plans', BillingPlanController::class);
    Route::post('billing-plans/{billingPlan}/toggle-status', [BillingPlanController::class, 'toggleStatus']);

    Route::apiResource('payments', PaymentController::class);
    Route::get('receipts/statistics', [ReceiptController::class, 'statistics']);
    Route::apiResource('receipts', ReceiptController::class);
    Route::get('receipts/{receipt}/download', [ReceiptController::class, 'download']);
    Route::post('receipts/{receipt}/resend', [ReceiptController::class, 'resend']);
    Route::post('receipts/{receipt}/regenerate-pdf', [\App\Http\Controllers\Admin\ReceiptController::class, 'regeneratePdf']);
    Route::post('receipts/{receipt}/void', [\App\Http\Controllers\Admin\ReceiptController::class, 'void']);

    // Expense Management
    Route::apiResource('expenses', \App\Http\Controllers\Admin\ExpenseController::class);
    Route::apiResource('expense-categories', \App\Http\Controllers\Admin\ExpenseCategoryController::class);

    // Financial Analytics
    Route::get('financial-analytics/stats', [\App\Http\Controllers\Admin\FinancialAnalyticsController::class, 'getStats']);
    Route::get('financial-analytics/chart-data', [\App\Http\Controllers\Admin\FinancialAnalyticsController::class, 'getChartData']);
    Route::get('financial-analytics/weekly-stats', [\App\Http\Controllers\Admin\FinancialAnalyticsController::class, 'getWeeklyStats']);

    Route::apiResource('user-entitlements', UserEntitlementController::class);
    Route::post('user-entitlements/{userEntitlement}/cancel', [UserEntitlementController::class, 'cancel']);

    // Access Control Management
    // Route::post('courses/{course}/access-type', [CourseAccessController::class, 'setCourseAccessType']);
    // Route::post('levels/{level}/access-type', [CourseAccessController::class, 'setLevelAccessType']);
    // Route::post('lessons/{lesson}/access-type', [CourseAccessController::class, 'setLessonAccessType']);
    // Route::get('courses/{course}/free-content', [CourseAccessController::class, 'getFreeCourseContent']);
    // Route::post('batch-update-free-access', [CourseAccessController::class, 'batchUpdateFreeAccess']);
});

/*
|--------------------------------------------------------------------------
| Learner Routes
|--------------------------------------------------------------------------
*/

// Protected Learner Routes (Must be defined before public courses/{course} to avoid conflict)
Route::middleware('auth:sanctum')->prefix('learner')->group(function () {
    Route::get('courses/enrolled', [LearnerEntitlementController::class, 'getEnrolledCourses']);
});

// Public Learner Routes
Route::prefix('learner')->group(function () {
    // Courses Browsing
    Route::get('courses', [LearnerCourseController::class, 'index']);
    Route::get('courses/{course}', [LearnerCourseController::class, 'show']);
});

Route::middleware('auth:sanctum')->prefix('learner')->group(function () {
    // Course Content
    Route::get('statistics', [LearnerDashboardController::class, 'getStatistics']);
    Route::get('course-content', [ActiveCourseController::class, 'show']);
    Route::get('dashboard/active-stats', [LearnerDashboardController::class, 'getActiveStats']);
    Route::get('courses/enrolled', [LearnerEntitlementController::class, 'getEnrolledCourses']);
    Route::get('lessons/{lesson}/content', [CoursesContentController::class, 'showLesson']);


    // Progress Tracking
    Route::post('lessons/{lesson}/complete', [ProgressController::class, 'completeLesson']);


    // Entitlement & Access
    Route::get('entitlements', [LearnerEntitlementController::class, 'index']);
    Route::get('entitlements/{entitlement}', [LearnerEntitlementController::class, 'show']);
    Route::post('acquire-entitlement', [LearnerEntitlementController::class, 'acquire']);
    Route::post('courses/{course}/enroll', [LearnerEntitlementController::class, 'enroll']);
    Route::post('entitlements/{entitlement}/cancel', [LearnerEntitlementController::class, 'cancel']);
    Route::post('entitlements/{entitlement}/renew', [LearnerEntitlementController::class, 'renew']);
    Route::get('entitlements/{entitlement}/upgrade/{newPlan}/calculate', [LearnerEntitlementController::class, 'calculateUpgrade']);
    Route::post('entitlements/{entitlement}/upgrade/{newPlan}', [LearnerEntitlementController::class, 'upgrade']);
    Route::get('courses/{course}/free-content', [LearnerEntitlementController::class, 'getFreeCourseContent']);
    Route::get('courses/{course}/billing-plans', [LearnerEntitlementController::class, 'getAvailablePlans']);
    Route::get('levels/{level}/access-status', [LearnerEntitlementController::class, 'checkLevelAccess']);
    Route::get('lessons/{lesson}/access-status', [LearnerEntitlementController::class, 'checkLessonAccess']);

    // Billing & Receipts
    Route::get('receipts', [LearnerReceiptController::class, 'index']);
    Route::get('receipts/{receipt}', [LearnerReceiptController::class, 'show']);
    Route::get('receipts/{receipt}/download', [LearnerReceiptController::class, 'download']);

    // Assessment System
    Route::get('exams', [\App\Http\Controllers\Learner\ExamController::class, 'index']);
    Route::get('exams/{exam}', [\App\Http\Controllers\Learner\ExamController::class, 'show']);
    Route::post('exams/{exam}/start', [\App\Http\Controllers\Learner\ExamController::class, 'startAttempt']);
    Route::get('exams/{exam}/attempts', [\App\Http\Controllers\Learner\ExamController::class, 'examAttempts']);
    Route::get('exam-attempts/{attempt}', [\App\Http\Controllers\Learner\ExamController::class, 'showAttempt']);
    Route::post('exam-attempts/{attempt}/complete', [\App\Http\Controllers\Learner\ExamController::class, 'completeAttempt']);
    Route::post('exam-attempts/{attempt_id}/questions/{question_id}/response', [\App\Http\Controllers\Learner\ExamController::class, 'submitResponse']);


    Route::get('placement-test', [\App\Http\Controllers\Learner\ExamController::class, 'getPlacementTest']);
});

/*
|--------------------------------------------------------------------------
| Specialized Feature Routes
|--------------------------------------------------------------------------
*/

// Revision System
Route::middleware('auth:sanctum')->prefix('revision')->group(function () {
    Route::get('items', [RevisionController::class, 'index']);
    Route::get('due-items', [RevisionController::class, 'getDueItems']);
    Route::post('add-item', [RevisionController::class, 'addItem']);

    // Updated Routes
    Route::post('response', [RevisionController::class, 'recordResponse']);
    Route::get('practice', [RevisionController::class, 'generatePractice']);
    Route::get('statistics', [RevisionController::class, 'getStatistics']);
    Route::get('grammar-topics', [RevisionController::class, 'getGrammarTopics']);
});

// Gamification System
Route::middleware('auth:sanctum')->prefix('gamification')->group(function () {
    Route::get('trophies', [GamificationController::class, 'getUserTrophies']);
    Route::get('available-trophies', [GamificationController::class, 'getAvailableTrophies']);
    Route::get('trophy-statistics', [GamificationController::class, 'getTrophyStatistics']);
    Route::get('points', [GamificationController::class, 'getUserPoints']);
    Route::get('leaderboards/{leaderboard}', [GamificationController::class, 'viewLeaderboard']);
    Route::get('rankings', [GamificationController::class, 'getUserLeaderboardRankings']);
});

/*
|--------------------------------------------------------------------------
| Payment Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('payments/checkout', [PaymentGatewayController::class, 'checkout']);
    Route::get('payments/methods', [PaymentGatewayController::class, 'getPaymentMethods']);
});

Route::get('payments/callback', [PaymentGatewayController::class, 'callback'])->name('payments.callback');
Route::get('payments/error', [PaymentGatewayController::class, 'error'])->name('payments.error');

Route::post('webhooks/myfatoorah', [\App\Http\Controllers\PaymentWebhookController::class, 'handleMyFatoorah']);
