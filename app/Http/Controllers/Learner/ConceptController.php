<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Concept;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConceptController extends Controller
{
    /**
     * Get concepts for a specific course for learners.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        // Check if the course is published
        if ($course->status !== 'published') {
            return response()->json(['error' => 'Course not available'], 404);
        }

        $query = $course->concepts();

        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('title')) {
            $query->where('title->en', 'like', '%' . $request->title . '%');
        }

        // Apply sorting
        $sortField = $request->get('sort_field', 'title->en');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Apply pagination
        $perPage = $request->get('per_page', 15);
        $concepts = $query->paginate($perPage);

        return response()->json($concepts);
    }

    /**
     * Get a specific concept for learners.
     */
    public function show(Concept $concept): JsonResponse
    {
        // Check if the course is published
        if ($concept->course->status !== 'published') {
            return response()->json(['error' => 'Course not available'], 404);
        }

        return response()->json($concept);
    }

    /**
     * Get concepts by type for a course.
     */
    public function getByType(Request $request, Course $course, string $type): JsonResponse
    {
        // Check if the course is published
        if ($course->status !== 'published') {
            return response()->json(['error' => 'Course not available'], 404);
        }

        $query = $course->concepts()->where('type', $type);

        // Apply sorting
        $sortField = $request->get('sort_field', 'title->en');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $concepts = $query->get();

        return response()->json($concepts);
    }
}
