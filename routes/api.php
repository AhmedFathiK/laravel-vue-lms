<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TokenController;
use App\Http\Controllers\Learner\RevisionController;
use App\Http\Controllers\Admin\ConceptController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\LessonController;
use App\Http\Controllers\Admin\LevelController;
use App\Http\Controllers\Admin\SlideController;
use App\Http\Controllers\Admin\TermController;
use App\Http\Controllers\Admin\TrophyController;
use App\Http\Controllers\Admin\LeaderboardController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\UserSubscriptionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Learner\ConceptController as LearnerConceptController;
use App\Http\Controllers\Learner\CourseController as LearnerCourseController;
use App\Http\Controllers\Learner\EnrollmentController;
use App\Http\Controllers\Learner\GamificationController;
use App\Http\Controllers\Learner\LessonController as LearnerLessonController;
use App\Http\Controllers\Learner\LevelController as LearnerLevelController;
use App\Http\Controllers\Learner\ProgressController;
use App\Http\Controllers\Learner\ReceiptController as LearnerReceiptController;
use App\Http\Controllers\Learner\TermController as LearnerTermController;

/*
|--------------------------------------------------------------------------
| SPA Authentication Routes (Session-based)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    // Public routes
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    // Protected routes (session-based)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });
});

/*
|--------------------------------------------------------------------------
| API Token Authentication Routes
|--------------------------------------------------------------------------
*/
Route::prefix('token')->group(function () {
    // Public routes
    Route::post('/create', [TokenController::class, 'createToken']);
    Route::post('/register', [TokenController::class, 'register']);

    // Protected routes (token-based)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', [TokenController::class, 'user']);
        Route::delete('/revoke', [TokenController::class, 'revokeToken']);
        Route::delete('/revoke-all', [TokenController::class, 'revokeAllTokens']);
        Route::delete('/revoke/{tokenId}', [TokenController::class, 'revokeSpecificToken']);
        Route::get('/list', [TokenController::class, 'getTokens']);
    });
});

