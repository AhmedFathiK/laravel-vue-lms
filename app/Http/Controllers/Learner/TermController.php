<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TermController extends Controller
{
    /**
     * Get terms for a specific course for learners.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        // Check if the course is published
        if ($course->status !== 'published') {
            return response()->json(['error' => 'Course not available'], 404);
        }

        $query = $course->terms();

        // Apply filters
        if ($request->has('term')) {
            $query->where('term', 'like', '%' . $request->term . '%');
        }

        // Apply sorting
        $sortField = $request->get('sort_field', 'term');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Apply pagination
        $perPage = $request->get('per_page', 15);
        $terms = $query->paginate($perPage);

        return response()->json($terms);
    }

    /**
     * Get a specific term for learners.
     */
    public function show(Term $term): JsonResponse
    {
        // Check if the course is published
        if ($term->course->status !== 'published') {
            return response()->json(['error' => 'Course not available'], 404);
        }

        return response()->json($term);
    }

    /**
     * Get terms that are due for revision for the current user.
     */
    public function dueForRevision(Request $request): JsonResponse
    {
        $query = Term::query()
            ->whereNotNull('next_revision_date')
            ->where('next_revision_date', '<=', Carbon::now())
            ->whereHas('course', function ($query) {
                $query->where('status', 'published');
            });

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        $perPage = $request->get('per_page', 15);
        $terms = $query->paginate($perPage);

        return response()->json($terms);
    }
}
