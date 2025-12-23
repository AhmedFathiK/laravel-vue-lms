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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        try {
            return DB::transaction(function () use ($request, $level) {
                // Compute next sort order for this level
                $nextSortOrder = ($level->lessons()->max('sort_order') ?? 0) + 1;

                // Merge it into validated data
                $data = array_merge(
                    $request->validated(),
                    ['sort_order' => $nextSortOrder]
                );

                $uploadedPath = null;

                // Handle thumbnail upload
                if ($request->hasFile('thumbnail')) {
                    $file = $request->file('thumbnail');
                    $uploadedPath = $file->store('lesson-thumbnails', 'public');
                    $data['thumbnail'] = Storage::url($uploadedPath);
                }

                // Create lesson
                $lesson = Lesson::create($data);

                return response()->json(
                    new LessonResource($lesson),
                    201
                );
            });
        } catch (\Exception $e) {
            // If we uploaded a file but the DB transaction failed, delete the file
            if (isset($uploadedPath) && Storage::disk('public')->exists($uploadedPath)) {
                Storage::disk('public')->delete($uploadedPath);
            }

            Log::error('Failed to store lesson: ' . $e->getMessage());

            return response()->json([
                'message' => 'An error occurred while creating the lesson. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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
        try {
            return DB::transaction(function () use ($request, $lesson) {
                $data = $request->validated();
                $uploadedPath = null;
                $oldThumbnailPath = $lesson->thumbnail ? str_replace('/storage/', '', $lesson->thumbnail) : null;

                // Handle thumbnail deletion
                if ($request->has('delete_thumbnail') && $request->boolean('delete_thumbnail')) {
                    $data['thumbnail'] = null;
                }
                // Handle thumbnail upload
                else if ($request->hasFile('thumbnail')) {
                    $file = $request->file('thumbnail');
                    $uploadedPath = $file->store('lesson-thumbnails', 'public');
                    $data['thumbnail'] = Storage::url($uploadedPath);
                } else {
                    // If no new thumbnail is provided, remove it from the data array
                    // to prevent overwriting the existing thumbnail with null
                    unset($data['thumbnail']);
                }

                $lesson->update($data);

                // If update was successful:
                // 1. If we deleted the thumbnail, remove old file
                if ($request->has('delete_thumbnail') && $request->boolean('delete_thumbnail')) {
                    if ($oldThumbnailPath && Storage::disk('public')->exists($oldThumbnailPath)) {
                        Storage::disk('public')->delete($oldThumbnailPath);
                    }
                }
                // 2. If we uploaded a new thumbnail, remove old file
                else if ($uploadedPath && $oldThumbnailPath) {
                    if (Storage::disk('public')->exists($oldThumbnailPath)) {
                        Storage::disk('public')->delete($oldThumbnailPath);
                    }
                }

                return response()->json(new LessonResource($lesson));
            });
        } catch (\Exception $e) {
            // Cleanup: if we uploaded a new file but the DB update failed, delete it
            if (isset($uploadedPath) && Storage::disk('public')->exists($uploadedPath)) {
                Storage::disk('public')->delete($uploadedPath);
            }

            Log::error('Failed to update lesson: ' . $e->getMessage());

            return response()->json([
                'message' => 'An error occurred while updating the lesson. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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
        if (!Gate::allows('reorder.lessons')) {
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
}
