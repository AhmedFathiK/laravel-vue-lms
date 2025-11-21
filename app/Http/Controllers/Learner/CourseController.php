<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Http\Resources\Learner\CourseResource;
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
        $query = Course::where('status', 'published')->with('category');

        // Filter by search query
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('course_category_id', $request->category_id);
        }

        // Filter by pricing
        if ($request->has('is_free')) {
            $query->where('is_free', $request->boolean('is_free'));
        }

        // Apply sorting
        $sort = $request->input('sort', 'created_at');
        $order = $request->input('order', 'desc');

        if ($sort === 'popularity') {
            $query->withCount('enrollments')->orderBy('enrollments_count', $order);
        } elseif ($sort === 'title') {
            $query->orderBy('title', $order);
        } else {
            $query->orderBy($sort, $order);
        }

        // Apply pagination
        $perPage = $request->get('perPage', 9);
        $courses = $query->paginate($perPage);

        return response()->json([
            'data' => CourseResource::collection($courses->items()),
            'total' => $courses->total(),
        ]);
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

        return response()->json(new CourseResource($course));
    }
}
