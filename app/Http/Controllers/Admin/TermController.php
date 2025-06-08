<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Term\StoreRequest;
use App\Http\Requests\Term\UpdateRequest;
use App\Models\Course;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TermController extends Controller
{
    /**
     * Display a listing of the terms for a course.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        if (!Gate::allows('view.term')) {
            abort(403);
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
     * Store a newly created term in storage.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $term = Term::create($request->validated());

        return response()->json($term, 201);
    }

    /**
     * Display the specified term.
     */
    public function show(Term $term): JsonResponse
    {
        if (!Gate::allows('view.term')) {
            abort(403);
        }

        return response()->json($term);
    }

    /**
     * Update the specified term in storage.
     */
    public function update(UpdateRequest $request, Term $term): JsonResponse
    {
        $term->update($request->validated());

        return response()->json($term);
    }

    /**
     * Remove the specified term from storage.
     */
    public function destroy(Term $term): JsonResponse
    {
        if (!Gate::allows('delete.term')) {
            abort(403);
        }

        $term->delete();

        return response()->json(null, 204);
    }

    /**
     * Set revision schedule for a term.
     */
    public function setRevisionSchedule(Request $request, Term $term): JsonResponse
    {
        if (!Gate::allows('configure_revision.term')) {
            abort(403);
        }

        $validated = $request->validate([
            'next_revision_date' => ['required', 'date'],
            'revision_counter' => ['required', 'integer', 'min:0'],
        ]);

        $term->update($validated);

        return response()->json($term);
    }

    /**
     * Mark a term as revised and calculate the next revision date.
     */
    public function markRevised(Term $term): JsonResponse
    {
        if (!Gate::allows('configure_revision.term')) {
            abort(403);
        }

        // Implement SuperMemo 2 algorithm
        $counter = $term->revision_counter + 1;

        // Calculate interval (in days)
        $interval = 1;
        if ($counter == 1) {
            $interval = 1;
        } elseif ($counter == 2) {
            $interval = 6;
        } else {
            // Calculate using the formula: interval = last_interval * 2.5
            $lastInterval = Carbon::now()->diffInDays(Carbon::parse($term->last_revision_date));
            $interval = round($lastInterval * 2.5);
        }

        $term->last_revision_date = Carbon::now();
        $term->next_revision_date = Carbon::now()->addDays($interval);
        $term->revision_counter = $counter;
        $term->save();

        return response()->json($term);
    }

    /**
     * Get terms due for revision.
     */
    public function getDueRevisions(Request $request): JsonResponse
    {
        if (!Gate::allows('view.term')) {
            abort(403);
        }

        $query = Term::query()
            ->whereNotNull('next_revision_date')
            ->where('next_revision_date', '<=', Carbon::now());

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        $perPage = $request->get('per_page', 15);
        $terms = $query->paginate($perPage);

        return response()->json($terms);
    }

    /**
     * Translate a term to a specific locale.
     */
    public function translate(Request $request, Term $term): JsonResponse
    {
        if (!Gate::allows('translate.term')) {
            abort(403);
        }

        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'translation' => ['required', 'string'],
            'definition' => ['required', 'string'],
        ]);

        $translations = $term->getTranslations('translation');
        $translations[$validated['locale']] = $validated['translation'];
        $term->setTranslations('translation', $translations);

        $definitions = $term->getTranslations('definition');
        $definitions[$validated['locale']] = $validated['definition'];
        $term->setTranslations('definition', $definitions);

        $term->save();

        return response()->json($term);
    }
}
