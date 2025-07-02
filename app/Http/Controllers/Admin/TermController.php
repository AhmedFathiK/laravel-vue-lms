<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Term\StoreRequest;
use App\Http\Requests\Admin\Term\UpdateRequest;
use App\Http\Resources\TermResource;
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

        // Apply search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                // For JSON fields, we need to use whereRaw
                $q->whereRaw("JSON_EXTRACT(term, '$.*') LIKE ?", ['%' . $search . '%'])
                    ->orWhereRaw("JSON_EXTRACT(definition, '$.*') LIKE ?", ['%' . $search . '%'])
                    ->orWhereRaw("JSON_EXTRACT(example, '$.*') LIKE ?", ['%' . $search . '%']);
            });
        }

        // Apply sorting
        $sortField = $request->get('sort_field', 'term');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Apply pagination
        $perPage = $request->get('per_page', 15);
        $terms = $query->paginate($perPage);

        return response()->json([
            'items' => TermResource::collection($terms->items()),
            'total_items' => $terms->total(),
            'current_page' => $terms->currentPage(),
            'per_page' => $terms->perPage(),
            'last_page' => $terms->lastPage(),
        ]);
    }

    /**
     * Display a listing of the terms for a course.
     * This is designed for select fields with search ability
     */
    public function getTermsForSelectFields(Request $request, Course $course): JsonResponse
    {
        if (!Gate::allows('view.terms')) {
            abort(403);
        }

        $query = $course->terms();

        // Apply search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                // For JSON fields, we need to use whereRaw
                $q->whereRaw("JSON_EXTRACT(term, '$.*') LIKE ?", [$search . '%'])
                    ->orWhereRaw("JSON_EXTRACT(definition, '$.*') LIKE ?", ['%' . $search . '%'])
                    ->orWhereRaw("JSON_EXTRACT(example, '$.*') LIKE ?", ['%' . $search . '%']);
            });
        }
        $terms = $query->limit(5)->get();
        return response()->json(TermResource::collection($terms));
    }

    /**
     * Store a newly created term in storage.
     */
    public function store(StoreRequest $request, Course $course): JsonResponse
    {
        try {
            DB::beginTransaction();

            $term = Term::create($request->validated());

            DB::commit();

            return response()->json(new TermResource($term), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create term: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified term.
     */
    public function show(Course $course, Term $term): JsonResponse
    {
        if (!Gate::allows('view.terms')) {
            abort(403);
        }

        return response()->json(new TermResource($term));
    }

    /**
     * Update the specified term in storage.
     */
    public function update(UpdateRequest $request, Course $course, Term $term): JsonResponse
    {
        try {
            DB::beginTransaction();

            $term->update($request->validated());

            DB::commit();

            return response()->json(new TermResource($term));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update term: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified term from storage.
     */
    public function destroy(Course $course, Term $term): JsonResponse
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
    public function translate(Request $request, Course $course, Term $term): JsonResponse
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

        // For term, use the translation field
        $term->setTranslation('term', $validated['locale'], $validated['translation']);

        // For definition, use setTranslation directly
        $term->setTranslation('definition', $validated['locale'], $validated['definition']);

        // For example, check if it exists first
        if (isset($validated['example'])) {
            $term->setTranslation('example', $validated['locale'], $validated['example']);
        }

        // Save the term
        $term->save();

        return response()->json(new TermResource($term));
    }
}
