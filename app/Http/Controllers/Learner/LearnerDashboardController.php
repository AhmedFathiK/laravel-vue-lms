<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\RevisionItem;
use App\Models\UserStudiedLesson;
use App\Models\Concept;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LearnerDashboardController extends Controller
{
    /**
     * Get global and per-course statistics for the learner dashboard.
     */
    public function getStatistics(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $userId = $user->id;

        // 1. Get enrolled courses via active entitlements
        $accessibleCourseIds = Course::whereHas('billingPlans.entitlements', function($q) use ($userId) {
            $q->where('user_id', $userId)
              ->active();
        })->pluck('id');

        $enrolledCourses = Course::whereIn('id', $accessibleCourseIds)
            ->with(['levels.lessons'])
            ->get();

        $courseStats = [];
        $totalCompletedLessons = 0;
        $totalDueReviews = 0;

        foreach ($enrolledCourses as $course) {
            // Calculate progress
            $totalLessons = $course->levels->flatMap->lessons->count();
            $completedLessonsCount = UserStudiedLesson::where('user_id', $userId)
                ->where('course_id', $course->id)
                ->distinct('lesson_id')
                ->count();

            $totalCompletedLessons += $completedLessonsCount;
            $progress = $totalLessons > 0 ? round(($completedLessonsCount / $totalLessons) * 100) : 0;

            // Get due reviews for this course
            $dueReviews = RevisionItem::where('user_id', $userId)
                ->where('due_date', '<=', now())
                ->whereHasMorph(
                    'revisionable',
                    [Term::class, Concept::class],
                    function ($query) use ($course) {
                        $query->where('course_id', $course->id);
                    }
                )->count();

            $totalDueReviews += $dueReviews;

            $courseStats[] = [
                'id' => $course->id,
                'title' => $course->title, // Translatable handle by model toArray
                'image' => $course->thumbnail,
                'progress' => $progress,
                'due_reviews' => $dueReviews,
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessonsCount,
            ];
        }

        // 2. Determine Next Best Action (NBA)
        $nba = $this->determineNextBestAction($user, $enrolledCourses, $totalDueReviews);

        // 3. Global stats
        $globalStats = [
            'enrolled_courses_count' => $enrolledCourses->count(),
            'total_completed_lessons' => $totalCompletedLessons,
            'total_due_reviews' => $totalDueReviews,
            'points' => $user->points()->sum('points'), // Simple point sum
        ];

        return response()->json([
            'global' => $globalStats,
            'courses' => $courseStats,
            'next_best_action' => $nba,
        ]);
    }

    /**
     * Determine the Next Best Action for the learner.
     */
    private function determineNextBestAction($user, $courses, $totalDueReviews)
    {
        // Priority 1: Reviews due
        if ($totalDueReviews > 0) {
            return [
                'type' => 'review',
                'title' => 'Time to Review!',
                'description' => "You have {$totalDueReviews} items due for review. Keep your memory fresh!",
                'action_label' => 'Start Review',
                'route' => '/revisions',
                'params' => [],
            ];
        }

        // Priority 2: Continue last studied course
        $lastStudied = UserStudiedLesson::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($lastStudied) {
            $course = $courses->firstWhere('id', $lastStudied->course_id);
            if ($course) {
                // Find next lesson? For now, just link to the course
                return [
                    'type' => 'continue',
                    'title' => 'Continue Learning',
                    'description' => "Pick up where you left off in \"{$course->title}\".",
                    'action_label' => 'Continue Course',
                    'route' => "/my-courses/{$course->id}",
                    'params' => ['courseId' => $course->id],
                ];
            }
        }

        // Priority 3: Start an enrolled course with 0 progress
        foreach ($courses as $course) {
            $hasProgress = UserStudiedLesson::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->exists();

            if (!$hasProgress) {
                return [
                    'type' => 'start',
                    'title' => 'Start Something New',
                    'description' => "You've enrolled in \"{$course->title}\". Ready to begin?",
                    'action_label' => 'Start Learning',
                    'route' => "/my-courses/{$course->id}",
                    'params' => ['courseId' => $course->id],
                ];
            }
        }

        // Default: Browse more courses
        return [
            'type' => 'browse',
            'title' => 'Explore More',
            'description' => 'Looking for something else to learn? Check out our available courses.',
            'action_label' => 'Browse Courses',
            'route' => '/browse-courses',
            'params' => [],
        ];
    }
}
