<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Course\StoreRequest;
use App\Http\Requests\Admin\Course\UpdateRequest;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    /**
     * Display a listing of the courses.
     */
    public function index(Request $request): JsonResponse
    {
        if (!Gate::allows('view.course')) {
            abort(403);
        }

        $query = Course::query();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('featured')) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        if ($request->has('category')) {
            $query->where('course_category_id', $request->category);
        }

        // Apply search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                // For JSON fields, we need to use whereRaw
                $q->whereRaw("JSON_EXTRACT(title, '$.*') LIKE ?", ['%' . $search . '%'])
                    ->orWhereRaw("JSON_EXTRACT(description, '$.*') LIKE ?", ['%' . $search . '%']);
            });
        }

        // Apply sorting
        $sortBy = $request->get('sortBy', 'sort_order');
        $orderBy = $request->get('orderBy', 'asc');

        $query->orderBy($sortBy, $orderBy);

        // Include category relationship
        $query->with('category');

        // Apply pagination
        $perPage = $request->get('perPage', 15);
        $courses = $query->paginate($perPage);

        return response()->json([
            'courses' => $courses->items(),
            'totalCourses' => $courses->total(),
            'currentPage' => $courses->currentPage(),
            'perPage' => $courses->perPage(),
            'lastPage' => $courses->lastPage(),
        ]);
    }

    /**
     * Store a newly created course in storage.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $course = Course::create($request->validated());

        return response()->json($course, 201);
    }

    /**
     * Display the specified course.
     */
    public function show(Course $course): JsonResponse
    {
        if (!Gate::allows('view.course')) {
            abort(403);
        }

        $course->load(['levels', 'category']);

        return response()->json($course);
    }

    /**
     * Update the specified course in storage.
     */
    public function update(UpdateRequest $request, Course $course): JsonResponse
    {
        $course->update($request->validated());

        return response()->json($course);
    }

    /**
     * Remove the specified course from storage.
     */
    public function destroy(Course $course): JsonResponse
    {
        if (!Gate::allows('delete.course')) {
            abort(403);
        }

        $course->delete();

        return response()->json(null, 204);
    }
}
