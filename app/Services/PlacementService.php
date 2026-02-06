<?php

namespace App\Services;

use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\UserStudiedLesson;
use App\Models\UserLevelProgress;
use App\Models\Level;
use Illuminate\Support\Facades\DB;

class PlacementService
{
    /**
     * Process a placement exam attempt to determine and apply level status.
     * This method is idempotent and safe to run multiple times.
     */
    public function processAttempt(ExamAttempt $attempt): void
    {
        // 1. Idempotency Check: If outcome is already recorded, do nothing.
        if ($attempt->placement_outcome_level_id) {
            return;
        }

        // Ensure relationships are loaded
        $attempt->load(['exam', 'exam.course']);
        $exam = $attempt->exam;
        
        // Safety check for broken relationships
        if (!$exam || !$exam->course || !$exam->isPlacement()) {
            return;
        }

        // 2. Calculate Target Level
        $targetLevelId = $exam->determinePlacementLevel($attempt->percentage);
        
        // Persist outcome immediately (source of truth)
        $attempt->placement_outcome_level_id = $targetLevelId;
        $attempt->saveQuietly(); // Avoid triggering observers if any

        if (!$targetLevelId) {
            return;
        }

        // 3. First-Attempt Rule (Retake Safety)
        // If the user already has ANY progress record for this course, 
        // we treat this as a retake (or a subsequent placement attempt) 
        // and do NOT modify their progress. We only recorded the history above.
        $hasExistingProgress = UserLevelProgress::where('user_id', $attempt->user_id)
            ->where('course_id', $exam->course_id)
            ->exists();

        if ($hasExistingProgress) {
            return;
        }

        // 4. Apply Progress Logic
        // We need to fetch all levels for the course to determine order
        $levels = $exam->course->levels()
            ->orderBy('sort_order')
            ->get();

        $targetLevel = $levels->firstWhere('id', $targetLevelId);
        
        if (!$targetLevel) {
            // Configuration error: Target level not found in course
            return;
        }

        DB::transaction(function () use ($levels, $targetLevel, $attempt, $exam) {
            foreach ($levels as $level) {
                // Determine logic based on sort order
                if ($level->sort_order < $targetLevel->sort_order) {
                    // Levels BELOW target: Determine if COMPLETED or SKIPPED
                    $totalLessons = Lesson::where('level_id', $level->id)->where('status', 'published')->count();
                    $studiedLessons = UserStudiedLesson::where('user_id', $attempt->user_id)
                        ->whereIn('lesson_id', function($query) use ($level) {
                            $query->select('id')->from('lessons')->where('level_id', $level->id)->where('status', 'published');
                        })->count();

                    $newStatus = ($studiedLessons >= $totalLessons && $totalLessons > 0) 
                        ? UserLevelProgress::STATUS_COMPLETED 
                        : UserLevelProgress::STATUS_SKIPPED;

                    $this->updateProgress(
                        $attempt->user_id,
                        $exam->course_id,
                        $level->id,
                        $newStatus,
                        $attempt->id
                    );
                } elseif ($level->id === $targetLevel->id) {
                    // Target Level: Mark as UNLOCKED
                    $this->updateProgress(
                        $attempt->user_id,
                        $exam->course_id,
                        $level->id,
                        UserLevelProgress::STATUS_UNLOCKED,
                        $attempt->id
                    );
                } else {
                    // Levels ABOVE target: Remain LOCKED
                    // No action needed, default state is locked (absence of record).
                }
            }
        });
    }

    private function updateProgress(int $userId, int $courseId, int $levelId, string $status, int $attemptId): void
    {
        // Only create/update if status is actually changing or new.
        // Since we checked $hasExistingProgress above, we are guaranteed to be in a "clean slate" scenario for this course.
        // However, standard safety applies.
        
        $progress = UserLevelProgress::firstOrNew([
            'user_id' => $userId,
            'course_id' => $courseId,
            'level_id' => $levelId,
        ]);

        // Safety: Never downgrade 'completed' or 'in_progress' to 'skipped'/'unlocked'
        // (Redundant check due to $hasExistingProgress, but kept for robustness)
        if (in_array($progress->status, [UserLevelProgress::STATUS_COMPLETED, UserLevelProgress::STATUS_IN_PROGRESS])) {
            return;
        }

        // Only save if it's a new record or status changed
        if (!$progress->exists || $progress->status !== $status) {
            $progress->status = $status;
            $progress->source_attempt_id = $attemptId;
            
            if ($status === UserLevelProgress::STATUS_UNLOCKED) {
                $progress->unlocked_at = now();
            }

            $progress->save();
        }
    }
}
