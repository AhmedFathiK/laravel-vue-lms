<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourseCategory\DeleteCategoryRequest;
use App\Http\Requests\Admin\CourseCategory\IndexCategoryRequest;
use App\Http\Requests\Admin\CourseCategory\ShowCategoryRequest;
use App\Http\Requests\Admin\CourseCategory\StoreCategoryRequest;
use App\Http\Requests\Admin\CourseCategory\UpdateCategoryRequest;
use App\Http\Resources\CourseCategoryResource;
use App\Models\CourseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseCategoryController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    public function index(IndexCategoryRequest $request): JsonResponse
    {
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
        $sortField = $request->get('sort_field', 'id');
        $sortDirection = $request->get('sort_direction', 'asc');

        if ($sortField === 'name') {
            // For JSON fields, we need to use orderByRaw
            $query->orderByRaw("JSON_EXTRACT(name, '$." . app()->getLocale() . "') $sortDirection");
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        // Apply pagination
        $perPage = $request->get('per_page', 15);
        $categories = $query->withCount('courses')->paginate($perPage);

        return response()->json([
            'categories' => CourseCategoryResource::collection($categories->items()),
            'total' => $categories->total(),
            'currentPage' => $categories->currentPage(),
            'per_page' => $categories->perPage(),
            'lastPage' => $categories->lastPage(),
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $category = CourseCategory::create($validated);

        return response()->json(new CourseCategoryResource($category), 201);
    }

    /**
     * Display the specified category.
     */
    public function show(CourseCategory $courseCategory, ShowCategoryRequest $request): JsonResponse
    {
        // Load relationship counts
        $courseCategory = CourseCategory::withCount('courses')->find($courseCategory->id);

        return response()->json(new CourseCategoryResource($courseCategory));
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategoryRequest $request, CourseCategory $courseCategory): JsonResponse
    {
        $validated = $request->validated();
        $courseCategory->update($validated);

        return response()->json(new CourseCategoryResource($courseCategory));
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(CourseCategory $courseCategory, DeleteCategoryRequest $request): JsonResponse
    {
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
