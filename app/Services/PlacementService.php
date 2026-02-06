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
        // 1. Determine Target Level
        // If outcome is already recorded, we use it (to allow retrying failed applications).
        // If not, we calculate it.
        $targetLevelId = $attempt->placement_outcome_level_id;

        // Ensure relationships are loaded
        $attempt->load(['exam', 'exam.course']);
        $exam = $attempt->exam;

        // Safety check for broken relationships
        if (!$exam || !$exam->course || !$exam->isPlacement()) {
            return;
        }

        if (!$targetLevelId) {
            // Calculate Target Level
            $targetLevelId = $exam->determinePlacementLevel($attempt->percentage);

            // Persist outcome immediately (source of truth)
            $attempt->placement_outcome_level_id = $targetLevelId;
            $attempt->saveQuietly(); // Avoid triggering observers if any
        }

        if (!$targetLevelId) {
            return;
        }

        // 2. Fetch Levels & Compare Progress
        // We need to fetch all levels for the course to determine order
        $levels = $exam->course->levels()
            ->orderBy('sort_order')
            ->get();

        $targetLevel = $levels->firstWhere('id', $targetLevelId);

        if (!$targetLevel) {
            // Configuration error: Target level not found in course
            return;
        }

        // Check existing progress to prevent downgrading.
        // If the user is already BEYOND the target level, we do nothing.
        // If the user is BEHIND the target level, we upgrade them.
        $existingProgress = UserLevelProgress::where('user_id', $attempt->user_id)
            ->where('course_id', $exam->course_id)
            ->get();

        if ($existingProgress->isNotEmpty()) {
            $levelMap = $levels->pluck('sort_order', 'id');
            $maxCurrentSortOrder = -1;

            foreach ($existingProgress as $p) {
                // If unlocked/completed/in_progress, it counts as "reached"
                if (in_array($p->status, [UserLevelProgress::STATUS_UNLOCKED, UserLevelProgress::STATUS_IN_PROGRESS, UserLevelProgress::STATUS_COMPLETED, UserLevelProgress::STATUS_SKIPPED])) {
                    if (isset($levelMap[$p->level_id])) {
                        $order = $levelMap[$p->level_id];
                        if ($order > $maxCurrentSortOrder) {
                            $maxCurrentSortOrder = $order;
                        }
                    }
                }
            }

            // If target is NOT higher than current max, do nothing (don't downgrade/reset)
            if ($targetLevel->sort_order <= $maxCurrentSortOrder) {
                return;
            }
        }

        // 3. Apply Progress Logic
        DB::transaction(function () use ($levels, $targetLevel, $attempt, $exam) {
            foreach ($levels as $level) {
                // Determine logic based on sort order
                if ($level->sort_order < $targetLevel->sort_order) {
                    // Levels BELOW target: Determine if COMPLETED or SKIPPED
                    $totalLessons = Lesson::where('level_id', $level->id)->where('status', 'published')->count();
                    $studiedLessons = UserStudiedLesson::where('user_id', $attempt->user_id)
                        ->whereIn('lesson_id', function ($query) use ($level) {
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
        $progress = UserLevelProgress::firstOrNew([
            'user_id' => $userId,
            'course_id' => $courseId,
            'level_id' => $levelId,
        ]);

        // Safety: Never downgrade 'completed'.
        if ($progress->status === UserLevelProgress::STATUS_COMPLETED) {
            return;
        }

        // Handle IN_PROGRESS state preservation
        if ($progress->status === UserLevelProgress::STATUS_IN_PROGRESS) {
            // If we are just unlocking it (target level), keep it in progress (don't reset to unlocked).
            if ($status === UserLevelProgress::STATUS_UNLOCKED) {
                return;
            }
            // However, if we are skipping it (levels below target), we allow overwriting IN_PROGRESS with SKIPPED.
        }

        // Only save if it's a new record or status changed
        if (!$progress->exists || $progress->status !== $status) {
            $progress->status = $status;
            $progress->source_attempt_id = $attemptId;

            if ($status === UserLevelProgress::STATUS_UNLOCKED && !$progress->unlocked_at) {
                $progress->unlocked_at = now();
            }

            $progress->save();
        }
    }
}
