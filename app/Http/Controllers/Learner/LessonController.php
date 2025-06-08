<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;

class LessonController extends Controller
{
    /**
     * Get a specific lesson with its slides for learners.
     */
    public function show(Lesson $lesson): JsonResponse
    {
        // Check if the lesson is published
        if ($lesson->status !== 'published') {
            return response()->json(['error' => 'Lesson not available'], 404);
        }

        // Check if the level is published and unlocked
        if ($lesson->level->status !== 'published' || !$lesson->level->is_unlocked) {
            return response()->json(['error' => 'Level not available'], 404);
        }

        // Check if the course is published
        if ($lesson->level->course->status !== 'published') {
            return response()->json(['error' => 'Course not available'], 404);
        }

        $lesson->load(['slides' => function ($query) {
            $query->orderBy('sort_order');
        }]);

        return response()->json($lesson);
    }
}
