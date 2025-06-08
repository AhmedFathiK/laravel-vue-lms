<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnrollmentController extends Controller
{
    /**
     * Get all enrollments for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CourseEnrollment::where('user_id', Auth::id())
            ->with(['course' => function ($query) {
                $query->where('status', 'published')
                    ->select('id', 'title', 'description', 'thumbnail');
            }]);

        // Apply filters
        if ($request->has('completed')) {
            $query->where('is_completed', $request->boolean('completed'));
        }

        // Apply sorting
        $sortField = $request->get('sort_field', 'last_accessed_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $enrollments = $query->get();

        return response()->json($enrollments);
    }

    /**
     * Enroll the current user in a course.
     */
    public function enroll(Course $course): JsonResponse
    {
        // Check if the course is published
        if ($course->status !== 'published') {
            return response()->json(['error' => 'Course not available'], 404);
        }

        // Check if already enrolled
        $existingEnrollment = CourseEnrollment::where('user_id', Auth::id())
            ->where('course_id', $course->id)
            ->first();

        if ($existingEnrollment) {
            return response()->json(['message' => 'Already enrolled in this course', 'enrollment' => $existingEnrollment]);
        }

        // Create new enrollment
        $enrollment = CourseEnrollment::create([
            'user_id' => Auth::id(),
            'course_id' => $course->id,
            'enrolled_at' => Carbon::now(),
            'last_accessed_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Successfully enrolled in course',
            'enrollment' => $enrollment
        ], 201);
    }

    /**
     * Update the last accessed time for a course enrollment.
     */
    public function updateLastAccessed(Course $course): JsonResponse
    {
        $enrollment = CourseEnrollment::where('user_id', Auth::id())
            ->where('course_id', $course->id)
            ->first();

        if (!$enrollment) {
            return response()->json(['error' => 'Not enrolled in this course'], 404);
        }

        $enrollment->last_accessed_at = Carbon::now();
        $enrollment->save();

        return response()->json(['message' => 'Last accessed time updated', 'enrollment' => $enrollment]);
    }

    /**
     * Get the enrollment details for a specific course.
     */
    public function show(Course $course): JsonResponse
    {
        $enrollment = CourseEnrollment::where('user_id', Auth::id())
            ->where('course_id', $course->id)
            ->first();

        if (!$enrollment) {
            return response()->json(['error' => 'Not enrolled in this course'], 404);
        }

        return response()->json($enrollment);
    }

    /**
     * Mark a course as completed.
     */
    public function markAsCompleted(Course $course): JsonResponse
    {
        $enrollment = CourseEnrollment::where('user_id', Auth::id())
            ->where('course_id', $course->id)
            ->first();

        if (!$enrollment) {
            return response()->json(['error' => 'Not enrolled in this course'], 404);
        }

        $enrollment->is_completed = true;
        $enrollment->completion_percentage = 100;
        $enrollment->completed_at = Carbon::now();
        $enrollment->save();

        return response()->json(['message' => 'Course marked as completed', 'enrollment' => $enrollment]);
    }
}
