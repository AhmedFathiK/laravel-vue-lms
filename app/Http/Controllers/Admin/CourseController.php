<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Course\StoreCourseRequest;
use App\Http\Requests\Admin\Course\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\CourseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    /**
     * Display a listing of the courses.
     */
    public function index(Request $request): JsonResponse
    {
        if (!Gate::allows('view.courses')) {
            abort(403);
        }

        $query = Course::query();

        // Include category relationship and counts
        $query->with('category')->withCount(['levels', 'enrollments as subscriptions_count']);

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

        if ($request->has('is_free')) {
            $isFree = $request->boolean('is_free');
            if ($isFree) {
                $query->whereHas('subscriptionPlans', function ($q) {
                    $q->where('is_free', true)->where('is_active', true);
                });
            } else {
                $query->whereDoesntHave('subscriptionPlans', function ($q) {
                    $q->where('is_free', true)->where('is_active', true);
                });
            }
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
        $sortBy = $request->get('sort_by', 'created_at');
        $orderBy = $request->get('order_by', 'desc');

        if ($sortBy === 'levels') {
            $sortBy = 'levels_count';
        } elseif ($sortBy === 'subscriptions') {
            $sortBy = 'subscriptions_count';
        }

        $query->orderBy($sortBy, $orderBy);

        // Apply pagination
        $perPage = $request->get('per_page', 15);
        $courses = $query->paginate($perPage);

        return response()->json([
            'items' => CourseResource::collection($courses->items()),
            'totalItems' => $courses->total(),
            'currentPage' => $courses->currentPage(),
            'perPage' => $courses->perPage(),
            'lastPage' => $courses->lastPage(),
            'stats' => [
                'total' => Course::count(),
                'active' => Course::where('status', 'active')->count(),
                'draft' => Course::where('status', 'draft')->count(),
                'subscription' => Course::whereHas('subscriptionPlans', function ($q) {
                    $q->where('plan_type', 'recurring');
                })->count(),
            ],
        ]);
    }

    /**
     * Display a listing of the courses.
     */
    public function getCoursesForSelectFields(Request $request): JsonResponse
    {
        if (!Gate::allows('view.courses')) {
            abort(403);
        }

        $query = Course::query();

        // Include category relationship and counts
        $query->with('category')->withCount(['levels', 'enrollments as subscriptions_count']);

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

        if ($request->has('is_free')) {
            $isFree = $request->boolean('is_free');
            if ($isFree) {
                $query->whereHas('subscriptionPlans', function ($q) {
                    $q->where('is_free', true)->where('is_active', true);
                });
            } else {
                $query->whereDoesntHave('subscriptionPlans', function ($q) {
                    $q->where('is_free', true)->where('is_active', true);
                });
            }
        }

        // Apply search
        if ($request->has('search')) {
            $search = strtolower($request->get('search'));
            $query->where(function ($q) use ($search) {
                // For JSON fields, we need to use whereRaw
                $q->whereRaw("LOWER(JSON_EXTRACT(title, '$.*')) LIKE ?", ['%' . $search . '%'])
                    ->orWhereRaw("LOWER(JSON_EXTRACT(description, '$.*')) LIKE ?", ['%' . $search . '%']);
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $orderBy = $request->get('order_by', 'desc');

        $query->orderBy($sortBy, $orderBy);

        // Include category relationship
        $query->with('category:id,name');

        // Apply pagination
        $query->limit(5);
        $courses = $query->get();

        return response()->json(CourseResource::collection($courses));
    }

    /**
     * Store a newly created course in storage.
     */
    public function store(StoreCourseRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');
            $path = $file->store('course-thumbnails', 'public');
            $data['thumbnail'] = Storage::url($path);
        }

        $course = Course::create($data);

        return response()->json(new CourseResource($course), 201);
    }

    /**
     * Display the specified course.
     */
    public function show(Course $course): JsonResponse
    {
        if (!Gate::allows('view.courses')) {
            abort(403);
        }

        $course->load(['levels', 'category']);

        // Also load subscription plans for this course
        $course->load(['subscriptionPlans' => function ($query) {
            $query->where('is_active', true);
        }]);

        return response()->json(new CourseResource($course));
    }

    /**
     * Update the specified course in storage.
     */
    public function update(UpdateCourseRequest $request, Course $course): JsonResponse
    {
        $data = $request->validated();

        // Handle thumbnail deletion
        if ($request->has('delete_thumbnail') && $request->boolean('delete_thumbnail')) {
            // Delete the existing thumbnail file
            if ($course->thumbnail && Storage::disk('public')->exists(str_replace('/storage/', '', $course->thumbnail))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $course->thumbnail));
            }

            // Set thumbnail to null
            $data['thumbnail'] = null;
        }
        // Handle thumbnail upload
        else if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail if exists
            if ($course->thumbnail && Storage::disk('public')->exists(str_replace('/storage/', '', $course->thumbnail))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $course->thumbnail));
            }

            $file = $request->file('thumbnail');
            $path = $file->store('course-thumbnails', 'public');
            $data['thumbnail'] = Storage::url($path);
        } else {
            // If no new thumbnail is provided, remove it from the data array
            // to prevent overwriting the existing thumbnail with null
            unset($data['thumbnail']);
        }

        $course->update($data);

        return response()->json(new CourseResource($course));
    }

    /**
     * Remove the specified course from storage.
     */
    public function destroy(Course $course): JsonResponse
    {
        if (!Gate::allows('delete.courses')) {
            abort(403);
        }

        $course->delete();

        return response()->json(null, 204);
    }

    /**
     * Get subscription plans for a course.
     */
    public function getSubscriptionPlans(Course $course): JsonResponse
    {
        if (!Gate::allows('view.courses')) {
            abort(403);
        }

        $plans = $course->subscriptionPlans()
            ->where('is_active', true)
            ->orderBy('price')
            ->get();

        return response()->json($plans);
    }
}
