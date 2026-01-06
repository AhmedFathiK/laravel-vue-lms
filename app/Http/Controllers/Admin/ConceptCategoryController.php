<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConceptCategory\StoreRequest;
use App\Http\Requests\Admin\ConceptCategory\UpdateRequest;
use App\Models\ConceptCategory;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConceptCategoryController extends Controller
{
    /**
     * Display a listing of the concept categories for a course.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        if (!Gate::allows('view.terms')) { // Reusing terms permission for concepts
            abort(403);
        }

        $query = $course->conceptCategories();

        if ($request->has('title')) {
            $query->where('title->en', 'like', '%' . $request->title . '%');
        }

        $categories = $query->get();

        return response()->json($categories);
    }

    /**
     * Store a newly created concept category in storage.
     */
    public function store(StoreRequest $request, Course $course): JsonResponse
    {
        $data = $request->validated();
        $data['course_id'] = $course->id;

        $category = ConceptCategory::create($data);

        return response()->json($category, 201);
    }

    /**
     * Display the specified concept category.
     */
    public function show(Course $course, ConceptCategory $category): JsonResponse
    {
        if (!Gate::allows('view.terms')) {
            abort(403);
        }

        return response()->json($category);
    }

    /**
     * Update the specified concept category in storage.
     */
    public function update(UpdateRequest $request, Course $course, ConceptCategory $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json($category);
    }

    /**
     * Remove the specified concept category from storage.
     */
    public function destroy(Course $course, ConceptCategory $category): JsonResponse
    {
        if (!Gate::allows('delete.terms')) {
            abort(403);
        }

        $category->delete();

        return response()->json(null, 204);
    }
}
