<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Level\StoreRequest;
use App\Http\Requests\Admin\Level\UpdateRequest;
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
        if (!Gate::allows('view.level')) {
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

        $levels = $query->get();

        return response()->json($levels);
    }

    /**
     * Store a newly created level in storage.
     */
    public function store(StoreRequest $request): JsonResponse
    {
        $level = Level::create($request->validated());

        return response()->json($level, 201);
    }

    /**
     * Display the specified level.
     */
    public function show(Level $level): JsonResponse
    {
        if (!Gate::allows('view.level')) {
            abort(403);
        }

        $level->load('lessons');

        return response()->json($level);
    }

    /**
     * Update the specified level in storage.
     */
    public function update(UpdateRequest $request, Level $level): JsonResponse
    {
        $level->update($request->validated());

        return response()->json($level);
    }

    /**
     * Remove the specified level from storage.
     */
    public function destroy(Level $level): JsonResponse
    {
        if (!Gate::allows('delete.level')) {
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
        if (!Gate::allows('reorder.slide')) {
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
    public function toggleUnlock(Level $level): JsonResponse
    {
        if (!Gate::allows('unlock.level')) {
            abort(403);
        }

        $level->is_unlocked = !$level->is_unlocked;
        $level->save();

        return response()->json($level);
    }
}