/*
|--------------------------------------------------------------------------
| Example Protected API Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // This route works with both session and token authentication
    Route::get('/profile', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
            'auth_type' => $request->user()->currentAccessToken() ? 'token' : 'session'
        ]);
    });

    // Example resource routes
    Route::apiResource('posts', 'PostController');
    Route::apiResource('categories', 'CategoryController');

    // User routes
    Route::post('/user/locale', [UserController::class, 'updateLocale']);
});

// Admin API Routes
Route::middleware(['auth:sanctum', 'role:super_admin|admin|content_manager|instructor'])->prefix('admin')->group(function () {
    // Course Management
    Route::apiResource('courses', CourseController::class);

    // Level Management
    Route::get('courses/{course}/levels', [LevelController::class, 'index']);
    Route::post('courses/{course}/levels/order', [LevelController::class, 'updateOrder']);
    Route::apiResource('levels', LevelController::class)->except(['index']);
    Route::patch('levels/{level}/unlock', [LevelController::class, 'toggleUnlock']);

    // Lesson Management
    Route::get('levels/{level}/lessons', [LessonController::class, 'index']);
    Route::post('levels/{level}/lessons/order', [LessonController::class, 'updateOrder']);
    Route::apiResource('lessons', LessonController::class)->except(['index']);
    Route::patch('lessons/{lesson}/configure', [LessonController::class, 'configure']);

    // Slide Management
    Route::get('lessons/{lesson}/slides', [SlideController::class, 'index']);
    Route::post('lessons/{lesson}/slides/order', [SlideController::class, 'updateOrder']);
    Route::apiResource('slides', SlideController::class)->except(['index']);
    Route::get('slides/types', [SlideController::class, 'getTypes']);

    // Term Management
    Route::get('courses/{course}/terms', [TermController::class, 'index']);
    Route::apiResource('terms', TermController::class)->except(['index']);
    Route::patch('terms/{term}/revision', [TermController::class, 'setRevisionSchedule']);
    Route::post('terms/{term}/mark-revised', [TermController::class, 'markRevised']);
    Route::get('terms/due-revisions', [TermController::class, 'getDueRevisions']);
    Route::post('terms/{term}/translate', [TermController::class, 'translate']);

    // Concept Management
    Route::get('courses/{course}/concepts', [ConceptController::class, 'index']);
    Route::apiResource('concepts', ConceptController::class)->except(['index']);
    Route::get('concepts/types', [ConceptController::class, 'getTypes']);
    Route::post('concepts/{concept}/translate', [ConceptController::class, 'translate']);

    // Gamification - Trophy Management
    Route::apiResource('trophies', TrophyController::class);
    Route::get('trophy-trigger-types', [TrophyController::class, 'getTriggerTypes']);
    Route::get('trophy-rarity-levels', [TrophyController::class, 'getRarityLevels']);

    // Gamification - Leaderboard Management
    Route::apiResource('leaderboards', LeaderboardController::class);
    Route::get('leaderboard-reset-frequencies', [LeaderboardController::class, 'getResetFrequencies']);
    Route::get('leaderboards/{leaderboard}/entries', [LeaderboardController::class, 'viewEntries']);
    Route::post('leaderboards/{leaderboard}/recalculate', [LeaderboardController::class, 'recalculateRanks']);
    Route::post('leaderboards/{leaderboard}/reset', [LeaderboardController::class, 'resetLeaderboard']);
});

// Admin Assessment System routes
Route::middleware(['auth:sanctum', 'role:admin|supervisor'])->prefix('admin')->group(function () {
    // Question bank routes
    Route::apiResource('questions', \App\Http\Controllers\Admin\QuestionController::class);

    // Exam routes
    Route::apiResource('exams', \App\Http\Controllers\Admin\ExamController::class);

    // Exam section routes
    Route::apiResource('exam-sections', \App\Http\Controllers\Admin\ExamSectionController::class);
    Route::post('exam-sections/{section}/questions', [\App\Http\Controllers\Admin\ExamSectionController::class, 'addQuestion']);
    Route::delete('exam-sections/{section}/questions/{question}', [\App\Http\Controllers\Admin\ExamSectionController::class, 'removeQuestion']);
    Route::post('exam-sections/{section}/reorder-questions', [\App\Http\Controllers\Admin\ExamSectionController::class, 'reorderQuestions']);

    // Exam response routes (for grading writing questions)
    Route::get('exam-responses/pending', [\App\Http\Controllers\Admin\ExamResponseController::class, 'pendingResponses']);
    Route::get('exam-responses/{response}', [\App\Http\Controllers\Admin\ExamResponseController::class, 'show']);
    Route::post('exam-responses/{response}/grade', [\App\Http\Controllers\Admin\ExamResponseController::class, 'gradeResponse']);
});

// Admin Payment & Subscription routes
Route::middleware(['auth:sanctum', 'permission:view.payment'])->prefix('admin')->group(function () {
    // Payment routes
    Route::apiResource('payments', PaymentController::class);

    // Receipt routes
    Route::apiResource('receipts', ReceiptController::class)->only(['index', 'show']);
    Route::get('receipts/{receipt}/download', [ReceiptController::class, 'download']);

    // Subscription plan routes
    Route::apiResource('subscription-plans', SubscriptionPlanController::class);

    // User subscription routes
    Route::apiResource('user-subscriptions', UserSubscriptionController::class);
    Route::post('user-subscriptions/{userSubscription}/cancel', [UserSubscriptionController::class, 'cancel']);
});

// Learner API Routes
Route::middleware('auth:sanctum')->prefix('learner')->group(function () {
    // Course Routes
    Route::get('courses', [LearnerCourseController::class, 'index']);
    Route::get('courses/{course}', [LearnerCourseController::class, 'show']);

    // Level Routes
    Route::get('levels/{level}', [LearnerLevelController::class, 'show']);

    // Lesson Routes
    Route::get('lessons/{lesson}', [LearnerLessonController::class, 'show']);

    // Term Routes
    Route::get('courses/{course}/terms', [LearnerTermController::class, 'index']);
    Route::get('terms/{term}', [LearnerTermController::class, 'show']);
    Route::get('terms/due-revisions', [LearnerTermController::class, 'dueForRevision']);

    // Concept Routes
    Route::get('courses/{course}/concepts', [LearnerConceptController::class, 'index']);
    Route::get('concepts/{concept}', [LearnerConceptController::class, 'show']);
    Route::get('courses/{course}/concepts/{type}', [LearnerConceptController::class, 'getByType']);

    // Enrollment Routes
    Route::get('enrollments', [EnrollmentController::class, 'index']);
    Route::post('courses/{course}/enroll', [EnrollmentController::class, 'enroll']);
    Route::get('courses/{course}/enrollment', [EnrollmentController::class, 'show']);
    Route::post('courses/{course}/update-last-accessed', [EnrollmentController::class, 'updateLastAccessed']);
    Route::post('courses/{course}/mark-completed', [EnrollmentController::class, 'markAsCompleted']);

    // Progress Routes
    Route::get('courses/{course}/progress', [ProgressController::class, 'courseProgress']);
    Route::get('lessons/{lesson}/progress', [ProgressController::class, 'lessonProgress']);
    Route::post('slides/{slide}/progress', [ProgressController::class, 'updateSlideProgress']);
    Route::post('lessons/{lesson}/reset-progress', [ProgressController::class, 'resetLessonProgress']);
    Route::post('courses/{course}/reset-progress', [ProgressController::class, 'resetCourseProgress']);
    Route::get('statistics', [ProgressController::class, 'userStatistics']);

    // Receipt Routes (Billing History)
    Route::get('receipts', [LearnerReceiptController::class, 'index']);
    Route::get('receipts/{receipt}', [LearnerReceiptController::class, 'show']);
    Route::get('receipts/{receipt}/download', [LearnerReceiptController::class, 'download']);
});

// Learner Assessment System routes
Route::middleware(['auth:sanctum'])->prefix('learner')->group(function () {
    // Exam routes
    Route::get('exams', [\App\Http\Controllers\Learner\ExamController::class, 'index']);
    Route::get('exams/{exam}', [\App\Http\Controllers\Learner\ExamController::class, 'show']);
    Route::post('exams/{exam}/start', [\App\Http\Controllers\Learner\ExamController::class, 'startAttempt']);
    Route::get('exams/{exam}/attempts', [\App\Http\Controllers\Learner\ExamController::class, 'examAttempts']);

    // Exam attempt routes
    Route::get('exam-attempts/{attempt}', [\App\Http\Controllers\Learner\ExamController::class, 'showAttempt']);
    Route::post('exam-attempts/{attempt}/complete', [\App\Http\Controllers\Learner\ExamController::class, 'completeAttempt']);
    Route::post('exam-attempts/{attempt}/questions/{question}', [\App\Http\Controllers\Learner\ExamController::class, 'submitResponse']);

    // Placement test routes
    Route::get('placement-test', [\App\Http\Controllers\Learner\ExamController::class, 'getPlacementTest']);
});

/*
|--------------------------------------------------------------------------
| Revision System Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('revision')->group(function () {
    // Revision item management
    Route::get('items', [RevisionController::class, 'index']);
    Route::get('due-items', [RevisionController::class, 'getDueItems']);
    Route::post('add-item', [RevisionController::class, 'addItem']);
    Route::post('items/{revisionItem}/response', [RevisionController::class, 'recordResponse']);

    // Mastery progress tracking
    Route::get('mastery-progress', [RevisionController::class, 'getMasteryProgress']);

    // Practice generation
    Route::get('practice', [RevisionController::class, 'generatePractice']);

    // Statistics
    Route::get('statistics', [RevisionController::class, 'getStatistics']);
});

/*
|--------------------------------------------------------------------------
| Gamification System Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->prefix('gamification')->group(function () {
    // Trophy routes
    Route::get('trophies', [GamificationController::class, 'getUserTrophies']);
    Route::get('available-trophies', [GamificationController::class, 'getAvailableTrophies']);
    Route::get('trophy-statistics', [GamificationController::class, 'getTrophyStatistics']);

    // Points routes
    Route::get('points', [GamificationController::class, 'getUserPoints']);

    // Leaderboard routes
    Route::get('leaderboards/{leaderboard}', [GamificationController::class, 'viewLeaderboard']);
    Route::get('rankings', [GamificationController::class, 'getUserLeaderboardRankings']);
});
