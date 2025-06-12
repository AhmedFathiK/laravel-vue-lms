<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Concept\StoreRequest;
use App\Http\Requests\Admin\Concept\UpdateRequest;
use App\Models\Concept;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConceptController extends Controller
{
    /**
     * Display a listing of the concepts for a course.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        if (!Gate::allows('view.term')) {
            abort(403);
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
     * Store a newly created concept in storage.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $concept = Concept::create($request->validated());

        return response()->json($concept, 201);
    }

    /**
     * Display the specified concept.
     */
    public function show(Concept $concept): JsonResponse
    {
        if (!Gate::allows('view.term')) {
            abort(403);
        }

        return response()->json($concept);
    }

    /**
     * Update the specified concept in storage.
     */
    public function update(UpdateRequest $request, Concept $concept): JsonResponse
    {
        $concept->update($request->validated());

        return response()->json($concept);
    }

    /**
     * Remove the specified concept from storage.
     */
    public function destroy(Concept $concept): JsonResponse
    {
        if (!Gate::allows('delete.term')) {
            abort(403);
        }

        $concept->delete();

        return response()->json(null, 204);
    }

    /**
     * Get all concept types.
     */
    public function getTypes(): JsonResponse
    {
        if (!Gate::allows('view.term')) {
            abort(403);
        }

        $types = [
            ['value' => 'grammar', 'label' => 'Grammar Rule'],
            ['value' => 'vocabulary', 'label' => 'Vocabulary Group'],
            ['value' => 'pronunciation', 'label' => 'Pronunciation Rule'],
            ['value' => 'idiom', 'label' => 'Idiomatic Expression'],
            ['value' => 'structure', 'label' => 'Sentence Structure'],
            ['value' => 'culture', 'label' => 'Cultural Context'],
        ];

        return response()->json($types);
    }

    /**
     * Translate a concept to a specific locale.
     */
    public function translate(Request $request, Concept $concept): JsonResponse
    {
        if (!Gate::allows('translate.term')) {
            abort(403);
        }

        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'title' => ['required', 'string', 'max:255'],
            'explanation' => ['required', 'string'],
            'examples' => ['nullable', 'array'],
        ]);

        $titles = $concept->getTranslations('title');
        $titles[$validated['locale']] = $validated['title'];
        $concept->setTranslations('title', $titles);

        $explanations = $concept->getTranslations('explanation');
        $explanations[$validated['locale']] = $validated['explanation'];
        $concept->setTranslations('explanation', $explanations);

        if (isset($validated['examples'])) {
            $examples = $concept->getTranslations('examples');
            $examples[$validated['locale']] = $validated['examples'];
            $concept->setTranslations('examples', $examples);
        }

        $concept->save();

        return response()->json($concept);
    }
}
