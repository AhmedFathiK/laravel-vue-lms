<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Lesson;

use App\Http\Resources\Learner\LessonResource;

class CoursesContentController extends Controller
{
    /**
     * Display a specific lesson's content.
     */
    public function showLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $user = Auth::user();
        $course = $lesson->level->course;

        // Check enrollment
        $enrollment = CourseEnrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->with('userSubscription')
            ->first();

        if (!$enrollment) {
            return response()->json([
                "error" => "You are not enrolled in this course.",
                "course_id" => $course->id
            ], 403);
        }

        // Check subscription status
        if ($enrollment->userSubscription && !$enrollment->userSubscription->isActive()) {
            return response()->json([
                "error" => "Your subscription for this course has expired.",
                "expired" => true,
                "course_id" => $course->id,
                "ends_at" => $enrollment->userSubscription->ends_at
            ], 403);
        }

        // Check access to this specific lesson (e.g., if previous lessons are completed)
        // For simplicity, we might skip the strict "previous completed" check here if the frontend handles it,
        // but ideally, we should check it.
        // Using the model's helper:
        if (!$lesson->isAccessibleToUser($user)) {
            return response()->json(["error" => "You do not have access to this lesson yet."], 403);
        }

        // Load slides with content
        $lesson->load([
            'slides' => function ($query) {
                $query->orderBy('sort_order')
                    ->with(['question', 'term']);
            }
        ]);

        return response()->json(new LessonResource($lesson));
    }

    /**
     * Display a user's courses content.
     */
    public function show(Request $request, Course $course): JsonResponse
    {
        // Check if user is enrolled
        $enrollment = CourseEnrollment::where('user_id', Auth::id())
            ->where('course_id', $course->id)
            ->with('userSubscription')
            ->first();

        if (!$enrollment) {
            return response()->json(["error" => "You are not enrolled in this course."], 403);
        }

        // Check if subscription is active
        if ($enrollment->userSubscription && !$enrollment->userSubscription->isActive()) {
            return response()->json([
                "error" => "Your subscription for this course has expired.",
                "expired" => true,
                "ends_at" => $enrollment->userSubscription->ends_at
            ], 403);
        }

        $course->load([
            'levels' => function ($query) {
                $query->orderBy('sort_order');
            },
            'levels.lessons' => function ($query) {
                $query->orderBy('sort_order')
                    ->withCount(['studiedBy as is_completed' => function ($query) {
                        $query->where('user_id', Auth::id());
                    }]);
            },
            'levels.exams' => function ($query) {
                $query->withCount(['attempts as is_completed' => function ($query) {
                    $query->where('user_id', Auth::id())
                        ->where('is_passed', true);
                }]);
            }
        ]);

        $courseData = $course->toArray();

        // Calculate locked status logic:
        // Item N is locked if Item N-1 is not completed.
        // First item of first level is unlocked.

        $previousCompleted = true; // First item is unlocked by default

        foreach ($courseData['levels'] as &$level) {
            $items = [];

            // Add lessons
            if (isset($level['lessons'])) {
                foreach ($level['lessons'] as $lesson) {
                    $lesson['type'] = 'lesson';
                    $lesson['icon'] = 'tabler-book';
                    // is_completed is count, convert to boolean
                    $lesson['completed'] = $lesson['is_completed'] > 0;
                    $items[] = $lesson;
                }
            }

            // Add exams
            if (isset($level['exams'])) {
                foreach ($level['exams'] as $exam) {
                    $exam['type'] = 'exam';
                    $exam['icon'] = 'tabler-clipboard-check';
                    $exam['completed'] = $exam['is_completed'] > 0;
                    $items[] = $exam;
                }
            }

            // Sort items. Assuming exams come after lessons as we don't have unified sort_order.
            // If there was a unified sort_order, we would sort by it here.
            // usort($items, function($a, $b) { return $a['sort_order'] <=> $b['sort_order']; });

            // Apply locked status
            foreach ($items as &$item) {
                $item['locked'] = !$previousCompleted;

                if ($item['locked']) {
                    $previousCompleted = false;
                } else {
                    $previousCompleted = $item['completed'];
                }
            }
            unset($item);

            $level['items'] = $items;
            unset($level['lessons']);
            unset($level['exams']);
        }
        unset($level);

        return response()->json($courseData);
    }
}
