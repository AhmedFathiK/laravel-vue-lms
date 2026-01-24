<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Level\StoreRequest;
use App\Http\Requests\Admin\Level\UpdateRequest;
use App\Http\Resources\LevelResource;
use App\Models\Course;
use App\Models\Level;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LevelController extends Controller
{
    /**
     * Display a listing of the levels for a course.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        if (!Gate::allows('view.levels')) {
            abort(403);
        }

        $query = $course->levels();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Apply sorting
        $sortField = $request->get('sort_field', 'sort_order');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Apply pagination if requested
        if ($request->has('per_page')) {
            $perPage = (int) $request->get('per_page', 10);
            $levels = $query->paginate($perPage);

            return response()->json([
                'data' => LevelResource::collection($levels->items()),
                'total' => $levels->total(),
                'current_page' => $levels->currentPage(),
                'per_page' => $levels->perPage(),
                'last_page' => $levels->lastPage(),
            ]);
        }

        // Otherwise return all levels
        $levels = $query->get();
        return response()->json(LevelResource::collection($levels));
    }

    /**
     * Store a newly created level in storage.
     */
    public function store(StoreRequest $request, Course $course): JsonResponse
    {
        $data = $request->validated();

        // Set default status if not provided
        if (!isset($data['status'])) {
            $data['status'] = 'draft';
        }

        $lastLevel = Level::select("course_id", "sort_order")->where("course_id", $data['course_id'])->orderBy('sort_order', 'desc')->get("sort_order")->first();
        // Create level with current locale data
        $level = Level::create([
            'course_id' => $data['course_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'sort_order' => $lastLevel ? $lastLevel->sort_order + 1 : 1,
            'status' => $data['status'],
            'is_unlocked' => $data['is_unlocked'] ?? false,
            'final_exam_id' => $data['final_exam_id'] ?? null,
        ]);

        return response()->json(new LevelResource($level), 201);
    }

    /**
     * Display the specified level.
     */
    public function show(Course $course, Level $level): JsonResponse
    {
        if (!Gate::allows('view.levels')) {
            abort(403);
        }

        $level->load('lessons');

        return response()->json(new LevelResource($level));
    }

    /**
     * Update the specified level in storage.
     */
    public function update(UpdateRequest $request, Course $course, Level $level): JsonResponse
    {
        $data = $request->validated();

        // Update the level with the validated data
        $level->update($data);

        return response()->json(new LevelResource($level));
    }

    /**
     * Remove the specified level from storage.
     */
    public function destroy(Course $course, Level $level): JsonResponse
    {
        if (!Gate::allows('delete.levels')) {
            abort(403);
        }

        $level->delete();

        return response()->json(null, 204);
    }

    /**
     * Update level order within a course.
     */
    public function updateOrder(Request $request, Course $course): JsonResponse
    {
        if (!Gate::allows('reorder.levels')) {
            abort(403);
        }

        $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:levels,id'],
        ]);

        $order = $request->input('order');
        foreach ($order as $index => $levelId) {
            Level::where('id', $levelId)
                ->where('course_id', $course->id)
                ->update(['sort_order' => $index + 1]);
        }

        return response()->json(['message' => 'Order updated successfully']);
    }

    /**
     * Toggle the unlock status of a level.
     */
    public function toggleUnlock(Course $course, Level $level): JsonResponse
    {
        if (!Gate::allows('unlock.levels')) {
            abort(403);
        }

        $level->is_unlocked = !$level->is_unlocked;
        $level->save();

        return response()->json(new LevelResource($level));
    }
}
