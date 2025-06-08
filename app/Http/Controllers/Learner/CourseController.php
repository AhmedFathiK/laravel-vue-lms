<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    /**
     * Get a list of all published courses.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Course::where('status', 'published');

        // Apply filters
        if ($request->has('featured')) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        // Apply sorting
        $sortField = $request->get('sort_field', 'sort_order');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Apply pagination
        $perPage = $request->get('per_page', 15);
        $courses = $query->paginate($perPage);

        return response()->json($courses);
    }

    /**
     * Get a specific published course with its levels.
     */
    public function show(Course $course): JsonResponse
    {
        // Check if the course is published
        if ($course->status !== 'published') {
            return response()->json(['error' => 'Course not available'], 404);
        }

        $course->load(['levels' => function ($query) {
            $query->where('status', 'published')
                ->where('is_unlocked', true)
                ->orderBy('sort_order');
        }]);

        return response()->json($course);
    }
}
