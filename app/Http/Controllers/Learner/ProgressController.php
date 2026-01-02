<?php

namespace App\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use App\Models\Concept;
use App\Models\CourseEnrollment;
use App\Models\Lesson;
use App\Models\RevisionItem;
use App\Models\Slide;
use App\Models\Term;
use App\Models\UserStudiedLesson;
use App\Services\FSRSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    protected FSRSService $fsrsService;

    public function __construct(FSRSService $fsrsService)
    {
        $this->fsrsService = $fsrsService;
    }

    /**
     * Mark a lesson as complete and update FSRS progress.
     */
    public function completeLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $request->validate([
            'results' => ['required', 'array'],
            'results.*.slide_id' => ['required', 'exists:slides,id'],
            'results.*.attempts' => ['required', 'integer', 'min:1'],
        ]);

        $user = Auth::user();
        $results = $request->input('results');

        // 1. Mark Lesson as Studied
        UserStudiedLesson::firstOrCreate([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id
        ], [
            'course_id' => $lesson->level->course_id,
            'level_id' => $lesson->level_id
        ]);

        // Ensure enrollment exists
        CourseEnrollment::firstOrCreate([
            'user_id' => $user->id,
            'course_id' => $lesson->level->course_id,
        ], [
            'enrolled_at' => now(),
            'last_accessed_at' => now(),
        ]);

        // 2. Process FSRS Progress
        // Collect all attempts per Term/Concept
        $itemAttempts = []; // ['term_1' => [1, 2], 'concept_5' => [1]]

        $slides = Slide::with(['question.terms', 'question.concepts'])
            ->whereIn('id', array_column($results, 'slide_id'))
            ->get()
            ->keyBy('id');

        foreach ($results as $result) {
            $slideId = $result['slide_id'];
            $attempts = $result['attempts'];

            $slide = $slides->get($slideId);
            if (!$slide || !$slide->question) {
                continue;
            }

            // Process Terms
            foreach ($slide->question->terms as $term) {
                $key = 'term_' . $term->id;
                $itemAttempts[$key][] = $attempts;
            }

            // Process Concepts
            foreach ($slide->question->concepts as $concept) {
                $key = 'concept_' . $concept->id;
                $itemAttempts[$key][] = $attempts;
            }
        }

        // 3. Update Revision Items
        DB::transaction(function () use ($user, $itemAttempts) {
            foreach ($itemAttempts as $key => $attemptsList) {
                [$type, $id] = explode('_', $key);
                $modelType = $type === 'term' ? Term::class : Concept::class;

                // Determine effective grade based on worst performance (max attempts)
                $maxAttempts = max($attemptsList);
                $grade = $this->calculateGrade($maxAttempts);

                // Find or Init Revision Item
                $revisionItem = RevisionItem::firstOrNew([
                    'user_id' => $user->id,
                    'revisionable_type' => $modelType,
                    'revisionable_id' => $id,
                ]);

                if (!$revisionItem->exists) {
                    $revisionItem = $this->fsrsService->initializeRevisionItem($revisionItem, $grade);
                } else {
                    $revisionItem = $this->fsrsService->updateRevisionItem($revisionItem, $grade);
                }

                $revisionItem->save();
            }
        });

        return response()->json([
            'message' => 'Lesson completed and progress updated',
            'updated_items_count' => count($itemAttempts)
        ]);
    }

    /**
     * Map attempts to FSRS Grade.
     * 1 attempt  -> Easy (4)
     * 2 attempts -> Good (3)
     * 3 attempts -> Hard (2)
     * 4+ attempts -> Again (1)
     */
    private function calculateGrade(int $attempts): int
    {
        if ($attempts <= 1) return FSRSService::GRADE_EASY;
        if ($attempts === 2) return FSRSService::GRADE_GOOD;
        if ($attempts === 3) return FSRSService::GRADE_HARD;
        return FSRSService::GRADE_AGAIN;
    }
}
