<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Lesson\StoreRequest;
use App\Http\Requests\Admin\Lesson\UpdateRequest;
use App\Http\Resources\Admin\LessonResource;
use App\Models\Course;
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
    public function index(Request $request, Course $course, Level $level): JsonResponse
    {
        // Authorization
        if (!Gate::allows('view.lessons')) {
            abort(403);
        }

        // Validate filtering & sorting query parameters
        $request->validate([
            'status'   => 'nullable|in:active,inactive',
            'sort_by'  => 'nullable|string|in:id,title,sort_order,status,created_at',
            'order_by' => 'nullable|in:asc,desc',
        ]);

        // Build query
        $query = $level->lessons();

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Apply sorting
        $sortField = $request->get('sort_by', 'id');
        $sortDirection = $request->get('order_by', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Fetch lessons
        $lessons = $query->get();

        // Return as Resource Collection
        return response()->json(
            LessonResource::collection($lessons)
        );
    }


    /**
     * Store a newly created lesson in storage.
     */
    public function store(StoreRequest $request, Course $course, Level $level): JsonResponse
    {
        // Compute next sort order for this level
        $nextSortOrder = $level->lessons()->max('sort_order') + 1;

        // Merge it into validated data
        $data = array_merge(
            $request->validated(),
            ['sort_order' => $nextSortOrder]
        );

        // Create lesson
        $lesson = Lesson::create($data);

        return response()->json(
            new LessonResource($lesson),
            201
        );
    }

    /**
     * Display the specified lesson.
     */
    public function show(Course $course, Level $level, Lesson $lesson): JsonResponse
    {
        if (!Gate::allows('view.lessons')) {
            abort(403);
        }

        $lesson->load('slides');
        return response()->json(new LessonResource($lesson));
    }

    /**
     * Update the specified lesson in storage.
     */
    public function update(UpdateRequest $request, Course $course, Level $level, Lesson $lesson): JsonResponse
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
    public function destroy(Course $course, Level $level, Lesson $lesson): JsonResponse
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
    public function updateOrder(Request $request, Course $course, Level $level): JsonResponse
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
    public function configure(Request $request, Course $course, Level $level, Lesson $lesson): JsonResponse
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
