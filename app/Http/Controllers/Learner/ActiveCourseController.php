<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Learner\CourseResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Learner\CoursesContentController;

class ActiveCourseController extends Controller
{
    /**
     * Get the currently active course for the user.
     */
    public function show(Request $request)
    {
        $user = Auth::user();

        if (!$user->active_course_id) {
            return response()->json(null);
        }

        $course = $user->activeCourse;

        if (!$course) {
            // Cleanup invalid reference
            $user->active_course_id = null;
            $user->save();
            return response()->json(null);
        }

        // Delegate to CoursesContentController to ensure consistent response structure
        // This ensures the dashboard sees exactly the same content/progress as the course page
        $controller = app(CoursesContentController::class);
        return $controller->show($request, $course);
    }

    /**
     * Set the active course for the user.
     */
    public function update(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id'
        ]);

        $user = Auth::user();
        $courseId = $request->course_id;

        // Verify the user has access to this course
        // Check 1: Direct Enrollment
        $isEnrolled = $user->enrollments()
            ->where('course_id', $courseId)
            ->exists();

        // Check 2: Active Entitlement (Subscription/Plan)
        $hasEntitlement = $user->entitlements()
            ->active()
            ->whereHas('billingPlan.courses', function ($q) use ($courseId) {
                $q->where('courses.id', $courseId);
            })
            ->exists();

        if (!$isEnrolled && !$hasEntitlement) {
            return response()->json(['message' => 'You do not have access to this course.'], 403);
        }

        $user->active_course_id = $courseId;
        $user->save();

        return response()->json([
            'message' => 'Active course updated successfully.',
            'active_course_id' => $courseId
        ]);
    }
}
