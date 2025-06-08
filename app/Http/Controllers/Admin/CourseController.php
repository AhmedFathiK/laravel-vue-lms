<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Course\StoreRequest;
use App\Http\Requests\Course\UpdateRequest;
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

        $course->load('levels');

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
