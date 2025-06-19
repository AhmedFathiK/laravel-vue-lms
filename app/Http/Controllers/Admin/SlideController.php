<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Slide\StoreRequest;
use App\Http\Requests\Admin\Slide\UpdateRequest;
use App\Models\Lesson;
use App\Models\Slide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SlideController extends Controller
{
    /**
     * Display a listing of the slides for a lesson.
     */
    public function index(Request $request, Lesson $lesson): JsonResponse
    {
        if (!Gate::allows('view.slides')) {
            abort(403);
        }

        $query = $lesson->slides();

        // Apply filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Apply sorting
        $sortField = $request->get('sort_field', 'sort_order');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $slides = $query->get();

        // Transform slides to ensure translations are properly handled
        $transformedSlides = $slides->map(function ($slide) {
            return [
                'id' => $slide->id,
                'lesson_id' => $slide->lesson_id,
                'type' => $slide->type,
                'content' => $slide->content,
                'options' => $slide->options,
                'correct_answer' => $slide->correct_answer,
                'feedback' => $slide->feedback,
                'sort_order' => $slide->sort_order,
                'created_at' => $slide->created_at,
                'updated_at' => $slide->updated_at,
            ];
        });

        return response()->json($transformedSlides);
    }

    /**
     * Store a newly created slide in storage.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $slide = Slide::create($request->validated());

        return response()->json([
            'id' => $slide->id,
            'lesson_id' => $slide->lesson_id,
            'type' => $slide->type,
            'content' => $slide->content,
            'options' => $slide->options,
            'correct_answer' => $slide->correct_answer,
            'feedback' => $slide->feedback,
            'sort_order' => $slide->sort_order,
            'created_at' => $slide->created_at,
            'updated_at' => $slide->updated_at,
        ], 201);
    }

    /**
     * Display the specified slide.
     */
    public function show(Slide $slide): JsonResponse
    {
        if (!Gate::allows('view.slides')) {
            abort(403);
        }

        return response()->json([
            'id' => $slide->id,
            'lesson_id' => $slide->lesson_id,
            'type' => $slide->type,
            'content' => $slide->content,
            'options' => $slide->options,
            'correct_answer' => $slide->correct_answer,
            'feedback' => $slide->feedback,
            'sort_order' => $slide->sort_order,
            'created_at' => $slide->created_at,
            'updated_at' => $slide->updated_at,
        ]);
    }

    /**
     * Update the specified slide in storage.
     */
    public function update(UpdateRequest $request, Slide $slide): JsonResponse
    {
        $slide->update($request->validated());

        return response()->json([
            'id' => $slide->id,
            'lesson_id' => $slide->lesson_id,
            'type' => $slide->type,
            'content' => $slide->content,
            'options' => $slide->options,
            'correct_answer' => $slide->correct_answer,
            'feedback' => $slide->feedback,
            'sort_order' => $slide->sort_order,
            'created_at' => $slide->created_at,
            'updated_at' => $slide->updated_at,
        ]);
    }

    /**
     * Remove the specified slide from storage.
     */
    public function destroy(Slide $slide): JsonResponse
    {
        if (!Gate::allows('delete.slides')) {
            abort(403);
        }

        $slide->delete();

        return response()->json(null, 204);
    }

    /**
     * Update slide order within a lesson.
     */
    public function updateOrder(Request $request, Lesson $lesson): JsonResponse
    {
        if (!Gate::allows('reorder.slides')) {
            abort(403);
        }

        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:slides,id'],
        ]);

        $order = $request->input('order');
        foreach ($order as $index => $slideId) {
            Slide::where('id', $slideId)
                ->where('lesson_id', $lesson->id)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json(['message' => 'Order updated successfully']);
    }

    /**
     * Get available slide types with descriptions.
     */
    public function getTypes(): JsonResponse
    {
        if (!Gate::allows('view.slides')) {
            abort(403);
        }

        $types = [
            [
                'value' => Slide::TYPE_MCQ,
                'label' => 'Multiple Choice Question',
                'description' => 'A question with multiple choices where one or more answers can be correct',
            ],
            [
                'value' => Slide::TYPE_MATCHING,
                'label' => 'Matching Pairs',
                'description' => 'Match items from the left column with items in the right column',
            ],
            [
                'value' => Slide::TYPE_REORDERING,
                'label' => 'Reordering Items',
                'description' => 'Arrange the given items in the correct order',
            ],
            [
                'value' => Slide::TYPE_FILL_BLANK,
                'label' => 'Fill in the Blank',
                'description' => 'Complete the text by filling in the blanks',
            ],
            [
                'value' => Slide::TYPE_FILL_BLANK_CHOICES,
                'label' => 'Fill in the Blank (with choices)',
                'description' => 'Complete the text by selecting from provided options',
            ],
            [
                'value' => Slide::TYPE_TERM,
                'label' => 'Terminology',
                'description' => 'Term with definition, translation, and optional media',
            ],
            [
                'value' => Slide::TYPE_EXPLANATION,
                'label' => 'Explanation',
                'description' => 'Text-based explanation with formatting support',
            ],
        ];

        return response()->json($types);
    }
}
