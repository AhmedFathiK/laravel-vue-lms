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
        if (!Gate::allows('view.terms')) {
            abort(403);
        }

        $query = $course->concepts()->with('category');

        // Apply filters
        if ($request->has('category_id')) {
            $query->where('concepts.category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('concepts.title->en', 'like', '%' . $search . '%')
                    ->orWhere('concepts.title->ar', 'like', '%' . $search . '%')
                    ->orWhere('concepts.explanation->en', 'like', '%' . $search . '%')
                    ->orWhere('concepts.explanation->ar', 'like', '%' . $search . '%');
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'title');
        $sortDesc = $request->get('sort_desc', '0') == '1';
        $direction = $sortDesc ? 'desc' : 'asc';

        if ($sortBy === 'category.title') {
            $query->leftJoin('concept_categories', 'concepts.category_id', '=', 'concept_categories.id')
                ->orderBy('concept_categories.title', $direction)
                ->select('concepts.*');
        } elseif (in_array($sortBy, ['title', 'explanation'])) {
            // For translatable fields, sort by the current locale (assuming 'en' as default for now)
            $query->orderBy($sortBy . '->en', $direction);
        } else {
            $query->orderBy($sortBy, $direction);
        }

        // Apply pagination
        $perPage = $request->get('per_page', 15);
        $concepts = $query->paginate($perPage);

        return response()->json($concepts);
    }

    /**
     * Display a listing of the concepts for a course.
     * This is designed for select fields with search ability
     */
    public function getConceptsForSelectFields(Request $request, Course $course): JsonResponse
    {
        if (!Gate::allows('view.terms')) {
            abort(403);
        }

        $query = $course->concepts()->with('category');

        // Apply search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('title->en', 'like', '%' . $search . '%');
            });
        }

        $limit = $request->get('limit', 50);
        $concepts = $query->limit($limit)->get();

        return response()->json($concepts);
    }

    /**
     * Store a newly created concept in storage.
     */
    public function store(StoreRequest $request, Course $course): JsonResponse
    {
        $data = $request->validated();
        $data['course_id'] = $course->id;

        if (isset($data['explanation'])) {
            if (is_array($data['explanation'])) {
                foreach ($data['explanation'] as $locale => $content) {
                    $data['explanation'][$locale] = $this->sanitizeHtml($content);
                }
            } else {
                $data['explanation'] = $this->sanitizeHtml($data['explanation']);
            }
        }

        $concept = Concept::create($data);

        return response()->json($concept, 201);
    }

    /**
     * Display the specified concept.
     */
    public function show(Course $course, Concept $concept): JsonResponse
    {
        if (!Gate::allows('view.terms')) {
            abort(403);
        }

        return response()->json($concept);
    }

    /**
     * Update the specified concept in storage.
     */
    public function update(UpdateRequest $request, Course $course, Concept $concept): JsonResponse
    {
        $data = $request->validated();
        $data['course_id'] = $course->id;

        if (isset($data['explanation'])) {
            if (is_array($data['explanation'])) {
                foreach ($data['explanation'] as $locale => $content) {
                    $data['explanation'][$locale] = $this->sanitizeHtml($content);
                }
            } else {
                $data['explanation'] = $this->sanitizeHtml($data['explanation']);
            }
        }

        $concept->update($data);

        return response()->json($concept);
    }

    /**
     * Remove the specified concept from storage.
     */
    public function destroy(Course $course, Concept $concept): JsonResponse
    {
        if (!Gate::allows('delete.terms')) {
            abort(403);
        }

        $concept->delete();

        return response()->json(null, 204);
    }

    /**
     * Translate a concept to a specific locale.
     */
    public function translate(Request $request, Course $course, Concept $concept): JsonResponse
    {
        if (!Gate::allows('translate.terms')) {
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
        $explanations[$validated['locale']] = $this->sanitizeHtml($validated['explanation']);
        $concept->setTranslations('explanation', $explanations);

        if (isset($validated['examples'])) {
            $examples = $concept->getTranslations('examples');
            $examples[$validated['locale']] = $validated['examples'];
            $concept->setTranslations('examples', $examples);
        }

        $concept->save();

        return response()->json($concept);
    }

    /**
     * Sanitize HTML content
     */
    protected function sanitizeHtml($html): string
    {
        if (empty($html)) return '';

        // Allowed tags for concept explanations
        $allowedTags = '<b><i><u><ul><li><ol><p><br><strong><em><span><div><table><thead><tbody><tr><th><td>';

        return strip_tags($html, $allowedTags);
    }
}
