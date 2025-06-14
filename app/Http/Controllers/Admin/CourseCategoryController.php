<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseCategoryResource;
use App\Models\CourseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CourseCategoryController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    public function index(Request $request): JsonResponse
    {
        // Check if user has permission to view categories
        if (Gate::has('view.course_category') && !Gate::allows('view.course_category')) {
            abort(403);
        }

        $query = CourseCategory::query();

        // Apply filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Apply search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                // For JSON fields, we need to use whereRaw
                $q->whereRaw("JSON_EXTRACT(name, '$.*') LIKE ?", ['%' . $search . '%'])
                    ->orWhereRaw("JSON_EXTRACT(description, '$.*') LIKE ?", ['%' . $search . '%']);
            });
        }

        // Apply sorting
        $sortField = $request->get('sortBy', 'sort_order');
        $sortDirection = $request->get('orderBy', 'asc');

        if ($sortField === 'name') {
            // For JSON fields, we need to use orderByRaw
            $query->orderByRaw("JSON_EXTRACT(name, '$." . app()->getLocale() . "') $sortDirection");
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        // Apply pagination
        $perPage = $request->get('perPage', 15);
        $categories = $query->withCount('courses')->paginate($perPage);

        return response()->json([
            'categories' => CourseCategoryResource::collection($categories->items()),
            'total' => $categories->total(),
            'currentPage' => $categories->currentPage(),
            'perPage' => $categories->perPage(),
            'lastPage' => $categories->lastPage(),
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request): JsonResponse
    {
        // Check if user has permission to create categories
        if (Gate::has('create.course_category') && !Gate::allows('create.course_category')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required',
            'description' => 'nullable',
            'slug' => 'nullable|string|unique:course_categories,slug',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        // Generate slug if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category = CourseCategory::create($validated);

        return response()->json(new CourseCategoryResource($category), 201);
    }

    /**
     * Display the specified category.
     */
    public function show(CourseCategory $courseCategory): JsonResponse
    {
        // Check if user has permission to view categories
        if (Gate::has('view.course_category') && !Gate::allows('view.course_category')) {
            abort(403);
        }

        // Load relationship counts
        $courseCategory = CourseCategory::withCount('courses')->find($courseCategory->id);

        return response()->json(new CourseCategoryResource($courseCategory));
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, CourseCategory $courseCategory): JsonResponse
    {
        // Check if user has permission to update categories
        if (Gate::has('update.course_category') && !Gate::allows('update.course_category')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required',
            'description' => 'nullable',
            'slug' => 'nullable|string|unique:course_categories,slug,' . $courseCategory->id,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        // Generate slug if not provided but name is changed
        if (isset($validated['name']) && empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $courseCategory->update($validated);

        return response()->json(new CourseCategoryResource($courseCategory));
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(CourseCategory $courseCategory): JsonResponse
    {
        // Check if user has permission to delete categories
        if (Gate::has('delete.course_category') && !Gate::allows('delete.course_category')) {
            abort(403);
        }

        // Check if category has courses
        if ($courseCategory->courses()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with associated courses',
            ], 422);
        }

        $courseCategory->delete();

        return response()->json(null, 204);
    }
}
