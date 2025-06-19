<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Term\StoreRequest;
use App\Http\Requests\Admin\Term\UpdateRequest;
use App\Models\Course;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TermController extends Controller
{
    /**
     * Display a listing of the terms for a course.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        if (!Gate::allows('view.terms')) {
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
        try {
            DB::beginTransaction();

            $term = Term::create($request->validated());

            DB::commit();

            return response()->json($term, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create term: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified term.
     */
    public function show(Term $term): JsonResponse
    {
        if (!Gate::allows('view.terms')) {
            abort(403);
        }

        return response()->json($term);
    }

    /**
     * Update the specified term in storage.
     */
    public function update(UpdateRequest $request, Term $term): JsonResponse
    {
        try {
            DB::beginTransaction();

            $term->update($request->validated());

            DB::commit();

            return response()->json($term);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update term: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified term from storage.
     */
    public function destroy(Term $term): JsonResponse
    {
        if (!Gate::allows('delete.terms')) {
            abort(403);
        }

        $term->delete();

        return response()->json(null, 204);
    }

    /**
     * Translate a term to a specific locale.
     */
    public function translate(Request $request, Term $term): JsonResponse
    {
        if (!Gate::allows('translate.terms')) {
            abort(403);
        }

        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'translation' => ['required', 'string'],
            'definition' => ['required', 'string'],
            'example' => ['nullable', 'string'],
        ]);

        // For definition, use setTranslation directly
        $term->setTranslation('definition', $validated['locale'], $validated['definition']);

        // For example, check if it exists first
        if (isset($validated['example'])) {
            $term->setTranslation('example', $validated['locale'], $validated['example']);
        }

        // Save the term
        $term->save();

        return response()->json($term);
    }
}
