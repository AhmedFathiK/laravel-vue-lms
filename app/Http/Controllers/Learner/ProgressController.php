<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\LearnerProgress;
use App\Models\Slide;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    /**
     * Get all progress for the current user within a course.
     */
    public function courseProgress(Course $course): JsonResponse
    {
        // Check if enrolled
        $enrollment = CourseEnrollment::where('user_id', Auth::id())
            ->where('course_id', $course->id)
            ->first();

        if (!$enrollment) {
            return response()->json(['error' => 'Not enrolled in this course'], 404);
        }

        // Get progress data
        $progress = LearnerProgress::where('user_id', Auth::id())
            ->where('course_id', $course->id)
            ->get();

        // Get completion statistics
        $totalSlides = Slide::whereHas('lesson.level', function ($query) use ($course) {
            $query->where('course_id', $course->id)
                ->where('status', 'published');
        })->count();

        $completedSlides = $progress->where('is_completed', true)->count();

        $completionPercentage = $totalSlides > 0
            ? round(($completedSlides / $totalSlides) * 100, 2)
            : 0;

        // Update enrollment completion percentage
        if ($enrollment) {
            $enrollment->completion_percentage = $completionPercentage;

            // Mark as completed if 100%
            if ($completionPercentage >= 100) {
                $enrollment->is_completed = true;
                $enrollment->completed_at = Carbon::now();
            }

            $enrollment->save();
        }

        return response()->json([
            'total_slides' => $totalSlides,
            'completed_slides' => $completedSlides,
            'completion_percentage' => $completionPercentage,
            'is_completed' => $completionPercentage >= 100,
            'progress' => $progress
        ]);
    }

    /**
     * Get progress for a specific lesson.
     */
    public function lessonProgress(Lesson $lesson): JsonResponse
    {
        // Check if enrolled in the course
        $enrollment = CourseEnrollment::where('user_id', Auth::id())
            ->where('course_id', $lesson->level->course_id)
            ->first();

        if (!$enrollment) {
            return response()->json(['error' => 'Not enrolled in this course'], 404);
        }

        // Get progress data
        $progress = LearnerProgress::where('user_id', Auth::id())
            ->where('lesson_id', $lesson->id)
            ->get();

        // Get completion statistics
        $totalSlides = $lesson->slides()->count();
        $completedSlides = $progress->where('is_completed', true)->count();

        $completionPercentage = $totalSlides > 0
            ? round(($completedSlides / $totalSlides) * 100, 2)
            : 0;

        return response()->json([
            'total_slides' => $totalSlides,
            'completed_slides' => $completedSlides,
            'completion_percentage' => $completionPercentage,
            'is_completed' => $completionPercentage >= 100,
            'progress' => $progress
        ]);
    }

    /**
     * Update progress for a specific slide.
     */
    public function updateSlideProgress(Request $request, Slide $slide): JsonResponse
    {
        // Validate request
        $validated = $request->validate([
            'response_data' => ['nullable', 'array'],
            'is_correct' => ['nullable', 'boolean'],
            'is_completed' => ['required', 'boolean'],
        ]);

        // Get the lesson, level and course
        $lesson = $slide->lesson;
        $level = $lesson->level;
        $course = $level->course;

        // Check if enrolled in the course
        $enrollment = CourseEnrollment::where('user_id', Auth::id())
            ->where('course_id', $course->id)
            ->first();

        if (!$enrollment) {
            // Auto-enroll user if not already enrolled
            $enrollment = CourseEnrollment::create([
                'user_id' => Auth::id(),
                'course_id' => $course->id,
                'enrolled_at' => Carbon::now(),
                'last_accessed_at' => Carbon::now(),
            ]);
        } else {
            // Update last accessed time
            $enrollment->last_accessed_at = Carbon::now();
            $enrollment->save();
        }

        // Find or create progress record
        $progress = LearnerProgress::firstOrNew([
            'user_id' => Auth::id(),
            'slide_id' => $slide->id,
        ]);

        // Set or update values
        $progress->course_id = $course->id;
        $progress->level_id = $level->id;
        $progress->lesson_id = $lesson->id;

        if (isset($validated['response_data'])) {
            $progress->response_data = $validated['response_data'];
        }

        if (isset($validated['is_correct'])) {
            $progress->is_correct = $validated['is_correct'];
        }

        $progress->is_completed = $validated['is_completed'];
        $progress->last_attempted_at = Carbon::now();

        // If this is an existing record, increment attempt count
        if ($progress->exists) {
            $progress->attempt_count = $progress->attempt_count + 1;
        }

        $progress->save();

        // Update course completion percentage
        $this->updateCourseCompletionPercentage($course->id);

        return response()->json([
            'message' => 'Progress updated successfully',
            'progress' => $progress
        ]);
    }

    /**
     * Reset progress for a specific lesson.
     */
    public function resetLessonProgress(Lesson $lesson): JsonResponse
    {
        // Delete all progress records for this lesson
        $deleted = LearnerProgress::where('user_id', Auth::id())
            ->where('lesson_id', $lesson->id)
            ->delete();

        // Update course completion percentage
        $this->updateCourseCompletionPercentage($lesson->level->course_id);

        return response()->json([
            'message' => 'Lesson progress has been reset',
            'records_deleted' => $deleted
        ]);
    }

    /**
     * Reset progress for an entire course.
     */
    public function resetCourseProgress(Course $course): JsonResponse
    {
        // Delete all progress records for this course
        $deleted = LearnerProgress::where('user_id', Auth::id())
            ->where('course_id', $course->id)
            ->delete();

        // Update enrollment record
        $enrollment = CourseEnrollment::where('user_id', Auth::id())
            ->where('course_id', $course->id)
            ->first();

        if ($enrollment) {
            $enrollment->completion_percentage = 0;
            $enrollment->is_completed = false;
            $enrollment->completed_at = null;
            $enrollment->save();
        }

        return response()->json([
            'message' => 'Course progress has been reset',
            'records_deleted' => $deleted
        ]);
    }

    /**
     * Get statistics for all user progress.
     */
    public function userStatistics(): JsonResponse
    {
        $userId = Auth::id();

        // Get overall statistics
        $totalEnrollments = CourseEnrollment::where('user_id', $userId)->count();
        $completedCourses = CourseEnrollment::where('user_id', $userId)
            ->where('is_completed', true)
            ->count();

        $totalAttemptedSlides = LearnerProgress::where('user_id', $userId)->count();
        $totalCompletedSlides = LearnerProgress::where('user_id', $userId)
            ->where('is_completed', true)
            ->count();

        $correctAnswers = LearnerProgress::where('user_id', $userId)
            ->where('is_correct', true)
            ->count();

        $totalAnswered = LearnerProgress::where('user_id', $userId)
            ->whereNotNull('is_correct')
            ->count();

        $accuracy = $totalAnswered > 0
            ? round(($correctAnswers / $totalAnswered) * 100, 2)
            : 0;

        return response()->json([
            'enrollments' => [
                'total' => $totalEnrollments,
                'completed' => $completedCourses,
                'in_progress' => $totalEnrollments - $completedCourses,
            ],
            'slides' => [
                'attempted' => $totalAttemptedSlides,
                'completed' => $totalCompletedSlides,
            ],
            'answers' => [
                'total_answered' => $totalAnswered,
                'correct' => $correctAnswers,
                'accuracy_percentage' => $accuracy,
            ]
        ]);
    }

    /**
     * Helper method to update course completion percentage.
     */
    private function updateCourseCompletionPercentage(int $courseId): void
    {
        $userId = Auth::id();

        // Get completion statistics
        $totalSlides = Slide::whereHas('lesson.level', function ($query) use ($courseId) {
            $query->where('course_id', $courseId)
                ->where('status', 'published');
        })->count();

        $completedSlides = LearnerProgress::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('is_completed', true)
            ->count();

        $completionPercentage = $totalSlides > 0
            ? round(($completedSlides / $totalSlides) * 100, 2)
            : 0;

        // Update enrollment
        $enrollment = CourseEnrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if ($enrollment) {
            $enrollment->completion_percentage = $completionPercentage;

            // Mark as completed if 100%
            if ($completionPercentage >= 100 && !$enrollment->is_completed) {
                $enrollment->is_completed = true;
                $enrollment->completed_at = Carbon::now();
            } elseif ($completionPercentage < 100 && $enrollment->is_completed) {
                // If previously completed but now under 100%, update status
                $enrollment->is_completed = false;
                $enrollment->completed_at = null;
            }

            $enrollment->save();
        }
    }
}
