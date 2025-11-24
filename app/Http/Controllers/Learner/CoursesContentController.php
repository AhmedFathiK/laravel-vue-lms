<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CoursesContentController extends Controller
{
    /**
     * Display a user's courses content.
     */
    public function show(Request $request, CourseEnrollment $enrollment): JsonResponse
    {
        if ($enrollment->user_id != Auth::id()) {
            return response()->json(["error" => "Course not found."], 404);
        }

        $query = Course::where('id', $enrollment->course_id)
            ->with([
                'levels',
                'levels.lessons'
            ]);

        $course = $query->first();

        return response()->json($course);
    }
}
