<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lesson\StoreRequest;
use App\Http\Requests\Admin\Lesson\UpdateRequest;
use App\Models\Level;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LessonController extends Controller
{
    /**
     * Display a listing of the lessons for a level.
     */
    public function index(Request $request, Level $level): JsonResponse
    {
        if (!Gate::allows('view.lessons')) {
            abort(403);
        }

        $query = $level->lessons();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Apply sorting
        $sortField = $request->get('sort_field', 'sort_order');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $lessons = $query->get();

        // Transform lessons to ensure translations are properly handled
        $transformedLessons = $lessons->map(function ($lesson) {
            return [
                'id' => $lesson->id,
                'level_id' => $lesson->level_id,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'sort_order' => $lesson->sort_order,
                'status' => $lesson->status,
                'is_free' => $lesson->is_free,
                'video_url' => $lesson->video_url,
                'reshow_incorrect_slides' => $lesson->reshow_incorrect_slides,
                'reshow_count' => $lesson->reshow_count,
                'require_correct_answers' => $lesson->require_correct_answers,
                'created_at' => $lesson->created_at,
                'updated_at' => $lesson->updated_at,
            ];
        });

        return response()->json($transformedLessons);
    }

    /**
     * Store a newly created lesson in storage.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $lesson = Lesson::create($request->validated());

        return response()->json([
            'id' => $lesson->id,
            'level_id' => $lesson->level_id,
            'title' => $lesson->title,
            'description' => $lesson->description,
            'sort_order' => $lesson->sort_order,
            'status' => $lesson->status,
            'is_free' => $lesson->is_free,
            'video_url' => $lesson->video_url,
            'reshow_incorrect_slides' => $lesson->reshow_incorrect_slides,
            'reshow_count' => $lesson->reshow_count,
            'require_correct_answers' => $lesson->require_correct_answers,
            'created_at' => $lesson->created_at,
            'updated_at' => $lesson->updated_at,
        ], 201);
    }

    /**
     * Display the specified lesson.
     */
    public function show(Lesson $lesson): JsonResponse
    {
        if (!Gate::allows('view.lessons')) {
            abort(403);
        }

        $lesson->load('slides');

        // Transform lesson to ensure translations are properly handled
        $transformedLesson = [
            'id' => $lesson->id,
            'level_id' => $lesson->level_id,
            'title' => $lesson->title,
            'description' => $lesson->description,
            'sort_order' => $lesson->sort_order,
            'status' => $lesson->status,
            'is_free' => $lesson->is_free,
            'video_url' => $lesson->video_url,
            'reshow_incorrect_slides' => $lesson->reshow_incorrect_slides,
            'reshow_count' => $lesson->reshow_count,
            'require_correct_answers' => $lesson->require_correct_answers,
            'created_at' => $lesson->created_at,
            'updated_at' => $lesson->updated_at,
            'slides' => $lesson->slides->map(function ($slide) {
                return [
                    'id' => $slide->id,
                    'lesson_id' => $slide->lesson_id,
                    'title' => $slide->title ?? null,
                    'content' => $slide->content,
                    'options' => $slide->options,
                    'correct_answer' => $slide->correct_answer,
                    'feedback' => $slide->feedback,
                    'sort_order' => $slide->sort_order,
                    'type' => $slide->type,
                    'created_at' => $slide->created_at,
                    'updated_at' => $slide->updated_at,
                ];
            }),
        ];

        return response()->json($transformedLesson);
    }

    /**
     * Update the specified lesson in storage.
     */
    public function update(UpdateRequest $request, Lesson $lesson): JsonResponse
    {
        $lesson->update($request->validated());

        return response()->json([
            'id' => $lesson->id,
            'level_id' => $lesson->level_id,
            'title' => $lesson->title,
            'description' => $lesson->description,
            'sort_order' => $lesson->sort_order,
            'status' => $lesson->status,
            'is_free' => $lesson->is_free,
            'video_url' => $lesson->video_url,
            'reshow_incorrect_slides' => $lesson->reshow_incorrect_slides,
            'reshow_count' => $lesson->reshow_count,
            'require_correct_answers' => $lesson->require_correct_answers,
            'created_at' => $lesson->created_at,
            'updated_at' => $lesson->updated_at,
        ]);
    }

    /**
     * Remove the specified lesson from storage.
     */
    public function destroy(Lesson $lesson): JsonResponse
    {
        if (!Gate::allows('delete.lessons')) {
            abort(403);
        }

        $lesson->delete();

        return response()->json(null, 204);
    }

    /**
     * Update lesson order within a level.
     */
    public function updateOrder(Request $request, Level $level): JsonResponse
    {
        if (!Gate::allows('reorder.slides')) {
            abort(403);
        }

        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:lessons,id'],
        ]);

        $order = $request->input('order');
        foreach ($order as $index => $lessonId) {
            Lesson::where('id', $lessonId)
                ->where('level_id', $level->id)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json(['message' => 'Order updated successfully']);
    }

    /**
     * Configure lesson settings.
     */
    public function configure(Request $request, Lesson $lesson): JsonResponse
    {
        if (!Gate::allows('configure.lessons')) {
            abort(403);
        }

        $validated = $request->validate([
            'reshow_incorrect_slides' => ['required', 'boolean'],
            'reshow_count' => ['required', 'integer', 'min:1', 'max:10'],
            'require_correct_answers' => ['required', 'boolean'],
        ]);

        $lesson->update($validated);

        return response()->json([
            'id' => $lesson->id,
            'level_id' => $lesson->level_id,
            'title' => $lesson->title,
            'description' => $lesson->description,
            'sort_order' => $lesson->sort_order,
            'status' => $lesson->status,
            'is_free' => $lesson->is_free,
            'video_url' => $lesson->video_url,
            'reshow_incorrect_slides' => $lesson->reshow_incorrect_slides,
            'reshow_count' => $lesson->reshow_count,
            'require_correct_answers' => $lesson->require_correct_answers,
            'created_at' => $lesson->created_at,
            'updated_at' => $lesson->updated_at,
        ]);
    }
}
