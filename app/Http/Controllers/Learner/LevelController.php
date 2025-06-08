<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Level;
use Illuminate\Http\JsonResponse;

class LevelController extends Controller
{
    /**
     * Get a specific level with its lessons for learners.
     */
    public function show(Level $level): JsonResponse
    {
        // Check if the level is published and unlocked
        if ($level->status !== 'published' || !$level->is_unlocked) {
            return response()->json(['error' => 'Level not available'], 404);
        }

        // Check if the course is published
        if ($level->course->status !== 'published') {
            return response()->json(['error' => 'Course not available'], 404);
        }

        $level->load(['lessons' => function ($query) {
            $query->where('status', 'published')
                ->orderBy('sort_order');
        }]);

        return response()->json($level);
    }
}
