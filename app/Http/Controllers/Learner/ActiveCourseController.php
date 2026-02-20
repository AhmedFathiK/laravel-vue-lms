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
        /** @var \App\Models\User $user */
        $user = Auth::user();

        \Illuminate\Support\Facades\Log::info('ActiveCourseController::show called for user ' . $user->id . ' | active_course_id: ' . $user->active_course_id);

        if (!$user->active_course_id) {
            \Illuminate\Support\Facades\Log::info('No active_course_id, returning 404');
            return response()->json(['message' => 'No active course selected.'], 404);
        }

        $course = $user->activeCourse;

        if (!$course) {
            \Illuminate\Support\Facades\Log::info('Active course model not found for ID ' . $user->active_course_id . ', clearing ID');
            // Cleanup invalid reference
            $user->active_course_id = null;
            $user->save();
            return response()->json(['message' => 'Active course not found.'], 404);
        }

        \Illuminate\Support\Facades\Log::info('Delegating to CoursesContentController for course ' . $course->id);

        // Delegate to CoursesContentController to ensure consistent response structure
        // This ensures the dashboard sees exactly the same content/progress as the course page
        try {
            $controller = app(CoursesContentController::class);
            $response = $controller->show($request, $course);

            \Illuminate\Support\Facades\Log::info('CoursesContentController returned status: ' . $response->status());

            // Check for error response from controller (it might return JsonResponse with 403)
            if ($response->status() === 403) {
                \Illuminate\Support\Facades\Log::info('Received 403 from delegate, clearing active course');
                $user->active_course_id = null;
                $user->save();
                // Return the error response so the frontend knows why
                return $response;
            }

            return $response;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Exception in ActiveCourseController: ' . $e->getMessage());
            // If exception has 403 code
            if ($e->getCode() == 403) {
                \Illuminate\Support\Facades\Log::info('Exception code 403 caught, clearing active course');
                $user->active_course_id = null;
                $user->save();
                return response()->json(['error' => $e->getMessage()], 403);
            }
            throw $e;
        }
    }

    /**
     * Set the active course for the user.
     */
    public function update(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id'
        ]);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $courseId = $request->course_id;

        // Verify the user has access to this course
        // Check 1: Direct Enrollment
        $isEnrolled = $user->enrollments()
            ->where('course_id', $courseId)
            ->exists();

        // Check 2: Active Entitlement (Subscription/Plan)
        $entitlements = $user->entitlements()
            ->active()
            ->whereHas('billingPlan.courses', function ($q) use ($courseId) {
                $q->where('courses.id', $courseId);
            })
            ->get();

        $hasEntitlement = $entitlements->filter(function ($entitlement) {
            return $entitlement->isActive();
        })->isNotEmpty();

        if (!$isEnrolled && !$hasEntitlement) {
            return response()->json(['message' => 'You do not have access to this course.'], 403);
        }

        $user->active_course_id = $courseId;
        $user->save();

        return response()->json(['message' => 'Active course updated']);
    }
}
